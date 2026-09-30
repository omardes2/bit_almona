<?php

namespace Tests\Feature\Store;

use App\Enums\ProductStatus;
use App\Models\Category;
use App\Models\Offer;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ProductPagesTest extends TestCase
{
    use RefreshDatabase, StorefrontTestHelpers;

    public function test_product_page_shows_the_product(): void
    {
        $product = $this->product(['name' => 'جبنة الخيرات 24 مثلث', 'slug' => 'جبنة-الخيرات', 'sku' => 'CHS-24', 'description' => 'جبنة مثلثات']);

        $this->get('/product/جبنة-الخيرات')
            ->assertOk()
            ->assertSee('جبنة الخيرات 24 مثلث')
            ->assertSee('CHS-24')
            ->assertSee('جبنة مثلثات')
            ->assertSee('أضف للسلة');
    }

    public function test_hidden_and_deleted_products_are_not_shown(): void
    {
        $hidden = $this->product(['name' => 'منتج مخفي', 'status' => ProductStatus::Hidden]);
        $deleted = $this->product(['name' => 'منتج محذوف']);
        $deleted->delete();
        $inactiveCategory = $this->product(['name' => 'منتج قسم معطل'], Category::factory()->inactive()->create());

        $this->get($hidden->url())->assertNotFound();
        $this->get($deleted->url())->assertNotFound();
        $this->get($inactiveCategory->url())->assertNotFound();

        $this->get($hidden->category->url())->assertDontSee('منتج مخفي');
        $this->get('/')->assertDontSee('منتج مخفي')->assertDontSee('منتج محذوف');
    }

    public function test_unavailable_products_are_shown_but_cannot_be_bought(): void
    {
        $product = $this->product(['name' => 'منتج غير متوفر', 'status' => ProductStatus::Unavailable]);

        $this->get($product->url())
            ->assertOk()
            ->assertSee('غير متوفر حاليًا')
            ->assertDontSee("productId: {$product->id}", false);
    }

    public function test_active_offer_price_is_shown_with_server_side_discount(): void
    {
        $product = $this->product(['original_price' => 18, 'sale_price' => 18]);
        Offer::factory()->for($product)->create(['original_price' => 18, 'offer_price' => 10]);

        $this->get($product->url())
            ->assertSeeInOrder(['10 ₪', '18 ₪'])
            ->assertSee('خصم <bdi dir="ltr">44%</bdi>', false);
    }

    public function test_expired_offer_is_not_applied(): void
    {
        $product = $this->product(['original_price' => 18, 'sale_price' => 18]);
        Offer::factory()->for($product)->expired()->create(['offer_price' => 10]);

        $this->get($product->url())->assertSee('18 ₪')->assertDontSee('10 ₪')->assertDontSee('خصم');
    }

    public function test_related_products_come_from_the_same_category_and_exclude_the_current_one(): void
    {
        $parent = Category::factory()->create();
        $cheese = Category::factory()->childOf($parent)->create();
        $milk = Category::factory()->childOf($parent)->create();
        $other = Category::factory()->create();

        $product = $this->product(['name' => 'المنتج الحالي'], $cheese);
        $this->product(['name' => 'نفس القسم'], $cheese);
        $this->product(['name' => 'قسم شقيق'], $milk);
        $this->product(['name' => 'قسم آخر'], $other);
        $this->product(['name' => 'مخفي', 'status' => ProductStatus::Hidden], $cheese);

        $related = $this->get($product->url())->viewData('related')->pluck('name')->all();

        $this->assertSame(['نفس القسم', 'قسم شقيق'], $related);
    }

    public function test_category_page_lists_products_of_subcategories_with_sorting_and_pagination(): void
    {
        $dairy = Category::factory()->create(['name' => 'ألبان', 'slug' => 'ألبان']);
        $cheese = Category::factory()->childOf($dairy)->create(['name' => 'أجبان']);
        $this->product(['name' => 'رخيص', 'sale_price' => 1, 'original_price' => 1], $dairy);
        $this->product(['name' => 'غالي', 'sale_price' => 500, 'original_price' => 500], $cheese);
        Product::factory()->count(25)->for($dairy)->create();

        $response = $this->get('/category/ألبان?sort=price_asc')->assertOk()->assertSee('أجبان');
        $response->assertViewHas('products', fn ($p) => $p->total() === 27 && $p->count() === 24 && $p->first()->name === 'رخيص');

        $this->get('/category/ألبان?sort=price_desc')
            ->assertViewHas('products', fn ($p) => $p->first()->name === 'غالي');

        $this->get('/category/ألبان?page=2')->assertViewHas('products', fn ($p) => $p->count() === 3);
    }

    public function test_empty_and_inactive_categories(): void
    {
        $empty = Category::factory()->create();
        $this->get($empty->url())->assertOk()->assertSee('لا توجد منتجات في هذا القسم حاليًا');

        $inactive = Category::factory()->inactive()->create();
        $this->get($inactive->url())->assertNotFound();
    }

    public function test_offers_page_shows_only_running_offers(): void
    {
        $running = $this->product(['name' => 'عرض فعال']);
        $expired = $this->product(['name' => 'عرض منتهي']);
        $hidden = $this->product(['name' => 'منتج مخفي بعرض', 'status' => ProductStatus::Hidden]);
        Offer::factory()->for($running)->create(['original_price' => 20, 'offer_price' => 12]);
        Offer::factory()->for($expired)->expired()->create();
        Offer::factory()->for($hidden)->create();

        $this->get('/offers')
            ->assertOk()
            ->assertSee('عروض بيت المونة')
            ->assertSee('عرض فعال')
            ->assertSee('12 ₪')
            ->assertDontSee('عرض منتهي')
            ->assertDontSee('منتج مخفي بعرض');

        Offer::query()->delete();
        $this->get('/offers')->assertSee('لا توجد عروض حاليًا');
    }

    public function test_listing_pages_have_no_n_plus_one_queries(): void
    {
        $category = Category::factory()->create();

        $count = function () use ($category) {
            DB::flushQueryLog();
            DB::enableQueryLog();
            $this->get($category->url())->assertOk();
            $this->get('/offers')->assertOk();
            $this->get('/')->assertOk();

            return count(DB::getQueryLog());
        };

        foreach (range(1, 2) as $i) {
            Offer::factory()->for($this->product([], $category))->create();
        }
        $count();
        $few = $count();

        foreach (range(1, 10) as $i) {
            Offer::factory()->for($this->product([], $category))->create();
        }
        $many = $count();

        $this->assertSame($few, $many);
    }
}
