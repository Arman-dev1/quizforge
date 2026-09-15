<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Whether a plan may hide the "Powered by QuizForge" line on the public
     * player. Sits alongside the other paid feature gates.
     */
    public function up(): void
    {
        Schema::table('plans', function (Blueprint $table) {
            $table->boolean('remove_branding')->default(false)->after('custom_code');
        });

        // Existing managed plans keep working: anything that already unlocks
        // custom code is a paid tier, so it gets branding removal too.
        if (Schema::hasTable('plans')) {
            DB::table('plans')
                ->where('custom_code', true)
                ->update(['remove_branding' => true]);
        }
    }

    public function down(): void
    {
        Schema::table('plans', function (Blueprint $table) {
            $table->dropColumn('remove_branding');
        });
    }
};
