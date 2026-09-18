<?php

namespace Cms\Filament\Resources\Layouts\Pages;

use Cms\Filament\Actions\PreviewAction;
use Cms\Filament\Resources\Layouts\LayoutResource;
use Cms\Preview\PreviewStore;
use Filament\Resources\Pages\CreateRecord;

class CreateLayout extends CreateRecord
{
    protected static string $resource = LayoutResource::class;

    protected function getHeaderActions(): array
    {
        return [
            PreviewAction::make(PreviewStore::LAYOUT),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return static::getResource()::getUrl('edit', ['record' => $this->getRecord()]);
    }
}
