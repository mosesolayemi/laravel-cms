<?php

use Illuminate\Database\Seeder;
use Cms\Models\Pages\Layouts;

/**
 * Layouts map a CMS page onto a blade file under resources/views/web/pages/.
 *
 * Seeded through the Eloquent model rather than DB::table() so the `uuid`
 * hook on Layouts::booted() fires — the column is NOT NULL with no default.
 *
 * Only layouts whose blade file actually exists are seeded; add a line here
 * as each further template is ported.
 */
class WebPageLayoutSeeder extends Seeder
{
    public function run()
    {
        $layouts = [
            ['label' => 'Home', 'layout' => 'home.blade.php', 'status' => 1],
        ];

        foreach ($layouts as $layout) {
            Layouts::updateOrCreate(['layout' => $layout['layout']], $layout);
        }
    }
}
