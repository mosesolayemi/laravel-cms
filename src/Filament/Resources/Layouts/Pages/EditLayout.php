<?php

namespace Cms\Filament\Resources\Layouts\Pages;

use Cms\Filament\Actions\PreviewAction;
use Cms\Filament\Resources\Layouts\LayoutResource;
use Cms\Preview\PreviewStore;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditLayout extends EditRecord
{
    protected static string $resource = LayoutResource::class;

    protected function getHeaderActions(): array
    {
        return [
            PreviewAction::make(PreviewStore::LAYOUT),
            DeleteAction::make(),
        ];
    }
}
