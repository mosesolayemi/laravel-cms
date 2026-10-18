<?php

namespace PhynixMedia\Cms\Content;

/**
 * A set of components, layouts and page content to load into the CMS.
 *
 * `php artisan cms:seed-components` installs every definition listed in
 * `cms.content`. A site ships one describing its own pages, so a fresh
 * checkout — or another developer's machine — gets the whole site in the
 * database with one command. Everything is keyed by a stable handle (the page
 * URL for pages), so running it again updates rather than duplicates.
 *
 * Builder content is a list of Blocks::make() / Blocks::library() items.
 */
interface ContentDefinition
{
    /**
     * Library components, keyed by handle.
     *
     * @return array<string, array{name: string, type: string, group?: string|null, data?: array<string, mixed>, description?: string|null, status?: int}>
     */
    public function components(): array;

    /**
     * Layouts, keyed by handle. Region keys are those of Layouts::REGIONS.
     *
     * @return array<string, array{label: string, shell?: string|null, is_default?: bool, header?: array<int, mixed>, before_content?: array<int, mixed>, after_content?: array<int, mixed>, footer?: array<int, mixed>}>
     */
    public function layouts(): array;

    /**
     * Page content, keyed by page URL. `layout` is a layout handle, or null
     * for none. A page that does not exist yet is created, titled `title`.
     *
     * @return array<string, array{title?: string, layout?: string|null, content: array<int, mixed>}>
     */
    public function pages(): array;
}
