<?php

declare(strict_types=1);

use App\Http\Controllers\FilterListController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\NetworkProfileController;
use App\Http\Controllers\NetworkSourceController;
use App\Http\Controllers\NetworkTagController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\SharedFilterListController;
use Illuminate\Support\Facades\Route;

/**
 * Home
 */
Route::get('/', [HomeController::class, 'index'])->name('home');

/**
 * The hash is this page's only access control, so the endpoint is throttled to
 * blunt enumeration of the 200/404 oracle it would otherwise be.
 */
Route::get('/lists/{token}', [SharedFilterListController::class, 'show'])
    ->where('token', '[A-Za-z0-9]+')
    ->middleware('throttle:60,1')
    ->name('filter-lists.show');

// Locale switcher (English / Serbian)
Route::get('/locale/{locale}', function ($locale) {
    $available = ['en', 'sr'];

    abort_unless(in_array($locale, $available), 400);

    session(['locale' => $locale]);

    return back();
})->name('locale.switch');

/**
 * Profile
 */
Route::middleware('auth')->group(function (): void {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

/**
 * Routes protected by authentication and verification.
 */
Route::middleware(['auth', 'verified'])->group(function (): void {
    /**
     * Dashboard
     */
    Route::get('/dashboard', [NetworkProfileController::class, 'index'])->name('dashboard');

    /**
     * Network profiles
     */
    Route::prefix('network-profiles')
        ->name('network-profiles.')
        ->controller(NetworkProfileController::class)
        ->group(function (): void {
            Route::post('/', 'store')->name('store');
            Route::post('fetch', 'fetch')->middleware('throttle:6,1')->name('fetch');
            Route::get('fetch/status', 'fetchStatus')->name('fetch.status');
            Route::put('{networkProfile}', 'update')->name('update');
            Route::delete('{networkProfile}', 'destroy')->name('destroy');
            Route::post('{networkProfile}/record-visit', 'recordVisit')->name('recordVisit');
        });

    /**
     * Network sources
     */
    Route::prefix('network-sources')
        ->name('network-sources.')
        ->controller(NetworkSourceController::class)
        ->group(function (): void {
            Route::get('/', 'index')->name('index');
            Route::post('/', 'store')->name('store');
            Route::put('{networkSource}', 'update')->name('update');
            Route::delete('{networkSource}', 'destroy')->name('destroy');
        });

    /**
     * Network tags
     */
    Route::prefix('network-tags')
        ->name('network-tags.')
        ->controller(NetworkTagController::class)
        ->group(function (): void {
            Route::get('/', 'index')->name('index');
            Route::post('/', 'store')->name('store');
            Route::put('{networkTag}', 'update')->name('update');
            Route::delete('{networkTag}', 'destroy')->name('destroy');
        });

    Route::prefix('filter-lists')
        ->name('filter-lists.')
        ->controller(FilterListController::class)
        ->group(function (): void {
            Route::get('/', 'index')->name('index');
            Route::post('/', 'store')->name('store');
            Route::put('{filterList}', 'update')->name('update');
            Route::delete('{filterList}', 'destroy')->name('destroy');
        });
});

require __DIR__.'/auth.php';
