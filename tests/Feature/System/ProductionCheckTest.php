<?php

namespace Tests\Feature\System;

use App\Enums\AdminRole;
use App\Models\Category;
use App\Models\Product;
use App\Models\StoreSetting;
use App\Models\User;
use App\Services\System\CheckResult;
use App\Services\System\SchedulerHeartbeat;
use App\Services\System\SystemHealth;
use Database\Seeders\DemoDataSeeder;
use Database\Seeders\StoreSettingsSeeder;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Checkout\CheckoutTestHelpers;
use Tests\TestCase;

class ProductionCheckTest extends TestCase
{
    use CheckoutTestHelpers, RefreshDatabase;

    /** @return array<string, CheckResult> */
    private function checks(): array
    {
        return collect(app(SystemHealth::class)->checks())->keyBy('key')->all();
    }

    private function readyStore(): void
    {
        $this->seed(StoreSettingsSeeder::class);
        StoreSetting::set('store_phone', '02-2221234');
        User::factory()->admin(AdminRole::SuperAdmin)->create();
        $this->zone();
        $this->product();
        app(SchedulerHeartbeat::class)->beat();
        config(['app.debug' => false, 'app.url' => 'https://store.example']);
    }

    public function test_debug_mode_is_critical(): void
    {
        config(['app.debug' => true]);
        $this->assertTrue($this->checks()['debug']->isCritical());

        config(['app.debug' => false]);
        $this->assertTrue($this->checks()['debug']->passed());
    }

    public function test_http_app_url_is_critical(): void
    {
        config(['app.url' => 'http://store.example']);
        $this->assertTrue($this->checks()['https']->isCritical());

        config(['app.url' => 'https://store.example']);
        $this->assertTrue($this->checks()['https']->passed());
    }

    public function test_missing_delivery_zone_is_critical(): void
    {
        $this->assertTrue($this->checks()['delivery_zone']->isCritical());

        $this->zone(['is_active' => false]);
        $this->assertTrue($this->checks()['delivery_zone']->isCritical(), 'inactive zones do not count');

        $this->zone();
        $this->assertTrue($this->checks()['delivery_zone']->passed());
    }

    public function test_missing_super_admin_is_critical(): void
    {
        User::factory()->admin(AdminRole::Manager)->create();
        User::factory()->admin(AdminRole::SuperAdmin)->suspended()->create();
        $this->assertTrue($this->checks()['admin']->isCritical());

        User::factory()->admin(AdminRole::SuperAdmin)->create();
        $this->assertTrue($this->checks()['admin']->passed());
    }

    public function test_scheduler_heartbeat_detects_a_stopped_cron(): void
    {
        $this->assertTrue($this->checks()['scheduler']->isCritical(), 'never ran');

        $this->artisan('system:heartbeat')->assertSuccessful();
        $this->assertTrue($this->checks()['scheduler']->passed());
        $this->assertTrue(app(SchedulerHeartbeat::class)->isRunning());

        $this->travel(SchedulerHeartbeat::STALE_AFTER_MINUTES + 1)->minutes();
        $this->assertTrue($this->checks()['scheduler']->isCritical());
        $this->assertStringContainsString('متوقف', $this->checks()['scheduler']->message);
    }

    public function test_heartbeat_is_scheduled_every_minute(): void
    {
        $event = collect(app(Schedule::class)->events())
            ->first(fn ($event) => str_contains((string) $event->command, 'system:heartbeat'));

        $this->assertNotNull($event);
        $this->assertSame('* * * * *', $event->expression);
    }

    public function test_demo_data_is_critical_until_purged(): void
    {
        $this->seed(DemoDataSeeder::class);
        $this->assertTrue($this->checks()['demo_data']->isCritical());

        $this->artisan('store:purge-demo', ['--force' => true])->assertSuccessful();
        $this->assertTrue($this->checks()['demo_data']->passed());
    }

    public function test_incomplete_store_settings_are_reported(): void
    {
        $this->seed(StoreSettingsSeeder::class); // phone is empty by default

        $checks = $this->checks();
        $this->assertTrue($checks['setting_store_name']->passed());
        $this->assertTrue($checks['setting_store_phone']->isCritical());
        $this->assertSame(CheckResult::WARNING, $checks['setting_store_logo']->status);
    }

    public function test_dev_only_drivers_are_critical(): void
    {
        config(['otp.driver' => 'log', 'mail.default' => 'smtp']);
        $this->assertTrue($this->checks()['dev_drivers']->isCritical());

        config(['otp.driver' => null, 'messaging.channels.whatsapp.driver' => 'log']);
        $this->assertTrue($this->checks()['dev_drivers']->isCritical());

        config(['messaging.channels.whatsapp.driver' => null]);
        $this->assertTrue($this->checks()['dev_drivers']->passed());
    }

    public function test_queue_health_detects_failed_and_stuck_jobs(): void
    {
        config(['queue.default' => 'database']);
        $this->assertTrue($this->checks()['queue_worker']->passed());
        $this->assertTrue($this->checks()['failed_jobs']->passed());

        DB::table('jobs')->insert([
            'queue' => 'default', 'payload' => '{}', 'attempts' => 0, 'reserved_at' => null,
            'available_at' => now()->subMinutes(30)->getTimestamp(), 'created_at' => now()->subMinutes(30)->getTimestamp(),
        ]);
        DB::table('failed_jobs')->insert([
            'uuid' => (string) str()->uuid(), 'connection' => 'database', 'queue' => 'default',
            'payload' => '{}', 'exception' => 'RuntimeException: x', 'failed_at' => now(),
        ]);

        $checks = $this->checks();
        $this->assertTrue($checks['queue_worker']->isCritical());
        $this->assertSame(CheckResult::WARNING, $checks['failed_jobs']->status);
        $this->assertSame(1, app(SystemHealth::class)->queueStats()['failed']);
    }

    public function test_sync_queue_is_a_warning(): void
    {
        config(['queue.default' => 'sync']);

        $this->assertSame(CheckResult::WARNING, $this->checks()['queue_config']->status);
    }

    public function test_command_exits_non_zero_on_critical_issues(): void
    {
        config(['app.debug' => true]);

        $this->artisan('store:check-production')
            ->expectsOutputToContain('مشكلة حرجة')
            ->assertExitCode(1);
    }

    public function test_command_exits_zero_when_only_warnings_remain(): void
    {
        $this->app->instance(SystemHealth::class, new class(app(SchedulerHeartbeat::class)) extends SystemHealth
        {
            public function checks(): array
            {
                return [
                    CheckResult::ok('debug', 'وضع التصحيح', 'معطّل'),
                    CheckResult::warning('environment', 'بيئة التشغيل', 'staging'),
                ];
            }
        });

        $this->artisan('store:check-production')->assertExitCode(0);
    }

    public function test_a_ready_store_has_no_critical_data_checks(): void
    {
        $this->readyStore();

        $critical = collect($this->checks())
            ->filter(fn (CheckResult $c) => $c->isCritical())
            // Filesystem-dependent on the machine running the tests (public/storage link).
            ->except(['storage_link'])
            ->keys()
            ->all();

        $this->assertSame([], $critical);

        $launch = collect(app(SystemHealth::class)->launchChecklist());
        $this->assertCount(11, $launch);
        $this->assertTrue($launch->every(fn (CheckResult $c) => ! $c->isCritical()));
    }

    public function test_output_never_contains_secrets(): void
    {
        config([
            'app.key' => 'base64:SUPERSECRETKEYVALUE0000000000000000000000000=',
            'database.connections.sqlite.password' => 'DbP4ssw0rdSecret',
            'database.connections.mysql.password' => 'DbP4ssw0rdSecret',
            'mail.mailers.smtp.password' => 'MailSecret123',
        ]);

        Artisan::call('store:check-production', ['--json' => true]);
        $json = Artisan::output();
        Artisan::call('store:check-production');
        $table = Artisan::output();

        foreach ([$json, $table] as $output) {
            $this->assertStringNotContainsString('SUPERSECRETKEYVALUE', $output);
            $this->assertStringNotContainsString('DbP4ssw0rdSecret', $output);
            $this->assertStringNotContainsString('MailSecret123', $output);
        }

        $this->assertIsArray(json_decode($json, true)['checks']);
    }

    public function test_demo_seeder_refuses_production_without_confirmation(): void
    {
        $this->app['env'] = 'production';

        $this->artisan('db:seed', ['--class' => DemoDataSeeder::class, '--force' => true])
            ->expectsConfirmation('أنت في بيئة الإنتاج. هل تريد فعلًا إضافة بيانات تجريبية؟', 'no')
            ->assertSuccessful();

        $this->assertFalse(Product::withTrashed()->where('sku', DemoDataSeeder::PRODUCT_SKU)->exists());
        $this->assertFalse(Category::where('slug', DemoDataSeeder::CATEGORY_SLUG)->exists());
    }

    public function test_database_seeder_never_adds_demo_data_in_production(): void
    {
        $this->app['env'] = 'production';
        config(['store.seed_demo_data' => true]);

        $this->artisan('db:seed', ['--force' => true, '--no-interaction' => true])->assertSuccessful();

        $this->assertFalse(Product::withTrashed()->where('sku', DemoDataSeeder::PRODUCT_SKU)->exists());
        $this->assertGreaterThan(0, StoreSetting::count());
    }
}
