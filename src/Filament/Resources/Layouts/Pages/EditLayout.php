<?php

namespace PhynixMedia\Cms\Filament\Resources\Layouts\Pages;

use PhynixMedia\Cms\Filament\Actions\PreviewAction;
use PhynixMedia\Cms\Filament\Resources\Layouts\LayoutResource;
use PhynixMedia\Cms\Preview\PreviewStore;
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
