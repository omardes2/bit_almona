<?php

namespace Tests\Feature\Admin\Catalog;

use App\Enums\ScheduleStatus;
use App\Livewire\Admin\Banners\BannerForm;
use App\Livewire\Admin\Banners\BannerIndex;
use App\Models\Banner;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class BannerManagementTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        $this->admin = User::factory()->admin()->create();
    }

    public function test_admin_can_create_a_banner(): void
    {
        Livewire::actingAs($this->admin)->test(BannerForm::class)
            ->set('title', 'عروض رمضان')
            ->set('link_url', 'https://example.com/ramadan')
            ->set('image', UploadedFile::fake()->image('banner.jpg', 1600, 700))
            ->call('save')
            ->assertHasNoErrors()
            ->assertRedirect(route('admin.banners.index'));

        $banner = Banner::first();
        $this->assertSame('عروض رمضان', $banner->title);
        $this->assertStringStartsWith('banners/', $banner->image);
        Storage::disk('public')->assertExists($banner->image);
        $this->assertSame(ScheduleStatus::Running, $banner->scheduleStatus());
    }

    public function test_image_is_required_on_create_but_not_on_edit(): void
    {
        Livewire::actingAs($this->admin)->test(BannerForm::class)
            ->set('title', 'بدون صورة')
            ->call('save')
            ->assertHasErrors(['image' => 'required']);

        $banner = Banner::factory()->create(['image' => 'banners/existing.jpg']);

        Livewire::actingAs($this->admin)->test(BannerForm::class, ['banner' => $banner])
            ->set('title', 'عنوان جديد')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame('عنوان جديد', $banner->fresh()->title);
        $this->assertSame('banners/existing.jpg', $banner->fresh()->image);
    }

    public function test_dates_and_links_are_validated(): void
    {
        Livewire::actingAs($this->admin)->test(BannerForm::class)
            ->set('image', UploadedFile::fake()->image('banner.jpg', 1600, 700))
            ->set('starts_at', '2026-10-10T10:00')
            ->set('ends_at', '2026-10-01T10:00')
            ->call('save')
            ->assertHasErrors(['ends_at' => 'after']);

        foreach (['javascript:alert(1)', '//evil.example', 'not a url', 'ftp://example.com'] as $bad) {
            Livewire::actingAs($this->admin)->test(BannerForm::class)
                ->set('image', UploadedFile::fake()->image('banner.jpg', 1600, 700))
                ->set('link_url', $bad)
                ->call('save')
                ->assertHasErrors('link_url');
        }

        Livewire::actingAs($this->admin)->test(BannerForm::class)
            ->set('image', UploadedFile::fake()->image('banner.jpg', 1600, 700))
            ->set('link_url', '/offers')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame(1, Banner::count());
    }

    public function test_unsafe_files_are_rejected(): void
    {
        Livewire::actingAs($this->admin)->test(BannerForm::class)
            ->set('image', UploadedFile::fake()->create('banner.gif', 50, 'image/gif'))
            ->call('save')
            ->assertHasErrors('image');

        Livewire::actingAs($this->admin)->test(BannerForm::class)
            ->set('image', UploadedFile::fake()->create('banner.exe', 50, 'application/octet-stream'))
            ->call('save')
            ->assertHasErrors('image');

        $this->assertSame(0, Banner::count());
    }

    public function test_banner_can_be_disabled_and_reordered_and_deleted(): void
    {
        $a = Banner::factory()->create(['sort_order' => 1]);
        $b = Banner::factory()->create(['sort_order' => 2]);

        $component = Livewire::actingAs($this->admin)->test(BannerIndex::class)
            ->call('toggleActive', $a->id)
            ->call('move', $b->id, -1);

        $this->assertSame(ScheduleStatus::Disabled, $a->fresh()->scheduleStatus());
        $this->assertSame(0, Banner::running()->whereKey($a->id)->count());
        $this->assertSame([$b->id, $a->id], Banner::ordered()->pluck('id')->all());

        $component->call('delete', $a->id);
        $this->assertModelMissing($a);
    }

    public function test_expired_and_scheduled_banners_are_reported_correctly(): void
    {
        $expired = Banner::factory()->expired()->create();
        $scheduled = Banner::factory()->scheduled()->create();

        $this->assertSame(ScheduleStatus::Expired, $expired->scheduleStatus());
        $this->assertSame(ScheduleStatus::Scheduled, $scheduled->scheduleStatus());
        $this->assertSame(0, Banner::running()->count());

        Livewire::actingAs($this->admin)->test(BannerIndex::class)
            ->assertSee('منتهي')
            ->assertSee('مجدول');
    }
}
