<?php

namespace Cms\Console;

use Cms\Publishing\Publisher;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use RuntimeException;

/**
 * What cms:publish-page and cms:publish-component share: moving CMS content
 * between its Blade file and the panel.
 *
 *     (no flag)    write the file; the file draws the content
 *     --revert     file -> *.reverted.blade.php; the panel's content draws it
 *     --use-file   *.reverted.blade.php -> file; the file again, edits intact
 *     --sync       copy the data written in the file back into the panel
 *
 * No step loses an edit made to a file: only --force (on publish) and
 * --delete (on revert) remove one.
 */
abstract class PublishCommand extends Command
{
    abstract protected function publisher(): Publisher;

    abstract protected function find(string $reference): ?Model;

    /**
     * @return Collection<int, Model>
     */
    abstract protected function everything(): Collection;

    abstract protected function describe(Model $model): string;

    /**
     * Options every publish command takes, appended to its signature.
     */
    protected static function sharedOptions(): string
    {
        return '
        {--all : Everything the action applies to}
        {--compact : One line per section, rendered as the CMS would, instead of copying each section\'s markup}
        {--force : Replace a published or reverted file with a fresh publish}
        {--revert : Go back to the panel\'s content; the file is kept as *.reverted.blade.php}
        {--delete : With --revert, delete the file instead of keeping it}
        {--use-file : Use the file again, with the edits made to it}
        {--sync : Copy the data written in the file back into the panel (markup changes stay in the file)}';
    }

    public function handle(): int
    {
        $actions = array_filter(['revert', 'use-file', 'sync'], fn (string $option): bool => (bool) $this->option($option));

        if (count($actions) > 1) {
            $this->error('Choose one of --revert, --use-file or --sync.');

            return self::FAILURE;
        }

        if ($this->option('delete') && ! $this->option('revert')) {
            $this->error('--delete only goes with --revert.');

            return self::FAILURE;
        }

        $models = $this->targets();

        if ($models === null) {
            return self::FAILURE;
        }

        if ($models->isEmpty()) {
            $this->components->info('Nothing to do.');

            return self::SUCCESS;
        }

        if ($this->option('delete') && $this->input->isInteractive()
            && ! $this->confirm('Delete the published file(s)? Any edits made in them are lost.', false)) {
            return self::FAILURE;
        }

        $failed = false;

        foreach ($models as $model) {
            try {
                $this->components->info($this->apply($model));
            } catch (RuntimeException $e) {
                $this->components->warn($this->describe($model) . ': ' . $e->getMessage());
                $failed = true;
            }
        }

        return $failed ? self::FAILURE : self::SUCCESS;
    }

    protected function apply(Model $model): string
    {
        $publisher = $this->publisher();
        $name = $this->describe($model);

        if ($this->option('revert')) {
            $kept = $publisher->revert($model, (bool) $this->option('delete'));

            return "{$name} comes from the panel again. " . ($kept
                ? "File kept as {$publisher->relative($kept)} — bring it back with --use-file."
                : 'No file kept.');
        }

        if ($this->option('use-file')) {
            return "{$name} is drawn by {$publisher->relative($publisher->useFile($model))} again.";
        }

        if ($this->option('sync')) {
            $count = $publisher->sync($model);

            return "{$name}: {$count} section(s) copied from its file into the panel.";
        }

        $path = $publisher->publish($model, (bool) $this->option('compact'), (bool) $this->option('force'));

        return "{$name} → {$publisher->relative($path)}. Revert with --revert.";
    }

    /**
     * @return Collection<int, Model>|null
     */
    protected function targets(): ?Collection
    {
        $publisher = $this->publisher();

        if ($this->option('all')) {
            return $this->everything()->filter(fn (Model $model): bool => match (true) {
                $this->option('revert') && $this->option('delete') => $publisher->state($model) !== null || filled($model->view),
                (bool) $this->option('revert') => $publisher->state($model) === 'active' || filled($model->view),
                (bool) $this->option('use-file') => in_array($publisher->state($model), ['reverted', 'unused'], true),
                (bool) $this->option('sync') => $publisher->state($model) !== null,
                default => true,
            })->values();
        }

        $references = $this->argument('reference');

        if (! $references) {
            $this->error('Name what to publish, or use --all.');

            return null;
        }

        $models = collect();

        foreach ($references as $reference) {
            $model = $this->find($reference);

            if (! $model) {
                $this->error("Nothing found for “{$reference}”.");

                return null;
            }

            $models->push($model);
        }

        return $models;
    }
}
