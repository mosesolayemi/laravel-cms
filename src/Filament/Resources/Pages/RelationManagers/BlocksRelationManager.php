<?php

namespace PhynixMedia\Cms\Filament\Resources\Pages\RelationManagers;

use PhynixMedia\Cms\Filament\Resources\Blocks\BlockResource;
use PhynixMedia\Cms\Filament\Support\CmsFields;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * The sections that make up a page. Each block holds one or more groups, and
 * the groups hold the actual content elements.
 */
class BlocksRelationManager extends RelationManager
{
    protected static string $relationship = 'blocks';

    protected static ?string $title = 'Content blocks';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('block_name')
                    ->required()
                    ->maxLength(255)
                    ->helperText('The key the Blade view reads, e.g. "hero".'),
                Toggle::make('multiple')
                    ->label('Repeatable')
                    ->helperText('Allows more than one group of content in this block.')
                    ->default(false)
                    ->dehydrateStateUsing(fn ($state): int => $state ? 1 : 0),
                TextInput::make('item_max')
                    ->label('Maximum items')
                    ->numeric()
                    ->default(1)
                    ->required(),
                CmsFields::published(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('block_name')
            ->columns([
                TextColumn::make('block_name')->searchable(),
                IconColumn::make('multiple')->label('Repeatable')->boolean(),
                TextColumn::make('item_max')->label('Max')->alignCenter(),
                TextColumn::make('groups_count')
                    ->label('Groups')
                    ->counts('groups')
                    ->alignCenter(),
                IconColumn::make('status')->label('Published')->boolean(),
            ])
            ->headerActions([
                CreateAction::make(),
            ])
            ->recordActions([
                // Groups and their elements are edited on the block's own
                // screen, which has the room for a nested repeater.
                Action::make('content')
                    ->icon(Heroicon::OutlinedPencilSquare)
                    ->url(fn ($record): string => BlockResource::getUrl('edit', ['record' => $record])),
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }
}
