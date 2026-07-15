<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quiz_responses', function (Blueprint $table) {
            $table->text('notes')->nullable()->after('meta');
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::table('quiz_responses', function (Blueprint $table) {
            $table->dropColumn(['notes', 'deleted_at']);
        });
    }
};
