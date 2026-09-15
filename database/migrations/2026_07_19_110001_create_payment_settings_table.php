<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Payment gateway credentials, managed from the platform panel instead
     * of the environment file.
     *
     * One row, one active provider. Credentials are stored encrypted (see
     * PaymentSetting's casts) because these are live API secrets.
     */
    public function up(): void
    {
        Schema::create('payment_settings', function (Blueprint $table) {
            $table->id();

            // 'none' | 'paddle' | 'stripe' — only one gateway runs at a time.
            $table->string('provider')->default('none');
            $table->boolean('test_mode')->default(true);

            // Encrypted JSON, one blob per provider so switching back and
            // forth does not lose the other provider's keys.
            $table->text('paddle')->nullable();
            $table->text('stripe')->nullable();

            $table->timestamp('verified_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_settings');
    }
};
