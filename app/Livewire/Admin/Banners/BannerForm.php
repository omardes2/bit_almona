<?php

namespace App\Livewire\Admin\Banners;

use App\Livewire\Admin\Concerns\AuthorizesCatalog;
use App\Livewire\Admin\Concerns\Toasts;
use App\Models\Banner;
use App\Rules\SafeLink;
use App\Services\Media\ImageStorage;
use App\Support\ImageRules;
use Carbon\CarbonImmutable;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

#[Layout('layouts.admin')]
class BannerForm extends Component
{
    use AuthorizesCatalog, Toasts, WithFileUploads;

    public ?Banner $banner = null;

    public string $title = '';

    public string $description = '';

    public string $link_url = '';

    public string $starts_at = '';

    public string $ends_at = '';

    public int|string $sort_order = 0;

    public bool $is_active = true;

    /** @var TemporaryUploadedFile|null */
    public $image = null;

    public function mount(?Banner $banner = null): void
    {
        if ($banner?->exists) {
            $this->banner = $banner;
            $this->title = (string) $banner->title;
            $this->description = (string) $banner->description;
            $this->link_url = (string) $banner->link_url;
            $this->starts_at = $banner->starts_at?->format('Y-m-d\TH:i') ?? '';
            $this->ends_at = $banner->ends_at?->format('Y-m-d\TH:i') ?? '';
            $this->sort_order = $banner->sort_order;
            $this->is_active = $banner->is_active;

            return;
        }

        $this->sort_order = (int) Banner::max('sort_order') + 1;
    }

    protected function rules(): array
    {
        return [
            'title' => ['nullable', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:500'],
            'link_url' => ['nullable', 'string', 'max:500', new SafeLink],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => array_filter(['nullable', 'date', filled($this->starts_at) ? 'after:starts_at' : null]),
            'sort_order' => ['required', 'integer', 'min:0', 'max:100000'],
            'is_active' => ['boolean'],
            // Required when creating; optional when editing a banner that already has an image.
            'image' => ImageRules::rules(required: ! $this->banner?->image),
        ];
    }

    protected function messages(): array
    {
        return ['ends_at.after' => 'تاريخ النهاية يجب أن يكون بعد تاريخ البداية.'];
    }

    protected function validationAttributes(): array
    {
        return ['title' => 'العنوان', 'link_url' => 'الرابط', 'image' => 'صورة البنر'];
    }

    public function updatedImage(): void
    {
        $this->validateOnly('image');
    }

    public function save(ImageStorage $images)
    {
        $data = $this->validate();

        $banner = $this->banner ?? new Banner;
        $oldImage = $banner->image;

        $banner->fill([
            'title' => $data['title'] ?: null,
            'description' => $data['description'] ?: null,
            'link_url' => $data['link_url'] ?: null,
            'starts_at' => filled($data['starts_at']) ? CarbonImmutable::parse($data['starts_at']) : null,
            'ends_at' => filled($data['ends_at']) ? CarbonImmutable::parse($data['ends_at']) : null,
            'sort_order' => (int) $data['sort_order'],
            'is_active' => $data['is_active'],
        ]);

        if ($this->image) {
            $banner->image = $images->store($this->image, 'banners');
        }

        $banner->save();

        if ($this->image && $oldImage) {
            $images->delete($oldImage);
        }

        $this->flashToast($this->banner ? 'تم حفظ تعديلات البنر.' : 'تم إضافة البنر.');

        return $this->redirectRoute('admin.banners.index', navigate: true);
    }

    public function render()
    {
        return view('livewire.admin.banners.form')->title($this->banner ? 'تعديل بنر' : 'إضافة بنر');
    }
}
