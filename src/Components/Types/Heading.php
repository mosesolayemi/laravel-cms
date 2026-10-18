<?php

namespace PhynixMedia\Cms\Components\Types;

use PhynixMedia\Cms\Components\ComponentType;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;

/**
 * A standalone heading, with an optional line of supporting text.
 */
class Heading extends ComponentType
{
    public function key(): string
    {
        return 'heading';
    }

    public function label(): string
    {
        return 'Heading';
    }

    public function icon(): string
    {
        return 'heroicon-o-h1';
    }

    public function group(): string
    {
        return 'Text';
    }

    public function schema(): array
    {
        return [
            TextInput::make('heading')->required()->maxLength(255),
            Textarea::make('subheading')->rows(2),
            Grid::make(2)->schema([
                Select::make('level')
                    ->options(['h1' => 'H1 — page title', 'h2' => 'H2', 'h3' => 'H3', 'h4' => 'H4'])
                    ->default('h2')
                    ->selectablePlaceholder(false),
                Select::make('align')
                    ->options(Width::ALIGNMENTS)
                    ->default('left')
                    ->selectablePlaceholder(false),
            ]),
        ];
    }

    public function view(): string
    {
        return 'cms::components.heading';
    }
}
