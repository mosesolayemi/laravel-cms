<?php

namespace Cms\Filament\Resources\Components\Tables;

use Cms\Components\ComponentRegistry;
use Cms\Models\Components\Component;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ReplicateAction;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Grouping\Group;
use Filament\Tables\Table;
use Illuminate\Support\Str;

class ComponentsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable()
                    ->description(fn (Component $record): ?string => $record->description ? Str::limit($record->description, 80) : null),
                TextColumn::make('type')
                    ->badge()
                    ->formatStateUsing(fn (Component $record): string => $record->typeLabel())
                    ->color(fn (Component $record): string => $record->componentType() ? 'primary' : 'danger')
                    ->sortable(),
                TextColumn::make('group')
                    ->placeholder('—')
                    ->badge()
                    ->color('gray')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('handle')
                    ->visibleFrom('md')
                    ->fontFamily('mono')
                    ->size('sm')
                    ->color('gray')
                    ->searchable()
                    ->toggleable(),
                TextColumn::make('usage')
                    ->label('Used on')
                    ->state(fn (Component $record): int => $record->usages()->count())
                    ->formatStateUsing(fn (int $state): string => $state === 1 ? '1 place' : "{$state} places")
                    ->badge()
                    ->color(fn (int $state): string => $state ? 'success' : 'gray'),
                IconColumn::make('status')
                    ->label('Published')
                    ->boolean(),
                TextColumn::make('updated_at')
                    ->visibleFrom('md')
                    ->since()
                    ->sortable()
                    ->toggleable(),
            ])
            ->defaultSort('name')
            ->groups([
                Group::make('group')
                    ->label('Group')
                    ->getTitleFromRecordUsing(fn (Component $record): string => $record->group ?: 'No group'),
                Group::make('type')
                    ->label('Type')
                    ->getTitleFromRecordUsing(fn (Component $record): string => $record->typeLabel()),
            ])
            ->defaultGroup('group')
            ->filters([
                SelectFilter::make('group')
                    ->options(fn (): array => array_combine(Component::groups(), Component::groups())),
                SelectFilter::make('type')
                    ->options(fn (): array => app(ComponentRegistry::class)->options()),
            ])
            ->recordActions([
                Action::make('preview')
                    ->icon(Heroicon::OutlinedEye)
                    ->color('gray')
                    ->url(fn (Component $record): string => route('cms.preview.component', $record))
                    ->openUrlInNewTab(),
                EditAction::make(),
                ReplicateAction::make()
                    ->label('Duplicate')
                    ->excludeAttributes(['uuid', 'handle'])
                    ->beforeReplicaSaved(function (Component $replica, Component $record): void {
                        $replica->uuid = null;
                        $replica->name = "{$record->name} (copy)";
                        $replica->handle = Component::uniqueHandle($replica->name);
                    }),
                DeleteAction::make()
                    ->modalDescription(fn (Component $record): string => ($count = $record->usages()->count())
                        ? "This component is placed in {$count} place(s). Those sections will disappear from the site."
                        : 'Are you sure you would like to do this?'),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
