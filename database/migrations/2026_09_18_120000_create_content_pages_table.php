<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Editable public documents: About, Terms, Privacy, Refunds, Integrations.
 *
 * These are their own table rather than more keys in the site_settings JSON
 * blob because each is a long rich-text document with its own URL and
 * publish state — things a JSON column makes awkward to query or route.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('content_pages', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('title');
            $table->string('nav_label')->nullable();
            $table->text('excerpt')->nullable();
            $table->longText('body')->nullable();
            $table->boolean('is_published')->default(true);
            $table->boolean('show_in_footer')->default(true);
            $table->unsignedSmallInteger('position')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('content_pages');
    }
};
