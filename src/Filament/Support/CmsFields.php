<?php

namespace Cms\Filament\Support;

use Cms\Models\Assets\Asset;
use Filament\Actions\Action;
use Filament\Forms\Components\BaseFileUpload;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Str;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

/**
 * Shared form fields for the CMS tables.
 *
 * The legacy schema stores every boolean as an integer (or, for the menu
 * positions, a numeric string), so the conversion lives here once instead of
 * being repeated — and mis-repeated — in eight resources.
 */
class CmsFields
{
    /**
     * `status` doubles as the soft-delete flag across the CMS tables: the old
     * API set it to 0 rather than deleting rows, and the public site filters
     * on status = 1.
     */
    public static function published(string $name = 'status'): Toggle
    {
        return Toggle::make($name)
            ->label('Published')
            ->helperText('Unpublished records stay in the database but are hidden from the site.')
            ->default(true)
            ->dehydrateStateUsing(fn ($state): int => $state ? 1 : 0);
    }

    /**
     * The menu-position columns are strings holding "1" / "0".
     */
    public static function flag(string $name, string $label): Toggle
    {
        return Toggle::make($name)
            ->label($label)
            ->default(false)
            ->dehydrateStateUsing(fn ($state): string => $state ? '1' : '0');
    }

    /**
     * An image on the CMS media disk, for component fields.
     *
     * Two ways in: upload from the computer — the file is added to the asset
     * library as it is stored — or "Choose from assets" to reuse one already
     * there. Either way the field stores a plain path, which components
     * resolve with cms_media().
     */
    public static function image(string $name = 'image', string $directory = 'components'): FileUpload
    {
        return FileUpload::make($name)
            ->image()
            ->disk(config('cms.media.disk'))
            ->directory(trim(config('cms.media.images') . '/' . $directory, '/'))
            ->visibility('public')
            ->imageEditor()
            ->maxSize(8192)
            ->saveUploadedFileUsing(function (BaseFileUpload $component, TemporaryUploadedFile $file): ?string {
                $path = $component->saveUploadedFile($file);

                if ($path) {
                    Asset::register($path, $component->getDiskName(), [
                        'name' => Str::headline(pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME)),
                        'group' => 'Uploads',
                    ]);
                }

                return $path;
            })
            ->hintAction(static::chooseAssetAction());
    }

    /**
     * Opens the asset library and puts the chosen file into the field.
     */
    public static function chooseAssetAction(): Action
    {
        return Action::make('chooseAsset')
            ->label('Choose from assets')
            ->icon(Heroicon::OutlinedPhoto)
            ->modalHeading('Choose from the asset library')
            ->modalSubmitActionLabel('Use this image')
            ->modalWidth(Width::TwoExtraLarge)
            ->schema([
                Select::make('group')
                    ->label('Group')
                    ->placeholder('All groups')
                    ->options(fn (): array => array_combine(Asset::groups(), Asset::groups()) ?: [])
                    ->live(),
                Select::make('asset')
                    ->label('Image')
                    ->required()
                    ->searchable()
                    ->allowHtml()
                    ->options(fn (Get $get): array => static::assetOptions($get('group')))
                    ->getSearchResultsUsing(fn (string $search, Get $get): array => static::assetOptions($get('group'), $search))
                    ->getOptionLabelUsing(fn ($value): ?string => ($asset = Asset::query()->find($value)) ? static::assetLabel($asset) : null)
                    ->helperText('Upload new files on the Assets screen, or straight into this field from your computer.'),
            ])
            ->action(function (array $data, FileUpload $component): void {
                $asset = Asset::query()->find($data['asset']);

                if ($asset) {
                    // The upload field holds its files keyed by a uuid.
                    $component->state([(string) Str::uuid() => $asset->path]);
                }
            });
    }

    /**
     * @return array<int, string>
     */
    protected static function assetOptions(?string $group = null, ?string $search = null): array
    {
        return Asset::query()
            ->where('mime_type', 'like', 'image/%')
            ->when($group, fn ($query) => $query->where('group', $group))
            ->when($search, fn ($query) => $query->where(fn ($query) => $query
                ->where('name', 'like', "%{$search}%")
                ->orWhere('path', 'like', "%{$search}%")
                ->orWhere('group', 'like', "%{$search}%")))
            ->latest()
            ->limit(60)
            ->get()
            ->mapWithKeys(fn (Asset $asset): array => [$asset->getKey() => static::assetLabel($asset)])
            ->all();
    }

    /**
     * A thumbnail, name and group, as HTML for the picker.
     */
    protected static function assetLabel(Asset $asset): string
    {
        return '<span style="display:flex;align-items:center;gap:10px;">'
            . '<img src="' . e($asset->url()) . '" alt="" style="width:44px;height:44px;object-fit:cover;border-radius:6px;flex:none;">'
            . '<span><span style="display:block;">' . e($asset->name) . '</span>'
            . '<span style="display:block;font-size:12px;opacity:.65;">' . e(collect([$asset->group, $asset->dimensions()])->filter()->implode(' · ')) . '</span></span>'
            . '</span>';
    }
}
