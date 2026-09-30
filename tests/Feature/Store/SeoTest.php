<?php

namespace Tests\Feature\Store;

use App\Enums\ProductStatus;
use App\Models\Category;
use App\Models\Offer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SeoTest extends TestCase
{
    use RefreshDatabase, StorefrontTestHelpers;

    public function test_product_page_has_title_description_canonical_and_open_graph(): void
    {
        $product = $this->product(['name' => 'زيت زيتون', 'slug' => 'زيت-زيتون', 'seo_title' => 'زيت زيتون بلدي', 'seo_description' => 'أفضل زيت زيتون من الخليل']);

        $this->get('/product/زيت-زيتون?utm_source=x')
            ->assertSee('<title>زيت زيتون بلدي | بيت المونة</title>', false)
            ->assertSee('<meta name="description" content="أفضل زيت زيتون من الخليل">', false)
            ->assertSee('<link rel="canonical" href="'.$product->url().'">', false)
            ->assertSee('<meta property="og:type" content="product">', false)
            ->assertSee('<meta property="og:title" content="زيت زيتون بلدي">', false);
    }

    public function test_seo_fallbacks_use_the_product_name_and_description(): void
    {
        $product = $this->product(['name' => 'عسل', 'description' => 'عسل طبيعي من الجبال']);

        $this->get($product->url())
            ->assertSee('<title>عسل | بيت المونة</title>', false)
            ->assertSee('عسل طبيعي من الجبال', false);
    }

    public function test_product_structured_data(): void
    {
        $product = $this->product(['name' => 'جبنة', 'sku' => 'CHS-1', 'original_price' => 18, 'sale_price' => 18]);
        Offer::factory()->for($product)->create(['original_price' => 18, 'offer_price' => 10]);

        $html = $this->get($product->url())->getContent();

        preg_match('#<script type="application/ld\+json">(.*?)</script>#s', $html, $match);
        $data = json_decode($match[1], true);

        $this->assertSame('Product', $data['@type']);
        $this->assertSame('جبنة', $data['name']);
        $this->assertSame('CHS-1', $data['sku']);
        $this->assertSame('10.00', $data['offers']['price']);
        $this->assertSame('ILS', $data['offers']['priceCurrency']);
        $this->assertSame('https://schema.org/InStock', $data['offers']['availability']);
    }

    public function test_category_page_seo(): void
    {
        $category = Category::factory()->create(['name' => 'خضار', 'slug' => 'خضار']);

        $this->get('/category/خضار?sort=name')
            ->assertSee('<title>خضار | بيت المونة</title>', false)
            ->assertSee('<link rel="canonical" href="'.$category->url().'">', false)
            ->assertSee('<meta name="description" content="تسوّق خضار', false);
    }

    public function test_sitemap_contains_only_public_pages(): void
    {
        $visible = $this->product(['slug' => 'visible']);
        $unavailable = $this->product(['slug' => 'unavailable', 'status' => ProductStatus::Unavailable]);
        $this->product(['slug' => 'hidden-product', 'status' => ProductStatus::Hidden]);
        $this->product(['slug' => 'deleted-product'])->delete();
        $this->product(['slug' => 'in-inactive-category'], Category::factory()->inactive()->create(['slug' => 'inactive-cat']));

        $this->get('/sitemap.xml')
            ->assertOk()
            ->assertHeader('Content-Type', 'application/xml; charset=UTF-8')
            ->assertSee(route('home'), false)
            ->assertSee(route('offers'), false)
            ->assertSee($visible->url(), false)
            ->assertSee($visible->category->url(), false)
            ->assertSee($unavailable->url(), false)
            ->assertDontSee('hidden-product')
            ->assertDontSee('deleted-product')
            ->assertDontSee('in-inactive-category')
            ->assertDontSee('inactive-cat');
    }

    public function test_robots_txt(): void
    {
        $this->get('/robots.txt')
            ->assertOk()
            ->assertSee('Disallow: /admin')
            ->assertSee('Disallow: /cart')
            ->assertSee('Sitemap: '.route('sitemap'));
    }

    public function test_private_pages_are_noindex(): void
    {
        $this->get('/cart')->assertSee('noindex', false);
        $this->get('/search?q=x')->assertSee('noindex', false);
        $this->get('/')->assertDontSee('noindex', false);
    }
}
