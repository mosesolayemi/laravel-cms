<?php

namespace Cms\Filament\Resources\Pages\Tables;

use Cms\Cms;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class PagesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('title')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('url')
                    ->label('Slug')
                    ->badge()
                    ->searchable(),
                // Makes the menu structure visible at a glance, so a page
                // nested under the wrong parent is obvious rather than just
                // quietly missing from the menu.
                TextColumn::make('parentPage.title')
                    ->label('Nested under')
                    ->placeholder('Top level')
                    ->badge()
                    ->color('gray')
                    ->toggleable(),
                TextColumn::make('children_count')
                    ->label('Child pages')
                    ->counts('children')
                    ->alignCenter()
                    ->toggleable(),
                TextColumn::make('blocks_count')
                    ->label('Blocks')
                    ->counts('blocks')
                    ->alignCenter(),
                TextColumn::make('header_position')
                    ->label('Header')
                    ->badge()
                    ->formatStateUsing(fn ($state): string => $state ? 'Shown' : '—')
                    ->color(fn ($state): string => $state ? 'success' : 'gray')
                    ->toggleable(),
                TextColumn::make('footer_position')
                    ->label('Footer')
                    ->badge()
                    ->formatStateUsing(fn ($state): string => Cms::footerColumns()[$state ?: Cms::FOOTER_NONE] ?? '—')
                    ->color(fn ($state): string => in_array($state, [null, '', '0', Cms::FOOTER_NONE], true) ? 'gray' : 'success')
                    ->toggleable(),
                TextColumn::make('page_order')
                    ->label('Order')
                    ->sortable()
                    ->alignCenter(),
                IconColumn::make('status')
                    ->label('Published')
                    ->boolean(),
            ])
            ->defaultSort('page_order')
            ->filters([
                TernaryFilter::make('status')
                    ->label('Published')
                    ->queries(
                        true: fn ($query) => $query->where('status', 1),
                        false: fn ($query) => $query->where('status', 0),
                        blank: fn ($query) => $query,
                    ),
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
}
