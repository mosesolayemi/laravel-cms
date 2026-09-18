<?php

namespace Cms\Console;

use Cms\Content\ContentInstaller;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\Console\Helper\ProgressBar;

/**
 * Loads the component library, layouts and page content into the database.
 *
 * Reads every definition listed in `cms.content`. Run it after migrating on a
 * fresh checkout, or with --force to reset content to what the code defines.
 */
class SeedComponentsCommand extends Command
{
    protected $signature = 'cms:seed-components
        {--force : Overwrite components, layouts and page content that already exist}
        {--only=* : Limit to components, layouts or pages (repeatable)}';

    protected $description = 'Seed the CMS component library, layouts and page content';

    public function handle(ContentInstaller $installer): int
    {
        foreach (['cms_components', 'web_page_layouts', 'web_page_templates'] as $table) {
            if (! Schema::hasTable($table)) {
                $this->error("No {$table} table. Run: php artisan migrate");

                return self::FAILURE;
            }
        }

        $only = $this->option('only');
        $unknown = array_diff($only, [ContentInstaller::COMPONENTS, ContentInstaller::LAYOUTS, ContentInstaller::PAGES]);

        if ($unknown) {
            $this->error('--only accepts components, layouts or pages; got: ' . implode(', ', $unknown));

            return self::FAILURE;
        }

        $definitions = config('cms.content', []);

        if (! $definitions) {
            $this->warn('Nothing to seed: cms.content lists no content definitions.');

            return self::SUCCESS;
        }

        if ($this->option('force') && $this->input->isInteractive()
            && ! $this->confirm('--force overwrites edits made in the panel. Continue?', true)) {
            return self::FAILURE;
        }

        $this->components->info('Reading the content definitions and copying their images');

        // Warnings are held back until the bar has finished, so they do not
        // break it up line by line.
        $warnings = [];
        $bar = null;

        $tally = $installer->install(
            $definitions,
            force: (bool) $this->option('force'),
            only: $only,
            output: function (string $level, string $message) use (&$warnings) {
                $warnings[] = [$level, $message];
            },
            started: function (int $total) use (&$bar) {
                $bar = $this->progressBar($total);
            },
            progress: function (string $item) use (&$bar) {
                $bar?->setMessage($item);
                $bar?->advance();
            },
        );

        if ($bar) {
            $bar->setMessage('done');
            $bar->finish();
            $this->newLine(2);
        }

        foreach ($warnings as [$level, $message]) {
            $this->components->{$level}($message);
        }

        $this->table(
            ['', 'Created', 'Updated', 'Left as they were'],
            collect([ContentInstaller::COMPONENTS, ContentInstaller::LAYOUTS, ContentInstaller::PAGES])
                ->filter(fn (string $kind): bool => ! $only || in_array($kind, $only, true))
                ->map(fn (string $kind): array => [
                    ucfirst($kind),
                    $tally[$kind]['created'] ?? 0,
                    $tally[$kind]['updated'] ?? 0,
                    $tally[$kind]['skipped'] ?? 0,
                ])
                ->all(),
        );

        if (collect($tally)->sum(fn ($counts) => $counts['skipped'] ?? 0) > 0 && ! $this->option('force')) {
            $this->line('  Existing content was kept. Use --force to replace it with the definitions in code.');
        }

        return self::SUCCESS;
    }

    /**
     * Count, bar, percentage, time so far, and the item just written.
     */
    protected function progressBar(int $total): ProgressBar
    {
        $bar = $this->output->createProgressBar($total);
        $bar->setFormat("  %current%/%max% [%bar%] %percent:3s%%  %elapsed:6s%  <fg=gray>%message%</>");
        $bar->setBarCharacter('<fg=green>=</>');
        $bar->setProgressCharacter('<fg=green>></>');
        $bar->setMessage('starting');
        $bar->start();

        return $bar;
    }
}
