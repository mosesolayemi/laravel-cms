<?php

namespace Cms\Filament\Resources\BlogCategories\Pages;

use Cms\Filament\Resources\BlogCategories\BlogCategoryResource;
use Filament\Resources\Pages\ManageRecords;

class ManageBlogCategories extends ManageRecords
{
    protected static string $resource = BlogCategoryResource::class;
}
