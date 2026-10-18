<?php

namespace PhynixMedia\Cms\Filament\Resources\Blogs\Pages;

use PhynixMedia\Cms\Filament\Resources\Blogs\BlogResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListBlogs extends ListRecords
{
    protected static string $resource = BlogResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
