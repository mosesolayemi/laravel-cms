<?php

namespace PhynixMedia\Cms\Components;

use PhynixMedia\Cms\Rendering\RenderContext;

/**
 * A kind of component an editor can place on a page or layout.
 *
 * A type is code: the fields an editor fills in, and the Blade view those
 * fields are rendered through. What an editor creates in the panel is an
 * *instance* — the type plus data — stored inline in a page's builder content
 * or as a named component in the library.
 *
 * The package ships a few generic types (rich text, heading, image…). Anything
 * tied to one site's theme — its header, its hero, its card grids — is written
 * in that application and registered through `cms.components.types`, so the
 * package itself never depends on a particular front end.
 */
abstract class ComponentType
{
    /**
     * Where a component may be placed. A type restricted to the header will
     * not be offered in a page's content or a layout's footer.
     */
    public const AREA_CONTENT = 'content';

    public const AREA_HEADER = 'header';

    public const AREA_FOOTER = 'footer';

    /**
     * The stable key stored with every instance. Changing it orphans every
     * instance already saved, so treat it like a database column name.
     */
    abstract public function key(): string;

    abstract public function label(): string;

    /**
     * The Filament fields for this component's data. State paths are relative
     * to the component, so `TextInput::make('title')` stores `data.title`.
     *
     * @return array<int, mixed>
     */
    abstract public function schema(): array;

    /**
     * The Blade view the data is rendered through.
     */
    abstract public function view(): string;

    public function description(): ?string
    {
        return null;
    }

    /**
     * A Heroicon name for the component picker.
     */
    public function icon(): string
    {
        return 'heroicon-o-square-3-stack-3d';
    }

    /**
     * Groups the type with its neighbours in the picker and the library.
     */
    public function group(): string
    {
        return 'Content';
    }

    /**
     * The areas this type may be placed in, or null for anywhere.
     *
     * @return array<int, string>|null
     */
    public function areas(): ?array
    {
        return null;
    }

    public function allowedIn(string $area): bool
    {
        $areas = $this->areas();

        return $areas === null || in_array($area, $areas, true);
    }

    /**
     * Whether the type can be used at all in this installation — a type that
     * depends on configuration can hide itself until it is configured.
     */
    public function isAvailable(): bool
    {
        return true;
    }

    /**
     * A short description of one instance, shown on its collapsed builder
     * item so a long page reads as a list of sections rather than of types.
     *
     * @param  array<string, mixed>  $data
     */
    public function summary(array $data): ?string
    {
        foreach (['title', 'heading', 'name', 'label', 'tagline'] as $key) {
            if (is_string($data[$key] ?? null) && filled($data[$key])) {
                // Plain text: tags dropped, entities such as &mdash; decoded.
                $text = html_entity_decode(strip_tags(str_replace(['<br>', '<br/>', '<br />'], ' ', $data[$key])), ENT_QUOTES | ENT_HTML5);

                return trim(preg_replace('/\s+/u', ' ', $text));
            }
        }

        return null;
    }

    /**
     * Turn stored data into the variables the view expects. Override to fill
     * in defaults, resolve media, or pull in data from elsewhere.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function with(array $data, RenderContext $context): array
    {
        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function render(array $data, RenderContext $context): string
    {
        return view($this->view(), [
            ...$this->with($data, $context),
            'cms' => $context,
        ])->render();
    }
}
