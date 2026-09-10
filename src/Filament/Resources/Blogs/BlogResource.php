<?php

namespace Cms\Filament\Resources\Blogs;

use BackedEnum;
use Cms\Filament\Resources\Blogs\Pages\CreateBlog;
use Cms\Filament\Resources\Blogs\Pages\EditBlog;
use Cms\Filament\Resources\Blogs\Pages\ListBlogs;
use Cms\Filament\Resources\Blogs\Schemas\BlogForm;
use Cms\Filament\Resources\Blogs\Tables\BlogsTable;
use Cms\Models\Blogs\Blogs;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class BlogResource extends Resource
{
    protected static ?string $model = Blogs::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedNewspaper;

    protected static string|UnitEnum|null $navigationGroup = 'Blog';

    protected static ?string $modelLabel = 'post';

    protected static ?string $recordTitleAttribute = 'title';

    protected static ?int $navigationSort = 10;

    public static function form(Schema $schema): Schema
    {
        return BlogForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return BlogsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListBlogs::route('/'),
            'create' => CreateBlog::route('/create'),
            'edit' => EditBlog::route('/{record}/edit'),
        ];
    }
}
