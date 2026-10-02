<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Remembers what a seeded image looked like when the seeder last wrote it.
 *
 * Media::import() refreshes a seeded image on the media disk whenever the file
 * in the repository has changed, so a deploy can update a theme photograph. It
 * had no way to tell that apart from an editor having replaced the same image
 * in the panel, and overwrote the editor's upload every time it ran.
 *
 * With the hash of what was seeded recorded here, import() can see that the
 * file on disk is no longer the one it put there, and leave it alone.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cms_assets', function (Blueprint $table) {
            $table->string('seeded_hash', 64)->nullable()->after('managed');
        });
    }

    public function down(): void
    {
        Schema::table('cms_assets', function (Blueprint $table) {
            $table->dropColumn('seeded_hash');
        });
    }
};
