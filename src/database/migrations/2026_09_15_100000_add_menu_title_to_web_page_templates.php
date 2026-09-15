<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * How a page is labelled in a menu, when that differs from its own title.
 *
 * "/about" is titled "Company Overview" on the page itself, but heads the
 * "About Us" dropdown; "/faq" is "FAQs" but reads "Help & FAQS" in the footer.
 * Without somewhere to put the menu wording, turning the menus into data would
 * silently rename four links on the live site.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('web_page_templates', function (Blueprint $table) {
            $table->string('menu_title')->nullable()->after('title');
        });
    }

    public function down(): void
    {
        Schema::table('web_page_templates', function (Blueprint $table) {
            $table->dropColumn('menu_title');
        });
    }
};
