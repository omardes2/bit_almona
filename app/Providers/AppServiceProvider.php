<?php

namespace App\Providers;

use App\Enums\AdminRole;
use App\Http\Middleware\EnsureUserIsAdmin;
use App\Models\Banner;
use App\Models\Category;
use App\Models\Offer;
use App\Models\Product;
use App\Models\User;
use App\Services\Media\GdImageProcessor;
use App\Services\Media\ImageProcessor;
use App\Support\Store;
use Carbon\CarbonImmutable;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
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
        ]);

        // super_admin + manager manage the catalog; staff only sees the dashboard for now.
        Gate::define('manage-catalog', fn (User $user) => $user->isActive()
            && $user->hasAdminRole(AdminRole::SuperAdmin, AdminRole::Manager));

        // Re-apply the admin check on every Livewire update request, not only on page load.
        Livewire::addPersistentMiddleware([EnsureUserIsAdmin::class]);

        View::composer(['layouts.*', 'home'], fn ($view) => $view->with('storeName', Store::name()));
    }
}
