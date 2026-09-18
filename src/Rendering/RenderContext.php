<?php

namespace Cms\Rendering;

use Cms\Components\ComponentType;
use Cms\Models\Pages\Layouts;
use Cms\Models\Pages\Templates;

/**
 * What a component is being rendered for: which page, inside which layout,
 * in which region, and whether it is a preview.
 *
 * Available in every component view as `$cms`, so a component can adapt —
 * a banner defaulting to the page's title, a header that floats on the home
 * page only.
 */
final class RenderContext
{
    /**
     * @param  array<int, int>  $trail  library components currently being
     *                                  rendered, outermost first, so a
     *                                  component that places itself cannot
     *                                  recurse forever
     */
    public function __construct(
        public readonly ?Templates $page = null,
        public readonly ?Layouts $layout = null,
        public readonly string $area = ComponentType::AREA_CONTENT,
        public readonly bool $preview = false,
        public readonly array $trail = [],
    ) {}

    public function inArea(string $area): self
    {
        return new self($this->page, $this->layout, $area, $this->preview, $this->trail);
    }

    public function entering(int $componentId): self
    {
        return new self($this->page, $this->layout, $this->area, $this->preview, [...$this->trail, $componentId]);
    }

    public function isRendering(int $componentId): bool
    {
        return in_array($componentId, $this->trail, true);
    }

    /**
     * The page's title, for components that default to it.
     */
    public function pageTitle(): ?string
    {
        return $this->page?->title;
    }
}
