<?php

namespace Tests\Feature\Catalog;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CategoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_categories_support_sub_categories_ordering_and_activation(): void
    {
        $dairy = Category::factory()->create(['name' => 'ألبان وأجبان']);
        $second = Category::factory()->childOf($dairy)->create(['sort_order' => 2]);
        $first = Category::factory()->childOf($dairy)->create(['sort_order' => 1]);
        Category::factory()->childOf($dairy)->inactive()->create();

        $this->assertTrue($dairy->isRoot());
        $this->assertTrue($first->parent->is($dairy));
        $this->assertSame([$first->id, $second->id], $dairy->children()->active()->pluck('id')->all());
        $this->assertSame([$dairy->id], Category::roots()->pluck('id')->all());
    }

    public function test_a_category_with_products_cannot_be_deleted(): void
    {
        $product = Product::factory()->create();

        $this->expectException(QueryException::class);

        $product->category->delete();
    }
}
