<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workspace_integrations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->string('provider', 60);
            $table->text('settings')->nullable(); // encrypted JSON credentials
            $table->timestamp('connected_at')->nullable();
            $table->timestamps();

            $table->unique(['workspace_id', 'provider']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workspace_integrations');
    }
};
