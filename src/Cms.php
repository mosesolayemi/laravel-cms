<?php

namespace PhynixMedia\Cms;

use PhynixMedia\Cms\Models\Blogs\Blogs;
use PhynixMedia\Cms\Models\Blogs\Category;
use PhynixMedia\Cms\Models\Components\Component;
use PhynixMedia\Cms\Models\Pages\PageVisit;
use PhynixMedia\Cms\Models\Pages\Templates;
use PhynixMedia\Cms\Models\Settings\Setting;
use PhynixMedia\Cms\Rendering\ComponentRenderer;
use PhynixMedia\Cms\Rendering\PageRenderer;
use PhynixMedia\Cms\Rendering\RenderContext;
use PhynixMedia\Cms\Settings\SettingsSchema;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Contracts\View\View;
use Illuminate\Support\HtmlString;
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
     * A page rendered in its layout, ready to return from a controller —
     * from its components, or from its published Blade view if it has one.
     */
    public function render(Templates $page): View
    {
        return app(PageRenderer::class)->view($page);
    }

    /**
     * One library component, by handle or id. Renders nothing if it is
     * missing or unpublished.
     */
    public function component(Component|int|string $reference, ?Templates $page = null): HtmlString
    {
        return new HtmlString(app(ComponentRenderer::class)->renderLibrary($reference, new RenderContext(page: $page)));
    }

    /**
     * The header menu: top level pages flagged for the header, each with its
     * children nested underneath.
     *
     * @return array<int, Templates>
     */
    public function menu(): array
    {
        return $this->tree(fn ($query) => $query->where('header_position', 1));
    }

    /**
     * The footer's link columns, keyed by column.
     *
     * `footer_position` names which column a page belongs in rather than being
     * a plain yes/no, because the footer has two lists side by side.
     *
     * @return array<string, array<int, Templates>>
     */
    public function footerMenu(): array
    {
        $pages = Templates::query()
            ->where('status', self::PUBLISHED)
            ->whereNotNull('footer_position')
            ->whereNotIn('footer_position', ['', '0', self::FOOTER_NONE])
            ->orderBy('footer_order')
            ->orderBy('page_order')
            ->get();

        return $pages->groupBy('footer_position')
            ->map(fn ($group) => $group->values()->all())
            ->all();
    }

    public const FOOTER_NONE = 'none';

    /**
     * Where the panel's edited column list is kept. Absent until someone
     * saves the footer screen, so config holds what a fresh site starts with.
     */
    public const FOOTER_COLUMNS_SETTING = 'footer.columns';

    /**
     * The footer's link columns, in the order the footer draws them.
     *
     * Columns are data rather than a fixed pair: the panel can rename them,
     * reorder them, add a third or remove one. Rows are normalised here, so
     * neither the theme nor the panel has to defend against a half-filled
     * column left behind by an edit.
     *
     * @return array<int, array{key: string, title: string, highlight: string}>
     */
    public static function footerColumnList(): array
    {
        $stored = Setting::get(self::FOOTER_COLUMNS_SETTING);

        $columns = is_array($stored) && $stored !== []
            ? $stored
            : config('cms.footer.columns', []);

        return collect($columns)
            ->map(fn ($column): array => [
                'key' => (string) ($column['key'] ?? ''),
                'title' => (string) ($column['title'] ?? ''),
                'highlight' => (string) ($column['highlight'] ?? ''),
            ])
            // A column with no key is one nothing can be filed under, so it
            // would render as a permanently empty list.
            ->filter(fn (array $column): bool => filled($column['key']) && $column['key'] !== self::FOOTER_NONE)
            ->unique('key')
            ->values()
            ->all();
    }

    /**
     * The columns a page can be placed in, for the panel's select.
     *
     * @return array<string, string>
     */
    public static function footerColumns(): array
    {
        return collect(static::footerColumnList())
            ->mapWithKeys(fn (array $column): array => [
                $column['key'] => trim($column['title'] . ' ' . $column['highlight']) ?: $column['key'],
            ])
            ->prepend('Not shown', self::FOOTER_NONE)
            ->all();
    }

    /**
     * Build a menu tree.
     *
     * Nesting comes from `parent`, which is explicit and editable: the header
     * groups pages that are not related by URL at all — "About Us" holds
     * /clinical-standards and /team, neither of which sits under /about.
     *
     * @return array<int, Templates>
     */
    protected function tree(callable $filter, int $parent = 0, int $depth = 0): array
    {
        // Three levels is what the theme renders; the guard also stops a
        // parent loop in the data from recursing forever.
        if ($depth > 2) {
            return [];
        }

        $pages = Templates::query()
            ->where('status', self::PUBLISHED)
            ->where('parent', $parent)
            ->tap($filter)
            ->orderBy('page_order')
            ->get();

        foreach ($pages as $page) {
            $page->setRelation('children', collect(
                $this->tree($filter, $page->getKey(), $depth + 1)
            ));
        }

        return $pages->all();
    }

    /**
     * An editable piece of site chrome — footer wording, contact details.
     *
     * With no default given, the one declared for the key in
     * `cms.settings.sections` stands in. That keeps the fallback wording in
     * one place instead of being repeated, and mis-repeated, in every template
     * that reads the value.
     */
    public function setting(string $key, mixed $default = null): mixed
    {
        return Setting::get($key, $default ?? (SettingsSchema::defaults()[$key] ?? null));
    }

    /**
     * @param  array<string, mixed>  $values
     */
    public function putSettings(array $values): void
    {
        Setting::put($values);
    }

    public function blogs(): Collection
    {
        return Blogs::query()
            ->where('status', self::PUBLISHED)
            ->latest()
            ->get();
    }

    /**
     * A page of published posts, newest first, optionally within a category.
     */
    public function blogsPage(int|string|null $categoryId = null, int $perPage = 9): LengthAwarePaginator
    {
        return Blogs::query()
            ->with('category')
            ->where('status', self::PUBLISHED)
            ->when($categoryId, fn ($query) => $query->where('category_id', $categoryId))
            ->latest()
            ->paginate($perPage)
            ->withQueryString();
    }

    /**
     * The few most recent posts, for the footer.
     */
    public function recentBlogs(int $limit = 2): Collection
    {
        return Blogs::query()
            ->where('status', self::PUBLISHED)
            ->latest()
            ->limit($limit)
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
     * Record a hit against today's counter for a page.
     *
     * One row per page per day: monthly figures are a sum over the month's
     * rows, so the same data answers daily, weekly and monthly questions.
     */
    public function recordVisit(?string $url): void
    {
        if (blank($url)) {
            return;
        }

        PageVisit::query()
            ->firstOrCreate(
                [
                    'page_url' => $url,
                    'year' => (int) date('Y'),
                    'month' => (int) date('m'),
                    'day' => (int) date('d'),
                ],
                ['visits' => 0],
            )
            ->increment('visits');
    }
}
