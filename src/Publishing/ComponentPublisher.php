<?php

namespace Cms\Publishing;

use Cms\Models\Components\Component;
use Illuminate\Database\Eloquent\Model;
use RuntimeException;

/**
 * Publishes a library component to a Blade file: its data and a copy of its
 * type's markup. While the file is in use, every page that links to the
 * component shows the file.
 *
 * Pages that took a copy of the component ("Copy from the library") hold
 * their own data and are not affected.
 *
 * @extends Publisher<Component>
 */
class ComponentPublisher extends Publisher
{
    protected function prefixConfig(): string
    {
        return 'cms.publishing.components';
    }

    protected function slug(Model $model): string
    {
        return $model->handle;
    }

    protected function blocks(Model $model): array
    {
        return [['type' => $model->type, 'data' => $model->data ?? []]];
    }

    protected function storeBlocks(Model $model, array $blocks): void
    {
        if (count($blocks) !== 1 || $blocks[0]['type'] !== $model->type) {
            throw new RuntimeException("The file for “{$model->handle}” should hold exactly one {$model->type} section to sync.");
        }

        $model->forceFill(['data' => $blocks[0]['data']])->save();
    }

    protected function header(Model $model, bool $compact): string
    {
        $command = 'php artisan cms:publish-component ' . $model->handle;
        $compactFlag = $compact ? ' --compact' : '';

        return <<<BLADE
{{--
    Library component: {$model->name} ({$model->handle})

    Published from the CMS with `{$command}{$compactFlag}`
    on {$this->today()}. Every page linked to this component now shows this
    file. Pages holding their own copy of it are not affected.

    Its data in the panel is kept but not shown while this file is in use.
    `{$command} --revert` goes back to it (this file is renamed to
    *.reverted.blade.php, edits intact; `--use-file` brings it back), and
    `{$command} --sync` copies the data written below back into the panel.
--}}

BLADE;
    }
}
