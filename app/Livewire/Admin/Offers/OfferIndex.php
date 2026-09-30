<?php

namespace App\Livewire\Admin\Offers;

use App\Enums\ScheduleStatus;
use App\Livewire\Admin\Concerns\AuthorizesCatalog;
use App\Livewire\Admin\Concerns\ReordersRecords;
use App\Livewire\Admin\Concerns\Toasts;
use App\Models\Offer;
use App\Models\Product;
use App\Services\Media\ImageStorage;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.admin')]
#[Title('العروض')]
class OfferIndex extends Component
{
    use AuthorizesCatalog, ReordersRecords, Toasts, WithPagination;

    public const SORTS = [
        'sort_order' => 'حسب الترتيب',
        'starts_at' => 'تاريخ البداية',
        'ends_at' => 'تاريخ النهاية (الأقرب أولًا)',
    ];

    #[Url(except: '')]
    public string $status = '';

    #[Url(except: '')]
    public string $search = '';

    #[Url(except: 'sort_order')]
    public string $sort = 'sort_order';

    public function updating(string $property): void
    {
        if (in_array($property, ['status', 'search', 'sort'], true)) {
            $this->resetPage();
        }
    }

    public function toggleActive(int $id): void
    {
        $offer = Offer::findOrFail($id);

        if (! $offer->is_active && $offer->product?->trashed()) {
            $this->toast('لا يمكن تفعيل عرض لمنتج محذوف.', 'error');

            return;
        }

        $offer->update(['is_active' => ! $offer->is_active]);

        $this->toast($offer->is_active ? 'تم تفعيل العرض.' : 'تم إيقاف العرض.');
    }

    public function move(int $id, int $direction): void
    {
        $this->moveInOrder(Offer::query(), Offer::findOrFail($id), $direction > 0 ? 1 : -1);
    }

    public function delete(int $id, ImageStorage $images): void
    {
        $offer = Offer::findOrFail($id);
        $image = $offer->image;

        // order_items.offer_id is nullOnDelete: old orders keep their price snapshot.
        $offer->delete();
        $images->delete($image);

        $this->toast('تم حذف العرض.');
    }

    public function render()
    {
        $query = Offer::query()
            ->with('product:id,name,sku,main_image,sale_price,deleted_at')
            ->when(ScheduleStatus::tryFrom($this->status), fn ($q, $status) => $q->withScheduleStatus($status))
            ->when($this->search !== '', fn ($q) => $q->whereIn('product_id', Product::withTrashed()->search($this->search)->select('id')));

        match ($this->sort) {
            'starts_at' => $query->orderByDesc('starts_at')->orderByDesc('id'),
            'ends_at' => $query->orderByRaw('ends_at is null')->orderBy('ends_at')->orderBy('id'),
            default => $query->orderBy('sort_order')->orderByDesc('id'),
        };

        return view('livewire.admin.offers.index', [
            'offers' => $query->paginate(config('store.admin_per_page')),
            'statuses' => ScheduleStatus::cases(),
            'hasAnyOffer' => Offer::query()->exists(),
        ]);
    }
}
