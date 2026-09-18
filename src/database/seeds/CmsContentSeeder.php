<?php

use Cms\Content\ContentInstaller;
use Illuminate\Database\Seeder;

/**
 * Loads the component library, layouts and page content listed in
 * `cms.content` — the same thing `php artisan cms:seed-components` does.
 *
 * Content an editor has already changed is left alone; run the command with
 * --force to reset it to what the code defines.
 */
class CmsContentSeeder extends Seeder
{
    public function run(ContentInstaller $installer): void
    {
        $output = $this->command?->getOutput();
        $warnings = [];
        $bar = null;

        $tally = $installer->install(
            config('cms.content', []),
            output: function (string $level, string $message) use (&$warnings) {
                $warnings[] = [$level, $message];
            },
            // The same bar as cms:seed-components, when run from Artisan.
            started: function (int $total) use ($output, &$bar) {
                $bar = $output?->createProgressBar($total);
                $bar?->setFormat("  %current%/%max% [%bar%] %percent:3s%%  %elapsed:6s%  %message%");
                $bar?->setMessage('starting');
                $bar?->start();
            },
            progress: function (string $item) use (&$bar) {
                $bar?->setMessage($item);
                $bar?->advance();
            },
        );

        if ($bar) {
            $bar->setMessage('done');
            $bar->finish();
            $output->newLine(2);
        }

        foreach ($warnings as [$level, $message]) {
            $this->command?->{$level}($message);
        }

        foreach ($tally as $kind => $counts) {
            $this->command?->info(sprintf(
                '%s: %d created, %d updated, %d left as they were.',
                ucfirst($kind),
                $counts['created'] ?? 0,
                $counts['updated'] ?? 0,
                $counts['skipped'] ?? 0,
            ));
        }
    }
}
