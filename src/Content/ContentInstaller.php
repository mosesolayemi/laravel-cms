<?php

namespace PhynixMedia\Cms\Content;

use Closure;
use PhynixMedia\Cms\Components\ComponentRegistry;
use PhynixMedia\Cms\Models\Components\Component;
use PhynixMedia\Cms\Models\Pages\Layouts;
use PhynixMedia\Cms\Models\Pages\Templates;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Writes content definitions into the database.
 *
 * Safe to run repeatedly. Without $force, anything an editor may have changed
 * is left alone: an existing library component keeps its data, a layout with
 * regions keeps them, and a page that already has content keeps it. Only
 * what is missing is filled in. With $force, the definitions win.
 */
class ContentInstaller
{
    public const COMPONENTS = 'components';

    public const LAYOUTS = 'layouts';

    public const PAGES = 'pages';

    /**
     * @var array<string, array<string, int>>
     */
    protected array $tally = [];

    protected ?Closure $output = null;

    protected ?Closure $progress = null;

    public function __construct(protected ComponentRegistry $registry) {}

    /**
     * @param  array<int, ContentDefinition|class-string<ContentDefinition>>  $definitions
     * @param  array<int, string>|null  $only  any of the three constants above
     * @param  Closure(string $level, string $message): void|null  $output
     * @param  Closure(int $total): void|null  $started  called once the work is counted
     * @param  Closure(string $item): void|null  $progress  called after each item, e.g. "page /about"
     * @return array<string, array<string, int>> created/updated/skipped per kind
     */
    public function install(
        array $definitions,
        bool $force = false,
        ?array $only = null,
        ?Closure $output = null,
        ?Closure $started = null,
        ?Closure $progress = null,
    ): array {
        $this->output = $output;
        $this->progress = $progress;
        $this->tally = [];

        $wants = fn (string $kind): bool => $only === null || $only === [] || in_array($kind, $only, true);

        // Read every definition before writing anything, so the amount of
        // work is known up front. This is also where seeded images are copied
        // onto the media disk, the slow part of a first run.
        $components = $layouts = $pages = [];

        foreach (array_map($this->resolve(...), $definitions) as $definition) {
            // A later definition overrides an earlier one with the same key.
            $components = array_replace($components, $wants(self::COMPONENTS) ? $definition->components() : []);
            $layouts = array_replace($layouts, $wants(self::LAYOUTS) ? $definition->layouts() : []);
            $pages = array_replace($pages, $wants(self::PAGES) ? $definition->pages() : []);
        }

        if ($started) {
            $started(count($components) + count($layouts) + count($pages));
        }

        DB::transaction(function () use ($components, $layouts, $pages, $force) {
            // Components first: layouts and pages reference them by handle.
            $this->installComponents($components, $force);
            $this->installLayouts($layouts, $force);
            $this->installPages($pages, $force);
        });

        return $this->tally;
    }

    /**
     * @param  array<string, array<string, mixed>>  $components
     */
    protected function installComponents(array $components, bool $force): void
    {
        foreach ($components as $handle => $definition) {
            $this->warnIfUnregistered($definition['type'], "component “{$handle}”");

            $existing = Component::query()->where('handle', $handle)->first();

            if ($existing && ! $force) {
                $this->count(self::COMPONENTS, 'skipped', "component {$handle}");

                continue;
            }

            $attributes = [
                'name' => $definition['name'],
                'type' => $definition['type'],
                'group' => $definition['group'] ?? null,
                'data' => $definition['data'] ?? [],
                'description' => $definition['description'] ?? null,
                'status' => $definition['status'] ?? 1,
            ];

            if ($existing) {
                $existing->update($attributes);
                $this->count(self::COMPONENTS, 'updated', "component {$handle}");
            } else {
                Component::query()->create(['handle' => $handle, ...$attributes]);
                $this->count(self::COMPONENTS, 'created', "component {$handle}");
            }
        }
    }

    /**
     * @param  array<string, array<string, mixed>>  $layouts
     */
    protected function installLayouts(array $layouts, bool $force): void
    {
        foreach ($layouts as $handle => $definition) {
            $regions = [];

            foreach (array_keys(Layouts::REGIONS) as $region) {
                $regions[$region] = $this->blocks($definition[$region] ?? [], "layout “{$handle}” ({$region})");
            }

            $existing = Layouts::query()->where('layout', $handle)->first();

            // A layout someone has already filled in is theirs.
            $untouched = $existing && collect(Layouts::REGIONS)
                ->keys()
                ->every(fn (string $region): bool => blank($existing->getAttribute($region)));

            if ($existing && ! $force && ! $untouched) {
                $this->count(self::LAYOUTS, 'skipped', "layout {$handle}");

                continue;
            }

            $attributes = [
                'label' => $definition['label'],
                'shell' => $definition['shell'] ?? null,
                'is_default' => (bool) ($definition['is_default'] ?? false),
                'status' => 1,
                ...$regions,
            ];

            if ($existing) {
                $existing->update($attributes);
                $this->count(self::LAYOUTS, 'updated', "layout {$handle}");
            } else {
                Layouts::query()->create(['layout' => $handle, ...$attributes]);
                $this->count(self::LAYOUTS, 'created', "layout {$handle}");
            }
        }
    }

    /**
     * @param  array<string, array<string, mixed>>  $pages
     */
    protected function installPages(array $pages, bool $force): void
    {
        foreach ($pages as $url => $definition) {
            $page = Templates::query()->where('url', $url)->first();

            if ($page && filled($page->content) && ! $force) {
                $this->count(self::PAGES, 'skipped', "page /{$url}");

                continue;
            }

            $attributes = ['content' => $this->blocks($definition['content'] ?? [], "page “{$url}”")];

            if (array_key_exists('layout', $definition)) {
                $attributes['layout_id'] = $this->layoutId($definition['layout'], $url);
            }

            if ($page) {
                $page->update($attributes);
                $this->count(self::PAGES, 'updated', "page /{$url}");

                continue;
            }

            $title = $definition['title'] ?? Str::headline(Str::afterLast($url, '/'));

            Templates::query()->create([
                'title' => $title,
                'url' => $url,
                'meta_title' => $title,
                'parent' => 0,
                'page_order' => 0,
                'header_position' => '0',
                'footer_position' => 'none',
                'status' => 1,
                ...$attributes,
            ]);

            $this->count(self::PAGES, 'created', "page /{$url}");
        }
    }

    /**
     * Resolve library handles to ids and flag types nobody registered.
     *
     * @param  array<int, mixed>  $blocks
     * @return array<int, array<string, mixed>>
     */
    protected function blocks(array $blocks, string $where): array
    {
        $resolved = [];

        foreach ($blocks as $block) {
            $type = $block['type'] ?? null;

            if ($type === ComponentRegistry::LIBRARY && isset($block['data']['component_handle'])) {
                $handle = $block['data']['component_handle'];
                $id = Component::query()->where('handle', $handle)->value('id');

                if (! $id) {
                    $this->say('warn', "{$where} places library component “{$handle}”, which does not exist. Left out.");

                    continue;
                }

                $block = ['type' => $type, 'data' => ['component_id' => $id]];
            } elseif ($type !== ComponentRegistry::LIBRARY) {
                $this->warnIfUnregistered($type, $where);
            }

            $resolved[] = $block;
        }

        return $resolved;
    }

    protected function layoutId(?string $handle, string $url): ?int
    {
        if ($handle === null) {
            return null;
        }

        $id = Layouts::query()->where('layout', $handle)->value('id');

        if (! $id) {
            $this->say('warn', "Page “{$url}” asks for layout “{$handle}”, which does not exist. It will render without one.");
        }

        return $id;
    }

    protected function warnIfUnregistered(?string $type, string $where): void
    {
        if (! $this->registry->has($type)) {
            $this->say('warn', "{$where} uses the “{$type}” component type, which is not registered. It is stored, but will not render until it is.");
        }
    }

    protected function resolve(ContentDefinition|string $definition): ContentDefinition
    {
        $definition = is_string($definition) ? app($definition) : $definition;

        if (! $definition instanceof ContentDefinition) {
            throw new InvalidArgumentException(get_debug_type($definition) . ' must implement ' . ContentDefinition::class . '.');
        }

        return $definition;
    }

    protected function count(string $kind, string $outcome, string $item): void
    {
        $this->tally[$kind][$outcome] = ($this->tally[$kind][$outcome] ?? 0) + 1;

        if ($this->progress) {
            ($this->progress)($item);
        }
    }

    protected function say(string $level, string $message): void
    {
        if ($this->output) {
            ($this->output)($level, $message);
        }
    }
}
