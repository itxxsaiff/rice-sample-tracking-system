<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\BuyerController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\LookupController;
use App\Http\Controllers\MillController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\RiceVarietyController;
use App\Http\Controllers\SampleBundleController;
use App\Http\Controllers\SampleController;
use App\Http\Controllers\SampleDispatchController;
use App\Http\Controllers\Settings\UserController;
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

    Route::get('samples/{sample}/photo', [SampleController::class, 'photo'])->name('samples.photo');
    Route::resource('samples', SampleController::class);
    Route::get('bundles/{bundle}/samples/create', [SampleBundleController::class, 'addSamples'])->name('bundles.samples.create');
    Route::post('bundles/{bundle}/samples', [SampleBundleController::class, 'storeSamples'])->name('bundles.samples.store');
    Route::resource('bundles', SampleBundleController::class)->parameters(['bundles' => 'bundle']);

    Route::get('dispatches/{dispatch}/respond', [SampleDispatchController::class, 'respondForm'])->name('dispatches.respond');
    Route::put('dispatches/{dispatch}/respond', [SampleDispatchController::class, 'respond'])->name('dispatches.respond.update');
    Route::patch('dispatches/{dispatch}/reopen', [SampleDispatchController::class, 'reopen'])->name('dispatches.reopen');
    Route::resource('dispatches', SampleDispatchController::class)->except(['edit', 'update']);

    Route::resource('mills', MillController::class);
    Route::resource('buyers', BuyerController::class);
    Route::resource('varieties', RiceVarietyController::class)->except(['show', 'create'])
        ->parameters(['varieties' => 'variety']);

    Route::prefix('lookup')->name('lookup.')->group(function () {
        Route::get('mills', [LookupController::class, 'mills'])->name('mills');
        Route::get('buyers', [LookupController::class, 'buyers'])->name('buyers');
        Route::get('samples', [LookupController::class, 'samples'])->name('samples');
    });

    Route::prefix('settings')->middleware('admin')->group(function () {
        Route::resource('users', UserController::class)->except(['show', 'destroy']);
        Route::patch('users/{user}/toggle-active', [UserController::class, 'toggleActive'])->name('users.toggle-active');
    });
});
