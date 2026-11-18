<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\BuyerController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\LoadingController;
use App\Http\Controllers\LookupController;
use App\Http\Controllers\MillController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\RecordsController;
use App\Http\Controllers\RiceVarietyController;
use App\Http\Controllers\SampleBundleController;
use App\Http\Controllers\SampleController;
use App\Http\Controllers\SampleDispatchController;
use App\Http\Controllers\SearchController;
use App\Http\Controllers\Settings\UserController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('login', [LoginController::class, 'create'])->name('login');
    Route::post('login', [LoginController::class, 'store'])->middleware('throttle:10,1')->name('login.store');
});

Route::middleware(['auth', 'active'])->group(function () {
    Route::post('logout', [LoginController::class, 'destroy'])->name('logout');

    Route::get('/', DashboardController::class)->name('dashboard');
    Route::get('search', SearchController::class)->name('search');
    Route::get('records', [RecordsController::class, 'index'])->name('records.index');

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

    Route::get('loading', [LoadingController::class, 'index'])->name('loading.index');
    Route::get('dispatches/{dispatch}/loadings/create', [LoadingController::class, 'create'])->name('loadings.create');
    Route::post('dispatches/{dispatch}/loadings', [LoadingController::class, 'store'])->name('loadings.store');
    Route::patch('dispatches/{dispatch}/complete-loading', [LoadingController::class, 'completeOrder'])->name('dispatches.complete-loading');
    Route::patch('dispatches/{dispatch}/reopen-loading', [LoadingController::class, 'reopenOrder'])->name('dispatches.reopen-loading');
    Route::patch('loadings/{loading}/loaded', [LoadingController::class, 'markLoaded'])->name('loadings.loaded');
    Route::post('loadings/{loading}/photos', [LoadingController::class, 'storePhotos'])->name('loadings.photos.store');
    Route::get('loading-photos/{photo}', [LoadingController::class, 'photo'])->name('loading-photos.show');
    Route::delete('loading-photos/{photo}', [LoadingController::class, 'destroyPhoto'])->name('loading-photos.destroy');
    Route::resource('loadings', LoadingController::class)->only(['show', 'edit', 'update', 'destroy']);

    Route::resource('mills', MillController::class);
    Route::resource('buyers', BuyerController::class);
    Route::resource('varieties', RiceVarietyController::class)->except(['show', 'create'])
        ->parameters(['varieties' => 'variety']);

    Route::prefix('lookup')->name('lookup.')->middleware('throttle:120,1')->group(function () {
        Route::get('mills', [LookupController::class, 'mills'])->name('mills');
        Route::get('buyers', [LookupController::class, 'buyers'])->name('buyers');
        Route::get('samples', [LookupController::class, 'samples'])->name('samples');
    });

    Route::prefix('settings')->middleware('admin')->group(function () {
        Route::resource('users', UserController::class)->except(['show', 'destroy']);
        Route::patch('users/{user}/toggle-active', [UserController::class, 'toggleActive'])->name('users.toggle-active');
    });
});
