<?php

namespace Tests\Feature\Catalog;

use App\Enums\ProductStatus;
use App\Models\Offer;
use App\Models\Product;
use App\Services\Pricing\ProductPriceResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductPricingTest extends TestCase
{
    use RefreshDatabase;

    private function resolve(Product $product)
    {
        return app(ProductPriceResolver::class)->resolve($product->fresh());
    }

    public function test_without_an_offer_the_sale_price_is_used(): void
    {
        $product = Product::factory()->create(['original_price' => 20, 'sale_price' => 17]);

        $price = $this->resolve($product);

        $this->assertSame(20.0, $price->originalPrice);
        $this->assertSame(17.0, $price->finalPrice);
        $this->assertNull($price->offerId);
    }

    public function test_a_running_offer_overrides_the_sale_price(): void
    {
        $product = Product::factory()->create(['original_price' => 18, 'sale_price' => 18]);
        $offer = Offer::factory()->for($product)->create(['original_price' => 18, 'offer_price' => 10]);

        $price = $this->resolve($product);

        $this->assertSame(10.0, $price->finalPrice);
        $this->assertSame($offer->id, $price->offerId);
        $this->assertSame(44, $offer->discountPercentage());
    }

    public function test_expired_upcoming_and_inactive_offers_are_ignored(): void
    {
        $product = Product::factory()->create(['original_price' => 18, 'sale_price' => 18]);
        Offer::factory()->for($product)->expired()->create(['offer_price' => 5]);
        Offer::factory()->for($product)->upcoming()->create(['offer_price' => 6]);
        Offer::factory()->for($product)->create(['offer_price' => 7, 'is_active' => false]);

        $this->assertSame(18.0, $this->resolve($product)->finalPrice);
    }

    public function test_an_offer_stops_applying_the_moment_it_ends(): void
    {
        $product = Product::factory()->create(['original_price' => 18, 'sale_price' => 18]);
        Offer::factory()->for($product)->create(['offer_price' => 10, 'ends_at' => now()->addHour()]);

        $this->assertSame(10.0, $this->resolve($product)->finalPrice);

        $this->travel(61)->minutes();

        $this->assertSame(18.0, $this->resolve($product)->finalPrice);
    }

    public function test_expired_offers_are_deactivated_by_the_scheduled_command(): void
    {
        $expired = Offer::factory()->expired()->create();
        $running = Offer::factory()->create();

        $this->artisan('offers:deactivate-expired')->assertSuccessful();

        $this->assertFalse($expired->fresh()->is_active);
        $this->assertTrue($running->fresh()->is_active);
    }

    public function test_hidden_products_are_not_visible_and_unavailable_ones_are_not_purchasable(): void
    {
        $available = Product::factory()->create();
        $unavailable = Product::factory()->status(ProductStatus::Unavailable)->create();
        $hidden = Product::factory()->status(ProductStatus::Hidden)->create();

        $this->assertEqualsCanonicalizing([$available->id, $unavailable->id], Product::visible()->pluck('id')->all());
        $this->assertTrue($available->isPurchasable());
        $this->assertFalse($unavailable->isPurchasable());
        $this->assertFalse($hidden->isPurchasable());
    }
}
