<?php

namespace Tests\Feature\Security;

use App\Enums\ProductStatus;
use App\Livewire\Account\Addresses;
use App\Livewire\Admin\Notifications\NotificationCenter;
use App\Livewire\Store\CartPage;
use App\Livewire\Store\Checkout;
use App\Models\Cart;
use App\Models\Order;
use App\Models\User;
use App\Services\Cart\CartException;
use App\Services\Cart\CartService;
use App\Services\Media\ImageStorage;
use App\Support\ImageRules;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;
use Tests\Feature\Checkout\CheckoutTestHelpers;
use Tests\TestCase;

/**
 * Phase 6 security review: IDOR, malicious uploads and checkout abuse.
 */
class SecurityReviewTest extends TestCase
{
    use CheckoutTestHelpers, RefreshDatabase;

    // ------------------------------------------------------------------
    // IDOR
    // ------------------------------------------------------------------

    public function test_a_customer_cannot_reach_another_customers_records(): void
    {
        $victim = $this->customer();
        $victimAddress = $this->addressFor($victim);
        $zone = $this->zone();
        $this->actingAs($victim);
        $this->addToCart($this->product());
        Livewire::test(Checkout::class)->set('addressId', $victimAddress->id)->set('zoneId', $zone->id)->call('placeOrder');
        $victimOrder = Order::sole();
        $this->addToCart($this->product(['name' => 'في سلة الضحية']), 1);
        $victimItem = Cart::firstWhere('user_id', $victim->id)->items()->first();

        $attacker = $this->customer();
        $this->actingAs($attacker);
        $this->addToCart($this->product(['name' => 'منتج المهاجم']));

        // Orders + confirmation page.
        $this->assertContains($this->get(route('account.orders.show', $victimOrder))->status(), [403, 404]);
        $this->assertContains($this->get(route('order.confirmed', $victimOrder))->status(), [403, 404]);

        // Address book actions with a foreign id.
        Livewire::test(Addresses::class)->call('edit', $victimAddress->id)->assertForbidden();
        Livewire::test(Addresses::class)->call('delete', $victimAddress->id)->assertForbidden();
        $this->assertModelExists($victimAddress);

        // Checkout with the victim's address id: refused, nothing created.
        Livewire::test(Checkout::class)->set('useNewAddress', false)->set('addressId', $victimAddress->id)->set('zoneId', $zone->id)->call('placeOrder')->assertForbidden();
        $this->assertSame(1, Order::count());

        // Cart lines belong to the current cart only.
        Livewire::test(CartPage::class)->call('setQuantity', $victimItem->id, 5);
        Livewire::test(CartPage::class)->call('remove', $victimItem->id);
        $this->assertSame('1.000', $victimItem->fresh()->quantity);
    }

    public function test_admins_only_touch_their_own_notifications(): void
    {
        $adminA = User::factory()->admin()->create();
        $adminB = User::factory()->admin()->create();
        $notification = DatabaseNotification::create([
            'id' => (string) Str::uuid(), 'type' => 'x', 'notifiable_type' => 'user', 'notifiable_id' => $adminB->id,
            'data' => ['title' => 'x'], 'read_at' => null,
        ]);

        Livewire::actingAs($adminA)->test(NotificationCenter::class)->call('markRead', $notification->id)->assertStatus(404);

        $this->assertNull($notification->fresh()->read_at);
    }

    public function test_customers_cannot_open_any_admin_page(): void
    {
        $this->actingAs($this->customer());

        foreach (['/admin', '/admin/orders', '/admin/products', '/admin/customers', '/admin/reports', '/admin/settings', '/admin/system', '/admin/exports/orders'] as $url) {
            $this->get($url)->assertForbidden();
        }
    }

    // ------------------------------------------------------------------
    // Uploads
    // ------------------------------------------------------------------

    private function passes(UploadedFile $file): bool
    {
        return Validator::make(['image' => $file], ['image' => ImageRules::rules(true)])->passes();
    }

    private function gdImage(string $format, int $w = 200, int $h = 200, bool $transparent = false): string
    {
        $image = imagecreatetruecolor($w, $h);
        imagesavealpha($image, true);
        imagealphablending($image, false);
        imagefill($image, 0, 0, $transparent ? imagecolorallocatealpha($image, 0, 0, 0, 127) : imagecolorallocate($image, 200, 50, 50));

        ob_start();
        match ($format) {
            'png' => imagepng($image),
            'webp' => imagewebp($image),
            default => imagejpeg($image),
        };

        return (string) ob_get_clean();
    }

    public function test_php_renamed_as_jpg_is_rejected(): void
    {
        $this->assertFalse($this->passes(UploadedFile::fake()->createWithContent('shell.jpg', '<?php system($_GET["c"]); ?>')));
        $this->assertFalse($this->passes(UploadedFile::fake()->createWithContent('shell.php.jpg', '<?php echo 1; ?>')));
        $this->assertFalse($this->passes(UploadedFile::fake()->createWithContent('shell.php', $this->gdImage('jpg'))), 'wrong extension');
    }

    public function test_code_hidden_inside_a_real_image_is_stripped_by_re_encoding(): void
    {
        Storage::fake('public');
        $polyglot = UploadedFile::fake()->createWithContent('photo.jpg', $this->gdImage('jpg')."\n<?php system('id'); ?>");

        $this->assertTrue($this->passes($polyglot), 'it is a genuine JPEG, so validation accepts it');

        $path = app(ImageStorage::class)->store($polyglot, 'products/1');

        $this->assertMatchesRegularExpression('#^products/1/[0-9a-z]{26}\.jpg$#i', $path, 'random name, extension from MIME');
        $stored = Storage::disk('public')->get($path);
        $this->assertStringNotContainsString('<?php', $stored);
        $this->assertStringNotContainsString('<?php', Storage::disk('public')->get(ImageStorage::thumbnailPath($path)));
    }

    public function test_svg_with_script_is_rejected(): void
    {
        $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="200" height="200"><script>alert(document.cookie)</script></svg>';

        $this->assertFalse($this->passes(UploadedFile::fake()->createWithContent('logo.svg', $svg)));
        $this->assertFalse($this->passes(UploadedFile::fake()->createWithContent('logo.png', $svg)), 'SVG disguised as PNG');
    }

    public function test_huge_images_are_rejected(): void
    {
        $this->assertFalse($this->passes(UploadedFile::fake()->image('big.jpg', 5000, 5000)), 'dimensions');
        $this->assertFalse($this->passes(UploadedFile::fake()->image('heavy.jpg', 800, 800)->size(5000)), 'file size');
        $this->assertFalse($this->passes(UploadedFile::fake()->image('tiny.jpg', 20, 20)), 'too small');
    }

    public function test_invalid_mime_types_are_rejected(): void
    {
        $this->assertFalse($this->passes(UploadedFile::fake()->createWithContent('doc.png', 'plain text, not an image')));
        $this->assertFalse($this->passes(UploadedFile::fake()->create('file.pdf', 10, 'application/pdf')));
        $this->assertFalse($this->passes(UploadedFile::fake()->createWithContent('anim.gif', base64_decode('R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7'))));
    }

    public function test_transparent_png_keeps_its_transparency(): void
    {
        Storage::fake('public');
        $png = UploadedFile::fake()->createWithContent('logo.png', $this->gdImage('png', 300, 300, transparent: true));

        $this->assertTrue($this->passes($png));
        $path = app(ImageStorage::class)->store($png, 'branding');

        $this->assertStringEndsWith('.png', $path);
        $original = imagecreatefromstring(Storage::disk('public')->get($path));
        $thumb = imagecreatefromstring(Storage::disk('public')->get(ImageStorage::thumbnailPath($path)));

        $this->assertSame(127, (imagecolorat($original, 10, 10) >> 24) & 0x7F, 'original stays transparent');
        $this->assertSame(127, (imagecolorat($thumb, 10, 10) >> 24) & 0x7F, 'WebP thumbnail stays transparent');
    }

    public function test_webp_is_accepted(): void
    {
        Storage::fake('public');
        $webp = UploadedFile::fake()->createWithContent('photo.webp', $this->gdImage('webp'));

        $this->assertTrue($this->passes($webp));
        $this->assertStringEndsWith('.webp', app(ImageStorage::class)->store($webp, 'products/2'));
    }

    // ------------------------------------------------------------------
    // Checkout abuse
    // ------------------------------------------------------------------

    public function test_cart_quantity_tampering_is_rejected(): void
    {
        $this->actingAs($this->customer());
        $product = $this->product(['stock_quantity' => 5]);

        foreach ([-1, 0, '0', 'abc', '1e9', 1000000, '1.5', '2;DROP TABLE', [1]] as $quantity) {
            $this->assertThrows(fn () => app(CartService::class)->add($product->id, $quantity), CartException::class);
        }

        $this->assertSame(0, app(CartService::class)->count());
    }

    public function test_unknown_hidden_or_deleted_products_cannot_be_bought(): void
    {
        $this->actingAs($this->customer());
        $hidden = $this->product(['status' => ProductStatus::Hidden]);
        $deleted = $this->product();
        $deleted->delete();

        foreach ([999999, $hidden->id, $deleted->id] as $id) {
            $this->assertThrows(fn () => app(CartService::class)->add($id, 1), CartException::class);
        }
    }

    public function test_zone_fee_and_price_cannot_be_tampered_with(): void
    {
        $customer = $this->customer();
        $address = $this->addressFor($customer);
        $zone = $this->zone(['delivery_fee' => 15]);
        $inactive = $this->zone(['delivery_fee' => 0, 'is_active' => false]);
        $this->actingAs($customer);
        $this->addToCart($this->product(['sale_price' => 20, 'original_price' => 20]), 2);

        // Inactive or unknown zones are refused.
        foreach ([$inactive->id, 999999, 'abc'] as $badZone) {
            Livewire::test(Checkout::class)->set('addressId', $address->id)->set('zoneId', $badZone)->call('placeOrder')->assertHasErrors('zoneId');
        }

        // The shown total is locked: the browser cannot lower it.
        $this->assertThrows(fn () => Livewire::test(Checkout::class)->set('shownTotalCents', 1), CannotUpdateLockedPropertyException::class);
        $this->assertThrows(fn () => Livewire::test(Checkout::class)->set('checkoutToken', 'reused-token'), CannotUpdateLockedPropertyException::class);

        Livewire::test(Checkout::class)->set('addressId', $address->id)->set('zoneId', $zone->id)->call('placeOrder')->assertHasNoErrors();

        $order = Order::sole();
        $this->assertSame('55.00', $order->total, '2×20 + 15, all computed on the server');
        $this->assertSame('15.00', $order->delivery_fee);
    }

    public function test_replaying_the_same_checkout_never_creates_a_second_order(): void
    {
        $customer = $this->customer();
        $address = $this->addressFor($customer);
        $zone = $this->zone();
        $this->actingAs($customer);
        $product = $this->product(['stock_quantity' => 10]);
        $this->addToCart($product, 2);

        $component = Livewire::test(Checkout::class)->set('addressId', $address->id)->set('zoneId', $zone->id);
        $component->call('placeOrder');
        $this->addToCart($product, 2); // new cart content, same page (same token)
        $component->call('placeOrder');

        $this->assertSame(1, Order::count());
        $this->assertSame('8.000', $product->fresh()->stock_quantity);
    }
}
