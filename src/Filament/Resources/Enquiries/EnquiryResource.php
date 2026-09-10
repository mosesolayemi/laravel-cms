<?php

namespace Cms\Filament\Resources\Enquiries;

use BackedEnum;
use Cms\Filament\Resources\Enquiries\Pages\ListEnquiries;
use Cms\Models\Pages\WebEnquiry;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

/**
 * Enquiries arrive from the public contact form. They are read-only here —
 * nobody should be editing what a visitor wrote.
 */
class EnquiryResource extends Resource
{
    protected static ?string $model = WebEnquiry::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedInbox;

    protected static string|UnitEnum|null $navigationGroup = 'Insights';

    protected static ?string $recordTitleAttribute = 'subject';

    protected static ?int $navigationSort = 10;

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit($record): bool
    {
        return false;
    }

    public static function getNavigationBadge(): ?string
    {
        return (string) static::getModel()::query()->count() ?: null;
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            TextEntry::make('name'),
            TextEntry::make('email')->copyable(),
            TextEntry::make('phone')->copyable(),
            TextEntry::make('subject'),
            TextEntry::make('created_at')->dateTime('d M Y H:i'),
            TextEntry::make('message')->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->searchable()->sortable(),
                TextColumn::make('email')->searchable()->copyable(),
                TextColumn::make('phone')->toggleable(),
                TextColumn::make('subject')->searchable()->limit(40),
                TextColumn::make('created_at')->label('Received')->dateTime('d M Y H:i')->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->recordActions([
                ViewAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([DeleteBulkAction::make()]),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => ListEnquiries::route('/')];
    }
}
