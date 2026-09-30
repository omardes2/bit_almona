<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Addresses: simpler, Hebron-friendly shape (line + city + area + notes).
        Schema::table('addresses', function (Blueprint $table) {
            $table->string('address_line')->nullable()->after('recipient_phone');
            $table->string('city', 100)->nullable()->after('address_line');
            $table->text('notes')->nullable()->after('area');
        });

        Schema::table('addresses', function (Blueprint $table) {
            $table->dropColumn(['street', 'building', 'floor', 'details']);
        });

        // A zone may have no minimum of its own.
        Schema::table('delivery_zones', function (Blueprint $table) {
            $table->decimal('min_order_amount', 10, 2)->nullable()->default(null)->change();
        });

        // Which provider handled the payment (cash_on_delivery now, gateways later).
        Schema::table('payments', function (Blueprint $table) {
            $table->string('provider', 50)->default('cash_on_delivery')->after('order_id');
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropColumn('provider');
        });

        Schema::table('addresses', function (Blueprint $table) {
            $table->string('street')->nullable();
            $table->string('building')->nullable();
            $table->string('floor')->nullable();
            $table->text('details')->nullable();
        });

        Schema::table('addresses', function (Blueprint $table) {
            $table->dropColumn(['address_line', 'city', 'notes']);
        });
    }
};
