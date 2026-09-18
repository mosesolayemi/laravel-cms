<?php

use Illuminate\Database\Seeder;
use Cms\Models\Pages\Layouts;

/**
 * The layout every page is placed in by default.
 *
 * Only the row is made here. What the layout is made of — header, footer and
 * the rest — is content, seeded by CmsContentSeeder from the site's content
 * definition, so the package does not need to know what this site's header
 * looks like.
 *
 * Seeded through the Eloquent model rather than DB::table() so the `uuid`
 * hook on Layouts::booted() fires — the column is NOT NULL with no default.
 */
class WebPageLayoutSeeder extends Seeder
{
    public const DEFAULT = 'default';

    public function run()
    {
        // Before layouts were built in the panel, the one row pointed at a
        // Blade file. Keep its id, so pages placed in it stay placed.
        if (! Layouts::where('layout', self::DEFAULT)->exists()) {
            Layouts::where('layout', 'home.blade.php')->update(['layout' => self::DEFAULT]);
        }

        Layouts::updateOrCreate(['layout' => self::DEFAULT], [
            'label' => 'Site layout',
            'is_default' => true,
            'status' => 1,
        ]);
    }
}
