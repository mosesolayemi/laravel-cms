<?php

namespace PhynixMedia\Cms\Filament\Resources\Components\Pages;

use PhynixMedia\Cms\Filament\Actions\PreviewAction;
use PhynixMedia\Cms\Filament\Resources\Components\ComponentResource;
use PhynixMedia\Cms\Filament\Support\PublishedFile;
use PhynixMedia\Cms\Publishing\ComponentPublisher;
use PhynixMedia\Cms\Preview\PreviewStore;
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
