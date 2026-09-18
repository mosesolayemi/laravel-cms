<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The Blade view that draws a page's content instead of its components.
 *
 * Null for a page built in the panel. `php artisan cms:publish-page` writes a
 * page out to a Blade file for changes the components cannot express, and
 * sets this so the file takes over; the panel can switch it back.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('web_page_templates', function (Blueprint $table) {
            $table->string('view')->nullable()->after('content');
        });
    }

    public function down(): void
    {
        Schema::table('web_page_templates', function (Blueprint $table) {
            $table->dropColumn('view');
        });
    }
};
