<?php

namespace PhynixMedia\Cms\Rendering;

use PhynixMedia\Cms\Components\ComponentRegistry;
use PhynixMedia\Cms\Models\Components\Component;
use PhynixMedia\Cms\Settings\Tokens;
use Illuminate\Support\HtmlString;
use Throwable;

/**
 * Turns builder content — a list of {type, data} items — into HTML.
 *
 * A broken component should never take a whole page down: on the live site a
 * component that throws is reported and skipped. In a preview, and when
 * debugging, the problem is shown in place instead, so an editor can see which
 * section needs attention.
 */
class ComponentRenderer
{
    /**
     * Components that failed this request, by fingerprint, with the notice
     * shown in their place. A second attempt returns the notice without
     * rendering again — see PageRenderer for why that matters.
     *
     * @var array<string, string>
     */
    protected array $failures = [];

    public function __construct(protected ComponentRegistry $registry) {}

    public function failureCount(): int
    {
        return count($this->failures);
    }

    /**
     * @param  array<int|string, mixed>|null  $blocks
     */
    public function render(?array $blocks, RenderContext $context): HtmlString
    {
        $html = '';

        // Builder state is keyed by item uuid while editing; order is what
        // matters, not the keys.
        foreach (array_values($blocks ?? []) as $block) {
            $html .= $this->renderBlock($block, $context);
        }

        return new HtmlString($html);
    }

    public function renderBlock(mixed $block, RenderContext $context): string
    {
        if (! is_array($block)) {
            return '';
        }

        $type = $block['type'] ?? null;
        $data = is_array($block['data'] ?? null) ? $block['data'] : [];

        if (! empty($data[ComponentRegistry::HIDDEN])) {
            return $this->hiddenNotice($type, $data, $context);
        }

        if ($type === ComponentRegistry::LIBRARY) {
            return $this->renderLibrary($data['component_id'] ?? null, $context);
        }

        if ($type === ComponentRegistry::LIBRARY_COPY) {
            return $this->notice($context, 'A “Copy from the library” item has no component chosen yet.');
        }

        return $this->renderType($type, $data, $context);
    }

    /**
     * Render a library component, by model, id or handle.
     */
    public function renderLibrary(Component|int|string|null $component, RenderContext $context): string
    {
        if (! $component instanceof Component) {
            $component = $this->findComponent($component);
        }

        if (! $component) {
            return $this->notice($context, 'A library component placed here no longer exists.');
        }

        if ((int) $component->status !== 1 && ! $context->preview) {
            return '';
        }

        if ($context->isRendering($component->getKey())) {
            return $this->notice($context, "“{$component->name}” places itself, so it was not rendered again.");
        }

        if ($view = $component->publishedView()) {
            return $this->renderPublished($view, $component, $context->entering($component->getKey()));
        }

        return $this->renderType($component->type, $component->data ?? [], $context->entering($component->getKey()));
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function renderType(?string $key, array $data, RenderContext $context): string
    {
        // Published files call this directly (@cmsBlock), so it checks too.
        if (! empty($data[ComponentRegistry::HIDDEN])) {
            return $this->hiddenNotice($key, $data, $context);
        }

        unset($data[ComponentRegistry::HIDDEN]);

        // A library reference written out by a publish, then shown again.
        if ($key === ComponentRegistry::LIBRARY) {
            return $this->renderLibrary($data['component_id'] ?? null, $context);
        }

        // A shared value written into the content as {phone} or {email} is
        // resolved here rather than in each component, so every section gets
        // it without knowing the feature exists.
        $data = Tokens::apply($data);

        $type = $this->registry->get($key);

        if (! $type) {
            return $this->notice($context, "There is no “{$key}” component type registered, so this section was skipped.");
        }

        $fingerprint = md5($key . '|' . serialize($data) . '|' . (int) $context->preview);

        if (isset($this->failures[$fingerprint])) {
            return $this->failures[$fingerprint];
        }

        try {
            return $type->render($data, $context);
        } catch (Throwable $e) {
            if (config('app.debug') && ! $context->preview) {
                throw $e;
            }

            report($e);

            return $this->failures[$fingerprint] = $this->notice(
                $context,
                "“{$type->label()}” could not be rendered — check its fields are filled in. ({$e->getMessage()})",
            );
        }
    }

    /**
     * A library component published with cms:publish-component.
     */
    protected function renderPublished(string $view, Component $component, RenderContext $context): string
    {
        $fingerprint = md5('view|' . $view . '|' . (int) $context->preview);

        if (isset($this->failures[$fingerprint])) {
            return $this->failures[$fingerprint];
        }

        try {
            return view($view, ['cms' => $context, 'component' => $component])->render();
        } catch (Throwable $e) {
            if (config('app.debug') && ! $context->preview) {
                throw $e;
            }

            report($e);

            return $this->failures[$fingerprint] = $this->notice($context, "The published file for “{$component->name}” could not be rendered. ({$e->getMessage()})");
        }
    }

    /**
     * Nothing for visitors; in a preview, a slim marker where the hidden
     * component sits, so the editor can see it is there.
     *
     * @param  array<string, mixed>  $data
     */
    protected function hiddenNotice(?string $key, array $data, RenderContext $context): string
    {
        if (! $context->preview) {
            return '';
        }

        $label = $key === ComponentRegistry::LIBRARY
            ? (Component::query()->find((int) ($data['component_id'] ?? 0))?->name ?? 'Library component')
            : ($this->registry->get($key)?->label() ?? $key);

        return $this->notice($context, "Hidden from the site: “{$label}”. Show it again from its menu on the page's screen.");
    }

    protected function findComponent(int|string|null $reference): ?Component
    {
        if (blank($reference)) {
            return null;
        }

        return is_numeric($reference)
            ? Component::query()->find((int) $reference)
            : Component::query()->where('handle', $reference)->first();
    }

    /**
     * An explanation for editors, shown only in previews. Visitors see
     * nothing rather than a half-rendered section.
     */
    protected function notice(RenderContext $context, string $message): string
    {
        if (! $context->preview) {
            return '';
        }

        return view('cms::preview.notice', ['message' => $message])->render();
    }
}
