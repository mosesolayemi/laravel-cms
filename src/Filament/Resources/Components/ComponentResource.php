<?php

namespace PhynixMedia\Cms\Filament\Resources\Components;

use BackedEnum;
use PhynixMedia\Cms\Filament\Resources\Components\Pages\CreateComponent;
use PhynixMedia\Cms\Filament\Resources\Components\Pages\EditComponent;
use PhynixMedia\Cms\Filament\Resources\Components\Pages\ListComponents;
use PhynixMedia\Cms\Filament\Resources\Components\Schemas\ComponentForm;
use PhynixMedia\Cms\Filament\Resources\Components\Tables\ComponentsTable;
use PhynixMedia\Cms\Models\Components\Component;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

/**
 * The component library: shared components any page or layout can place.
 */
class ComponentResource extends Resource
{
    protected static ?string $model = Component::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPuzzlePiece;

    protected static string|UnitEnum|null $navigationGroup = 'Content';

    protected static ?string $navigationLabel = 'Component library';

    protected static ?string $modelLabel = 'component';

    protected static ?string $recordTitleAttribute = 'name';

    protected static ?int $navigationSort = 20;

    public static function form(Schema $schema): Schema
    {
        return ComponentForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ComponentsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListComponents::route('/'),
            'create' => CreateComponent::route('/create'),
            'edit' => EditComponent::route('/{record}/edit'),
        ];
    }
}
