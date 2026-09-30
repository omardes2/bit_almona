<?php

namespace App\Livewire\Admin\Customers;

use App\Enums\AccountStatus;
use App\Enums\OrderStatus;
use App\Models\User;
use App\Support\PhoneNumber;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.admin')]
#[Title('العملاء')]
class CustomerIndex extends Component
{
    use WithPagination;

    #[Url(except: '')]
    public string $search = '';

    #[Url(except: '')]
    public string $status = '';

    #[Url(except: 'latest')]
    public string $sort = 'latest';

    public function boot(): void
    {
        Gate::authorize('manage-customers');
    }

    public function updating(string $property): void
    {
        if (in_array($property, ['search', 'status', 'sort'], true)) {
            $this->resetPage();
        }
    }

    /**
     * Customers with their order stats in ONE query (aggregate subqueries).
     * "Purchases" = delivered orders only (realised revenue).
     */
    public static function query(string $search = '', string $status = '', string $sort = 'latest'): Builder
    {
        $term = trim($search);
        $phone = PhoneNumber::normalize($term);

        return User::query()
            ->customers()
            ->with('customer:id,user_id,whatsapp')
            ->withCount('orders')
            ->withSum(['orders as purchases_total' => fn ($q) => $q->where('status', OrderStatus::Delivered)], 'total')
            ->withMax('orders as last_order_at', 'created_at')
            ->when($term !== '', fn ($q) => $q->where(function ($q) use ($term, $phone) {
                $q->where('name', 'like', '%'.$term.'%');

                if (strlen($phone) >= 3 && ctype_digit($phone)) {
                    $q->orWhere('phone', 'like', '%'.$phone.'%')
                        ->orWhereHas('customer', fn ($c) => $c->where('whatsapp', 'like', '%'.$phone.'%'));
                }
            }))
            ->when(AccountStatus::tryFrom($status), fn ($q, $s) => $q->where('status', $s))
            ->when($sort === 'top', fn ($q) => $q->orderByDesc('purchases_total')->orderByDesc('id'), fn ($q) => $q->latest('id'));
    }

    public function render()
    {
        return view('livewire.admin.customers.index', [
            'customers' => self::query($this->search, $this->status, $this->sort)->paginate(20),
            'total' => User::customers()->count(),
        ]);
    }
}
