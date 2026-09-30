<?php

namespace Tests\Feature\Store;

use App\Enums\ProductStatus;
use App\Livewire\Store\SearchBox;
use App\Livewire\Store\SearchPage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class SearchTest extends TestCase
{
    use RefreshDatabase, StorefrontTestHelpers;

    public function test_arabic_normalized_search_finds_spelling_variants(): void
    {
        $this->product(['name' => 'جبنة الخيرات 24 مثلث']);
        $this->product(['name' => 'أرز بسمتي', 'sku' => 'RICE-5']);

        Livewire::test(SearchPage::class)->set('q', 'جبنه')->assertSee('جبنة الخيرات 24 مثلث')->assertDontSee('أرز بسمتي');
        Livewire::test(SearchPage::class)->set('q', 'ارز')->assertSee('أرز بسمتي');
        Livewire::test(SearchPage::class)->set('q', 'rice-5')->assertSee('أرز بسمتي');
    }

    public function test_hidden_deleted_and_inactive_category_products_are_excluded(): void
    {
        $this->product(['name' => 'جبنة ظاهرة']);
        $this->product(['name' => 'جبنة مخفية', 'status' => ProductStatus::Hidden]);
        $this->product(['name' => 'جبنة محذوفة'])->delete();

        Livewire::test(SearchPage::class)->set('q', 'جبنة')
            ->assertSee('جبنة ظاهرة')->assertDontSee('جبنة مخفية')->assertDontSee('جبنة محذوفة');

        Livewire::test(SearchBox::class)->set('q', 'جبنة')
            ->assertSee('جبنة ظاهرة')->assertDontSee('جبنة مخفية')->assertDontSee('جبنة محذوفة');
    }

    public function test_header_suggestions_are_limited_and_link_to_all_results(): void
    {
        foreach (range(1, 9) as $i) {
            $this->product(['name' => "جبنة رقم {$i}"]);
        }

        $component = Livewire::test(SearchBox::class)->set('q', 'جب');

        $this->assertCount(SearchBox::LIMIT, $component->get('results'));
        $component->assertSee('عرض كل النتائج')->assertSee(route('search', ['q' => 'جب']), false);

        Livewire::test(SearchBox::class)->set('q', 'ج')->assertDontSee('عرض كل النتائج');
    }

    public function test_search_page_via_url(): void
    {
        $this->product(['name' => 'حليب طازج']);

        $this->get('/search?q='.urlencode('حليب'))->assertOk()->assertSee('حليب طازج');
    }
}
