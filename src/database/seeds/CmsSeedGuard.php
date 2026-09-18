<?php

use Cms\Models\Pages\Templates;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

/**
 * Keeps the seeders that reset content to what the code says — page titles,
 * menus, gallery filters — from overwriting a live site's panel edits.
 *
 * On production, once the site has pages, those seeders skip themselves. The
 * answer is taken once, at the start of a seeding run, so a first deploy
 * (no pages yet) still runs every seeder even after the page seeder has
 * created the pages.
 *
 * To reset a live site to the code on purpose, set CMS_SEED_OVERWRITE=true.
 */
class CmsSeedGuard
{
    protected static ?bool $existingSite = null;

    /**
     * Take the reading now. CmsDatabaseSeeder calls this before anything is
     * seeded; any seeder asking later gets the same answer.
     */
    public static function start(): void
    {
        static::$existingSite ??= Schema::hasTable('web_page_templates') && Templates::query()->exists();
    }

    /**
     * Whether a seeder that overwrites panel edits must stand aside. Explains
     * why on the console when it does.
     */
    public static function protects(string $seeder, ?Command $command = null): bool
    {
        static::start();

        $protected = static::$existingSite
            && app()->isProduction()
            && ! config('cms.seeding.overwrite_live', false);

        if ($protected) {
            $command?->warn(sprintf(
                'Skipped %s: this is a live site that already has pages, and it would overwrite changes made in the panel. '
                . 'Set CMS_SEED_OVERWRITE=true to run it anyway.',
                $seeder,
            ));
        }

        return $protected;
    }

    /**
     * For tests: forget the reading, as a new process would.
     */
    public static function reset(): void
    {
        static::$existingSite = null;
    }
}
