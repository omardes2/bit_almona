<?php

namespace Tests\Feature\Admin\Catalog;

use App\Livewire\Admin\Categories\CategoryForm;
use App\Livewire\Admin\Categories\CategoryIndex;
use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use App\Services\Media\ImageStorage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class CategoryManagementTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        $this->admin = User::factory()->admin()->create();
    }

    public function test_admin_can_create_a_category_with_an_auto_generated_slug(): void
    {
        Livewire::actingAs($this->admin)->test(CategoryForm::class)
            ->set('name', 'ألبان وأجبان')
            ->set('sort_order', 3)
            ->call('save')
            ->assertHasNoErrors()
            ->assertRedirect(route('admin.categories.index'));

        $category = Category::firstWhere('name', 'ألبان وأجبان');
        $this->assertSame('ألبان-وأجبان', $category->slug);
        $this->assertSame(3, $category->sort_order);
        $this->assertTrue($category->is_active);
        $this->assertNull($category->parent_id);
    }

    public function test_duplicate_names_get_unique_slugs(): void
    {
        Category::factory()->create(['slug' => 'خضار']);

        Livewire::actingAs($this->admin)->test(CategoryForm::class)
            ->set('name', 'خضار')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertTrue(Category::where('slug', 'خضار-2')->exists());
    }

    public function test_admin_can_create_a_subcategory(): void
    {
        $parent = Category::factory()->create();

        Livewire::actingAs($this->admin)->test(CategoryForm::class)
            ->set('name', 'أجبان بيضاء')
            ->set('parent_id', $parent->id)
            ->call('save')
            ->assertHasNoErrors();

        $this->assertTrue(Category::firstWhere('name', 'أجبان بيضاء')->parent->is($parent));
    }

    public function test_a_category_cannot_be_its_own_parent(): void
    {
        $category = Category::factory()->create();

        Livewire::actingAs($this->admin)->test(CategoryForm::class, ['category' => $category])
            ->set('parent_id', $category->id)
            ->call('save')
            ->assertHasErrors('parent_id');

        $this->assertNull($category->fresh()->parent_id);
    }

    public function test_a_category_cannot_be_moved_under_its_own_descendant(): void
    {
        $root = Category::factory()->create();
        $child = Category::factory()->childOf($root)->create();
        $grandChild = Category::factory()->childOf($child)->create();

        Livewire::actingAs($this->admin)->test(CategoryForm::class, ['category' => $root])
            ->set('parent_id', $grandChild->id)
            ->call('save')
            ->assertHasErrors('parent_id');

        $this->assertNull($root->fresh()->parent_id);
    }

    public function test_validation_works(): void
    {
        Category::factory()->create(['slug' => 'used']);

        Livewire::actingAs($this->admin)->test(CategoryForm::class)
            ->set('name', '')
            ->set('slug', 'used')
            ->set('sort_order', -1)
            ->set('parent_id', 99999)
            ->call('save')
            ->assertHasErrors(['name' => 'required', 'slug' => 'unique', 'sort_order', 'parent_id']);

        $this->assertSame(1, Category::count());
    }

    public function test_category_image_is_uploaded_with_a_thumbnail(): void
    {
        Livewire::actingAs($this->admin)->test(CategoryForm::class)
            ->set('name', 'مخبوزات')
            ->set('image', UploadedFile::fake()->image('../../evil name.jpg', 800, 600))
            ->call('save')
            ->assertHasNoErrors();

        $path = Category::firstWhere('name', 'مخبوزات')->image;

        $this->assertMatchesRegularExpression('#^categories/[0-9a-z]{26}\.jpg$#', $path);
        Storage::disk('public')->assertExists($path);
        Storage::disk('public')->assertExists(ImageStorage::thumbnailPath($path));
    }

    public function test_replacing_the_image_deletes_the_old_file(): void
    {
        Livewire::actingAs($this->admin)->test(CategoryForm::class)
            ->set('name', 'مخبوزات')
            ->set('image', UploadedFile::fake()->image('a.png', 300, 300))
            ->call('save');

        $category = Category::firstWhere('name', 'مخبوزات');
        $oldPath = $category->image;

        Livewire::actingAs($this->admin)->test(CategoryForm::class, ['category' => $category])
            ->set('image', UploadedFile::fake()->image('b.webp', 300, 300))
            ->call('save')
            ->assertHasNoErrors();

        Storage::disk('public')->assertMissing($oldPath);
        Storage::disk('public')->assertExists($category->fresh()->image);
    }

    public function test_non_image_files_are_rejected(): void
    {
        Livewire::actingAs($this->admin)->test(CategoryForm::class)
            ->set('name', 'اختبار')
            ->set('image', UploadedFile::fake()->create('shell.php', 10, 'application/x-php'))
            ->call('save')
            ->assertHasErrors('image');

        Livewire::actingAs($this->admin)->test(CategoryForm::class)
            ->set('name', 'اختبار')
            ->set('image', UploadedFile::fake()->create('logo.svg', 5, 'image/svg+xml'))
            ->call('save')
            ->assertHasErrors('image');

        Livewire::actingAs($this->admin)->test(CategoryForm::class)
            ->set('name', 'اختبار')
            ->set('image', UploadedFile::fake()->image('tiny.jpg', 20, 20))
            ->call('save')
            ->assertHasErrors('image');

        $this->assertSame(0, Category::count());
    }

    public function test_list_shows_product_counts_and_subcategories(): void
    {
        $root = Category::factory()->create(['name' => 'ألبان']);
        Category::factory()->childOf($root)->create(['name' => 'أجبان']);
        Product::factory()->count(3)->for($root)->create();

        Livewire::actingAs($this->admin)->test(CategoryIndex::class)
            ->assertSee('ألبان')
            ->assertSee('أجبان')
            ->assertSee('3 منتج')
            ->assertSee('1 قسم فرعي');
    }

    public function test_categories_with_children_or_products_are_not_deleted(): void
    {
        $withChild = Category::factory()->create();
        Category::factory()->childOf($withChild)->create();

        $withProduct = Category::factory()->create();
        Product::factory()->for($withProduct)->create()->delete(); // even a soft-deleted product blocks deletion

        $empty = Category::factory()->create();

        $component = Livewire::actingAs($this->admin)->test(CategoryIndex::class);

        $component->call('delete', $withChild->id)->assertDispatched('toast', type: 'error');
        $component->call('delete', $withProduct->id)->assertDispatched('toast', type: 'error');
        $component->call('delete', $empty->id);

        $this->assertModelExists($withChild);
        $this->assertModelExists($withProduct);
        $this->assertModelMissing($empty);
    }

    public function test_toggle_and_reorder(): void
    {
        $a = Category::factory()->create(['sort_order' => 1]);
        $b = Category::factory()->create(['sort_order' => 2]);

        Livewire::actingAs($this->admin)->test(CategoryIndex::class)
            ->call('toggleActive', $a->id)
            ->call('move', $b->id, -1);

        $this->assertFalse($a->fresh()->is_active);
        $this->assertSame(1, $b->fresh()->sort_order);
        $this->assertSame(2, $a->fresh()->sort_order);
    }
}
