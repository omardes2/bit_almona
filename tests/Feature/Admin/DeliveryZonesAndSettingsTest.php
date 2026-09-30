<?php

namespace Tests\Feature\Admin;

use App\Enums\AdminRole;
use App\Livewire\Admin\DeliveryZones\ZoneForm;
use App\Livewire\Admin\DeliveryZones\ZoneIndex;
use App\Livewire\Admin\Settings\StoreSettingsForm;
use App\Models\AuditLog;
use App\Models\DeliveryZone;
use App\Models\Order;
use App\Models\StoreSetting;
use App\Models\User;
use App\Services\Media\ImageStorage;
use Database\Seeders\StoreSettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class DeliveryZonesAndSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_edit_toggle_and_reorder_zones(): void
    {
        $admin = User::factory()->admin(AdminRole::Manager)->create();

        Livewire::actingAs($admin)->test(ZoneForm::class)
            ->set('name', 'عين سارة')
            ->set('delivery_fee', '10')
            ->set('min_order_amount', '50')
            ->call('save')
            ->assertHasNoErrors()
            ->assertRedirect(route('admin.delivery-zones.index'));

        $zone = DeliveryZone::firstWhere('name', 'عين سارة');
        $this->assertSame('10.00', $zone->delivery_fee);
        $this->assertSame('50.00', $zone->min_order_amount);

        Livewire::actingAs($admin)->test(ZoneForm::class, ['zone' => $zone])
            ->set('min_order_amount', '')
            ->call('save')
            ->assertHasNoErrors();
        $this->assertNull($zone->fresh()->min_order_amount);

        $other = DeliveryZone::factory()->create(['sort_order' => 0]);
        Livewire::actingAs($admin)->test(ZoneIndex::class)
            ->call('toggleActive', $zone->id)
            ->call('move', $zone->id, -1);

        $this->assertFalse($zone->fresh()->is_active);
        $this->assertSame([$zone->id, $other->id], DeliveryZone::ordered()->pluck('id')->all());
        $this->assertTrue(AuditLog::where('auditable_type', 'delivery_zone')->where('event', 'created')->exists());
    }

    public function test_zone_validation(): void
    {
        DeliveryZone::factory()->create(['name' => 'مكرر']);

        Livewire::actingAs(User::factory()->admin()->create())->test(ZoneForm::class)
            ->set('name', 'مكرر')
            ->set('delivery_fee', '-5')
            ->set('min_order_amount', 'abc')
            ->call('save')
            ->assertHasErrors(['name' => 'unique', 'delivery_fee', 'min_order_amount']);
    }

    public function test_zones_used_by_orders_are_not_deleted(): void
    {
        $admin = User::factory()->admin()->create();
        $used = DeliveryZone::factory()->create();
        Order::factory()->create(['delivery_zone_id' => $used->id]);
        $unused = DeliveryZone::factory()->create();

        Livewire::actingAs($admin)->test(ZoneIndex::class)
            ->call('delete', $used->id)->assertDispatched('toast', type: 'error')
            ->call('delete', $unused->id);

        $this->assertModelExists($used);
        $this->assertModelMissing($unused);
    }

    public function test_customers_and_staff_cannot_manage_zones_or_settings(): void
    {
        $customer = User::factory()->create();
        $staff = User::factory()->admin(AdminRole::Staff)->create();

        $urls = ['/admin/delivery-zones', '/admin/delivery-zones/create', '/admin/settings'];

        foreach ($urls as $url) {
            $this->get($url)->assertRedirect(route('admin.login'));
        }

        foreach ($urls as $url) {
            $this->actingAs($customer)->get($url)->assertForbidden();
            $this->actingAs($staff)->get($url)->assertForbidden();
        }

        $this->actingAs(User::factory()->admin(AdminRole::Manager)->create())->get('/admin/settings')->assertForbidden();
        $this->actingAs(User::factory()->admin(AdminRole::SuperAdmin)->create())->get('/admin/settings')->assertOk();
    }

    public function test_settings_are_saved_and_shown_in_the_store(): void
    {
        Storage::fake('public');
        $this->seed(StoreSettingsSeeder::class);
        $admin = User::factory()->admin(AdminRole::SuperAdmin)->create();

        Livewire::actingAs($admin)->test(StoreSettingsForm::class)
            ->set('store_name', 'بيت المونة - الخليل')
            ->set('store_phone', '02-2221234')
            ->set('store_whatsapp', '+970 599 123 456')
            ->set('store_address', 'الخليل، دوار ابن رشد')
            ->set('min_order_amount', '30')
            ->set('working_hours', 'يوميًا 8 صباحًا – 11 مساءً')
            ->set('logo', UploadedFile::fake()->image('logo.png', 300, 300))
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame('بيت المونة - الخليل', StoreSetting::get('store_name'));
        $this->assertSame('0599123456', StoreSetting::get('store_whatsapp'));
        $this->assertSame(30.0, StoreSetting::get('min_order_amount'));
        $logo = StoreSetting::get('store_logo');
        $this->assertStringStartsWith('branding/', $logo);
        Storage::disk('public')->assertExists($logo);

        $this->get('/')
            ->assertSee('بيت المونة - الخليل')
            ->assertSee(ImageStorage::url($logo), false)
            ->assertSee('02-2221234');

        $this->assertTrue(AuditLog::where('auditable_type', 'store_setting')->where('event', 'updated')->exists());
    }

    public function test_settings_validation(): void
    {
        Livewire::actingAs(User::factory()->admin(AdminRole::SuperAdmin)->create())->test(StoreSettingsForm::class)
            ->set('store_name', '')
            ->set('store_whatsapp', '123')
            ->set('min_order_amount', '-1')
            ->set('currency_code', 'EUR')
            ->set('logo', UploadedFile::fake()->create('logo.svg', 5, 'image/svg+xml'))
            ->call('save')
            ->assertHasErrors(['store_name', 'store_whatsapp', 'min_order_amount', 'currency_code', 'logo']);
    }

    public function test_transparent_png_logo_keeps_its_alpha_channel(): void
    {
        Storage::fake('public');
        $image = imagecreatetruecolor(200, 200);
        imagesavealpha($image, true);
        imagefill($image, 0, 0, imagecolorallocatealpha($image, 0, 0, 0, 127));
        $path = tempnam(sys_get_temp_dir(), 'logo').'.png';
        imagepng($image, $path);

        $stored = app(ImageStorage::class)->store(new UploadedFile($path, 'logo.png', 'image/png', null, true), 'branding');

        $result = imagecreatefromstring(Storage::disk('public')->get($stored));
        $alpha = (imagecolorat($result, 10, 10) >> 24) & 0x7F;
        $this->assertSame(127, $alpha, 'the transparent pixel stays transparent');
    }
}
