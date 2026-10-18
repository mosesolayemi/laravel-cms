<?php

namespace PhynixMedia\Cms\Publishing;

use PhynixMedia\Cms\Components\ComponentRegistry;
use PhynixMedia\Cms\Rendering\RenderContext;
use Illuminate\Support\Str;

/**
 * Runtime support for published page files.
 *
 * A published section keeps its data as the CMS stored it and its markup as
 * the component's view has it; this turns the one into the variables the
 * other expects, through the component type — so anything a type works out
 * at render time (a gallery read from the database, a resolved image URL)
 * stays live rather than being frozen into the file.
 */
class Sections
{
    /**
     * Variables a published file already relies on, never overwritten by a
     * section's data.
     */
    protected const RESERVED = ['app', 'errors', 'page'];

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function variables(string $type, array $data, ?RenderContext $context = null): array
    {
        $component = app(ComponentRegistry::class)->get($type);

        if (! $component) {
            return [];
        }

        $context ??= new RenderContext;
        unset($data[ComponentRegistry::HIDDEN]);

        $variables = [...$component->with($data, $context), 'cms' => $context];

        return array_filter(
            $variables,
            fn (string $name): bool => ! in_array($name, self::RESERVED, true) && ! Str::startsWith($name, '__'),
            ARRAY_FILTER_USE_KEY,
        );
    }

    /**
     * The Blade directives published files are written with.
     *
     *     @cmsSection('intro', $data) ...markup... @endCmsSection
     *         Runs the markup with the section's variables, then removes
     *         every variable the section defined, so nothing leaks into the
     *         next section's `$var ?? ''` defaults.
     *
     *     @cmsBlock('intro', $data)
     *         Renders the section through its component type, as the CMS does.
     *
     * @return array<string, \Closure(string): string>
     */
    public static function directives(): array
    {
        return [
            'cmsSection' => fn (string $expression): string => '<?php $__cmsBefore = array_keys(get_defined_vars()); '
                . 'extract(\\' . self::class . "::variables({$expression}, \$cms ?? null)); ?>",

            'endCmsSection' => fn (): string => '<?php foreach (array_diff(array_keys(get_defined_vars()), $__cmsBefore) as $__cmsVar) { unset($$__cmsVar); } '
                . 'unset($__cmsVar); ?>',

            'cmsBlock' => fn (string $expression): string => '<?php echo app(\\' . \PhynixMedia\Cms\Rendering\ComponentRenderer::class
                . "::class)->renderType({$expression}, \$cms ?? new \\" . RenderContext::class . '); ?>',
        ];
    }
}
