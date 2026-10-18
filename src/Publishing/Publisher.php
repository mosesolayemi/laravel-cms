<?php

namespace PhynixMedia\Cms\Publishing;

use PhynixMedia\Cms\Components\ComponentRegistry;
use PhynixMedia\Cms\Models\Components\Component;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Writes CMS content out as a Blade file, for the change the components
 * cannot express, and moves it between its file and the CMS.
 *
 *     publish()   {name}.blade.php written; the file draws the content
 *     revert()    {name}.blade.php -> {name}.reverted.blade.php; the CMS draws it
 *     useFile()   {name}.reverted.blade.php -> {name}.blade.php; the file again
 *     sync()      the data written in the file is read back into the CMS
 *
 * Each section is written as its data, exactly as the CMS stored it, then a
 * copy of its component's markup, so both can be edited in place. Library
 * components stay shared: they are placed with @cmsComponent, not copied.
 *
 * No step loses an edit made to the file: only $overwrite on publish() and
 * $deleteFile on revert() remove one.
 *
 * @template TModel of Model
 */
abstract class Publisher
{
    public function __construct(protected ComponentRegistry $registry) {}

    /**
     * The config key naming the view prefix files are written under.
     */
    abstract protected function prefixConfig(): string;

    /**
     * The part of the view name that identifies this record.
     *
     * @param  TModel  $model
     */
    abstract protected function slug(Model $model): string;

    /**
     * The builder items to write out.
     *
     * @param  TModel  $model
     * @return array<int, mixed>
     */
    abstract protected function blocks(Model $model): array;

    /**
     * Store items read back from the file (sync()).
     *
     * @param  TModel  $model
     * @param  array<int, array<string, mixed>>  $blocks
     */
    abstract protected function storeBlocks(Model $model, array $blocks): void;

    /**
     * The comment at the top of the file.
     *
     * @param  TModel  $model
     */
    abstract protected function header(Model $model, bool $compact): string;

    /**
     * @param  TModel  $model
     */
    public function viewName(Model $model): string
    {
        $prefix = trim((string) config($this->prefixConfig()), '.');

        return $prefix . '.' . str_replace('/', '.', trim($this->slug($model), '/'));
    }

    /**
     * Where the file lives while it draws the content.
     *
     * @param  TModel  $model
     */
    public function path(Model $model): string
    {
        return resource_path('views/' . str_replace('.', '/', $this->viewName($model)) . '.blade.php');
    }

    /**
     * Where the file is kept while the CMS draws the content again.
     *
     * The extra dot means no view name can resolve to it, so a reverted file
     * is never served by mistake — and the folder shows at a glance which
     * published files are not in use.
     *
     * @param  TModel  $model
     */
    public function revertedPath(Model $model): string
    {
        return Str::replaceLast('.blade.php', '.reverted.blade.php', $this->path($model));
    }

    /**
     * active   — the file is drawing the content
     * reverted — the CMS draws it; the file is kept as .reverted
     * unused   — a file is at the active path but nothing uses it
     * null     — never published
     *
     * @param  TModel  $model
     */
    public function state(Model $model): ?string
    {
        return match (true) {
            filled($model->view) && File::exists($this->path($model)) => 'active',
            File::exists($this->revertedPath($model)) => 'reverted',
            File::exists($this->path($model)) => 'unused',
            default => null,
        };
    }

    /**
     * Write the file and hand the content over to it.
     *
     * Never replaces a file silently: one already published, or a reverted
     * copy that may hold hand edits, is only overwritten with $overwrite.
     *
     * @param  TModel  $model
     * @return string the file written
     */
    public function publish(Model $model, bool $compact = false, bool $overwrite = false): string
    {
        if ($this->blocks($model) === []) {
            throw new RuntimeException("“{$this->slug($model)}” has nothing to publish.");
        }

        $path = $this->path($model);
        $reverted = $this->revertedPath($model);

        if (File::exists($path) && ! $overwrite) {
            throw new RuntimeException('Already published: ' . $this->relative($path) . '. Use --use-file to serve it, or --force to replace it.');
        }

        if (File::exists($reverted) && ! $overwrite) {
            throw new RuntimeException('A reverted copy exists: ' . $this->relative($reverted) . '. Use --use-file to bring it back with its edits, or --force to replace it with a fresh publish.');
        }

        File::ensureDirectoryExists(dirname($path));
        File::put($path, $this->source($model, $compact));
        File::delete($reverted);

        $model->forceFill(['view' => $this->viewName($model)])->save();

        return $path;
    }

    /**
     * Hand the content back to the CMS. The file is renamed to
     * .reverted.blade.php with its content untouched, or deleted when
     * $deleteFile says so.
     *
     * @param  TModel  $model
     * @return string|null the reverted copy, or null when none is kept
     */
    public function revert(Model $model, bool $deleteFile = false): ?string
    {
        $path = $this->path($model);
        $reverted = $this->revertedPath($model);

        if ($deleteFile) {
            File::delete([$path, $reverted]);
            $model->forceFill(['view' => null])->save();

            return null;
        }

        if (File::exists($path)) {
            if (File::exists($reverted)) {
                throw new RuntimeException('Both ' . $this->relative($path) . ' and ' . $this->relative($reverted)
                    . ' exist, and reverting would overwrite the second. Delete whichever you no longer need, then revert again.');
            }

            File::move($path, $reverted);
        }

        $model->forceFill(['view' => null])->save();

        return File::exists($reverted) ? $reverted : null;
    }

    /**
     * Serve the content from its file again — the reverted copy with every
     * edit made to it, or a file already at the active path. Nothing is
     * regenerated or overwritten.
     *
     * @param  TModel  $model
     * @return string the file now in use
     */
    public function useFile(Model $model): string
    {
        $path = $this->path($model);
        $reverted = $this->revertedPath($model);

        if (! File::exists($path)) {
            if (! File::exists($reverted)) {
                throw new RuntimeException("“{$this->slug($model)}” has no published file to use. Publish it first.");
            }

            File::move($reverted, $path);
        } elseif (File::exists($reverted)) {
            throw new RuntimeException('Both ' . $this->relative($path) . ' and ' . $this->relative($reverted)
                . ' exist. Delete whichever you no longer need, then try again.');
        }

        $model->forceFill(['view' => $this->viewName($model)])->save();

        return $path;
    }

    /**
     * Read the data written in the file back into the CMS: each section's
     * data block, its component type, and the order sections appear in.
     * Markup changes stay in the file — they have nowhere to go in the CMS.
     *
     * @param  TModel  $model
     * @return int the number of sections read
     */
    public function sync(Model $model): int
    {
        $file = collect([$this->path($model), $this->revertedPath($model)])->first(fn (string $path): bool => File::exists($path));

        if (! $file) {
            throw new RuntimeException("“{$this->slug($model)}” has no published file to sync from.");
        }

        $blocks = SectionReader::read(File::get($file));

        if ($blocks === []) {
            throw new RuntimeException('No sections found in ' . $this->relative($file) . '.');
        }

        $this->storeBlocks($model, $blocks);

        return count($blocks);
    }

    /**
     * @param  TModel  $model
     */
    public function source(Model $model, bool $compact = false): string
    {
        $sections = [];

        foreach (array_values($this->blocks($model)) as $index => $block) {
            $sections[] = $this->section($index + 1, $block, $compact);
        }

        return $this->header($model, $compact) . "\n" . implode("\n", $sections);
    }

    public function relative(string $path): string
    {
        return Str::after($path, base_path() . DIRECTORY_SEPARATOR);
    }

    protected function section(int $number, mixed $block, bool $compact): string
    {
        $type = is_array($block) ? ($block['type'] ?? null) : null;
        $data = is_array($block['data'] ?? null) ? $block['data'] : [];

        $hidden = ! empty($data[ComponentRegistry::HIDDEN]);

        if ($type === ComponentRegistry::LIBRARY && ! $hidden) {
            return $this->librarySection($number, $data['component_id'] ?? null);
        }

        $component = $this->registry->get($type);
        $label = $type === ComponentRegistry::LIBRARY
            ? 'Library: ' . (Component::query()->find((int) ($data['component_id'] ?? 0))?->name ?? 'missing component')
            : ($component?->label() ?? "{$type} (not registered — renders nothing)");

        // Hidden sections stay in the file, flagged, so they are kept
        // through a --sync; they render nothing until the flag is cleared.
        if ($hidden) {
            $label .= ' (hidden — set ' . ComponentRegistry::HIDDEN . ' to false to show it)';
            $compact = true;
        }

        $source = $this->comment($number, $label)
            . "@php\n    \$__cmsData = " . $this->export($data, 1) . ";\n@endphp\n";

        if ($compact || ! $component) {
            return $source . "@cmsBlock('{$type}', \$__cmsData)\n";
        }

        return $source
            . "@cmsSection('{$type}', \$__cmsData)\n"
            . $this->markup($component->view())
            . "@endCmsSection\n";
    }

    protected function librarySection(int $number, mixed $id): string
    {
        $component = Component::query()->find((int) $id);

        if (! $component) {
            return $this->comment($number, 'Library component — no longer exists, left out');
        }

        return $this->comment($number, "Library: {$component->name} — shared, edit it in the Component library")
            . "@cmsComponent('{$component->handle}')\n";
    }

    protected function comment(int $number, string $label): string
    {
        $text = "── {$number}. {$label} ";

        return '{{-- ' . $text . str_repeat('─', max(3, 70 - mb_strlen($text))) . " --}}\n";
    }

    /**
     * The component view's source, indented, without its opening doc comment
     * (which describes variables the data above now supplies).
     */
    protected function markup(string $view): string
    {
        $source = File::get(app('view')->getFinder()->find($view));
        $source = preg_replace('/^\s*\{\{--.*?--\}\}\s*/s', '', $source, 1);
        $source = $this->expandInlinePhp($source);

        $lines = array_map(
            fn (string $line): string => $line === '' ? '' : '    ' . $line,
            explode("\n", rtrim($source)),
        );

        return implode("\n", $lines) . "\n";
    }

    /**
     * Rewrite one-line `@php(...)` as `@php ... @endphp`.
     *
     * Blade pairs every `@php` with the next `@endphp` before it compiles
     * anything, so a one-line `@php(...)` copied into a file that also holds
     * block `@php` sections swallows everything up to that block's end.
     */
    protected function expandInlinePhp(string $source): string
    {
        $offset = 0;

        while (preg_match('/(?<!@)@php\s*\(/', $source, $match, PREG_OFFSET_CAPTURE, $offset)) {
            $start = $match[0][1];
            $open = $start + strlen($match[0][0]) - 1;
            $close = $this->closingParenthesis($source, $open);

            if ($close === null) {
                break;
            }

            $statement = trim(substr($source, $open + 1, $close - $open - 1));
            $replacement = "@php {$statement}; @endphp";

            $source = substr_replace($source, $replacement, $start, $close - $start + 1);
            $offset = $start + strlen($replacement);
        }

        return $source;
    }

    /**
     * The parenthesis closing the one at $open, skipping any inside strings.
     */
    protected function closingParenthesis(string $source, int $open): ?int
    {
        $depth = 0;
        $quote = null;

        for ($i = $open, $length = strlen($source); $i < $length; $i++) {
            $char = $source[$i];

            if ($quote !== null) {
                if ($char === '\\') {
                    $i++;
                } elseif ($char === $quote) {
                    $quote = null;
                }

                continue;
            }

            if ($char === '"' || $char === "'") {
                $quote = $char;
            } elseif ($char === '(') {
                $depth++;
            } elseif ($char === ')' && --$depth === 0) {
                return $i;
            }
        }

        return null;
    }

    /**
     * Data as readable PHP: short arrays, lists without keys, indented.
     */
    protected function export(mixed $value, int $depth): string
    {
        if (! is_array($value)) {
            return var_export($value, true);
        }

        if ($value === []) {
            return '[]';
        }

        $pad = str_repeat('    ', $depth + 1);
        $list = array_is_list($value);
        $lines = [];

        foreach ($value as $key => $item) {
            $lines[] = $pad . ($list ? '' : var_export($key, true) . ' => ') . $this->export($item, $depth + 1) . ',';
        }

        return "[\n" . implode("\n", $lines) . "\n" . str_repeat('    ', $depth) . ']';
    }

    protected function today(): string
    {
        return now()->toDateString();
    }
}
