<?php

namespace App\Console\Commands;

use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Database\Seeders\DemoDataSeeder;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

#[Signature('store:purge-demo {--force : Skip the confirmation prompt}')]
#[Description('حذف البيانات التجريبية (القسم والمنتج والعرض والزبون التجريبي)')]
class PurgeDemoData extends Command
{
    public function handle(): int
    {
        if (! $this->option('force') && ! $this->confirm('سيتم حذف البيانات التجريبية نهائيًا. متابعة؟')) {
            return self::SUCCESS;
        }

        DB::transaction(function () {
            // Offers and images are removed by cascading foreign keys.
            Product::withTrashed()->where('sku', DemoDataSeeder::PRODUCT_SKU)->get()->each->forceDelete();

            $category = Category::where('slug', DemoDataSeeder::CATEGORY_SLUG)->first();

            if ($category && ! $category->products()->withTrashed()->exists() && ! $category->children()->exists()) {
                $category->delete();
            }

            User::customers()->where('phone', DemoDataSeeder::CUSTOMER_PHONE)->delete();
        });

        $this->info('تم حذف البيانات التجريبية.');

        return self::SUCCESS;
    }
}
