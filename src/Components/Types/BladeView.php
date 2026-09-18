<?php

namespace Cms\Components\Types;

use Cms\Components\ComponentType;
use Cms\Rendering\RenderContext;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Select;

/**
 * Places a developer-written Blade view as a section.
 *
 * For markup too bespoke to be worth a component of its own. Only views the
 * host lists in `cms.components.views` can be chosen, so an editor cannot
 * render arbitrary templates; the type hides itself until some are listed.
 */
class BladeView extends ComponentType
{
    public function key(): string
    {
        return 'blade-view';
    }

    public function label(): string
    {
        return 'Developer section';
    }

    public function description(): ?string
    {
        return 'A section built in code by a developer.';
    }

    public function icon(): string
    {
        return 'heroicon-o-command-line';
    }

    public function group(): string
    {
        return 'Advanced';
    }

    public function isAvailable(): bool
    {
        return filled(config('cms.components.views'));
    }

    public function schema(): array
    {
        return [
            Select::make('view')
                ->label('Section')
                ->options(fn (): array => config('cms.components.views', []))
                ->required(),
            KeyValue::make('parameters')
                ->helperText('Optional values handed to the section, if it reads any.')
                ->keyLabel('Name')
                ->valueLabel('Value'),
        ];
    }

    public function view(): string
    {
        return 'cms::components.blade-view';
    }

    public function summary(array $data): ?string
    {
        return config('cms.components.views')[$data['view'] ?? ''] ?? null;
    }

    public function with(array $data, RenderContext $context): array
    {
        $view = $data['view'] ?? null;

        return [
            // Re-checked at render time: a view removed from the list stops
            // rendering rather than staying reachable through old content.
            'section' => array_key_exists((string) $view, config('cms.components.views', [])) ? $view : null,
            'parameters' => is_array($data['parameters'] ?? null) ? $data['parameters'] : [],
        ];
    }
}
