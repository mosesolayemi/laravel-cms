<?php

namespace PhynixMedia\Cms\Filament\Resources\Blocks\Pages;

use PhynixMedia\Cms\Filament\Resources\Blocks\BlockResource;
use PhynixMedia\Cms\Filament\Resources\Pages\PageResource;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Icons\Heroicon;

class EditBlock extends EditRecord
{
    protected static string $resource = BlockResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // Blocks are always reached from a page, so offer the way back.
            Action::make('page')
                ->label('Back to page')
                ->icon(Heroicon::OutlinedArrowUturnLeft)
                ->color('gray')
                ->url(fn (): string => PageResource::getUrl('edit', ['record' => $this->getRecord()->template_id])),
            DeleteAction::make(),
        ];
    }
}
