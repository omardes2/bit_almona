<?php

use App\Support\ArabicText;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->decimal('low_stock_threshold', 10, 3)->default(5)->after('quantity_step');
            $table->boolean('is_featured')->default(false)->after('status')->index();
            // Normalised Arabic name + SKU for fast, hamza/taa-marbuta-insensitive search.
            $table->string('search_text', 500)->nullable()->after('seo_description');
            // SKU is optional (duplicated products start without one) but stays unique.
            $table->string('sku', 64)->nullable()->change();
        });

        // Backfill the search column for products that already exist.
        DB::table('products')->select(['id', 'name', 'sku'])->orderBy('id')->each(function ($product) {
            DB::table('products')->where('id', $product->id)
                ->update(['search_text' => ArabicText::normalize($product->name.' '.$product->sku)]);
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropIndex(['is_featured']);
            $table->dropColumn(['low_stock_threshold', 'is_featured', 'search_text']);
        });
    }
};
