<?php

namespace PhynixMedia\Cms\Filament\Resources\Blogs\Pages;

use PhynixMedia\Cms\Filament\Resources\Blogs\BlogResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditBlog extends EditRecord
{
    protected static string $resource = BlogResource::class;

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()];
    }
}
