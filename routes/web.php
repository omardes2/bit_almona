<?php

use App\Http\Controllers\Auth\LogoutController;
use App\Livewire\Account\Dashboard as AccountDashboard;
use App\Livewire\Admin\Auth\Login as AdminLogin;
use App\Livewire\Admin\Banners\BannerForm;
use App\Livewire\Admin\Banners\BannerIndex;
use App\Livewire\Admin\Categories\CategoryForm;
use App\Livewire\Admin\Categories\CategoryIndex;
use App\Livewire\Admin\Dashboard as AdminDashboard;
use App\Livewire\Admin\Offers\OfferForm;
use App\Livewire\Admin\Offers\OfferIndex;
use App\Livewire\Admin\Products\ProductForm;
use App\Livewire\Admin\Products\ProductIndex;
use App\Livewire\Auth\Login;
use App\Livewire\Auth\Register;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Storefront
|--------------------------------------------------------------------------
*/

Route::view('/', 'home')->name('home');

/*
|--------------------------------------------------------------------------
| Customer authentication
|--------------------------------------------------------------------------
*/

Route::middleware(['guest', 'throttle:auth'])->group(function () {
    Route::livewire('/login', Login::class)->name('login');
    Route::livewire('/register', Register::class)->name('register');
});

Route::post('/logout', LogoutController::class)->middleware('auth')->name('logout');

/*
|--------------------------------------------------------------------------
| Customer account
|--------------------------------------------------------------------------
*/

Route::middleware(['auth', 'customer'])->group(function () {
    Route::livewire('/account', AccountDashboard::class)->name('account');
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
    });
});
