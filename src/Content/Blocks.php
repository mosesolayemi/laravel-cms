<?php

namespace Cms\Content;

use Cms\Components\ComponentRegistry;

/**
 * Builds builder items for content definitions.
 *
 *     Blocks::make('heading', ['heading' => 'Welcome'])
 *     Blocks::library('call-to-action')
 */
final class Blocks
{
    /**
     * An inline component: this page's own copy.
     *
     * @param  array<string, mixed>  $data
     * @return array{type: string, data: array<string, mixed>}
     */
    public static function make(string $type, array $data = []): array
    {
        return ['type' => $type, 'data' => $data];
    }

    /**
     * A reference to a library component by handle, resolved to its id when
     * installed. Editing the library component changes every placement.
     *
     * @return array{type: string, data: array<string, string>}
     */
    public static function library(string $handle): array
    {
        return ['type' => ComponentRegistry::LIBRARY, 'data' => ['component_handle' => $handle]];
    }
}
