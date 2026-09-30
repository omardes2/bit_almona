<?php

namespace Tests\Feature\Security;

use App\Enums\AccountStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class HardeningTest extends TestCase
{
    use RefreshDatabase;

    public function test_security_headers(): void
    {
        $response = $this->get('/');

        $response->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('X-Frame-Options', 'SAMEORIGIN')
            ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
        $this->assertStringContainsString("frame-ancestors 'self'", $response->headers->get('Content-Security-Policy'));
        $this->assertStringContainsString('camera=()', $response->headers->get('Permissions-Policy'));
    }

    public function test_session_cookie_is_secure_by_default_in_production(): void
    {
        $config = require base_path('config/session.php');
        $this->assertTrue($config['http_only']);
        $this->assertSame('lax', $config['same_site']);

        $backup = [$_SERVER['APP_ENV'] ?? null, $_ENV['APP_ENV'] ?? null];
        $_SERVER['APP_ENV'] = $_ENV['APP_ENV'] = 'production';
        unset($_SERVER['SESSION_SECURE_COOKIE'], $_ENV['SESSION_SECURE_COOKIE']);

        try {
            $this->assertTrue((require base_path('config/session.php'))['secure']);
        } finally {
            [$_SERVER['APP_ENV'], $_ENV['APP_ENV']] = $backup;
        }

        $this->assertFalse((require base_path('config/session.php'))['secure'], 'plain HTTP keeps working outside production');
    }

    public function test_error_pages_are_arabic_and_expose_nothing(): void
    {
        config(['app.debug' => false]);
        Route::get('/_boom', fn () => throw new \RuntimeException('SECRET_DB_PASSWORD=hunter2 in /var/www/app.php'));
        Route::get('/_forbidden', fn () => abort(403));
        Route::get('/_expired', fn () => abort(419));
        Route::get('/_throttled', fn () => abort(429));

        $this->get('/does-not-exist')->assertNotFound()->assertSee('الصفحة غير موجودة')->assertSee('dir="rtl"', false);
        $this->get('/_forbidden')->assertForbidden()->assertSee('غير مسموح');
        $this->get('/_expired')->assertStatus(419)->assertSee('انتهت صلاحية الصفحة');
        $this->get('/_throttled')->assertStatus(429)->assertSee('طلبات كثيرة');

        $this->get('/_boom')->assertStatus(500)
            ->assertSee('حدث خطأ غير متوقع')
            ->assertDontSee('SECRET_DB_PASSWORD')
            ->assertDontSee('hunter2')
            ->assertDontSee('RuntimeException')
            ->assertDontSee('/var/www');
    }

    public function test_health_endpoint_is_minimal(): void
    {
        $response = $this->get('/up')->assertOk();

        foreach (['DB_', 'APP_KEY', 'password', 'testing', 'sqlite', env('DB_DATABASE', ':memory:')] as $secret) {
            $response->assertDontSee($secret, false);
        }
    }

    public function test_suspended_customer_session_is_invalidated(): void
    {
        $customer = User::factory()->create();
        $this->actingAs($customer)->get('/account')->assertOk();

        $customer->update(['status' => AccountStatus::Suspended]);

        $this->get('/cart')->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_passwords_are_never_flashed_back_to_the_session(): void
    {
        Route::post('/_validate', function (Request $request) {
            $request->validate(['name' => 'required']);
        })->middleware('web');

        $this->from('/')->post('/_validate', ['password' => 'secret-1', 'code' => '123456', 'phone' => '0599'])
            ->assertSessionHasErrors('name');

        $this->assertNull(session()->getOldInput('password'));
        $this->assertNull(session()->getOldInput('code'));
        $this->assertSame('0599', session()->getOldInput('phone'));
    }
}
