<?php

namespace PhynixMedia\Cms\Http\Controllers;

use PhynixMedia\Cms\Components\ComponentRegistry;
use PhynixMedia\Cms\Components\ComponentType;
use PhynixMedia\Cms\Models\Components\Component;
use PhynixMedia\Cms\Models\Pages\Layouts;
use PhynixMedia\Cms\Models\Pages\Templates;
use PhynixMedia\Cms\Preview\PreviewStore;
use PhynixMedia\Cms\Rendering\PageRenderer;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Illuminate\Support\Arr;

/**
 * Renders pages, layouts and components for administrators only — including
 * unpublished ones, and unsaved edits handed over through PreviewStore.
 */
class PreviewController extends Controller
{
    public function __construct(protected PageRenderer $pages) {}

    /**
     * Unsaved form state from the panel's Preview button.
     */
    public function show(string $token): Response
    {
        $this->authorize();

        $payload = PreviewStore::get($token);

        abort_unless($payload, 404, 'This preview has expired. Press Preview again in the panel.');

        ['kind' => $kind, 'record' => $record, 'state' => $state] = $payload;

        return $this->respond(match ($kind) {
            PreviewStore::PAGE => $this->pageView($this->fill(Templates::class, $record, $state)),
            PreviewStore::LAYOUT => $this->layoutView($this->fill(Layouts::class, $record, $state)),
            PreviewStore::COMPONENT => $this->componentView($this->fill(Component::class, $record, $state)),
            default => abort(404),
        });
    }

    public function page(Templates $page): Response
    {
        $this->authorize();

        return $this->respond($this->pageView($page));
    }

    public function layout(Layouts $layout): Response
    {
        $this->authorize();

        return $this->respond($this->layoutView($layout));
    }

    public function component(Component $component): Response
    {
        $this->authorize();

        return $this->respond($this->componentView($component));
    }

    protected function pageView(Templates $page): View
    {
        return $this->pages->view($page, preview: true);
    }

    /**
     * A layout around placeholder content.
     */
    protected function layoutView(Layouts $layout): View
    {
        $page = new Templates(['title' => $layout->label ?: 'Layout preview', 'url' => 'preview']);

        return $this->pages->view($page, preview: true, layout: $layout);
    }

    /**
     * A component on its own, inside the default layout — unless it belongs
     * in a header or footer, where the default layout would show it twice.
     */
    protected function componentView(Component $component): View
    {
        $type = app(ComponentRegistry::class)->get($component->type);

        $page = new Templates([
            'title' => $component->name ?: 'Component preview',
            'url' => 'preview',
            'content' => [['type' => $component->type, 'data' => $component->data ?? []]],
        ]);

        $layout = $type && ! $type->allowedIn(ComponentType::AREA_CONTENT) ? false : Layouts::defaultLayout();

        return $this->pages->view($page, preview: true, layout: $layout ?? false);
    }

    /**
     * The saved record, if any, with the form's unsaved values laid over it.
     *
     * @template T of \Illuminate\Database\Eloquent\Model
     *
     * @param  class-string<T>  $model
     * @param  array<string, mixed>  $state
     * @return T
     */
    protected function fill(string $model, int|string|null $record, array $state)
    {
        $instance = ($record ? $model::query()->find($record) : null) ?? new $model;

        return $instance->forceFill(Arr::only($state, $instance->getFillable()));
    }

    protected function respond(View $view): Response
    {
        return response($view->render())
            // Previews show drafts; keep them out of search results and caches.
            ->header('X-Robots-Tag', 'noindex, nofollow')
            ->header('Cache-Control', 'no-store, private');
    }

    /**
     * Previews sit outside the panel, so check the panel's guard directly.
     */
    protected function authorize(): void
    {
        abort_unless(auth(config('cms.auth.guard'))->check(), 403, 'Sign in to the admin panel to see previews.');
    }
}
