<?php

namespace Cms\Components\Types;

use Cms\Components\ComponentType;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;

/**
 * One or more link buttons in a row.
 */
class Buttons extends ComponentType
{
    public function key(): string
    {
        return 'buttons';
    }

    public function label(): string
    {
        return 'Buttons';
    }

    public function icon(): string
    {
        return 'heroicon-o-cursor-arrow-rays';
    }

    public function group(): string
    {
        return 'Text';
    }

    public function schema(): array
    {
        return [
            Repeater::make('buttons')
                ->schema([
                    TextInput::make('label')->required(),
                    TextInput::make('url')->label('Link to')->required()->placeholder('/contact or https://…'),
                    Select::make('style')
                        ->options(['primary' => 'Primary', 'secondary' => 'Secondary', 'link' => 'Plain link'])
                        ->default('primary')
                        ->selectablePlaceholder(false),
                    Toggle::make('new_tab')->label('Open in a new tab')->inline(false),
                ])
                ->columns(2)
                ->minItems(1)
                ->defaultItems(1)
                ->itemLabel(fn (array $state): ?string => $state['label'] ?? null)
                ->collapsible(),
            Select::make('align')
                ->options(Width::ALIGNMENTS)
                ->default('left')
                ->selectablePlaceholder(false),
        ];
    }

    public function view(): string
    {
        return 'cms::components.buttons';
    }

    public function summary(array $data): ?string
    {
        return collect($data['buttons'] ?? [])->pluck('label')->filter()->implode(' · ') ?: null;
    }
}
