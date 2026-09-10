<?php

namespace Cms\Filament\Resources\Layouts;

use BackedEnum;
use Cms\Filament\Resources\Layouts\Pages\ManageLayouts;
use Cms\Filament\Support\CmsFields;
use Cms\Models\Pages\Layouts;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

/**
 * Layouts are a short, flat list, so they are managed on a single page.
 */
class LayoutResource extends Resource
{
    protected static ?string $model = Layouts::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleGroup;

    protected static string|UnitEnum|null $navigationGroup = 'Content';

    protected static ?string $recordTitleAttribute = 'label';

    protected static ?int $navigationSort = 30;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('label')
                ->required()
                ->maxLength(255),
            TextInput::make('layout')
                ->required()
                ->unique(ignoreRecord: true)
                ->maxLength(255)
                ->helperText('The Blade view name, e.g. "default.blade.php".'),
            CmsFields::published(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('label')->searchable()->sortable(),
                TextColumn::make('layout')->badge()->searchable(),
                IconColumn::make('status')->label('Published')->boolean(),
            ])
            ->headerActions([
                CreateAction::make(),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageLayouts::route('/'),
        ];
    }
}
