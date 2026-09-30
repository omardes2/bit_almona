<?php

namespace Tests\Feature\Store;

use App\Livewire\Store\SearchPage;
use App\Models\Category;
use App\Models\Offer;
use App\Models\User;
use App\Services\Cart\CartException;
use App\Services\Cart\CartService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ParentCategoryVisibilityTest extends TestCase
{
    use RefreshDatabase, StorefrontTestHelpers;

    private Category $root;

    private Category $child;

    private Category $grandChild;

    protected function setUp(): void
    {
        parent::setUp();

        $this->root = Category::factory()->create(['name' => 'جذر', 'slug' => 'root']);
        $this->child = Category::factory()->childOf($this->root)->create(['name' => 'ابن', 'slug' => 'child']);
        $this->grandChild = Category::factory()->childOf($this->child)->create(['name' => 'حفيد', 'slug' => 'grandchild']);
    }

    public function test_a_product_under_an_inactive_ancestor_is_hidden_everywhere(): void
    {
        $product = $this->product(['name' => 'منتج الحفيد', 'slug' => 'grandchild-product'], $this->grandChild);
        $sibling = $this->product(['name' => 'منتج مرئي', 'slug' => 'visible-product'], Category::factory()->create());
        Offer::factory()->for($product)->create();
        Offer::factory()->for($sibling)->create();

        // Visible while the whole chain is active.
        $this->get('/')->assertSee('منتج الحفيد');

        // Deactivate the MIDDLE category only: the grandchild itself is still is_active = true.
        $this->child->update(['is_active' => false]);

        $this->get('/')->assertDontSee('منتج الحفيد')->assertSee('منتج مرئي');
        $this->get('/offers')->assertDontSee('منتج الحفيد')->assertSee('منتج مرئي');
        $this->get('/product/grandchild-product')->assertNotFound();
        $this->get('/category/grandchild')->assertNotFound();
        $this->get('/category/child')->assertNotFound();
        $this->get('/category/root')->assertOk()->assertDontSee('منتج الحفيد');
        Livewire::test(SearchPage::class)->set('q', 'منتج')->assertDontSee('منتج الحفيد')->assertSee('منتج مرئي');
        $this->get('/sitemap.xml')->assertDontSee('grandchild')->assertDontSee('/category/child')->assertSee('/category/root');

        // Related products on a visible product in the same (visible) family do not leak it either.
        $cousin = $this->product(['name' => 'ابن عم', 'slug' => 'cousin'], $this->root);
        $related = $this->get('/product/cousin')->viewData('related')->pluck('id')->all();
        $this->assertNotContains($product->id, $related);

        // And it cannot be bought.
        $this->expectException(CartException::class);
        app(CartService::class)->add($product->id);
    }

    public function test_deactivating_the_root_hides_the_whole_tree_and_reactivating_restores_it(): void
    {
        $this->product(['name' => 'منتج عميق'], $this->grandChild);

        $this->root->update(['is_active' => false]);
        $this->get('/')->assertDontSee('منتج عميق');

        $this->root->update(['is_active' => true]);
        $this->get('/')->assertSee('منتج عميق');
    }

    public function test_items_already_in_a_cart_become_unavailable(): void
    {
        $customer = User::factory()->create();
        $product = $this->product(['name' => 'منتج في السلة'], $this->grandChild);
        $this->actingAs($customer);
        app(CartService::class)->add($product->id);

        $this->root->update(['is_active' => false]);

        $line = app(CartService::class)->summary()->lines[0];
        $this->assertFalse($line->purchasable);
    }
}
