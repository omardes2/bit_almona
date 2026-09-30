<?php

namespace Tests\Feature\System;

use App\Enums\AdminRole;
use App\Livewire\Admin\Settings\Integrations;
use App\Livewire\Admin\System\FailedJobs;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

class AdminSystemPagesTest extends TestCase
{
    use RefreshDatabase;

    private function superAdmin(): User
    {
        return User::factory()->admin(AdminRole::SuperAdmin)->create();
    }

    private function failedJob(array $overrides = []): string
    {
        $uuid = (string) Str::uuid();

        DB::table('failed_jobs')->insert(array_merge([
            'uuid' => $uuid,
            'connection' => 'database',
            'queue' => 'notifications',
            'payload' => json_encode([
                'uuid' => $uuid,
                'displayName' => 'App\\Notifications\\OrderStatusMessage',
                'job' => 'Illuminate\\Queue\\CallQueuedHandler@call',
                'data' => ['phone' => '0599999888', 'password' => 'PAYLOADPASS'],
            ]),
            'exception' => "RuntimeException: gateway refused token=abc123SECRET in /var/www/app/X.php:10\nStack trace:\n#0 /var/www/secret/path.php(1): x()",
            'failed_at' => now(),
        ], $overrides));

        return $uuid;
    }

    public function test_only_super_admins_can_open_system_pages(): void
    {
        foreach (['/admin/system', '/admin/system/failed-jobs', '/admin/settings/integrations'] as $url) {
            $this->get($url)->assertRedirect(route('admin.login'));
        }

        foreach ([AdminRole::Manager, AdminRole::Staff] as $role) {
            $this->actingAs(User::factory()->admin($role)->create());

            foreach (['/admin/system', '/admin/system/failed-jobs', '/admin/settings/integrations'] as $url) {
                $this->get($url)->assertForbidden();
            }
        }

        $this->actingAs(User::factory()->create()); // customer
        $this->get('/admin/system')->assertForbidden();

        $this->actingAs($this->superAdmin());
        $this->get('/admin/system')->assertOk()->assertSee('جاهزية الإطلاق')->assertSee('حالة النظام');
        $this->get('/admin/system/failed-jobs')->assertOk();
        $this->get('/admin/settings/integrations')->assertOk();
    }

    public function test_system_page_shows_status_without_secrets(): void
    {
        config([
            'app.key' => 'base64:'.base64_encode('SUPERSECRETKEYVALUE0123456789ABC'),
            'app.version' => 'abc1234',
            'database.connections.mysql.password' => 'DbP4ssw0rdSecret',
            'database.connections.mysql.username' => 'db_user_secret',
            'mail.mailers.smtp.password' => 'MailSecret123',
        ]);

        $this->actingAs($this->superAdmin());

        $this->get('/admin/system')
            ->assertOk()
            ->assertSee('abc1234')
            ->assertSee(PHP_VERSION)
            ->assertSee('المُجدول')
            ->assertSee('لم يعمل أبدًا')
            ->assertDontSee(base64_encode('SUPERSECRETKEYVALUE0123456789ABC'))
            ->assertDontSee('DbP4ssw0rdSecret')
            ->assertDontSee('db_user_secret')
            ->assertDontSee('MailSecret123');
    }

    public function test_system_link_is_in_the_sidebar_for_super_admins_only(): void
    {
        $this->actingAs($this->superAdmin());
        $this->get('/admin')->assertSee(route('admin.system'));

        $this->actingAs(User::factory()->admin(AdminRole::Manager)->create());
        $this->get('/admin')->assertDontSee(route('admin.system'));
    }

    public function test_failed_jobs_show_a_safe_summary_only(): void
    {
        $this->failedJob();
        $this->actingAs($this->superAdmin());

        $this->get('/admin/system/failed-jobs')
            ->assertOk()
            ->assertSee('OrderStatusMessage')
            ->assertSee('notifications')
            ->assertSee('RuntimeException: gateway refused')
            ->assertDontSee('abc123SECRET')      // redacted
            ->assertDontSee('PAYLOADPASS')       // payload never shown
            ->assertDontSee('0599999888')
            ->assertDontSee('/var/www/secret');  // no stack trace
    }

    public function test_failed_jobs_can_be_retried_and_deleted(): void
    {
        Queue::fake();
        $retry = $this->failedJob();
        $delete = $this->failedJob();
        $this->actingAs($this->superAdmin());

        Livewire::test(FailedJobs::class)
            ->call('retry', $retry)
            ->call('forget', $delete)
            ->call('forget', 'not-a-uuid');

        $this->assertDatabaseMissing('failed_jobs', ['uuid' => $retry]);
        $this->assertDatabaseMissing('failed_jobs', ['uuid' => $delete]);
        $this->assertCount(1, Queue::pushedRaw(), 'the retried job is pushed back to the queue');
    }

    public function test_failed_job_actions_are_forbidden_for_managers(): void
    {
        $uuid = $this->failedJob();
        $this->actingAs(User::factory()->admin(AdminRole::Manager)->create());

        Livewire::test(FailedJobs::class)->assertForbidden();

        $this->assertDatabaseHas('failed_jobs', ['uuid' => $uuid]);
    }

    public function test_integrations_page_is_status_only_and_never_shows_secrets(): void
    {
        config([
            'messaging.channels.whatsapp.driver' => 'log',
            'services.whatsapp.token' => 'wa_tok_SECRET_987',
            'services.sms.key' => 'sms_key_SECRET_654',
            'otp.driver' => null,
        ]);

        $this->actingAs($this->superAdmin());

        $this->get('/admin/settings/integrations')
            ->assertOk()
            ->assertSee('واتساب')
            ->assertSee('وضع التطوير')
            ->assertSee('رسائل SMS')
            ->assertSee('غير مربوط')
            ->assertSee('الدفع الإلكتروني')
            ->assertSee('طابور المهام')
            ->assertSee('.env')
            ->assertDontSee('wa_tok_SECRET_987')
            ->assertDontSee('sms_key_SECRET_654');

        // The page body has no form at all: secrets cannot be typed in the admin panel.
        Livewire::test(Integrations::class)
            ->assertDontSeeHtml('<input')
            ->assertDontSeeHtml('<textarea')
            ->assertDontSeeHtml('<form');
    }
}
