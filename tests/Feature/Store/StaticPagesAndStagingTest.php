<?php

namespace Tests\Feature\Store;

use App\Enums\SettingType;
use App\Models\StoreSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StaticPagesAndStagingTest extends TestCase
{
    use RefreshDatabase;

    private const LEGAL_PLACEHOLDER = 'يجب استبدال هذا المحتوى بالنص القانوني المعتمد قبل الإطلاق.';

    public function test_privacy_and_terms_are_clear_placeholders(): void
    {
        $this->get('/privacy-policy')->assertOk()->assertSee('سياسة الخصوصية')->assertSee(self::LEGAL_PLACEHOLDER)->assertSee('noindex', false);
        $this->get('/terms')->assertOk()->assertSee('الشروط والأحكام')->assertSee(self::LEGAL_PLACEHOLDER);
    }

    public function test_contact_page_uses_store_settings_and_has_no_form(): void
    {
        StoreSetting::set('store_phone', '02-2221234');
        StoreSetting::set('store_whatsapp', '0599888777');
        StoreSetting::set('store_address', 'الخليل – دوار ابن رشد', SettingType::Text);
        StoreSetting::set('working_hours', 'يوميًا 8 صباحًا – 11 مساءً', SettingType::Text);

        $this->get('/contact')
            ->assertOk()
            ->assertSee('02-2221234')
            ->assertSee('https://wa.me/970599888777', false)
            ->assertSee('الخليل – دوار ابن رشد')
            ->assertSee('يوميًا 8 صباحًا')
            ->assertDontSee('<textarea', false)
            ->assertDontSee('method="POST"', false);
    }

    public function test_contact_page_without_settings(): void
    {
        $this->get('/contact')->assertOk()->assertSee('سيتم إضافة معلومات التواصل قريبًا');
    }

    public function test_about_page_uses_the_owner_text_when_set(): void
    {
        $this->get('/about')->assertOk()->assertSee('من نحن');

        StoreSetting::set('about_text', 'نص من صاحب المتجر', SettingType::Text);
        $this->get('/about')->assertOk()->assertSee('نص من صاحب المتجر');
    }

    public function test_footer_links_to_the_information_pages(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee(route('about'))
            ->assertSee(route('contact'))
            ->assertSee(route('privacy'))
            ->assertSee(route('terms'))
            ->assertSeeInOrder(['من نحن', 'اتصل بنا', 'سياسة الخصوصية', 'الشروط']);
    }

    public function test_sitemap_lists_about_and_contact(): void
    {
        $this->get('/sitemap.xml')->assertOk()->assertSee(route('about'))->assertSee(route('contact'));
    }

    public function test_staging_blocks_all_crawlers(): void
    {
        $this->app['env'] = 'staging';

        $response = $this->get('/robots.txt')->assertOk();
        $this->assertSame("User-agent: *\nDisallow: /\n", $response->getContent());
        $this->assertStringNotContainsString('Sitemap', $response->getContent());

        $this->get('/')->assertHeader('X-Robots-Tag', 'noindex, nofollow');
    }

    public function test_production_robots_allows_the_store(): void
    {
        $this->app['env'] = 'production';

        $content = $this->get('/robots.txt')->assertOk()->getContent();
        $this->assertStringContainsString('Allow: /', $content);
        $this->assertStringContainsString('Disallow: /admin', $content);
        $this->assertStringContainsString('Disallow: /checkout', $content);
        $this->assertStringNotContainsString("Disallow: /\n", $content);

        $this->get('/')->assertHeaderMissing('X-Robots-Tag');
    }
}
