<?php

namespace PhynixMedia\Cms\Console;

use PhynixMedia\Cms\Models\Pages\Templates;
use PhynixMedia\Cms\Publishing\PagePublisher;
use PhynixMedia\Cms\Publishing\Publisher;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * Pages to Blade files and back. See PublishCommand for the options.
 */
class PublishPageCommand extends PublishCommand
{
    protected $description = 'Publish CMS pages to Blade files, revert, use their files again, or sync file data back';

    public function __construct()
    {
        $this->signature = 'cms:publish-page
        {reference?* : The page URL(s), e.g. services/festival-cover (home for the home page)}'
            . static::sharedOptions();

        parent::__construct();
    }

    protected function publisher(): Publisher
    {
        return app(PagePublisher::class);
    }

    protected function find(string $reference): ?Model
    {
        return Templates::query()->where('url', trim($reference, '/') ?: 'home')->first();
    }

    protected function everything(): Collection
    {
        return Templates::query()->orderBy('url')->get()
            // A fresh publish needs components to write out.
            ->filter(fn (Templates $page): bool => $this->option('revert') || $this->option('use-file') || $this->option('sync') || $page->hasBuilderContent());
    }

    protected function describe(Model $model): string
    {
        return '/' . $model->url;
    }
}
