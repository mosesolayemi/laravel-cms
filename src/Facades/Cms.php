<?php

namespace Cms\Facades;

use Cms\Models\Blogs\Blogs;
use Cms\Models\Pages\Templates;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Facade;

/**
 * @method static Templates|null page(?string $url)
 * @method static \Illuminate\Contracts\View\View render(Templates $page)
 * @method static \Illuminate\Support\HtmlString component(\Cms\Models\Components\Component|int|string $reference, ?Templates $page = null)
 * @method static array menu()
 * @method static array footerMenu()
 * @method static array footerColumns()
 * @method static mixed setting(string $key, mixed $default = null)
 * @method static void putSettings(array $values)
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
