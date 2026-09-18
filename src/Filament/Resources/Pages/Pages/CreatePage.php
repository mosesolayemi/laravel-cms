<?php

namespace Cms\Filament\Resources\Pages\Pages;

use Cms\Filament\Actions\PreviewAction;
use Cms\Filament\Resources\Pages\PageResource;
use Cms\Preview\PreviewStore;
use Filament\Resources\Pages\CreateRecord;

class CreatePage extends CreateRecord
{
    protected static string $resource = PageResource::class;

    protected function getHeaderActions(): array
    {
        return [
            PreviewAction::make(PreviewStore::PAGE),
        ];
    }

    /**
     * Land on the edit screen, where the page can be previewed and visited.
     */
    protected function getRedirectUrl(): string
    {
        return static::getResource()::getUrl('edit', ['record' => $this->getRecord()]);
    }
}
