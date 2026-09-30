<?php

namespace App\Livewire\Admin\Banners;

use App\Enums\ScheduleStatus;
use App\Livewire\Admin\Concerns\AuthorizesCatalog;
use App\Livewire\Admin\Concerns\ReordersRecords;
use App\Livewire\Admin\Concerns\Toasts;
use App\Models\Banner;
use App\Services\Media\ImageStorage;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.admin')]
#[Title('البنرات')]
class BannerIndex extends Component
{
    use AuthorizesCatalog, ReordersRecords, Toasts, WithPagination;

    #[Url(except: '')]
    public string $status = '';

    public function updatingStatus(): void
    {
        $this->resetPage();
    }

    public function toggleActive(int $id): void
    {
        $banner = Banner::findOrFail($id);
        $banner->update(['is_active' => ! $banner->is_active]);

        $this->toast($banner->is_active ? 'تم تفعيل البنر.' : 'تم تعطيل البنر.');
    }

    public function move(int $id, int $direction): void
    {
        $this->moveInOrder(Banner::query(), Banner::findOrFail($id), $direction > 0 ? 1 : -1);
    }

    public function delete(int $id, ImageStorage $images): void
    {
        $banner = Banner::findOrFail($id);
        $image = $banner->image;
        $banner->delete();
        $images->delete($image);

        $this->toast('تم حذف البنر.');
    }

    public function render()
    {
        return view('livewire.admin.banners.index', [
            'banners' => Banner::query()
                ->when(ScheduleStatus::tryFrom($this->status), fn ($q, $status) => $q->withScheduleStatus($status))
                ->orderBy('sort_order')
                ->orderByDesc('id')
                ->paginate(config('store.admin_per_page')),
            'statuses' => ScheduleStatus::cases(),
            'hasAnyBanner' => Banner::query()->exists(),
        ]);
    }
}
