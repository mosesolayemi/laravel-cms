<?php

namespace PhynixMedia\Cms\Console;

use PhynixMedia\Cms\Models\Components\Component;
use PhynixMedia\Cms\Publishing\ComponentPublisher;
use PhynixMedia\Cms\Publishing\Publisher;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * Library components to Blade files and back. See PublishCommand for the
 * options.
 */
class PublishComponentCommand extends PublishCommand
{
    protected $description = 'Publish library components to Blade files, revert, use their files again, or sync file data back';

    public function __construct()
    {
        $this->signature = 'cms:publish-component
        {reference?* : The component handle(s), as shown in the Component library}'
            . static::sharedOptions();

        parent::__construct();
    }

    protected function publisher(): Publisher
    {
        return app(ComponentPublisher::class);
    }

    protected function find(string $reference): ?Model
    {
        return Component::query()->where('handle', $reference)->first()
            ?? (is_numeric($reference) ? Component::query()->find((int) $reference) : null);
    }

    protected function everything(): Collection
    {
        return Component::query()->orderBy('handle')->get();
    }

    protected function describe(Model $model): string
    {
        return "Component {$model->handle}";
    }
}
