<?php

namespace PhynixMedia\Cms\Filament\Resources\Components\Pages;

use PhynixMedia\Cms\Filament\Actions\PreviewAction;
use PhynixMedia\Cms\Filament\Resources\Components\ComponentResource;
use PhynixMedia\Cms\Preview\PreviewStore;
use Filament\Resources\Pages\CreateRecord;

class CreateComponent extends CreateRecord
{
    protected static string $resource = ComponentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            PreviewAction::make(PreviewStore::COMPONENT),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return static::getResource()::getUrl('edit', ['record' => $this->getRecord()]);
    }
}
