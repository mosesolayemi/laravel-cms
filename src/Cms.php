<?php

namespace Cms;

use Cms\Models\Blogs\Blogs;
use Cms\Models\Blogs\Category;
use Cms\Models\Pages\PageVisit;
use Cms\Models\Pages\Templates;
use Illuminate\Database\Eloquent\Collection;

/**
 * The CMS package's read API.
 *
 * Everything the public site needs from the CMS goes through here, so the site
 * never reaches into the package's models, and the package never reaches into
 * the site. Resolve it from the container or via the Cms facade.
 */
class Cms
{
    public const PUBLISHED = 1;

    /**
     * Load a page by its slug, with the content needed to render it.
     */
    public function page(?string $url): ?Templates
    {
        if (blank($url)) {
            return null;
        }

        return Templates::query()
            ->with(['groups', 'layouts'])
            ->where('url', $url)
            ->first();
    }

    /**
     * The header menu, as a nested tree three levels deep.
     */
    public function menu(): array
    {
        return $this->menuTree(['header_position' => 1]);
    }

    public function footerMenu(): array
    {
        return $this->menuTree(['footer_position' => 1]);
    }

    /**
     * Build the menu tree for a set of column filters.
     *
     * Three queries deep rather than recursive, matching the depth the theme
     * renders; children are attached as `children` on each node.
     */
    protected function menuTree(array $filter): array
    {
        $query = fn (int $parent): Collection => Templates::query()
            ->where($filter)
            ->where('status', self::PUBLISHED)
            ->where('parent', $parent)
            ->orderBy('page_order')
            ->get();

        $roots = $query(0);

        foreach ($roots as $root) {
            $children = $query($root->id);

            foreach ($children as $child) {
                $child->children = $query($child->id);
            }

            $root->children = $children;
        }

        return $roots->all();
    }

    public function blogs(): Collection
    {
        return Blogs::query()
            ->where('status', self::PUBLISHED)
            ->latest()
            ->get();
    }

    public function blog(int|string $id): ?Blogs
    {
        return Blogs::query()->find($id);
    }

    public function blogsInCategory(int|string $categoryId): Collection
    {
        return Blogs::query()
            ->where('category_id', $categoryId)
            ->where('status', self::PUBLISHED)
            ->latest()
            ->get();
    }

    public function categories(): Collection
    {
        return Category::query()->orderBy('name')->get();
    }

    /**
     * Record a hit against the current month's counter for a page.
     */
    public function recordVisit(?string $url): void
    {
        if (blank($url)) {
            return;
        }

        PageVisit::query()
            ->firstOrCreate(
                ['page_url' => $url, 'year' => (int) date('Y'), 'month' => (int) date('m')],
                ['visits' => 0],
            )
            ->increment('visits');
    }
}
