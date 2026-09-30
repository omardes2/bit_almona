<?php

namespace App\Livewire\Store;

use App\Services\Cart\CartException;
use App\Services\Cart\CartService;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * One instance lives in the store layout. Product cards and the product
 * page dispatch `add-to-cart` (product id + quantity only); everything
 * else is decided by CartService on the server.
 */
class CartDrawer extends Component
{
    public bool $open = false;

    public ?int $lastAddedItemId = null;

    #[On('add-to-cart')]
    public function add(CartService $cart, int $productId, mixed $quantity = null): void
    {
        $key = 'cart-add:'.request()->ip();

        if (RateLimiter::tooManyAttempts($key, 60)) {
            $this->fail('محاولات كثيرة، يرجى الانتظار قليلًا.');

            return;
        }

        RateLimiter::hit($key, 60);

        try {
            $item = $cart->add($productId, $quantity);
        } catch (CartException $e) {
            $this->fail($e->getMessage());

            return;
        }

        $this->lastAddedItemId = $item->id;
        $this->open = true;
        $this->dispatch('cart-updated', count: $cart->count());
        $this->dispatch('drawer-cart-changed');
    }

    public function remove(CartService $cart, int $itemId): void
    {
        try {
            $cart->remove($itemId);
        } catch (CartException) {
            // Already gone: nothing to do.
        }

        $this->dispatch('cart-updated', count: $cart->count());
        $this->dispatch('drawer-cart-changed');
    }

    public function close(): void
    {
        $this->open = false;
    }

    private function fail(string $message): void
    {
        $this->dispatch('toast', message: $message, type: 'error');
        $this->dispatch('cart-add-failed');
    }

    public function render(CartService $cart)
    {
        // The summary is only built while the drawer is open.
        return view('livewire.store.cart-drawer', [
            'summary' => $this->open ? $cart->summary() : null,
        ]);
    }
}
