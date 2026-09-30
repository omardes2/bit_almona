<?php

namespace Tests\Feature\Checkout;

use App\Enums\ProductStatus;
use App\Models\Address;
use App\Models\Category;
use App\Models\DeliveryZone;
use App\Models\Product;
use App\Models\User;
use App\Services\Cart\CartService;

trait CheckoutTestHelpers
{
    protected function customer(): User
    {
        return User::factory()->create(['name' => 'زبون', 'phone' => '0599'.random_int(100000, 999999)]);
    }

    protected function addressFor(User $user, array $attributes = []): Address
    {
        return Address::create(array_merge([
            'user_id' => $user->id,
            'label' => 'المنزل',
            'recipient_name' => 'مستلم الطلب',
            'recipient_phone' => '0599111222',
            'address_line' => 'شارع السلام، بناية 5',
            'city' => 'الخليل',
            'area' => 'عين سارة',
            'notes' => 'بجانب المسجد',
            'is_default' => true,
        ], $attributes));
    }

    protected function zone(array $attributes = []): DeliveryZone
    {
        return DeliveryZone::factory()->create(array_merge(['delivery_fee' => 10, 'min_order_amount' => null, 'is_active' => true], $attributes));
    }

    protected function product(array $attributes = []): Product
    {
        return Product::factory()->for(Category::factory())->create(array_merge([
            'name' => 'منتج',
            'original_price' => 20,
            'sale_price' => 20,
            'stock_quantity' => 10,
            'min_order_quantity' => 1,
            'quantity_step' => 1,
            'status' => ProductStatus::Available,
        ], $attributes));
    }

    /** Add to the cart of the currently authenticated user (or guest). */
    protected function addToCart(Product $product, mixed $quantity = null): void
    {
        app(CartService::class)->add($product->id, $quantity);
    }
}
