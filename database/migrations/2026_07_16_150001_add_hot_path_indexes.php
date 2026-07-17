<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quiz_answers', function (Blueprint $table) {
            // Leads page filters answers by contact-type questions.
            $table->index('question_type');
        });

        Schema::table('quiz_responses', function (Blueprint $table) {
            // Monthly quota counts per workspace.
            $table->index(['workspace_id', 'started_at']);
        });
    }

    public function down(): void
    {
        Schema::table('quiz_answers', function (Blueprint $table) {
            $table->dropIndex(['question_type']);
        });

        Schema::table('quiz_responses', function (Blueprint $table) {
            $table->dropIndex(['workspace_id', 'started_at']);
        });
    }
};
