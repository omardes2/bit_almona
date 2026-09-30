<?php

namespace Tests\Feature\Account;

use App\Livewire\Account\Addresses;
use App\Models\Address;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Feature\Checkout\CheckoutTestHelpers;
use Tests\TestCase;

class AddressesTest extends TestCase
{
    use CheckoutTestHelpers, RefreshDatabase;

    public function test_customer_can_create_an_address_and_the_first_one_becomes_default(): void
    {
        $customer = $this->customer();

        Livewire::actingAs($customer)->test(Addresses::class)
            ->call('create')
            ->assertSet('form.recipient_name', $customer->name)
            ->set('form.label', 'العمل')
            ->set('form.recipient_phone', '+970 599 555 666')
            ->set('form.address_line', 'شارع عين سارة، عمارة النور')
            ->set('form.city', 'الخليل')
            ->set('form.area', 'عين سارة')
            ->call('save')
            ->assertHasNoErrors();

        $address = $customer->addresses()->first();
        $this->assertSame('0599555666', $address->recipient_phone);
        $this->assertSame('العمل', $address->label);
        $this->assertTrue($address->is_default);

        $this->actingAs($customer)->get('/account/addresses')->assertOk()->assertSee('عمارة النور');
    }

    public function test_address_validation(): void
    {
        Livewire::actingAs($this->customer())->test(Addresses::class)
            ->call('create')
            ->set('form.recipient_phone', '123')
            ->set('form.address_line', '')
            ->set('form.area', '')
            ->set('form.notes', str_repeat('x', 501))
            ->call('save')
            ->assertHasErrors(['form.recipient_phone', 'form.address_line', 'form.area', 'form.notes']);
    }

    public function test_default_address_can_be_changed_and_is_unique(): void
    {
        $customer = $this->customer();
        $first = $this->addressFor($customer);
        $second = $this->addressFor($customer, ['is_default' => false, 'label' => 'العمل']);

        Livewire::actingAs($customer)->test(Addresses::class)->call('makeDefault', $second->id);

        $this->assertFalse($first->fresh()->is_default);
        $this->assertTrue($second->fresh()->is_default);

        // Deleting the default promotes another address.
        Livewire::actingAs($customer)->test(Addresses::class)->call('delete', $second->id);
        $this->assertTrue($first->fresh()->is_default);
    }

    public function test_customer_cannot_view_edit_or_delete_another_customers_address(): void
    {
        $owner = $this->customer();
        $address = $this->addressFor($owner, ['address_line' => 'عنوان سري للمالك']);
        $intruder = $this->customer();

        Livewire::actingAs($intruder)->test(Addresses::class)->call('edit', $address->id)->assertForbidden();
        Livewire::actingAs($intruder)->test(Addresses::class)->call('delete', $address->id)->assertForbidden();
        Livewire::actingAs($intruder)->test(Addresses::class)->call('makeDefault', $address->id)->assertForbidden();

        $this->actingAs($intruder)->get('/account/addresses')->assertDontSee('عنوان سري للمالك');
        $this->assertModelExists($address);
        $this->assertSame('عنوان سري للمالك', $address->fresh()->address_line);
    }

    public function test_guests_and_admins_cannot_open_the_address_book(): void
    {
        $this->get('/account/addresses')->assertRedirect(route('login'));
        $this->actingAs(User::factory()->admin()->create())->get('/account/addresses')->assertRedirect(route('admin.dashboard'));
    }
}
