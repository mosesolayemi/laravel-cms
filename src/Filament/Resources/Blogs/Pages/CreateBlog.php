<?php

namespace Cms\Filament\Resources\Blogs\Pages;

use Cms\Filament\Resources\Blogs\BlogResource;
use Filament\Resources\Pages\CreateRecord;

class CreateBlog extends CreateRecord
{
    protected static string $resource = BlogResource::class;
}
