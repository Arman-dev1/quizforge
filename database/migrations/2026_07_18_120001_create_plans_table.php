<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plans', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->string('name');
            $table->string('tagline')->nullable();
            $table->unsignedInteger('price')->default(0);
            $table->string('period')->default('mo');
            $table->string('price_id')->nullable();

            // Limits — null means unlimited.
            $table->unsignedInteger('quizzes')->nullable();
            $table->unsignedInteger('responses_per_month')->nullable();
            $table->unsignedInteger('members')->nullable();

            // Paid/free feature gates.
            $table->boolean('integrations')->default(false);
            $table->boolean('custom_code')->default(false);

            // Marketing / display.
            $table->json('features')->nullable();
            $table->boolean('is_popular')->default(false);
            $table->unsignedInteger('position')->default(0);
            $table->boolean('is_active')->default(true);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plans');
    }
};
