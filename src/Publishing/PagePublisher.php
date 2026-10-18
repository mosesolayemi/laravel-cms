<?php

namespace PhynixMedia\Cms\Publishing;

use PhynixMedia\Cms\Models\Pages\Templates;
use Illuminate\Database\Eloquent\Model;

/**
 * Publishes a builder page's content to a Blade file. The file holds the
 * page's content only; the CMS layout still wraps it.
 *
 * @extends Publisher<Templates>
 */
class PagePublisher extends Publisher
{
    protected function prefixConfig(): string
    {
        return 'cms.publishing.views';
    }

    protected function slug(Model $model): string
    {
        return $model->url;
    }

    protected function blocks(Model $model): array
    {
        return array_values($model->content ?? []);
    }

    protected function storeBlocks(Model $model, array $blocks): void
    {
        $model->forceFill(['content' => $blocks])->save();
    }

    protected function header(Model $model, bool $compact): string
    {
        $command = 'php artisan cms:publish-page ' . $model->url;
        $compactFlag = $compact ? ' --compact' : '';

        return <<<BLADE
{{--
    {$model->title} — {$model->link_path}

    Published from the CMS with `{$command}{$compactFlag}`
    on {$this->today()}. This file now draws the page's content; the header,
    footer and anything else in the page's layout still come from the CMS.

    The page's components are kept in the panel but not shown while this file
    is in use. To go back to them, set "Drawn by" to "Components" on the
    page's screen, or run `{$command} --revert`. Reverting renames this file
    to *.reverted.blade.php, edits intact; `--use-file` brings it back.

    `{$command} --sync` copies the data written below back into the page's
    components (markup changes stay here).

    Each section starts with its data, as the CMS stored it; change the data,
    the markup, or both. Variables a section defines do not leak into the
    next one.
--}}

BLADE;
    }
}
