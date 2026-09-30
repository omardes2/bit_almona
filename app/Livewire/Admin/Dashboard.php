<?php

namespace App\Livewire\Admin;

use App\Enums\OrderStatus;
use App\Models\Category;
use App\Models\Offer;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Placeholder admin home. The full admin panel is a later phase.
 */
#[Layout('layouts.admin')]
#[Title('لوحة التحكم')]
class Dashboard extends Component
{
    public function render()
    {
        return view('livewire.admin.dashboard', [
            'stats' => [
                'الأقسام' => Category::count(),
                'المنتجات' => Product::count(),
                'العروض الفعالة' => Offer::running()->count(),
                'الزبائن' => User::customers()->count(),
                'طلبات جديدة' => Order::where('status', OrderStatus::New)->count(),
            ],
        ]);
    }
}
