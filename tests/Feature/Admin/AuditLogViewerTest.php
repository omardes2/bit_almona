<?php

namespace Tests\Feature\Admin;

use App\Enums\AdminRole;
use App\Livewire\Admin\AuditLogs\AuditLogIndex;
use App\Models\AuditLog;
use App\Models\Category;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AuditLogViewerTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_super_admin_can_view(): void
    {
        $this->get('/admin/audit-logs')->assertRedirect(route('admin.login'));
        $this->actingAs(User::factory()->create())->get('/admin/audit-logs')->assertForbidden();
        $this->actingAs(User::factory()->admin(AdminRole::Staff)->create())->get('/admin/audit-logs')->assertForbidden();
        $this->actingAs(User::factory()->admin(AdminRole::Manager)->create())->get('/admin/audit-logs')->assertForbidden();
        $this->actingAs(User::factory()->admin(AdminRole::SuperAdmin)->create())->get('/admin/audit-logs')->assertOk();
    }

    public function test_filters_and_details(): void
    {
        $admin = User::factory()->admin(AdminRole::SuperAdmin)->create(['name' => 'المدير']);
        $this->actingAs($admin);
        $category = Category::factory()->create(['name' => 'قديم']);
        $category->update(['name' => 'جديد']);

        $component = Livewire::test(AuditLogIndex::class);
        $this->assertSame(2, $component->viewData('logs')->total());
        $this->assertSame(1, $component->set('event', 'updated')->viewData('logs')->total());
        $this->assertSame(0, $component->set('type', 'order')->viewData('logs')->total());
        $component->set('type', 'category')->set('user', (string) $admin->id);
        $this->assertSame(1, $component->viewData('logs')->total());
        $this->assertSame(0, $component->set('date', now()->subDay()->format('Y-m-d'))->viewData('logs')->total());

        $log = AuditLog::where('event', 'updated')->first();
        Livewire::test(AuditLogIndex::class)->call('toggle', $log->id)->assertSee('قديم')->assertSee('جديد')->assertSee('المدير');
    }

    public function test_sensitive_values_are_never_shown(): void
    {
        $admin = User::factory()->admin(AdminRole::SuperAdmin)->create();
        $this->actingAs($admin);

        // Even if someone records them, secrets are masked when written...
        $log = AuditLog::record($admin, 'updated', ['password' => 'old-secret-pass'], [
            'api_key' => 'sk_live_123', 'remember_token' => 'tok123', 'otp_code' => '482913', 'name' => 'ظاهر',
        ]);
        $this->assertSame('••••••', $log->fresh()->new_values['api_key']);

        // ...and when displayed.
        AuditLog::query()->whereKey($log->id)->update(['new_values' => json_encode(['session_token' => 'raw-leak', 'name' => 'ظاهر'])]);

        Livewire::test(AuditLogIndex::class)->call('toggle', $log->id)
            ->assertSee('ظاهر')
            ->assertDontSee('old-secret-pass')->assertDontSee('sk_live_123')->assertDontSee('tok123')
            ->assertDontSee('482913')->assertDontSee('raw-leak');

        // Model-level audits never include password hashes at all.
        $this->assertStringNotContainsString('password', json_encode(AuditLog::where('event', 'created')->pluck('new_values')));
    }
}
