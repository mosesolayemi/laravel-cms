<?php

namespace Cms\Filament\Resources\Pages\Tables;

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
                TextColumn::make('layouts.label')
                    ->label('Layout')
                    ->placeholder('None')
                    ->toggleable(),
                TextColumn::make('blocks_count')
                    ->label('Blocks')
                    ->counts('blocks')
                    ->alignCenter(),
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
