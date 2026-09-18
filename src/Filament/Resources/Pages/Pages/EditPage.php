<?php

namespace Cms\Filament\Resources\Pages\Pages;

use Cms\Filament\Actions\PreviewAction;
use Cms\Filament\Resources\Pages\PageResource;
use Cms\Filament\Support\PublishedFile;
use Cms\Models\Pages\Templates;
use Cms\Preview\PreviewStore;
use Cms\Publishing\PagePublisher;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Icons\Heroicon;

class EditPage extends EditRecord
{
    protected static string $resource = PageResource::class;

    protected function getHeaderActions(): array
    {
        return [
            PreviewAction::make(PreviewStore::PAGE),
            Action::make('visit')
                ->label('Visit URL')
                ->icon(Heroicon::OutlinedArrowTopRightOnSquare)
                ->color('gray')
                ->url(fn (Templates $record): string => $record->link())
                ->openUrlInNewTab()
                ->visible(fn (Templates $record): bool => (int) $record->status === 1),
            DeleteAction::make(),
        ];
    }

    /**
     * "Drawn by" renames the published file the way the command does.
     */
    protected function afterSave(): void
    {
        if (! PublishedFile::afterSave($this->getRecord(), app(PagePublisher::class))) {
            $this->refreshFormData(['view']);
        }
    }
}
