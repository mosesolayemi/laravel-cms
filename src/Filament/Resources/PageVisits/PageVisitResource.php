<?php

namespace Cms\Filament\Resources\PageVisits;

use BackedEnum;
use Cms\Filament\Resources\PageVisits\Pages\ListPageVisits;
use Cms\Models\Pages\PageVisit;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use UnitEnum;

/**
 * Replaces the old /api/cms/statistics/page-visits endpoint.
 */
class PageVisitResource extends Resource
{
    protected static ?string $model = PageVisit::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChartBar;

    protected static string|UnitEnum|null $navigationGroup = 'Insights';

    protected static ?string $label = 'page visit';

    protected static ?int $navigationSort = 20;

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit($record): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('page_url')->label('Page')->searchable()->sortable(),
                TextColumn::make('visits')->sortable()->alignCenter(),
                TextColumn::make('month')->sortable()->alignCenter(),
                TextColumn::make('year')->sortable()->alignCenter(),
            ])
            ->defaultSort('visits', 'desc')
            ->filters([
                SelectFilter::make('year')
                    ->options(fn (): array => PageVisit::query()
                        ->distinct()
                        ->orderByDesc('year')
                        ->pluck('year', 'year')
                        ->all()),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => ListPageVisits::route('/')];
    }
}
