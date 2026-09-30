<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The price the customer saw when adding the item. Used only to show
     * "the price changed" in the cart — never to calculate totals.
     */
    public function up(): void
    {
        Schema::table('cart_items', function (Blueprint $table) {
            $table->decimal('unit_price_at_add', 10, 2)->nullable()->after('quantity');
        });

        Schema::table('carts', function (Blueprint $table) {
            $table->index('updated_at'); // pruning of abandoned guest carts
        });
    }

    public function down(): void
    {
        Schema::table('carts', function (Blueprint $table) {
            $table->dropIndex(['updated_at']);
        });

        Schema::table('cart_items', function (Blueprint $table) {
            $table->dropColumn('unit_price_at_add');
        });
    }
};
