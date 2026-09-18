<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A page's own builder content: the ordered list of components it is made of.
 *
 * Stored in the shape Filament's Builder produces — a list of
 * {type, data} items — so the panel reads and writes it without translation.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('web_page_templates', function (Blueprint $table) {
            $table->json('content')->nullable()->after('url');
        });
    }

    public function down(): void
    {
        Schema::table('web_page_templates', function (Blueprint $table) {
            $table->dropColumn('content');
        });
    }
};
