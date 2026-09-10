<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateWebPageSeoKeywordsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('web_page_seo_keywords', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->longText('meta_title');
            $table->longText('meta_description');
            $table->longText('meta_keywords')->nullable();
            $table->integer('in_header');
            $table->integer('in_footer');
            $table->integer('status');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('web_page_seo_keywords');
    }
}
