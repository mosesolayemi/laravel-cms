<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The component library: named, reusable pieces of content.
 *
 * A component is one registered component type plus the data for its fields —
 * "Call to action (site default)" is the `call-to-action` type with a title and
 * some text. Pages and layouts either hold components inline in their builder
 * content, or reference one of these by id, so editing a library component
 * changes it everywhere it is placed.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cms_components', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('name');
            // Stable key for seeding and for cms_component('handle') in Blade.
            $table->string('handle')->unique();
            $table->string('type')->index();
            $table->json('data')->nullable();
            $table->text('description')->nullable();
            $table->integer('status')->default(1);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cms_components');
    }
};
