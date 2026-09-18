<?php

namespace Cms\Filament\Resources\Layouts;

use BackedEnum;
use Cms\Filament\Resources\Layouts\Pages\CreateLayout;
use Cms\Filament\Resources\Layouts\Pages\EditLayout;
use Cms\Filament\Resources\Layouts\Pages\ListLayouts;
use Cms\Filament\Resources\Layouts\Schemas\LayoutForm;
use Cms\Models\Pages\Layouts;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\ReplicateAction;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Str;
use UnitEnum;

/**
 * Layouts: the frame a page is rendered in — its header, its footer, and
 * anything that should sit before or after every page's own content.
 */
class LayoutResource extends Resource
{
    protected static ?string $model = Layouts::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleGroup;

    protected static string|UnitEnum|null $navigationGroup = 'Content';

    protected static ?string $modelLabel = 'layout';

    protected static ?string $recordTitleAttribute = 'label';

    protected static ?int $navigationSort = 30;

    public static function form(Schema $schema): Schema
    {
        return LayoutForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('label')
                    ->searchable()
                    ->sortable()
                    ->description(fn (Layouts $record): string => $record->layout),
                TextColumn::make('regions')
                    ->label('Made of')
                    ->visibleFrom('md')
                    ->state(fn (Layouts $record): string => collect(Layouts::REGIONS)
                        ->filter(fn (string $label, string $region): bool => filled($record->{$region}))
                        ->map(fn (string $label, string $region): string => $label . ' (' . count($record->{$region}) . ')')
                        ->implode(', ') ?: 'Content only'),
                TextColumn::make('templates_count')
                    ->label('Pages')
                    ->counts('templates')
                    ->alignCenter(),
                IconColumn::make('is_default')
                    ->label('Default')
                    ->boolean()
                    ->trueIcon(Heroicon::Star)
                    ->falseIcon(null),
                IconColumn::make('status')->label('Published')->boolean(),
            ])
            ->defaultSort('label')
            ->recordActions([
                Action::make('preview')
                    ->icon(Heroicon::OutlinedEye)
                    ->color('gray')
                    ->url(fn (Layouts $record): string => route('cms.preview.layout', $record))
                    ->openUrlInNewTab(),
                EditAction::make(),
                ReplicateAction::make()
                    ->label('Duplicate')
                    ->excludeAttributes(['uuid', 'layout', 'is_default'])
                    ->beforeReplicaSaved(function (Layouts $replica, Layouts $record): void {
                        $replica->label = "{$record->label} (copy)";
                        $replica->layout = Str::slug($replica->label) . '-' . Str::lower(Str::random(4));
                        $replica->is_default = false;
                    }),
                DeleteAction::make()
                    ->modalDescription(fn (Layouts $record): string => ($count = $record->templates()->count())
                        ? "{$count} page(s) use this layout; they will render with no layout until another is chosen."
                        : 'Are you sure you would like to do this?'),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListLayouts::route('/'),
            'create' => CreateLayout::route('/create'),
            'edit' => EditLayout::route('/{record}/edit'),
        ];
    }
}
