<?php

use App\Http\Middleware\EnsurePhoneIsVerified;
use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome');

// Route::view('dashboard', 'dashboard')
//     ->middleware(['auth', 'verified'])
//     ->name('dashboard');

Route::middleware(['auth', EnsurePhoneIsVerified::class])->group(function () {
    Route::view('dashboard', 'dashboard')->name('dashboard');
});

Route::view('profile', 'profile')
    ->middleware(['auth'])
    ->name('profile');

require __DIR__ . '/auth.php';
