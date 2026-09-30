<?php

namespace App\Livewire\Account;

use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app', ['noindex' => true])]
#[Title('طلباتي')]
class Orders extends Component
{
    use WithPagination;

    public function render()
    {
        return view('livewire.account.orders', [
            'orders' => Auth::user()->orders()->withCount('items')->latest('id')->paginate(10),
        ]);
    }
}
