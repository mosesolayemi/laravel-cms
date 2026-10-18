<?php

namespace PhynixMedia\Cms\Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Artisan;

/**
 * Lists every file already on the media disk in Content -> Assets — the same
 * as `php artisan cms:import-assets`. Safe to re-run: files already listed
 * are skipped.
 *
 * Call it last, after anything else that puts files on the disk (a gallery
 * import, say), so those are listed too. Skipped when CMS_SEED_MEDIA=false.
 */
class CmsAssetSeeder extends Seeder
{
    public function run(): void
    {
        if (! config('cms.seeding.media', true)) {
            $this->command?->info('Skipped the asset import (CMS_SEED_MEDIA=false).');

            return;
        }

        Artisan::call('cms:import-assets', [], $this->command?->getOutput());
    }
}
