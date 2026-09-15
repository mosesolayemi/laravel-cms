<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The footer orders its links differently from the header.
 *
 * "Why Choose Us" sits sixth in the footer's services column but last in the
 * header; the useful links column has its own sequence entirely. `page_order`
 * drives the header, this drives the footer.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('web_page_templates', function (Blueprint $table) {
            $table->integer('footer_order')->default(0)->after('footer_title');
        });
    }

    public function down(): void
    {
        Schema::table('web_page_templates', function (Blueprint $table) {
            $table->dropColumn('footer_order');
        });
    }
};
