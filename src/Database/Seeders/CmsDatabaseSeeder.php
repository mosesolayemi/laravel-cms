<?php

namespace PhynixMedia\Cms\Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * Seeds the CMS's own tables.
 *
 * A seeder lives wherever its table's migration lives, so everything touching
 * the package's tables belongs here rather than in the host application's
 * seeders. The host calls this one seeder and gets the whole CMS side set up:
 *
 *     $this->call(\PhynixMedia\Cms\Database\Seeders\CmsDatabaseSeeder::class);
 *
 * Or on its own:
 *
 *     php artisan db:seed --class='PhynixMedia\Cms\Database\Seeders\CmsDatabaseSeeder'
 *
 * What it seeds is deliberately generic. The pages, layouts and menus that
 * make up a particular site are that site's content, not the package's, so
 * they live in the host's own seeders — listed in `cms.seeding.seeders`, which
 * run after the guard is taken and before the content installer fills in what
 * those pages are made of.
 */
class CmsDatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Note whether the site existed before anything is seeded, so the
        // seeders that would overwrite a live site's panel edits know.
        CmsSeedGuard::start();

        foreach ($this->seeders() as $seeder) {
            if (! class_exists($seeder)) {
                $this->command?->warn(sprintf(
                    'Skipped %s — no such class. Seeder folders are classmap '
                    . 'autoloaded, so a newly added file is invisible until '
                    . 'you run: composer dump-autoload',
                    $seeder,
                ));

                continue;
            }

            $this->call($seeder);
        }
    }

    /**
     * The host's own seeders run between the guard and the content
     * installer: their pages and layouts must exist before the installer
     * can fill them, and the guard must already have taken its reading.
     *
     * @return array<int, class-string>
     */
    protected function seeders(): array
    {
        return [
            ...config('cms.seeding.seeders', []),
            CmsContentSeeder::class,
        ];
    }
}
