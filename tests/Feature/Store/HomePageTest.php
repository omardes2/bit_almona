<?php

namespace Tests\Feature\Store;

use App\Models\Banner;
use App\Models\Category;
use App\Models\Offer;
use App\Models\StoreSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HomePageTest extends TestCase
{
    use RefreshDatabase, StorefrontTestHelpers;

    public function test_only_running_banners_are_shown_in_order(): void
    {
        Banner::factory()->create(['title' => 'بنر ثاني', 'sort_order' => 2]);
        Banner::factory()->create(['title' => 'بنر أول', 'sort_order' => 1, 'link_url' => '/offers']);
        Banner::factory()->expired()->create(['title' => 'بنر منتهي']);
        Banner::factory()->scheduled()->create(['title' => 'بنر مجدول']);
        Banner::factory()->create(['title' => 'بنر متوقف', 'is_active' => false]);

        $this->get('/')
            ->assertOk()
            ->assertSeeInOrder(['بنر أول', 'بنر ثاني'])
            ->assertSee('href="/offers"', false)
            ->assertDontSee('بنر منتهي')
            ->assertDontSee('بنر مجدول')
            ->assertDontSee('بنر متوقف');
    }

    public function test_unsafe_banner_links_are_not_rendered(): void
    {
        // Validation already blocks this in the admin; the view is defensive too.
        Banner::factory()->create(['title' => 'بنر', 'link_url' => 'javascript:alert(1)']);

        $this->get('/')->assertOk()->assertDontSee('javascript:alert(1)', false);
    }

    public function test_scheduled_banner_appears_on_time_despite_the_cache(): void
    {
        Banner::factory()->create(['title' => 'بنر الغد', 'starts_at' => now()->addHour()]);

        $this->get('/')->assertDontSee('بنر الغد');
        $this->travel(61)->minutes();
        $this->get('/')->assertSee('بنر الغد');
    }

    public function test_only_active_categories_are_shown(): void
    {
        $dairy = Category::factory()->create(['name' => 'ألبان']);
        Category::factory()->childOf($dairy)->create(['name' => 'قسم فرعي ظاهر']);
        Category::factory()->inactive()->create(['name' => 'قسم معطل']);

        $this->get('/')->assertSee('ألبان')->assertSee('قسم فرعي ظاهر')->assertDontSee('قسم معطل');
    }

    public function test_admin_changes_to_categories_show_immediately(): void
    {
        $category = Category::factory()->create(['name' => 'اسم قديم']);
        $this->get('/')->assertSee('اسم قديم');

        $category->update(['name' => 'اسم جديد']);
        $this->get('/')->assertSee('اسم جديد')->assertDontSee('اسم قديم');

        $category->update(['is_active' => false]);
        $this->get('/')->assertDontSee('اسم جديد');
    }

    public function test_only_running_offers_are_shown(): void
    {
        $running = $this->product(['name' => 'منتج عليه عرض فعال']);
        $expired = $this->product(['name' => 'منتج عرضه منتهي']);
        $future = $this->product(['name' => 'منتج عرضه قادم']);
        Offer::factory()->for($running)->create(['original_price' => 20, 'offer_price' => 15]);
        Offer::factory()->for($expired)->expired()->create(['offer_price' => 15]);
        Offer::factory()->for($future)->upcoming()->create(['offer_price' => 15]);

        $response = $this->get('/')->assertOk();

        $offers = $response->viewData('offerProducts');
        $this->assertSame([$running->id], $offers->pluck('id')->all());
        $response->assertSee('خصم');
    }

    public function test_empty_sections_are_not_rendered(): void
    {
        $this->get('/')->assertOk()->assertDontSee('عروض اليوم')->assertSee('المتجر قيد التجهيز');
    }

    public function test_store_settings_drive_the_layout(): void
    {
        StoreSetting::set('store_name', 'متجر الاختبار');
        StoreSetting::set('store_phone', '0599111222');
        StoreSetting::set('store_whatsapp', '0599111222');

        $this->get('/')
            ->assertSee('<title>متجر الاختبار</title>', false)
            ->assertSee('0599111222')
            ->assertSee('https://wa.me/970599111222', false);
    }
}
