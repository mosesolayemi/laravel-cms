<?php

namespace Cms\Filament\Resources\Pages\Pages;

use Cms\Filament\Resources\Pages\PageResource;
use Filament\Resources\Pages\CreateRecord;

class CreatePage extends CreateRecord
{
    protected static string $resource = PageResource::class;

    /**
     * Blocks are managed from the edit screen's relation manager, so land
     * there rather than back on the list.
     */
    protected function getRedirectUrl(): string
    {
        return static::getResource()::getUrl('edit', ['record' => $this->getRecord()]);
    }
}
