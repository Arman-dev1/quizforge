<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('questions', function (Blueprint $table) {
            $table->json('logic')->nullable()->after('validation');
        });

        Schema::table('quiz_responses', function (Blueprint $table) {
            $table->integer('score')->nullable()->after('completed_at');
            $table->unsignedInteger('max_score')->nullable()->after('score');
            $table->decimal('percentage', 5, 1)->nullable()->after('max_score');
            $table->boolean('passed')->nullable()->after('percentage');
            $table->string('grade', 100)->nullable()->after('passed');
        });
    }

    public function down(): void
    {
        Schema::table('questions', function (Blueprint $table) {
            $table->dropColumn('logic');
        });

        Schema::table('quiz_responses', function (Blueprint $table) {
            $table->dropColumn(['score', 'max_score', 'percentage', 'passed', 'grade']);
        });
    }
};
