<?php

namespace PhynixMedia\Cms\Filament\Resources\Blocks\Schemas;

use PhynixMedia\Cms\Filament\Support\CmsFields;
use PhynixMedia\Cms\Models\Pages\Templates;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class BlockForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('template_id')
                    ->label('Page')
                    ->options(fn (): array => Templates::query()->pluck('title', 'id')->all())
                    ->searchable()
                    ->required(),
                TextInput::make('block_name')
                    ->required()
                    ->maxLength(255)
                    ->helperText('The key the Blade view reads, e.g. "hero".'),
                Toggle::make('multiple')
                    ->label('Repeatable')
                    ->helperText('Allows more than one group of content in this block.')
                    ->dehydrateStateUsing(fn ($state): int => $state ? 1 : 0),
                TextInput::make('item_max')
                    ->label('Maximum items')
                    ->numeric()
                    ->default(1)
                    ->required(),
                CmsFields::published(),
            ]);
    }
}
