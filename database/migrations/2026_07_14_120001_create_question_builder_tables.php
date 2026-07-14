<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('quiz_pages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('quiz_id')->constrained()->cascadeOnDelete();
            $table->string('title', 150)->nullable();
            $table->text('description')->nullable();
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();

            $table->index(['quiz_id', 'position']);
        });

        Schema::create('questions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('quiz_id')->constrained()->cascadeOnDelete();
            $table->foreignId('quiz_page_id')->constrained()->cascadeOnDelete();
            $table->string('type', 40);
            $table->string('title', 500)->default('');
            $table->text('description')->nullable();
            $table->string('placeholder')->nullable();
            $table->string('help_text', 500)->nullable();
            $table->boolean('is_required')->default(false);
            $table->boolean('is_hidden')->default(false);
            $table->unsignedInteger('position')->default(0);
            $table->json('settings')->nullable();
            $table->json('validation')->nullable();
            $table->timestamps();

            $table->index(['quiz_page_id', 'position']);
            $table->index('quiz_id');
        });

        Schema::create('question_options', function (Blueprint $table) {
            $table->id();
            $table->foreignId('question_id')->constrained()->cascadeOnDelete();
            $table->string('label', 500);
            $table->boolean('is_correct')->default(false);
            $table->unsignedInteger('position')->default(0);
            $table->json('settings')->nullable();
            $table->timestamps();

            $table->index(['question_id', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('question_options');
        Schema::dropIfExists('questions');
        Schema::dropIfExists('quiz_pages');
    }
};
