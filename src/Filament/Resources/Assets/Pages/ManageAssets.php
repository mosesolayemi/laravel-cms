<?php

namespace PhynixMedia\Cms\Filament\Resources\Assets\Pages;

use PhynixMedia\Cms\Filament\Resources\Assets\AssetResource;
use PhynixMedia\Cms\Models\Assets\Asset;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ManageRecords;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Str;

class ManageAssets extends ManageRecords
{
    protected static string $resource = AssetResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('upload')
                ->label('Upload files')
                ->icon(Heroicon::OutlinedArrowUpTray)
                ->modalHeading('Upload to the asset library')
                ->modalSubmitActionLabel('Upload')
                ->schema([
                    FileUpload::make('files')
                        ->label('Files from your computer')
                        ->multiple()
                        ->reorderable()
                        ->disk(config('cms.media.disk'))
                        ->directory(trim(config('cms.media.images') . '/' . config('cms.assets.directory', 'assets'), '/'))
                        ->visibility('public')
                        ->storeFileNamesIn('names')
                        ->maxSize(20480)
                        ->required(),
                    AssetResource::groupField(),
                ])
                ->action(function (array $data): void {
                    $count = 0;

                    foreach ($data['files'] ?? [] as $path) {
                        $original = $data['names'][$path] ?? basename($path);

                        Asset::register($path, config('cms.media.disk'), [
                            'name' => Str::headline(pathinfo($original, PATHINFO_FILENAME)),
                            'group' => $data['group'] ?: null,
                        ]) && $count++;
                    }

                    Notification::make()->success()->title("{$count} file(s) added to the library")->send();
                }),
        ];
    }
}
