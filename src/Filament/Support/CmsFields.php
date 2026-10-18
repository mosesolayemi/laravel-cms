<?php

namespace PhynixMedia\Cms\Filament\Support;

use Closure;
use PhynixMedia\Cms\Models\Assets\Asset;
use PhynixMedia\Cms\Support\Media;
use Filament\Actions\Action;
use Filament\Forms\Components\BaseFileUpload;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\HtmlString;
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
     * The group a library record is filed under — an asset, a component.
     *
     * A group is not a record of its own: it is whatever string the things in
     * it share. So the dropdown lists the groups in use, and + starts a new
     * one, which only has to hand back a name. Free text alone was the older
     * shape, and it files a second group on any typo; a dropdown alone cannot
     * name a group that does not exist yet. Both are needed.
     *
     * @param  callable(): array<int, string>  $existing  the groups in use
     */
    public static function group(callable $existing, ?string $help = null, string $name = 'group'): Select
    {
        return Select::make($name)
            ->options(function (Get $get) use ($existing, $name): array {
                $groups = $existing();

                // A group just created, or one whose records have since moved
                // away, is still the selected value; without it in the list
                // the field renders empty and quietly loses the choice.
                $current = $get($name);

                if (filled($current) && ! in_array($current, $groups, true)) {
                    $groups[] = $current;
                }

                return array_combine($groups, $groups);
            })
            ->searchable()
            ->placeholder('No group')
            ->helperText($help ?? 'Pick a group, or use + to start a new one.')
            ->createOptionForm([
                TextInput::make('name')
                    ->label('Group name')
                    ->required()
                    ->maxLength(255)
                    ->rule(static fn (): Closure => static function (string $attribute, $value, Closure $fail) use ($existing): void {
                        $clash = collect($existing())
                            ->first(fn (string $group): bool => mb_strtolower($group) === mb_strtolower(trim((string) $value)));

                        // Two groups differing only in case read as one to an
                        // editor but file separately, so the near-miss is
                        // stopped at the point it is made.
                        if ($clash !== null) {
                            $fail("“{$clash}” already exists — pick it from the list instead.");
                        }
                    }),
            ])
            ->createOptionModalHeading('New group')
            ->createOptionUsing(fn (array $data): string => trim($data['name']));
    }

    /**
     * An image on the CMS media disk, for component fields.
     *
     * Two ways in: drop a file straight onto the field, or "Choose an image"
     * for a modal that uploads from the computer or picks one already in the
     * library. Every route registers the file as an asset, and the field
     * stores a plain path, which components resolve with cms_media().
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
            ->saveUploadedFileUsing(static::registerUpload(...))
            ->hintAction(static::chooseAssetAction());
    }

    /**
     * Store an upload and list it in the asset library.
     *
     * Registering on the way past is what makes every image reusable: an
     * editor who uploads a photograph on one screen finds it in the picker on
     * the next, without a separate trip to the Assets screen.
     */
    public static function registerUpload(BaseFileUpload $component, TemporaryUploadedFile $file): ?string
    {
        $path = $component->saveUploadedFile($file);

        if ($path) {
            // Uploading over an image the theme shipped makes it the editor's,
            // so a deploy stops copying the repository's version back over it.
            Asset::claim($path, $component->getDiskName());

            Asset::register($path, $component->getDiskName(), [
                'name' => Str::headline(pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME)),
                'group' => 'Uploads',
            ]);
        }

        return $path;
    }

    /**
     * Opens the asset library and puts the chosen file into the field.
     *
     * The same modal also uploads, because "the image I want is on my
     * computer" is the other half of choosing one, and leaving the modal to
     * find the field's own upload area is a detour with nothing at the end of
     * it that this cannot do.
     */
    public static function chooseAssetAction(): Action
    {
        return Action::make('chooseAsset')
            ->label('Choose an image')
            ->icon(Heroicon::OutlinedPhoto)
            ->modalHeading('Choose an image')
            ->modalDescription('Pick one from the library, or upload a new one — an upload joins the library too.')
            ->modalSubmitActionLabel('Use this image')
            ->modalWidth(Width::TwoExtraLarge)
            ->schema([
                FileUpload::make('upload')
                    ->label('Upload from your computer')
                    ->image()
                    ->disk(fn (): string => config('cms.media.disk'))
                    ->directory(fn (): string => trim(config('cms.media.images') . '/' . config('cms.assets.directory', 'assets'), '/'))
                    ->visibility('public')
                    ->imageEditor()
                    ->maxSize(8192)
                    ->saveUploadedFileUsing(static::registerUpload(...))
                    ->live(),
                Select::make('group')
                    ->label('Group')
                    ->placeholder('All groups')
                    ->options(fn (): array => array_combine(Asset::groups(), Asset::groups()) ?: [])
                    ->live()
                    ->visible(fn (Get $get): bool => ! static::hasUpload($get('upload'))),
                Select::make('asset')
                    // The library's name is picked out, so the two ways in —
                    // upload above, library below — read as one choice.
                    ->label(new HtmlString('Or choose from <span style="color:#ca8a04;font-weight:600;">assets library</span>'))
                    // Only when nothing was uploaded, or the modal would
                    // refuse to close on a perfectly good upload.
                    ->required(fn (Get $get): bool => ! static::hasUpload($get('upload')))
                    ->visible(fn (Get $get): bool => ! static::hasUpload($get('upload')))
                    ->searchable()
                    ->allowHtml()
                    ->options(fn (Get $get): array => static::assetOptions($get('group')))
                    ->getSearchResultsUsing(fn (string $search, Get $get): array => static::assetOptions($get('group'), $search))
                    ->getOptionLabelUsing(fn ($value): ?string => ($asset = Asset::query()->find($value)) ? static::assetLabel($asset) : null),
            ])
            ->action(function (array $data, FileUpload $component): void {
                $path = static::uploadedPath($data['upload'] ?? null, $component)
                    ?? Asset::query()->find($data['asset'] ?? null)?->path;

                if ($path) {
                    // The upload field holds its files keyed by a uuid.
                    $component->state([(string) Str::uuid() => $path]);
                }
            });
    }

    /**
     * Whether the picker is holding an image to use — one already stored, or
     * one still on its way up. Both count, or the modal would go on asking
     * for a library image while a perfectly good upload sits above it.
     */
    protected static function hasUpload(mixed $state): bool
    {
        $value = static::single($state);

        return $value instanceof TemporaryUploadedFile || filled(Media::path($value));
    }

    /**
     * The path for whatever the picker is holding, storing an upload as it
     * goes — through the field that opened the picker, so a file uploaded
     * here lands where that field's own uploads land and joins the library
     * the same way.
     */
    protected static function uploadedPath(mixed $state, FileUpload $target): ?string
    {
        $value = static::single($state);

        return $value instanceof TemporaryUploadedFile
            ? static::registerUpload($target, $value)
            : Media::path($value);
    }

    /**
     * An upload field carries its files as a [uuid => file] array.
     */
    protected static function single(mixed $state): mixed
    {
        return is_array($state) ? collect($state)->first(fn ($item): bool => filled($item)) : $state;
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
