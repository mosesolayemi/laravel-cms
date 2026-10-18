<?php

namespace PhynixMedia\Cms\Filament\Resources\Pages;

use BackedEnum;
use PhynixMedia\Cms\Filament\Resources\Pages\Pages\CreatePage;
use PhynixMedia\Cms\Filament\Resources\Pages\Pages\EditPage;
use PhynixMedia\Cms\Filament\Resources\Pages\Pages\ListPages;
use PhynixMedia\Cms\Filament\Resources\Pages\RelationManagers\BlocksRelationManager;
use PhynixMedia\Cms\Filament\Resources\Pages\Schemas\PageForm;
use PhynixMedia\Cms\Filament\Resources\Pages\Tables\PagesTable;
use PhynixMedia\Cms\Models\Pages\Templates;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class PageResource extends Resource
{
    protected static ?string $model = Templates::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentText;

    protected static string|UnitEnum|null $navigationGroup = 'Content';

    protected static ?string $modelLabel = 'page';

    protected static ?string $recordTitleAttribute = 'title';

    protected static ?int $navigationSort = 10;

    public static function form(Schema $schema): Schema
    {
        return PageForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return PagesTable::configure($table);
    }

    /**
     * The older blocks/groups content model is superseded by the component
     * builder, so its screen only appears where a site still reads it.
     */
    public static function getRelations(): array
    {
        return config('cms.panel.legacy_blocks', false) ? [BlocksRelationManager::class] : [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPages::route('/'),
            'create' => CreatePage::route('/create'),
            'edit' => EditPage::route('/{record}/edit'),
        ];
    }
}
