<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Integrations are managed per quiz, not workspace-wide.
        Schema::dropIfExists('workspace_integrations');

        Schema::create('quiz_integrations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('quiz_id')->constrained()->cascadeOnDelete();
            $table->string('provider', 60);
            $table->text('credentials')->nullable();     // encrypted JSON
            $table->string('resource_id')->nullable();   // audience / list / form id
            $table->string('resource_name')->nullable();
            $table->json('mapping')->nullable();          // target field => question id
            $table->string('status', 20)->default('connected');
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamps();

            $table->unique(['quiz_id', 'provider']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quiz_integrations');
    }
};
