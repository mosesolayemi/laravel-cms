<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The asset library: every image and file the CMS knows about, so one upload
 * can be chosen again on any page or component.
 *
 * `managed` marks files the library owns (uploaded through the CMS); files
 * registered from elsewhere — another module's uploads — are listed and
 * reusable, but the library never deletes them.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cms_assets', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('name');
            $table->string('group')->nullable()->index();
            $table->string('disk');
            $table->string('path');
            $table->string('mime_type')->nullable();
            $table->unsignedBigInteger('size')->nullable();
            $table->unsignedInteger('width')->nullable();
            $table->unsignedInteger('height')->nullable();
            $table->string('alt')->nullable();
            $table->boolean('managed')->default(true);
            $table->timestamps();

            $table->unique(['disk', 'path']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cms_assets');
    }
};
