<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\BuyerController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\LoadingController;
use App\Http\Controllers\LookupController;
use App\Http\Controllers\MillController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\PwaController;
use App\Http\Controllers\RecordsController;
use App\Http\Controllers\RiceVarietyController;
use App\Http\Controllers\SampleController;
use App\Http\Controllers\SampleDispatchController;
use App\Http\Controllers\SearchController;
use App\Http\Controllers\Settings\UserController;
use App\Http\Controllers\Sync\SyncController;
use Illuminate\Support\Facades\Route;

// Installable / offline app (public: contain no user data).
Route::get('sw.js', [PwaController::class, 'serviceWorker'])->name('pwa.sw');
Route::get('offline-shell', [PwaController::class, 'shell'])->name('pwa.shell');

// Offline app: connectivity/session check (works signed in or out).
Route::get('sync/status', [SyncController::class, 'status'])->middleware('throttle:120,1')->name('sync.status');

Route::middleware('guest')->group(function () {
    Route::get('login', [LoginController::class, 'create'])->name('login');
    Route::post('login', [LoginController::class, 'store'])->middleware('throttle:10,1')->name('login.store');
});

Route::middleware(['auth', 'active'])->group(function () {
    Route::post('logout', [LoginController::class, 'destroy'])->name('logout');

    Route::get('/', DashboardController::class)->name('dashboard');
    Route::get('search', SearchController::class)->name('search');

    Route::prefix('sync')->name('sync.')->middleware('throttle:240,1')->group(function () {
        Route::get('snapshot', [SyncController::class, 'snapshot'])->name('snapshot');
        Route::post('operations', [SyncController::class, 'operation'])->name('operations');
    });
    Route::get('records', [RecordsController::class, 'index'])->name('records.index');
    Route::get('records/pdf', [RecordsController::class, 'pdf'])->name('records.pdf');

    Route::get('account', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::put('account', [ProfileController::class, 'update'])->name('profile.update');
    Route::put('account/password', [ProfileController::class, 'password'])->name('profile.password');

    Route::get('samples/pdf', [SampleController::class, 'pdf'])->name('samples.pdf');
    Route::get('samples/{sample}/photo', [SampleController::class, 'photo'])->name('samples.photo');
    Route::resource('samples', SampleController::class);
    Route::redirect('bundles/{any?}', '/samples')->where('any', '.*'); // bundles were removed; old links land on Samples

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

    Route::get('mills/{mill}/pdf', [MillController::class, 'pdf'])->name('mills.pdf');
    Route::get('buyers/{buyer}/pdf', [BuyerController::class, 'pdf'])->name('buyers.pdf');
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
