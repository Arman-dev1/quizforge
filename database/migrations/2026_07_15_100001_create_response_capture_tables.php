<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('quiz_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('quiz_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('version');
            $table->json('content');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['quiz_id', 'version']);
        });

        Schema::create('quiz_responses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignId('quiz_id')->constrained()->cascadeOnDelete();
            $table->foreignId('quiz_version_id')->constrained()->cascadeOnDelete();
            $table->string('status', 20)->default('in_progress');
            $table->string('respondent_token', 40);
            $table->unsignedInteger('current_page')->default(0);
            $table->timestamp('started_at');
            $table->timestamp('completed_at')->nullable();
            $table->string('user_agent', 500)->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index(['quiz_id', 'status']);
            $table->index('respondent_token');
        });

        Schema::create('quiz_answers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('quiz_response_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('question_id');
            $table->string('question_type', 40);
            $table->json('value')->nullable();
            $table->timestamps();

            $table->unique(['quiz_response_id', 'question_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quiz_answers');
        Schema::dropIfExists('quiz_responses');
        Schema::dropIfExists('quiz_versions');
    }
};
