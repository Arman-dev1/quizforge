<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A price id belongs to one gateway. Keeping both means switching
     * gateways does not silently point checkout at an id the new provider
     * has never heard of — the same reason we keep both credential sets.
     *
     * `price_id` stays as the Paddle id so existing rows keep working.
     */
    public function up(): void
    {
        Schema::table('plans', function (Blueprint $table) {
            $table->string('stripe_price_id')->nullable()->after('price_id');
        });
    }

    public function down(): void
    {
        Schema::table('plans', function (Blueprint $table) {
            $table->dropColumn('stripe_price_id');
        });
    }
};
