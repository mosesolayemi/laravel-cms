<?php

namespace Cms\Content;

/**
 * What every installation starts with: a blank layout, for pages that should
 * render their content and nothing else.
 */
class CoreContent implements ContentDefinition
{
    public function components(): array
    {
        return [];
    }

    public function layouts(): array
    {
        return [
            'blank' => [
                'label' => 'Blank (content only)',
            ],
        ];
    }

    public function pages(): array
    {
        return [];
    }
}
