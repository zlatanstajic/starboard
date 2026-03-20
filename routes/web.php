<?php

declare(strict_types=1);

use App\Http\Controllers\NetworkProfileController;
use App\Http\Controllers\NetworkSourceController;
use App\Http\Controllers\NetworkTagController;
use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;

/**
 * Home
 */
Route::get('/', fn () => view('welcome'))->name('home');

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
});

require __DIR__.'/auth.php';
