<?php

namespace Cms\Filament\Resources\Components\Pages;

use Cms\Filament\Actions\PreviewAction;
use Cms\Filament\Resources\Components\ComponentResource;
use Cms\Filament\Support\PublishedFile;
use Cms\Publishing\ComponentPublisher;
use Cms\Preview\PreviewStore;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditComponent extends EditRecord
{
    protected static string $resource = ComponentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            PreviewAction::make(PreviewStore::COMPONENT),
            DeleteAction::make(),
        ];
    }

    /**
     * "Drawn by" renames the published file the way the command does.
     */
    protected function afterSave(): void
    {
        if (! PublishedFile::afterSave($this->getRecord(), app(ComponentPublisher::class))) {
            $this->refreshFormData(['view']);
        }
    }
}
