<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('quiz_templates', function (Blueprint $table) {
            $table->id();
            // Null workspace = global system template, visible everywhere.
            $table->foreignId('workspace_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('name', 150);
            $table->string('description', 500)->nullable();
            $table->string('category', 100);
            $table->string('type', 40);
            $table->json('content');
            $table->timestamps();

            $table->index('workspace_id');
        });

        Schema::create('library_questions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('name', 500);
            $table->json('question');
            $table->timestamps();

            $table->index('workspace_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('library_questions');
        Schema::dropIfExists('quiz_templates');
    }
};
