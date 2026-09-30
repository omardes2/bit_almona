<?php

namespace App\Providers;

use App\Enums\AdminRole;
use App\Events\Orders\OrderEvent;
use App\Http\Middleware\EnsurePhoneIsVerified;
use App\Http\Middleware\EnsureUserIsAdmin;
use App\Models\Banner;
use App\Models\Category;
use App\Models\DeliveryZone;
use App\Models\Offer;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Product;
use App\Models\StoreSetting;
use App\Models\User;
use App\Notifications\Listeners\NotifyAdminsAboutOrders;
use App\Notifications\Listeners\NotifyCustomerAboutOrder;
use App\Services\Cart\CartService;
use App\Services\Media\GdImageProcessor;
use App\Services\Media\ImageProcessor;
use App\Support\Store;
use App\Support\StorefrontVisibility;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Events\Login;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\Request;
use Illuminate\Notifications\Events\NotificationFailed;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Livewire\Livewire;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(ImageProcessor::class, GdImageProcessor::class);
        $this->app->scoped(StorefrontVisibility::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Date::use(CarbonImmutable::class);

        // Catch N+1 queries, silently discarded attributes, etc. outside production.
        Model::shouldBeStrict(! $this->app->isProduction());

        Password::defaults(fn () => $this->app->isProduction()
            ? Password::min(8)->letters()->numbers()->uncompromised()
            : Password::min(8)->letters()->numbers());

        RateLimiter::for('auth', fn (Request $request) => Limit::perMinute(10)->by($request->ip()));
        RateLimiter::for('register', fn (Request $request) => Limit::perHour(10)->by($request->ip()));

        // Stable names in polymorphic columns (audit_logs.auditable_type).
        Relation::morphMap([
            'product' => Product::class,
            'category' => Category::class,
            'offer' => Offer::class,
            'banner' => Banner::class,
            'delivery_zone' => DeliveryZone::class,
            'store_setting' => StoreSetting::class,
            'order' => Order::class,
            'payment' => Payment::class,
            'user' => User::class,
        ]);

        // super_admin + manager manage the catalog; staff only sees the dashboard for now.
        Gate::define('manage-catalog', fn (User $user) => $user->isActive()
            && $user->hasAdminRole(AdminRole::SuperAdmin, AdminRole::Manager));

        // Delivery zones: same people as the catalog.
        Gate::define('manage-delivery', fn (User $user) => $user->isActive()
            && $user->hasAdminRole(AdminRole::SuperAdmin, AdminRole::Manager));

        // Orders: every active admin role, including staff who prepare/deliver orders.
        Gate::define('manage-orders', fn (User $user) => $user->isActive()
            && $user->hasAdminRole(AdminRole::SuperAdmin, AdminRole::Manager, AdminRole::Staff));

        // Customers and reports: super admin + manager (staff only handle orders).
        Gate::define('manage-customers', fn (User $user) => $user->isActive()
            && $user->hasAdminRole(AdminRole::SuperAdmin, AdminRole::Manager));

        Gate::define('view-reports', fn (User $user) => $user->isActive()
            && $user->hasAdminRole(AdminRole::SuperAdmin, AdminRole::Manager));

        Gate::define('view-audit-logs', fn (User $user) => $user->isActive()
            && $user->hasAdminRole(AdminRole::SuperAdmin));

        // Store identity and minimum order: super admin only.
        Gate::define('manage-settings', fn (User $user) => $user->isActive()
            && $user->hasAdminRole(AdminRole::SuperAdmin));

        // System status, failed jobs and integrations: super admin only.
        Gate::define('manage-system', fn (User $user) => $user->isActive()
            && $user->hasAdminRole(AdminRole::SuperAdmin));

        // For the future OTP endpoints (the OtpService also limits per phone + purpose).
        RateLimiter::for('otp', fn (Request $request) => [
            Limit::perMinute(3)->by('otp-ip:'.$request->ip()),
            Limit::perHour(20)->by('otp-ip-hour:'.$request->ip()),
        ]);

        RateLimiter::for('checkout', fn (Request $request) => Limit::perMinute(6)->by('checkout:'.($request->user()?->id ?: $request->ip())));

        // Re-apply the admin check on every Livewire update request, not only on page load.
        Livewire::addPersistentMiddleware([EnsureUserIsAdmin::class, EnsurePhoneIsVerified::class]);

        // Order lifecycle => admin bell + (future) customer WhatsApp/SMS/email.
        foreach (OrderEvent::all() as $orderEvent) {
            Event::listen($orderEvent, NotifyAdminsAboutOrders::class);
            Event::listen($orderEvent, NotifyCustomerAboutOrder::class);
        }

        // Operational failures are logged with safe context only (the log tap redacts secrets too).
        Event::listen(JobFailed::class, fn (JobFailed $event) => Log::error('queue.job_failed', [
            'job' => $event->job->resolveName(),
            'queue' => $event->job->getQueue(),
            'connection' => $event->connectionName,
            'error' => $event->exception::class.': '.Str::limit($event->exception->getMessage(), 300),
        ]));

        Event::listen(NotificationFailed::class, fn (NotificationFailed $event) => Log::warning('notification.failed', [
            'notification' => $event->notification::class,
            'channel' => $event->channel,
            'notifiable' => $event->notifiable instanceof Model ? $event->notifiable->getMorphClass().':'.$event->notifiable->getKey() : null,
        ]));

        // Guest cart => customer cart when signing in (or right after registering).
        Event::listen(Login::class, fn (Login $event) => app(CartService::class)->mergeGuestCart(
            $event->user,
            session()->pull(CartService::SESSION_KEY),
        ));

        // <x-layouts::app> for controller-rendered storefront pages (Livewire pages use layouts.app directly).
        Blade::anonymousComponentPath(resource_path('views/layouts'), 'layouts');

        View::composer(['layouts.*', 'store.*', 'account.*'], fn ($view) => $view->with('storeName', Store::name()));
    }
}
