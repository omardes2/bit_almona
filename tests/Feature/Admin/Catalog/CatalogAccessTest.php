<?php

namespace Tests\Feature\Admin\Catalog;

use App\Enums\AdminRole;
use App\Livewire\Admin\Products\ProductIndex;
use App\Models\Banner;
use App\Models\Category;
use App\Models\Offer;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class CatalogAccessTest extends TestCase
{
    use RefreshDatabase;

    public static function pages(): array
    {
        return [
            'products' => ['/admin/products'],
            'products create' => ['/admin/products/create'],
            'categories' => ['/admin/categories'],
            'categories create' => ['/admin/categories/create'],
            'offers' => ['/admin/offers'],
            'offers create' => ['/admin/offers/create'],
            'banners' => ['/admin/banners'],
            'banners create' => ['/admin/banners/create'],
        ];
    }

    #[DataProvider('pages')]
    public function test_guests_are_redirected_to_admin_login(string $url): void
    {
        $this->get($url)->assertRedirect(route('admin.login'));
    }

    #[DataProvider('pages')]
    public function test_customers_are_forbidden(string $url): void
    {
        $this->actingAs(User::factory()->create())->get($url)->assertForbidden();
    }

    #[DataProvider('pages')]
    public function test_staff_admins_are_forbidden(string $url): void
    {
        $this->actingAs(User::factory()->admin(AdminRole::Staff)->create())->get($url)->assertForbidden();
    }

    #[DataProvider('pages')]
    public function test_super_admins_and_managers_are_allowed(string $url): void
    {
        $this->actingAs(User::factory()->admin(AdminRole::SuperAdmin)->create())->get($url)->assertOk();
        $this->actingAs(User::factory()->admin(AdminRole::Manager)->create())->get($url)->assertOk();
    }

    public function test_edit_pages_are_protected(): void
    {
        $urls = [
            route('admin.products.edit', Product::factory()->create()),
            route('admin.categories.edit', Category::factory()->create()),
            route('admin.offers.edit', Offer::factory()->create()),
            route('admin.banners.edit', Banner::factory()->create()),
        ];

        foreach ($urls as $url) {
            $this->actingAs(User::factory()->create())->get($url)->assertForbidden();
            $this->actingAs(User::factory()->admin()->create())->get($url)->assertOk();
        }
    }

    public function test_livewire_actions_are_authorized_not_only_the_page(): void
    {
        $product = Product::factory()->create();

        Livewire::actingAs(User::factory()->admin(AdminRole::Staff)->create())
            ->test(ProductIndex::class)
            ->assertForbidden();

        $this->assertNotSoftDeleted($product);
    }

    public function test_staff_see_the_dashboard_without_catalog_links(): void
    {
        $this->actingAs(User::factory()->admin(AdminRole::Staff)->create())
            ->get('/admin')
            ->assertOk()
            ->assertDontSee(route('admin.products.index'));
    }
}
