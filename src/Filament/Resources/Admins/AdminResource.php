<?php

namespace Cms\Filament\Resources\Admins;

use Cms\Filament\Resources\Admins\Pages\CreateAdmin;
use Cms\Filament\Resources\Admins\Pages\EditAdmin;
use Cms\Filament\Resources\Admins\Pages\ListAdmins;
use Cms\Filament\Resources\Admins\Schemas\AdminForm;
use Cms\Filament\Resources\Admins\Tables\AdminsTable;
use Cms\Auth\Models\Admin;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class AdminResource extends Resource
{
    protected static ?string $model = Admin::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShieldCheck;

    protected static string|UnitEnum|null $navigationGroup = 'Settings';

    protected static ?string $recordTitleAttribute = 'email';

    protected static ?int $navigationSort = 90;

    public static function form(Schema $schema): Schema
    {
        return AdminForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return AdminsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAdmins::route('/'),
            'create' => CreateAdmin::route('/create'),
            'edit' => EditAdmin::route('/{record}/edit'),
        ];
    }
}
