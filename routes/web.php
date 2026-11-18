<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('login', [LoginController::class, 'create'])->name('login');
    Route::post('login', [LoginController::class, 'store'])->middleware('throttle:10,1')->name('login.store');
});

Route::middleware(['auth', 'active'])->group(function () {
    Route::post('logout', [LoginController::class, 'destroy'])->name('logout');

    Route::get('/', DashboardController::class)->name('dashboard');

    Route::get('account', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::put('account', [ProfileController::class, 'update'])->name('profile.update');
    Route::put('account/password', [ProfileController::class, 'password'])->name('profile.password');
});
