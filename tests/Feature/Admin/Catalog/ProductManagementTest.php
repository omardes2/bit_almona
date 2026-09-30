<?php

namespace Tests\Feature\Admin\Catalog;

use App\Enums\ProductStatus;
use App\Enums\SaleUnit;
use App\Livewire\Admin\Products\ProductForm;
use App\Livewire\Admin\Products\ProductIndex;
use App\Models\Category;
use App\Models\Offer;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;
use App\Services\Media\ImageStorage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class ProductManagementTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Category $category;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        $this->admin = User::factory()->admin()->create();
        $this->category = Category::factory()->create(['name' => 'ألبان وأجبان']);
    }

    private function validForm(array $overrides = [])
    {
        $component = Livewire::actingAs($this->admin)->test(ProductForm::class);

        foreach (array_merge([
            'name' => 'جبنة الخيرات 24 مثلث',
            'category_id' => $this->category->id,
            'sku' => 'CHS-024',
            'original_price' => '18',
            'sale_price' => '16.50',
            'stock_quantity' => '40',
            'min_order_quantity' => '1',
            'quantity_step' => '1',
            'low_stock_threshold' => '5',
            'unit' => 'pack',
            'status' => 'available',
        ], $overrides) as $field => $value) {
            $component->set($field, $value);
        }

        return $component;
    }

    public function test_admin_can_create_a_product(): void
    {
        $this->validForm(['is_featured' => true, 'seo_title' => 'جبنة', 'description' => 'وصف'])
            ->call('save')
            ->assertHasNoErrors()
            ->assertRedirect();

        $product = Product::firstWhere('sku', 'CHS-024');

        $this->assertSame('جبنة الخيرات 24 مثلث', $product->name);
        $this->assertSame('جبنة-الخيرات-24-مثلث', $product->slug);
        $this->assertSame('18.00', $product->original_price);
        $this->assertSame('16.50', $product->sale_price);
        $this->assertSame(SaleUnit::Pack, $product->unit);
        $this->assertTrue($product->is_featured);
        $this->assertSame('5.000', $product->low_stock_threshold);
    }

    public function test_product_needs_a_valid_price(): void
    {
        $this->validForm(['original_price' => '', 'sale_price' => 'abc'])
            ->call('save')
            ->assertHasErrors(['original_price' => 'required', 'sale_price']);

        $this->validForm(['original_price' => '10', 'sale_price' => '12'])
            ->call('save')
            ->assertHasErrors(['sale_price' => 'lte']);

        $this->validForm(['original_price' => '10.555'])
            ->call('save')
            ->assertHasErrors(['original_price' => 'decimal']);

        $this->validForm(['sale_price' => '0'])
            ->call('save')
            ->assertHasErrors(['sale_price' => 'min']);

        $this->assertSame(0, Product::count());
    }

    public function test_piece_units_need_whole_quantities_but_kilos_accept_fractions(): void
    {
        $this->validForm(['unit' => 'piece', 'stock_quantity' => '2.5'])
            ->call('save')
            ->assertHasErrors(['stock_quantity' => 'integer']);

        $this->validForm(['unit' => 'kg', 'stock_quantity' => '2.5', 'quantity_step' => '0.25', 'min_order_quantity' => '0.5'])
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame('0.250', Product::first()->quantity_step);
    }

    public function test_sku_must_be_unique(): void
    {
        Product::factory()->create(['sku' => 'CHS-024']);

        $this->validForm()->call('save')->assertHasErrors(['sku' => 'unique']);

        $this->validForm(['sku' => 'bad sku!'])->call('save')->assertHasErrors(['sku' => 'regex']);
    }

    public function test_admin_can_edit_a_product_without_touching_its_images(): void
    {
        $product = Product::factory()->for($this->category)->create(['main_image' => 'products/1/main.jpg', 'original_price' => 20, 'sale_price' => 18]);
        $product->images()->create(['path' => 'products/1/extra.jpg', 'sort_order' => 1]);

        Livewire::actingAs($this->admin)->test(ProductForm::class, ['product' => $product])
            ->assertSet('original_price', '20')
            ->assertSet('sale_price', '18')
            ->set('name', 'اسم جديد')
            ->set('sale_price', '17.25')
            ->call('save')
            ->assertHasNoErrors();

        $product->refresh();
        $this->assertSame('اسم جديد', $product->name);
        $this->assertSame('17.25', $product->sale_price);
        $this->assertSame('products/1/main.jpg', $product->main_image);
        $this->assertSame(['products/1/extra.jpg'], $product->images()->pluck('path')->all());
    }

    public function test_images_can_be_uploaded_and_managed(): void
    {
        $this->validForm()
            ->set('mainImage', UploadedFile::fake()->image('main.jpg', 1200, 1200))
            ->set('newImages', [UploadedFile::fake()->image('a.png', 500, 500), UploadedFile::fake()->image('b.webp', 500, 500)])
            ->call('save')
            ->assertHasNoErrors();

        $product = Product::first();
        $disk = Storage::disk('public');

        $this->assertStringStartsWith("products/{$product->id}/", $product->main_image);
        $disk->assertExists($product->main_image);
        $disk->assertExists(ImageStorage::thumbnailPath($product->main_image));
        $this->assertCount(2, $product->images);

        [$first, $second] = $product->images->all();
        $originalMain = $product->main_image;

        $component = Livewire::actingAs($this->admin)->test(ProductForm::class, ['product' => $product]);

        // Reorder
        $component->call('moveImage', $second->id, -1);
        $this->assertSame([$second->id, $first->id], $product->images()->pluck('id')->all());

        // Change the main image: the old main image moves into the gallery.
        $component->call('makeMain', $first->id);
        $this->assertSame($first->path, $product->fresh()->main_image);
        $this->assertSame($originalMain, $first->fresh()->path);

        // Delete
        $path = $second->path;
        $component->call('deleteImage', $second->id);
        $this->assertModelMissing($second);
        $disk->assertMissing($path);
        $disk->assertMissing(ImageStorage::thumbnailPath($path));
    }

    public function test_images_of_another_product_cannot_be_touched(): void
    {
        $product = Product::factory()->create();
        $foreign = Product::factory()->create();
        $image = $foreign->images()->create(['path' => 'products/x/a.jpg']);

        Livewire::actingAs($this->admin)->test(ProductForm::class, ['product' => $product])
            ->call('deleteImage', $image->id)
            ->assertNotFound();

        $this->assertModelExists($image);
    }

    public function test_non_image_uploads_are_rejected(): void
    {
        $this->validForm()
            ->set('mainImage', UploadedFile::fake()->create('invoice.pdf', 100, 'application/pdf'))
            ->call('save')
            ->assertHasErrors('mainImage');

        $this->validForm()
            ->set('newImages', [UploadedFile::fake()->create('script.html', 1, 'text/html')])
            ->call('save')
            ->assertHasErrors('newImages.0');

        $this->assertSame(0, Product::count());
    }

    public function test_product_can_be_duplicated(): void
    {
        $original = Product::factory()->for($this->category)->create([
            'name' => 'جبنة الخيرات 24 مثلث', 'sku' => 'CHS-024', 'sale_price' => 18, 'original_price' => 18,
        ]);
        $imageStorage = app(ImageStorage::class);
        $original->update(['main_image' => $imageStorage->store(UploadedFile::fake()->image('m.jpg', 300, 300), $original->imageDirectory())]);
        $original->images()->create(['path' => $imageStorage->store(UploadedFile::fake()->image('g.jpg', 300, 300), $original->imageDirectory())]);
        Offer::factory()->for($original)->create(['offer_price' => 10]);

        Livewire::actingAs($this->admin)->test(ProductIndex::class)
            ->call('duplicate', $original->id)
            ->assertRedirect();

        $copy = Product::latest('id')->first();

        $this->assertNotSame($original->id, $copy->id);
        $this->assertSame('جبنة الخيرات 24 مثلث (نسخة)', $copy->name);
        $this->assertNotSame($original->slug, $copy->slug);
        $this->assertNull($copy->sku);
        $this->assertSame(ProductStatus::Hidden, $copy->status);
        $this->assertSame('18.00', $copy->sale_price);
        $this->assertSame($original->category_id, $copy->category_id);
        $this->assertSame(0, $copy->offers()->count());

        // Files are real, independent copies.
        $this->assertStringStartsWith("products/{$copy->id}/", $copy->main_image);
        Storage::disk('public')->assertExists($copy->main_image);
        $this->assertCount(1, $copy->images);
        $this->assertNotSame($original->images->first()->path, $copy->images->first()->path);

        // Duplicating again still yields unique slugs.
        Livewire::actingAs($this->admin)->test(ProductIndex::class)->call('duplicate', $original->id);
        $this->assertSame(3, Product::count());
    }

    public function test_deleting_a_product_is_a_soft_delete_that_keeps_orders_intact(): void
    {
        $product = Product::factory()->create(['name' => 'زيت زيتون']);
        $offer = Offer::factory()->for($product)->create();
        $order = Order::factory()->create();
        $order->items()->save(OrderItem::snapshotFrom($product, 1));

        Livewire::actingAs($this->admin)->test(ProductIndex::class)->call('delete', $product->id);

        $this->assertSoftDeleted($product);
        $this->assertFalse($offer->fresh()->is_active);
        $item = $order->items()->first();
        $this->assertSame($product->id, $item->product_id);
        $this->assertSame('زيت زيتون', $item->product->name);

        Livewire::actingAs($this->admin)->test(ProductIndex::class)->call('restore', $product->id);
        $this->assertNotSoftDeleted($product);
    }

    public function test_search_filters_and_pagination(): void
    {
        $dairy = $this->category;
        $cheese = Category::factory()->childOf($dairy)->create();
        $other = Category::factory()->create();

        Product::factory()->for($cheese)->create(['name' => 'جبنة بيضاء', 'sku' => 'WHITE-1']);
        Product::factory()->for($other)->create(['name' => 'أرز بسمتي', 'sku' => 'RICE-9', 'status' => ProductStatus::Unavailable]);
        Product::factory()->for($other)->create(['name' => 'سكر', 'stock_quantity' => 2, 'low_stock_threshold' => 5]);
        Product::factory()->count(20)->for($other)->create();

        $component = Livewire::actingAs($this->admin)->test(ProductIndex::class);

        // Arabic spelling variants: "جبنه" (ه) finds "جبنة" (ة), "ارز" finds "أرز".
        $component->set('search', 'جبنه')->assertSee('جبنة بيضاء')->assertDontSee('أرز بسمتي');
        $component->set('search', 'ارز')->assertSee('أرز بسمتي')->assertDontSee('جبنة بيضاء');
        $component->set('search', 'rice-9')->assertSee('أرز بسمتي');
        $component->set('search', '');

        // Category filter includes sub-categories.
        $component->set('category', (string) $dairy->id)->assertSee('جبنة بيضاء')->assertDontSee('سكر');
        $component->set('category', '');

        $component->set('status', 'unavailable')->assertSee('أرز بسمتي')->assertDontSee('جبنة بيضاء');
        $component->set('status', '');

        $component->set('lowStock', true)->assertSee('سكر')->assertDontSee('جبنة بيضاء');
        $component->set('lowStock', false);

        $this->assertSame(15, $component->viewData('products')->count());
        $this->assertSame(23, $component->viewData('products')->total());
        $component->call('nextPage');
        $this->assertSame(8, $component->viewData('products')->count());
    }

    public function test_sorting(): void
    {
        Product::factory()->create(['name' => 'ب', 'sale_price' => 5, 'original_price' => 5, 'stock_quantity' => 30]);
        Product::factory()->create(['name' => 'أ', 'sale_price' => 9, 'original_price' => 9, 'stock_quantity' => 10]);

        $names = fn ($c) => $c->viewData('products')->pluck('name')->all();
        $component = Livewire::actingAs($this->admin)->test(ProductIndex::class);

        $this->assertSame(['أ', 'ب'], $names($component)); // latest first
        $this->assertSame(['ب', 'أ'], $names($component->set('sort', 'price_asc')));
        $this->assertSame(['أ', 'ب'], $names($component->set('sort', 'price_desc')));
        $this->assertSame(['أ', 'ب'], $names($component->set('sort', 'stock')));
    }

    public function test_product_list_has_no_n_plus_one_queries(): void
    {
        $count = function () {
            DB::flushQueryLog();
            DB::enableQueryLog();
            Livewire::actingAs($this->admin)->test(ProductIndex::class);

            return count(DB::getQueryLog());
        };

        Product::factory()->count(2)->for($this->category)->create();
        $count(); // warm up: the first call also loads the admin's role
        $few = $count();

        Product::factory()->count(12)->create()->each(fn ($p) => Offer::factory()->for($p)->create());
        $many = $count();

        $this->assertSame($few, $many);
        $this->assertLessThanOrEqual(8, $many);
    }
}
