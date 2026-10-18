<?php

namespace PhynixMedia\Cms\Rendering;

use PhynixMedia\Cms\Models\Pages\Layouts;
use PhynixMedia\Cms\Models\Pages\Templates;
use Illuminate\Contracts\View\View;
use Illuminate\Support\HtmlString;

/**
 * Renders a builder page inside its layout.
 *
 * The layout's regions and the page's components are rendered before the page
 * view starts, not from inside its sections. When any Blade view throws,
 * Laravel wipes all of Blade's in-progress state — the page's open sections
 * included — so a single broken component rendered mid-page would take the
 * whole page down. Rendered up front, a failure can only cost what was rendered
 * so far, and that is simply rendered again (see renderRegions()).
 */
class PageRenderer
{
    public function __construct(protected ComponentRenderer $components) {}

    /**
     * @param  Layouts|false|null  $layout  a layout to use instead of the
     *                                      page's own, or false for none
     */
    public function view(Templates $page, bool $preview = false, Layouts|false|null $layout = null): View
    {
        $layout = $layout === false ? null : ($layout ?? $this->layoutFor($page));
        $context = new RenderContext(page: $page, layout: $layout, preview: $preview);

        return view('cms::page', [
            'shell' => $this->shell($layout),
            'page' => $page,
            'layout' => $layout,
            'context' => $context,
            'regions' => $this->renderRegions($page, $layout, $context),
            'title' => $page->meta_title ?: $page->title,
            'description' => $page->meta_description,
        ]);
    }

    /**
     * @return array<string, HtmlString>
     */
    protected function renderRegions(Templates $page, ?Layouts $layout, RenderContext $context): array
    {
        $failures = $this->components->failureCount();
        $regions = $this->renderRegionsOnce($page, $layout, $context);

        if ($this->components->failureCount() > $failures) {
            // Blade dropped everything rendered so far when the component
            // failed, scripts pushed by earlier components included. Start
            // clean; the failed component now renders its notice directly.
            app('view')->flushState();
            $regions = $this->renderRegionsOnce($page, $layout, $context);
        }

        return $regions;
    }

    /**
     * @return array<string, HtmlString>
     */
    protected function renderRegionsOnce(Templates $page, ?Layouts $layout, RenderContext $context): array
    {
        $factory = app('view');

        // Counted as mid-render, so Blade keeps what components push onto
        // stacks until the page's shell prints them.
        $factory->incrementRender();

        try {
            return [
                'header' => $this->components->render($layout?->header, $context->inArea('header')),
                'before' => $this->components->render($layout?->before_content, $context),
                'content' => $page->publishedView() ? new HtmlString('') : $this->components->render($page->content, $context),
                'after' => $this->components->render($layout?->after_content, $context),
                'footer' => $this->components->render($layout?->footer, $context->inArea('footer')),
            ];
        } finally {
            if (! $factory->doneRendering()) {
                $factory->decrementRender();
            }
        }
    }

    /**
     * The page's layout. A page with no layout renders its content alone in
     * the shell — a landing page, say, with no site header or footer.
     */
    public function layoutFor(Templates $page): ?Layouts
    {
        if (blank($page->layout_id)) {
            return null;
        }

        if ($page->relationLoaded('layouts') && $page->layouts?->getKey() == $page->layout_id) {
            return $page->layouts;
        }

        return Layouts::query()->find($page->layout_id);
    }

    /**
     * The Blade view holding the document: <html>, <head>, assets and the
     * yields a page fills in. See the README for the sections it must yield.
     */
    public function shell(?Layouts $layout): string
    {
        foreach ([$layout?->shell, config('cms.rendering.shell')] as $shell) {
            if (filled($shell) && view()->exists($shell)) {
                return $shell;
            }
        }

        return 'cms::shell';
    }
}
