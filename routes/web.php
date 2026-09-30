<?php

use App\Http\Controllers\Account\OrderController as AccountOrderController;
use App\Http\Controllers\Admin\ExportController;
use App\Http\Controllers\Auth\LogoutController;
use App\Http\Controllers\Store\CategoryController;
use App\Http\Controllers\Store\HomeController;
use App\Http\Controllers\Store\OffersController;
use App\Http\Controllers\Store\PageController;
use App\Http\Controllers\Store\ProductController;
use App\Http\Controllers\Store\SeoController;
use App\Http\Middleware\EnsurePhoneIsVerified;
use App\Livewire\Account\Addresses as AccountAddresses;
use App\Livewire\Account\ChangePhone;
use App\Livewire\Account\Dashboard as AccountDashboard;
use App\Livewire\Account\NotificationPreferences;
use App\Livewire\Account\Orders as AccountOrders;
use App\Livewire\Account\VerifyPhone;
use App\Livewire\Admin\AuditLogs\AuditLogIndex;
use App\Livewire\Admin\Auth\Login as AdminLogin;
use App\Livewire\Admin\Banners\BannerForm;
use App\Livewire\Admin\Banners\BannerIndex;
use App\Livewire\Admin\Categories\CategoryForm;
use App\Livewire\Admin\Categories\CategoryIndex;
use App\Livewire\Admin\Customers\CustomerIndex;
use App\Livewire\Admin\Customers\CustomerShow;
use App\Livewire\Admin\Dashboard as AdminDashboard;
use App\Livewire\Admin\DeliveryZones\ZoneForm;
use App\Livewire\Admin\DeliveryZones\ZoneIndex;
use App\Livewire\Admin\Notifications\NotificationCenter;
use App\Livewire\Admin\Offers\OfferForm;
use App\Livewire\Admin\Offers\OfferIndex;
use App\Livewire\Admin\Orders\OrderIndex;
use App\Livewire\Admin\Orders\OrderShow;
use App\Livewire\Admin\Products\ProductForm;
use App\Livewire\Admin\Products\ProductIndex;
use App\Livewire\Admin\Reports\ReportsPage;
use App\Livewire\Admin\Settings\Integrations;
use App\Livewire\Admin\Settings\StoreSettingsForm;
use App\Livewire\Admin\System\FailedJobs;
use App\Livewire\Admin\System\SystemStatus;
use App\Livewire\Auth\ForgotPassword;
use App\Livewire\Auth\Login;
use App\Livewire\Auth\Register;
use App\Livewire\Store\CartPage;
use App\Livewire\Store\Checkout;
use App\Livewire\Store\SearchPage;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Storefront
|--------------------------------------------------------------------------
*/

Route::get('/', HomeController::class)->name('home');
Route::get('/category/{category}', [CategoryController::class, 'show'])->name('category.show');
Route::get('/product/{product}', [ProductController::class, 'show'])->name('product.show');
Route::get('/offers', OffersController::class)->name('offers');
Route::livewire('/search', SearchPage::class)->name('search');
Route::livewire('/cart', CartPage::class)->name('cart');

Route::get('/about', [PageController::class, 'about'])->name('about');
Route::get('/contact', [PageController::class, 'contact'])->name('contact');
Route::get('/privacy-policy', [PageController::class, 'privacy'])->name('privacy');
Route::get('/terms', [PageController::class, 'terms'])->name('terms');

Route::get('/sitemap.xml', [SeoController::class, 'sitemap'])->name('sitemap');
Route::get('/robots.txt', [SeoController::class, 'robots'])->name('robots');

/*
|--------------------------------------------------------------------------
| Customer authentication
|--------------------------------------------------------------------------
*/

Route::middleware(['guest', 'throttle:auth'])->group(function () {
    Route::livewire('/login', Login::class)->name('login');
    Route::livewire('/register', Register::class)->name('register');
    Route::livewire('/forgot-password', ForgotPassword::class)->name('password.forgot');
});

Route::post('/logout', LogoutController::class)->middleware('auth')->name('logout');

/*
|--------------------------------------------------------------------------
| Customer account
|--------------------------------------------------------------------------
*/

Route::middleware(['auth', 'customer'])->group(function () {
    Route::livewire('/account', AccountDashboard::class)->name('account');
    Route::livewire('/account/addresses', AccountAddresses::class)->name('account.addresses');
    Route::livewire('/account/orders', AccountOrders::class)->name('account.orders');
    Route::get('/account/orders/{order}', [AccountOrderController::class, 'show'])->name('account.orders.show');
    Route::livewire('/account/notifications', NotificationPreferences::class)->name('account.notifications');
    Route::livewire('/account/phone', ChangePhone::class)->name('account.phone');
    Route::livewire('/account/verify-phone', VerifyPhone::class)->name('account.verify-phone');

    // Checkout (customers only; guests are sent to login and brought back here).
    // A verified phone is required only when REQUIRE_PHONE_VERIFICATION=true and an OTP sender exists.
    Route::livewire('/checkout', Checkout::class)->middleware(EnsurePhoneIsVerified::class)->name('checkout');
    Route::get('/order-confirmed/{order}', [AccountOrderController::class, 'confirmed'])->name('order.confirmed');
});

/*
|--------------------------------------------------------------------------
| Admin panel
|--------------------------------------------------------------------------
*/

Route::prefix('admin')->name('admin.')->group(function () {
    Route::livewire('/login', AdminLogin::class)
        ->middleware(['guest', 'throttle:auth'])
        ->name('login');

    Route::middleware(['auth', 'admin'])->group(function () {
        Route::livewire('/', AdminDashboard::class)->name('dashboard');

        // Catalog management: super_admin + manager (see the manage-catalog gate).
        Route::middleware('admin:super_admin,manager')->group(function () {
            Route::livewire('/categories', CategoryIndex::class)->name('categories.index');
            Route::livewire('/categories/create', CategoryForm::class)->name('categories.create');
            Route::livewire('/categories/{category}/edit', CategoryForm::class)->name('categories.edit');

            Route::livewire('/products', ProductIndex::class)->name('products.index');
            Route::livewire('/products/create', ProductForm::class)->name('products.create');
            Route::livewire('/products/{product}/edit', ProductForm::class)->name('products.edit');

            Route::livewire('/offers', OfferIndex::class)->name('offers.index');
            Route::livewire('/offers/create', OfferForm::class)->name('offers.create');
            Route::livewire('/offers/{offer}/edit', OfferForm::class)->name('offers.edit');

            Route::livewire('/banners', BannerIndex::class)->name('banners.index');
            Route::livewire('/banners/create', BannerForm::class)->name('banners.create');
            Route::livewire('/banners/{banner}/edit', BannerForm::class)->name('banners.edit');
        });

        Route::middleware('can:manage-orders')->group(function () {
            Route::livewire('/orders', OrderIndex::class)->name('orders.index');
            Route::livewire('/orders/{order}', OrderShow::class)->name('orders.show');
        });

        Route::middleware('can:manage-delivery')->group(function () {
            Route::livewire('/delivery-zones', ZoneIndex::class)->name('delivery-zones.index');
            Route::livewire('/delivery-zones/create', ZoneForm::class)->name('delivery-zones.create');
            Route::livewire('/delivery-zones/{zone}/edit', ZoneForm::class)->name('delivery-zones.edit');
        });

        Route::livewire('/settings', StoreSettingsForm::class)->middleware('can:manage-settings')->name('settings');

        // System status, failed jobs and integration status: super admin only.
        Route::middleware('can:manage-system')->group(function () {
            Route::livewire('/system', SystemStatus::class)->name('system');
            Route::livewire('/system/failed-jobs', FailedJobs::class)->name('system.failed-jobs');
            Route::livewire('/settings/integrations', Integrations::class)->name('integrations');
        });

        Route::livewire('/notifications', NotificationCenter::class)->name('notifications');

        Route::middleware('can:manage-customers')->group(function () {
            Route::livewire('/customers', CustomerIndex::class)->name('customers.index');
            Route::livewire('/customers/{customer}', CustomerShow::class)->name('customers.show');
        });

        Route::livewire('/reports', ReportsPage::class)->middleware('can:view-reports')->name('reports');
        Route::livewire('/audit-logs', AuditLogIndex::class)->middleware('can:view-audit-logs')->name('audit-logs');

        // Authorisation per export type happens in the controller.
        Route::get('/exports/{type}', ExportController::class)
            ->whereIn('type', ['orders', 'customers', 'products'])
            ->middleware('throttle:10,1')
            ->name('exports');
    });
});
