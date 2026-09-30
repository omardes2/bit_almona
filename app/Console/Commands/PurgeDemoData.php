<?php

namespace App\Console\Commands;

use App\Enums\AccountStatus;
use App\Enums\ProductStatus;
use App\Models\Category;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;
use Database\Seeders\DemoDataSeeder;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Removes ONLY the rows DemoDataSeeder creates (identified by its constants).
 * Anything that real activity touched is kept: a demo product that appears in
 * an order is hidden instead of deleted, and a demo customer with orders is
 * suspended instead of deleted, so no order history is ever lost.
 */
#[Signature('store:purge-demo {--force : Skip the confirmation prompt} {--dry-run : Only show what would be removed}')]
#[Description('حذف البيانات التجريبية (القسم والمنتج والعرض والزبون التجريبي) دون المساس بالبيانات الحقيقية')]
class PurgeDemoData extends Command
{
    public function handle(): int
    {
        $products = Product::withTrashed()->where('sku', DemoDataSeeder::PRODUCT_SKU)->get();
        $inOrders = OrderItem::query()->whereIn('product_id', $products->modelKeys())->distinct()->pluck('product_id')->all();
        $category = Category::where('slug', DemoDataSeeder::CATEGORY_SLUG)->first();
        $customer = User::customers()->where('phone', DemoDataSeeder::CUSTOMER_PHONE)->withCount('orders')->first();

        if ($products->isEmpty() && ! $category && ! $customer) {
            $this->info('لا توجد بيانات تجريبية.');

            return self::SUCCESS;
        }

        $this->table(['العنصر', 'الإجراء'], array_filter([
            ...$products->map(fn (Product $p) => [
                "منتج {$p->sku}",
                in_array($p->id, $inOrders) ? 'إخفاء (موجود في طلبات)' : 'حذف',
            ])->all(),
            $category ? ["قسم {$category->slug}", 'حذف إن كان فارغًا'] : null,
            $customer ? ["زبون {$customer->phone}", $customer->orders_count > 0 ? 'إيقاف (لديه طلبات)' : 'حذف'] : null,
        ]));

        if ($this->option('dry-run')) {
            return self::SUCCESS;
        }

        if (! $this->option('force') && ! $this->confirm('سيتم حذف البيانات التجريبية نهائيًا. متابعة؟')) {
            return self::SUCCESS;
        }

        DB::transaction(function () use ($products, $inOrders, $category, $customer) {
            foreach ($products as $product) {
                if (in_array($product->id, $inOrders)) {
                    // Keep order history intact: hide the product and stop its offers.
                    $product->offers()->update(['is_active' => false]);
                    $product->forceFill(['status' => ProductStatus::Hidden])->save();
                    $product->delete();
                } else {
                    // Offers and images are removed by cascading foreign keys.
                    $product->forceDelete();
                }
            }

            if ($category && ! $category->products()->withTrashed()->exists() && ! $category->children()->exists()) {
                $category->delete();
            }

            if ($customer) {
                $customer->orders_count > 0
                    ? $customer->update(['status' => AccountStatus::Suspended])
                    : $customer->delete();
            }
        });

        $this->info('تم حذف البيانات التجريبية.');

        return self::SUCCESS;
    }
}
