<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The Blade view drawing a library component instead of its stored data,
 * set by `php artisan cms:publish-component` and cleared by --revert.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cms_components', function (Blueprint $table) {
            $table->string('view')->nullable()->after('data');
        });
    }

    public function down(): void
    {
        Schema::table('cms_components', function (Blueprint $table) {
            $table->dropColumn('view');
        });
    }
};
