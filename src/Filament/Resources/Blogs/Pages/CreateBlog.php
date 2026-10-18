<?php

namespace PhynixMedia\Cms\Filament\Resources\Blogs\Pages;

use PhynixMedia\Cms\Filament\Resources\Blogs\BlogResource;
use PhynixMedia\Cms\Filament\Resources\Blogs\Schemas\BlogForm;
use Filament\Resources\Pages\CreateRecord;

class CreateBlog extends CreateRecord
{
    protected static string $resource = BlogResource::class;

    /**
     * The slug is written from the title as it is typed, but that relies on
     * the title field losing focus. Saving straight from the title — tab, then
     * enter — would otherwise submit an empty slug into a NOT NULL column.
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        if (blank($data['url'] ?? null)) {
            $data['url'] = BlogForm::uniqueSlug((string) ($data['title'] ?? ''));
        }

        return $data;
    }
}
