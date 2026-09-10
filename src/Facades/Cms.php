<?php

namespace Cms\Facades;

use Cms\Models\Blogs\Blogs;
use Cms\Models\Pages\Templates;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Facade;

/**
 * @method static Templates|null page(?string $url)
 * @method static array menu()
 * @method static array footerMenu()
 * @method static Collection blogs()
 * @method static Blogs|null blog(int|string $id)
 * @method static Collection blogsInCategory(int|string $categoryId)
 * @method static Collection categories()
 * @method static void recordVisit(?string $url)
 *
 * @see \Cms\Cms
 */
class Cms extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \Cms\Cms::class;
    }
}
