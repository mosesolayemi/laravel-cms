<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The footer sometimes words a link differently from the header.
 *
 * "/services" heads the header dropdown as "Services" but reads "Our Services"
 * in the footer; "/contact" is "Contact" above and "Contact Us" below. One
 * shared menu label would silently rename whichever menu lost the argument.
 *
 * The chain is footer_title, then menu_title, then the page's own title.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('web_page_templates', function (Blueprint $table) {
            $table->string('footer_title')->nullable()->after('menu_title');
        });
    }

    public function down(): void
    {
        Schema::table('web_page_templates', function (Blueprint $table) {
            $table->dropColumn('footer_title');
        });
    }
};
