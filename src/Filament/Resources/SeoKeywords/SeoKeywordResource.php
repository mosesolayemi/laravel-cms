<?php

namespace Cms\Filament\Resources\SeoKeywords;

use BackedEnum;
use Cms\Filament\Resources\SeoKeywords\Pages\ManageSeoKeywords;
use Cms\Filament\Support\CmsFields;
use Cms\Models\Pages\SeoKeyword;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

class SeoKeywordResource extends Resource
{
    protected static ?string $model = SeoKeyword::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedMagnifyingGlass;

    protected static string|UnitEnum|null $navigationGroup = 'Content';

    protected static ?string $label = 'SEO keyword set';

    protected static ?string $recordTitleAttribute = 'meta_title';

    protected static ?int $navigationSort = 40;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('meta_title')
                ->required()
                ->maxLength(255)
                ->columnSpanFull(),
            Textarea::make('meta_description')
                ->required()
                ->rows(3)
                ->columnSpanFull(),
            Textarea::make('meta_keywords')
                ->rows(2)
                ->helperText('Comma separated.')
                ->columnSpanFull(),
            CmsFields::flag('in_header', 'Output in header'),
            CmsFields::flag('in_footer', 'Output in footer'),
            CmsFields::published(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('meta_title')->searchable()->limit(50),
                TextColumn::make('meta_description')->limit(60)->toggleable(),
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
            'index' => ManageSeoKeywords::route('/'),
        ];
    }
}
