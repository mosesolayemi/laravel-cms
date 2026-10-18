<?php

use Illuminate\Database\Seeder;

/**
 * Seeds the CMS's own tables.
 *
 * A seeder lives wherever its table's migration lives, so everything touching
 * web_page_layouts, web_page_templates and the menu columns on them belongs
 * here rather than in the host application's seeders. The host calls this one
 * seeder and gets the whole CMS side set up:
 *
 *     $this->call(\PhynixMedia\Cms\Database\Seeders\CmsDatabaseSeeder::class);
 *
 * Or on its own:
 *
 *     php artisan db:seed --class='PhynixMedia\Cms\Database\Seeders\CmsDatabaseSeeder'
 *
 * Order matters: a template row needs its layout row first, MenuSeeder
 * places pages in the menus, so the pages must already exist, and
 * CmsContentSeeder fills in what the layouts and pages are made of.
 */
class CmsDatabaseSeeder extends Seeder
{
    /**
     * @var array<int, class-string>
     */
    protected array $seeders = [
        WebPageLayoutSeeder::class,
        WebPageTemplateSeeder::class,
        MenuSeeder::class, // u can comment this out if u are reusing this module in anothe project and u dont want it in the project.... t for thanks
        CmsContentSeeder::class,
    ];

    public function run(): void
    {
        // Note whether the site existed before anything is seeded, so the
        // seeders that would overwrite a live site's panel edits know.
        CmsSeedGuard::start();

        foreach ($this->seeders as $seeder) {
            if (! class_exists($seeder)) {
                $this->command?->warn(sprintf(
                    'Skipped %s — no such class. Expected packages/cms/src/database/seeds/%s.php.',
                    $seeder,
                    class_basename($seeder),
                ));

                continue;
            }

            $this->call($seeder);
        }
    }
}
