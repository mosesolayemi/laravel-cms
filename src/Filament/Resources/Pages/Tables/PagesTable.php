<?php

namespace PhynixMedia\Cms\Filament\Resources\Pages\Tables;

use PhynixMedia\Cms\Cms;
use PhynixMedia\Cms\Models\Pages\Templates;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Support\Icons\Heroicon;
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
                    ->visibleFrom('md')
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
                TextColumn::make('layouts.label')
                    ->visibleFrom('md')
                    ->label('Layout')
                    ->placeholder('None')
                    ->badge()
                    ->color('gray')
                    ->toggleable(),
                TextColumn::make('content')
                    ->visibleFrom('md')
                    ->label('Components')
                    ->state(fn (Templates $record): string => $record->publishedView()
                        ? 'Blade file'
                        : (string) count($record->content ?? []))
                    ->badge(fn (Templates $record): bool => $record->publishedView() !== null)
                    ->color('warning')
                    ->tooltip(fn (Templates $record): ?string => $record->publishedView()
                        ? 'Drawn by resources/views/' . str_replace('.', '/', $record->view) . '.blade.php'
                        : null)
                    ->alignCenter()
                    ->toggleable(),
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
                    ->visibleFrom('md')
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
                ActionGroup::make([
                    Action::make('preview')
                        ->icon(Heroicon::OutlinedEye)
                        ->url(fn (Templates $record): string => route('cms.preview.page', $record))
                        ->openUrlInNewTab(),
                    Action::make('visit')
                        ->label('Visit URL')
                        ->icon(Heroicon::OutlinedArrowTopRightOnSquare)
                        ->url(fn (Templates $record): string => $record->link())
                        ->openUrlInNewTab()
                        ->visible(fn (Templates $record): bool => (int) $record->status === 1),
                    DeleteAction::make(),
                ]),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
