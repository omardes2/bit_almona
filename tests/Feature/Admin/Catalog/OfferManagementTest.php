<?php

namespace Tests\Feature\Admin\Catalog;

use App\Enums\ScheduleStatus;
use App\Livewire\Admin\Offers\OfferForm;
use App\Livewire\Admin\Offers\OfferIndex;
use App\Models\Offer;
use App\Models\Product;
use App\Models\User;
use App\Services\Pricing\ProductPriceResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class OfferManagementTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        $this->admin = User::factory()->admin()->create();
        $this->product = Product::factory()->create(['name' => 'جبنة الخيرات', 'original_price' => 18, 'sale_price' => 18]);
    }

    private function form(array $overrides = [])
    {
        $component = Livewire::actingAs($this->admin)->test(OfferForm::class)
            ->call('selectProduct', $this->product->id);

        foreach (array_merge(['offer_price' => '10'], $overrides) as $field => $value) {
            $component->set($field, $value);
        }

        return $component;
    }

    private function price(): float
    {
        return app(ProductPriceResolver::class)->resolve($this->product->fresh())->finalPrice;
    }

    public function test_admin_can_create_a_valid_offer(): void
    {
        $this->form(['title' => 'عرض الأسبوع'])
            ->assertSet('original_price', '18')
            ->assertSet('discountPercentage', 44)
            ->set('image', UploadedFile::fake()->image('offer.jpg', 600, 600))
            ->call('save')
            ->assertHasNoErrors()
            ->assertRedirect(route('admin.offers.index'));

        $offer = Offer::first();
        $this->assertSame('10.00', $offer->offer_price);
        $this->assertSame('18.00', $offer->original_price);
        $this->assertSame(44, $offer->discountPercentage());
        $this->assertStringStartsWith('offers/', $offer->image);
        $this->assertSame(ScheduleStatus::Running, $offer->scheduleStatus());
        $this->assertSame(10.0, $this->price());
    }

    public function test_offer_price_must_be_lower_than_original_and_current_sale_price(): void
    {
        $this->form(['offer_price' => '18'])->call('save')->assertHasErrors(['offer_price' => 'lt']);
        $this->form(['offer_price' => '25'])->call('save')->assertHasErrors('offer_price');

        // Lower than the (edited) original price but not lower than the product's sale price => would never apply.
        $this->form(['original_price' => '30', 'offer_price' => '20'])->call('save')->assertHasErrors('offer_price');

        $this->assertSame(0, Offer::count());
    }

    public function test_dates_must_be_logical(): void
    {
        $this->form(['starts_at' => now()->addDays(5)->format('Y-m-d\TH:i'), 'ends_at' => now()->addDay()->format('Y-m-d\TH:i')])
            ->call('save')
            ->assertHasErrors(['ends_at' => 'after']);

        $this->form(['starts_at' => '', 'ends_at' => now()->subDay()->format('Y-m-d\TH:i')])
            ->call('save')
            ->assertHasErrors('ends_at');

        $this->form()->set('product_id', null)->call('save')->assertHasErrors(['product_id' => 'required']);

        $this->assertSame(0, Offer::count());
    }

    public function test_overlapping_active_offers_for_the_same_product_are_rejected(): void
    {
        Offer::factory()->for($this->product)->create(['offer_price' => 12, 'ends_at' => now()->addWeek()]);

        $this->form()->call('save')->assertHasErrors('ends_at');

        // Allowed when it starts after the existing one ends, or when it is inactive.
        $this->form([
            'starts_at' => now()->addWeeks(2)->format('Y-m-d\TH:i'),
            'ends_at' => now()->addWeeks(3)->format('Y-m-d\TH:i'),
        ])->call('save')->assertHasNoErrors();

        $this->form(['is_active' => false])->call('save')->assertHasNoErrors();
    }

    public function test_expired_offers_do_not_apply(): void
    {
        $offer = Offer::factory()->for($this->product)->expired()->create(['offer_price' => 10]);

        $this->assertSame(18.0, $this->price());
        $this->assertSame(ScheduleStatus::Expired, $offer->scheduleStatus());
    }

    public function test_future_offers_do_not_apply_yet(): void
    {
        $offer = Offer::factory()->for($this->product)->upcoming()->create(['offer_price' => 10]);

        $this->assertSame(18.0, $this->price());
        $this->assertSame(ScheduleStatus::Scheduled, $offer->scheduleStatus());
        $this->assertSame('يبدأ لاحقًا', $offer->scheduleStatus()->offerLabel());

        $this->travel(2)->days();
        $this->assertSame(10.0, $this->price());
    }

    public function test_active_offers_apply_the_correct_price_and_disabled_ones_do_not(): void
    {
        $offer = Offer::factory()->for($this->product)->create(['offer_price' => 12.5]);
        $this->assertSame(12.5, $this->price());

        Livewire::actingAs($this->admin)->test(OfferIndex::class)->call('toggleActive', $offer->id);

        $this->assertFalse($offer->fresh()->is_active);
        $this->assertSame(ScheduleStatus::Disabled, $offer->fresh()->scheduleStatus());
        $this->assertSame(18.0, $this->price());
    }

    public function test_status_badges_use_dates_not_only_is_active(): void
    {
        // Still flagged active in the database, but its end date has passed.
        $expired = Offer::factory()->for($this->product)->create(['ends_at' => now()->subMinute(), 'is_active' => true]);
        $running = Offer::factory()->create();
        $scheduled = Offer::factory()->upcoming()->create();
        $disabled = Offer::factory()->create(['is_active' => false]);

        $this->assertSame(ScheduleStatus::Expired, $expired->scheduleStatus());

        foreach ([[ScheduleStatus::Running, $running], [ScheduleStatus::Scheduled, $scheduled], [ScheduleStatus::Disabled, $disabled], [ScheduleStatus::Expired, $expired]] as [$status, $offer]) {
            $this->assertSame($status, $offer->scheduleStatus());
            $this->assertSame([$offer->id], Offer::withScheduleStatus($status)->pluck('id')->all(), $status->value);
        }

        Livewire::actingAs($this->admin)->test(OfferIndex::class)
            ->assertSee('فعال الآن')->assertSee('يبدأ لاحقًا')->assertSee('منتهي')->assertSee('متوقف')
            ->set('status', 'expired')
            ->assertViewHas('offers', fn ($offers) => $offers->pluck('id')->all() === [$expired->id]);
    }

    public function test_admin_can_edit_and_delete_an_offer(): void
    {
        $offer = Offer::factory()->for($this->product)->create(['original_price' => 18, 'offer_price' => 12]);

        Livewire::actingAs($this->admin)->test(OfferForm::class, ['offer' => $offer])
            ->assertSet('offer_price', '12')
            ->set('offer_price', '11.5')
            ->set('sort_order', 7)
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame('11.50', $offer->fresh()->offer_price);
        $this->assertSame(7, $offer->fresh()->sort_order);

        Livewire::actingAs($this->admin)->test(OfferIndex::class)->call('delete', $offer->id);
        $this->assertModelMissing($offer);
    }

    public function test_offers_can_be_sorted(): void
    {
        $a = Offer::factory()->create(['sort_order' => 2, 'starts_at' => now()->subDays(3), 'ends_at' => now()->addDays(9)]);
        $b = Offer::factory()->create(['sort_order' => 1, 'starts_at' => now()->subDay(), 'ends_at' => now()->addDays(2)]);

        $ids = fn ($c) => $c->viewData('offers')->pluck('id')->all();
        $component = Livewire::actingAs($this->admin)->test(OfferIndex::class);

        $this->assertSame([$b->id, $a->id], $ids($component));
        $this->assertSame([$b->id, $a->id], $ids($component->set('sort', 'starts_at')));
        $this->assertSame([$b->id, $a->id], $ids($component->set('sort', 'ends_at')));
    }
}
