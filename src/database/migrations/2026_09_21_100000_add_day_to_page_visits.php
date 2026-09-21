<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Counters were kept per month, which is too coarse to chart: the dashboard
 * can only draw one point per month. Recording the day as well lets the same
 * rows answer daily, weekly and monthly questions.
 *
 * Nullable rather than defaulted: rows written before this ran are a month's
 * total with no day attached, and must not be mistaken for the 1st.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('page_visits', function (Blueprint $table) {
            $table->unsignedTinyInteger('day')->nullable()->after('month');
            $table->index(['year', 'month', 'day'], 'page_visits_date_index');
        });
    }

    public function down(): void
    {
        Schema::table('page_visits', function (Blueprint $table) {
            $table->dropIndex('page_visits_date_index');
            $table->dropColumn('day');
        });
    }
};
