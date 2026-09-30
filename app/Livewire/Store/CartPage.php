<?php

namespace App\Livewire\Store;

use App\Services\Cart\CartException;
use App\Services\Cart\CartService;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app', ['noindex' => true])]
#[Title('سلة المشتريات')]
class CartPage extends Component
{
    /** @var list<string> */
    public array $notices = [];

    public function mount(CartService $cart): void
    {
        // Adjust quantities to the current stock and report price changes.
        $this->notices = $cart->refresh();
    }

    public function increment(CartService $cart, int $itemId): void
    {
        $this->run(fn () => $cart->increment($itemId), $cart);
    }

    public function decrement(CartService $cart, int $itemId): void
    {
        $this->run(fn () => $cart->decrement($itemId), $cart);
    }

    public function setQuantity(CartService $cart, int $itemId, mixed $quantity): void
    {
        $this->run(fn () => $cart->updateQuantity($itemId, $quantity), $cart);
    }

    public function remove(CartService $cart, int $itemId): void
    {
        $this->run(fn () => $cart->remove($itemId), $cart, 'تم حذف المنتج من السلة.');
    }

    private function run(callable $action, CartService $cart, ?string $success = null): void
    {
        try {
            $action();
        } catch (CartException $e) {
            $this->dispatch('toast', message: $e->getMessage(), type: 'error');
        }

        if ($success) {
            $this->dispatch('toast', message: $success);
        }

        $this->dispatch('cart-updated', count: $cart->count());
    }

    #[On('drawer-cart-changed')]
    public function refreshSummary(): void
    {
        // Re-render when the cart drawer changes the cart.
    }

    public function render(CartService $cart)
    {
        return view('livewire.store.cart-page', ['summary' => $cart->summary()]);
    }
}
