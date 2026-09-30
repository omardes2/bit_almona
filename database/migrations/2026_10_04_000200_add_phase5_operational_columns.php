<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // One-time codes for future phone flows (password reset, phone change/verification).
        // Only a hash of the code is stored.
        Schema::create('otp_codes', function (Blueprint $table) {
            $table->id();
            $table->string('phone', 20);
            $table->string('purpose', 30); // App\Enums\OtpPurpose
            $table->string('code_hash');
            $table->timestamp('expires_at');
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->timestamp('used_at')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->timestamps();

            $table->index(['phone', 'purpose', 'created_at']);
            $table->index('expires_at');
        });

        // Stock alert de-duplication: set when an alert was sent, cleared when stock recovers.
        Schema::table('products', function (Blueprint $table) {
            $table->timestamp('low_stock_notified_at')->nullable()->after('low_stock_threshold');
            $table->timestamp('out_of_stock_notified_at')->nullable()->after('low_stock_notified_at');
        });

        // Future per-customer channel choices, e.g. {"whatsapp": true, "sms": false}. Null = defaults.
        Schema::table('customers', function (Blueprint $table) {
            $table->json('notification_preferences')->nullable()->after('whatsapp');
        });

        // Revenue reports filter and group delivered orders by delivered_at.
        Schema::table('orders', function (Blueprint $table) {
            $table->index(['status', 'delivered_at']);
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex(['status', 'delivered_at']);
        });

        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn('notification_preferences');
        });

        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['low_stock_notified_at', 'out_of_stock_notified_at']);
        });

        Schema::dropIfExists('otp_codes');
    }
};
