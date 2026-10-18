<?php

namespace PhynixMedia\Cms\Models\Components;

use PhynixMedia\Cms\Components\ComponentRegistry;
use PhynixMedia\Cms\Components\ComponentType;
use PhynixMedia\Cms\Models\Pages\Layouts;
use PhynixMedia\Cms\Models\Pages\Templates;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * A reusable component in the library.
 *
 * Pages and layouts place it by reference ({type: "library", data:
 * {component_id: 5}}), so an edit here shows up everywhere it is used.
 */
class Component extends Model
{
    protected $table = 'cms_components';

    protected $fillable = [
        'name',
        'handle',
        'type',
        'group',
        'data',
        'view',
        'description',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'data' => 'array',
            'status' => 'integer',
        ];
    }

    /**
     * The registered type this component is an instance of, or null when the
     * type has since been unregistered — the row is kept, just not rendered.
     */
    public function componentType(): ?ComponentType
    {
        return app(ComponentRegistry::class)->get($this->type);
    }

    /**
     * Groups already in use, for suggesting as an editor types one.
     *
     * @return array<int, string>
     */
    public static function groups(): array
    {
        return static::query()->whereNotNull('group')->where('group', '!=', '')
            ->distinct()->orderBy('group')->pluck('group')->all();
    }

    /**
     * The published Blade view drawing this component, if one is in use and
     * the file is there.
     */
    public function publishedView(): ?string
    {
        return filled($this->view) && view()->exists($this->view) ? $this->view : null;
    }

    public function typeLabel(): string
    {
        return $this->componentType()?->label() ?? Str::headline($this->type) . ' (not registered)';
    }

    /**
     * Every page and layout that places this component by reference.
     *
     * Scanned in PHP rather than with JSON queries: the builder stores ids as
     * whatever the select produced (int or string), and the tables are small.
     *
     * @return Collection<int, Templates|Layouts>
     */
    public function usages(): Collection
    {
        $id = (int) $this->getKey();

        $pages = Templates::query()
            ->whereNotNull('content')
            ->get(['id', 'title', 'url', 'content'])
            ->filter(fn (Templates $page): bool => in_array($id, self::referencedIds($page->content), true));

        $layouts = Layouts::query()
            ->get()
            ->filter(fn (Layouts $layout): bool => collect(Layouts::REGIONS)
                ->keys()
                ->contains(fn (string $region): bool => in_array($id, self::referencedIds($layout->{$region}), true)));

        return $pages->values()->concat($layouts->values());
    }

    /**
     * The library component ids placed in a builder value.
     *
     * @return array<int, int>
     */
    public static function referencedIds(?array $blocks): array
    {
        return collect($blocks ?? [])
            ->filter(fn ($block): bool => is_array($block) && ($block['type'] ?? null) === ComponentRegistry::LIBRARY)
            ->map(fn (array $block): int => (int) ($block['data']['component_id'] ?? 0))
            ->filter()
            ->values()
            ->all();
    }

    protected static function booted(): void
    {
        static::creating(function (Component $component) {
            $component->uuid ??= Str::uuid()->toString();
            $component->handle = filled($component->handle)
                ? Str::slug($component->handle)
                : static::uniqueHandle($component->name);
        });
    }

    public static function uniqueHandle(string $name): string
    {
        $base = Str::slug($name) ?: 'component';
        $handle = $base;
        $suffix = 1;

        while (static::query()->where('handle', $handle)->exists()) {
            $handle = $base . '-' . ++$suffix;
        }

        return $handle;
    }
}
