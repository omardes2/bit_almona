<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            // Delivery snapshot (independent of later address edits).
            $table->string('recipient_name')->nullable()->after('customer_whatsapp');
            $table->string('recipient_phone', 20)->nullable()->after('recipient_name');
            $table->string('delivery_city', 100)->nullable()->after('delivery_zone_name');
            $table->string('delivery_area', 100)->nullable()->after('delivery_city');

            $table->string('payment_method', 30)->default('cash_on_delivery')->after('total');

            // Idempotency: one order per checkout page load, even on double submit.
            $table->string('checkout_token', 64)->nullable()->unique()->after('order_number');

            // Stock bookkeeping so a cancellation restores stock exactly once.
            $table->timestamp('stock_deducted_at')->nullable()->after('cancelled_at');
            $table->timestamp('stock_restored_at')->nullable()->after('stock_deducted_at');

            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex(['status', 'created_at']);
            $table->dropUnique(['checkout_token']);
            $table->dropColumn([
                'recipient_name', 'recipient_phone', 'delivery_city', 'delivery_area',
                'payment_method', 'checkout_token', 'stock_deducted_at', 'stock_restored_at',
            ]);
        });
    }
};
