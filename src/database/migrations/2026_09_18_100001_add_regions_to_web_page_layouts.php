<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Layouts become the frame a page is rendered in.
 *
 * `shell` is the Blade view holding the <html>, <head> and scripts. The four
 * regions are builder content, exactly like a page's own, rendered around the
 * page: the header and footer, plus anything that should sit directly before
 * or after every page's content (a banner, a call to action).
 *
 * `layout` stays as the unique key it always was; it is now a handle rather
 * than a Blade filename.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('web_page_layouts', function (Blueprint $table) {
            $table->string('shell')->nullable()->after('layout');
            $table->json('header')->nullable()->after('shell');
            $table->json('before_content')->nullable()->after('header');
            $table->json('after_content')->nullable()->after('before_content');
            $table->json('footer')->nullable()->after('after_content');
            $table->boolean('is_default')->default(false)->after('footer');
        });
    }

    public function down(): void
    {
        Schema::table('web_page_layouts', function (Blueprint $table) {
            $table->dropColumn(['shell', 'header', 'before_content', 'after_content', 'footer', 'is_default']);
        });
    }
};
