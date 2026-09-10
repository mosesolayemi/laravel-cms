<?php

namespace Cms\Filament\Resources\Blocks;

use BackedEnum;
use Cms\Filament\Resources\Blocks\Pages\EditBlock;
use Cms\Filament\Resources\Blocks\Pages\ListBlocks;
use Cms\Filament\Resources\Blocks\RelationManagers\GroupsRelationManager;
use Cms\Filament\Resources\Blocks\Schemas\BlockForm;
use Cms\Filament\Resources\Blocks\Tables\BlocksTable;
use Cms\Models\Pages\Blocks;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

/**
 * Blocks are reached from the page they belong to, so they are kept out of the
 * sidebar to avoid a second, context-free way in.
 */
class BlockResource extends Resource
{
    protected static ?string $model = Blocks::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSquares2x2;

    protected static string|UnitEnum|null $navigationGroup = 'Content';

    protected static ?string $modelLabel = 'content block';

    protected static ?string $recordTitleAttribute = 'block_name';

    protected static bool $shouldRegisterNavigation = false;

    public static function form(Schema $schema): Schema
    {
        return BlockForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return BlocksTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            GroupsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListBlocks::route('/'),
            'edit' => EditBlock::route('/{record}/edit'),
        ];
    }
}
