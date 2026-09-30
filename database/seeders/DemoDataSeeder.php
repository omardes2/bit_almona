<?php

namespace Database\Seeders;

use App\Actions\Auth\RegisterCustomer;
use App\Enums\ProductStatus;
use App\Enums\SaleUnit;
use App\Models\Category;
use App\Models\Offer;
use App\Models\Product;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Test data only. Everything created here is identified by the constants
 * below and can be removed with: php artisan store:purge-demo
 */
class DemoDataSeeder extends Seeder
{
    public const CATEGORY_SLUG = 'demo-dairy-and-cheese';

    public const PRODUCT_SKU = 'DEMO-0001';

    public const CUSTOMER_PHONE = '0599000001';

    public const CUSTOMER_PASSWORD = 'Demo12345';

    public function run(RegisterCustomer $registerCustomer): void
    {
        $category = Category::firstOrCreate(
            ['slug' => self::CATEGORY_SLUG],
            ['name' => 'ألبان وأجبان', 'sort_order' => 1, 'is_active' => true],
        );

        $product = Product::firstOrCreate(
            ['sku' => self::PRODUCT_SKU],
            [
                'category_id' => $category->id,
                'name' => 'جبنة الخيرات 24 مثلث',
                'slug' => 'demo-al-khairat-cheese-24-triangles',
                'description' => 'جبنة مثلثات قابلة للدهن، 24 قطعة.',
                'original_price' => 18,
                'sale_price' => 18,
                'stock_quantity' => 100,
                'min_order_quantity' => 1,
                'quantity_step' => 1,
                'unit' => SaleUnit::Pack,
                'status' => ProductStatus::Available,
                'seo_title' => 'جبنة الخيرات 24 مثلث | بيت المونة',
                'seo_description' => 'اطلب جبنة الخيرات 24 مثلث من بيت المونة في الخليل.',
            ],
        );

        Offer::firstOrCreate(
            ['product_id' => $product->id],
            [
                'title' => 'عرض جبنة الخيرات',
                'original_price' => 18,
                'offer_price' => 10,
                'starts_at' => now(),
                'ends_at' => now()->addDays(30),
                'sort_order' => 1,
                'is_active' => true,
            ],
        );

        if (app()->environment('local', 'testing') && ! User::where('phone', self::CUSTOMER_PHONE)->exists()) {
            $registerCustomer->handle([
                'name' => 'زبون تجريبي',
                'phone' => self::CUSTOMER_PHONE,
                'password' => self::CUSTOMER_PASSWORD,
            ]);
        }
    }
}
