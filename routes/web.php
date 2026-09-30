<?php

use App\Http\Controllers\Auth\LogoutController;
use App\Livewire\Account\Dashboard as AccountDashboard;
use App\Livewire\Admin\Auth\Login as AdminLogin;
use App\Livewire\Admin\Dashboard as AdminDashboard;
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
    });
});
