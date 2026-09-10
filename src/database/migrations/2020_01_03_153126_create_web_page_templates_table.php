<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateWebPageTemplatesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('web_page_templates', function (Blueprint $table) 
        {
            $table->bigIncrements('id');
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('seo_keyword_id')->nullable();
            $table->string('layout_id')->nullable();
            $table->string('meta_title')->nullable();
            $table->string('meta_description')->nullable();
            $table->string('header_position')->nullable();
            $table->string('footer_position')->nullable();
            $table->string('title');
            $table->string('url')->unique();
            $table->integer('parent');
            $table->integer('category')->nullable();
            $table->integer('page_order');
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
        Schema::dropIfExists('web_page_templates');
    }
}
