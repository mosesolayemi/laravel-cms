<?php

namespace PhynixMedia\Cms\Filament\Resources\Assets;

use BackedEnum;
use Closure;
use PhynixMedia\Cms\Filament\Resources\Assets\Pages\ManageAssets;
use PhynixMedia\Cms\Filament\Resources\Components\ComponentResource;
use PhynixMedia\Cms\Filament\Resources\Layouts\LayoutResource;
use PhynixMedia\Cms\Filament\Resources\Pages\PageResource;
use PhynixMedia\Cms\Filament\Support\CmsFields;
use PhynixMedia\Cms\Models\Assets\Asset;
use PhynixMedia\Cms\Models\Components\Component;
use PhynixMedia\Cms\Models\Pages\Templates;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\Layout\Stack;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Grouping\Group;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Number;
use UnitEnum;

/**
 * The asset library: every file uploaded through the CMS, ready to be chosen
 * again on any page or component.
 */
class AssetResource extends Resource
{
    protected static ?string $model = Asset::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPhoto;

    protected static string|UnitEnum|null $navigationGroup = 'Content';

    protected static ?string $navigationLabel = 'Assets';

    protected static ?string $recordTitleAttribute = 'name';

    protected static ?int $navigationSort = 25;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')->required()->maxLength(255),
            static::groupField(),
            TextInput::make('alt')
                ->label('Description')
                ->helperText('What the image shows, for screen readers.')
                ->maxLength(255),
            Text::make(fn (?Asset $record): HtmlString => static::usageSummary($record)),
        ])->columns(1);
    }

    public static function groupField(): Select
    {
        return CmsFields::group(
            fn (): array => Asset::groups(),
            'Files the asset in the library. Pick a group, or use + to start a new one.',
        );
    }

    public static function table(Table $table): Table
    {
        return $table
            ->contentGrid(['default' => 2, 'md' => 3, 'xl' => 5])
            ->columns([
                Stack::make([
                    ImageColumn::make('path')
                        ->label('')
                        ->disk(fn (Asset $record): string => $record->disk)
                        ->visibility('public')
                        ->imageHeight(140)
                        ->extraImgAttributes(['style' => 'width:100%;object-fit:cover;border-radius:8px;'])
                        ->defaultImageUrl(null),
                    TextColumn::make('name')
                        ->weight('medium')
                        ->searchable()
                        ->sortable()
                        ->limit(40),
                    TextColumn::make('meta')
                        ->state(fn (Asset $record): string => collect([
                            $record->dimensions(),
                            $record->size ? Number::fileSize($record->size, 1) : null,
                        ])->filter()->implode(' · '))
                        ->color('gray')
                        ->size('sm'),
                    TextColumn::make('group')
                        ->badge()
                        ->color('gray')
                        // The badge is on screen, so it is what an editor
                        // types to find a file again; searching only the name
                        // returned nothing for a group they could plainly see.
                        ->searchable()
                        ->placeholder('No group'),
                ])->space(2),
            ])
            ->groups([
                Group::make('group')
                    ->label('Group')
                    ->getTitleFromRecordUsing(fn (Asset $record): string => $record->group ?: 'No group'),
            ])
            ->defaultGroup('group')
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('group')
                    ->options(fn (): array => array_combine(Asset::groups(), Asset::groups()) ?: []),
                TernaryFilter::make('images')
                    ->label('Kind')
                    ->trueLabel('Images')
                    ->falseLabel('Other files')
                    ->queries(
                        true: fn ($query) => $query->where('mime_type', 'like', 'image/%'),
                        false: fn ($query) => $query->where(fn ($q) => $q->whereNull('mime_type')->orWhere('mime_type', 'not like', 'image/%')),
                        blank: fn ($query) => $query,
                    ),
            ])
            ->recordActions([
                Action::make('open')
                    ->icon(Heroicon::OutlinedArrowTopRightOnSquare)
                    ->iconButton()
                    ->tooltip('Open the file')
                    ->url(fn (Asset $record): string => $record->url())
                    ->openUrlInNewTab(),
                EditAction::make()->iconButton(),
                DeleteAction::make()
                    ->iconButton()
                    ->modalDescription(fn (Asset $record): string => $record->managed
                        ? 'The file is deleted too.'
                        : 'The file belongs to another part of the site and is kept; only this library entry is removed.')
                    ->before(function (Asset $record, DeleteAction $action) {
                        if ($record->usages()->isNotEmpty()) {
                            static::refuseDeletion([$record]);
                            $action->cancel();
                        }
                    }),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('moveToGroup')
                        ->label('Move to group')
                        ->icon(Heroicon::OutlinedFolder)
                        ->schema([static::groupField()])
                        ->action(fn (Collection $records, array $data) => $records->each->update(['group' => $data['group'] ?: null]))
                        ->deselectRecordsAfterCompletion(),
                    DeleteBulkAction::make()
                        ->action(function (Collection $records) {
                            [$used, $free] = $records->partition(fn (Asset $asset): bool => $asset->usages()->isNotEmpty());
                            $free->each->delete();

                            if ($used->isNotEmpty()) {
                                static::refuseDeletion($used->all());
                            }
                        }),
                ]),
            ]);
    }

    /**
     * @param  array<int, Asset>  $assets
     */
    protected static function refuseDeletion(array $assets): void
    {
        Notification::make()
            ->warning()
            ->title(count($assets) === 1 ? 'That asset is still in use' : count($assets) . ' assets are still in use')
            ->body('Remove it from these first: ' . collect($assets)
                ->flatMap(fn (Asset $asset) => $asset->usages())
                ->map(fn ($model): string => $model->title ?? $model->name ?? $model->label)
                ->unique()
                ->implode(', '))
            ->persistent()
            ->send();
    }

    protected static function usageSummary(?Asset $record): HtmlString
    {
        $usages = $record?->usages() ?? collect();

        if ($usages->isEmpty()) {
            return new HtmlString('<strong>Used on:</strong> nothing yet.');
        }

        $links = $usages->map(function ($model): string {
            [$label, $url] = match (true) {
                $model instanceof Templates => [$model->title, PageResource::getUrl('edit', ['record' => $model])],
                $model instanceof Component => [$model->name . ' (component)', ComponentResource::getUrl('edit', ['record' => $model])],
                default => [$model->label . ' (layout)', LayoutResource::getUrl('edit', ['record' => $model])],
            };

            return '<a href="' . e($url) . '" style="text-decoration:underline;">' . e($label) . '</a>';
        });

        return new HtmlString('<strong>Used on:</strong> ' . $links->implode(', '));
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageAssets::route('/'),
        ];
    }
}
