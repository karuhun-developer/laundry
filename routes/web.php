<?php

use App\Http\Controllers\Cms\BranchController;
use App\Http\Controllers\Cms\CityController;
use App\Http\Controllers\Cms\OrganizationController;
use App\Http\Controllers\Cms\PriceOptionController;
use App\Http\Controllers\Cms\ServiceController;
use App\Http\Controllers\Cms\UserController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'Welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::inertia('dashboard', 'Dashboard')->name('dashboard');

    Route::prefix('cms')->name('cms.')->group(function () {
        Route::get('organizations', [OrganizationController::class, 'index'])->name('organizations.index');
        Route::post('organizations', [OrganizationController::class, 'store'])->name('organizations.store');
        Route::get('branches', [BranchController::class, 'index'])->name('branches.index');
        Route::post('cities', [CityController::class, 'store'])->name('cities.store');
        Route::post('branches', [BranchController::class, 'store'])->name('branches.store');
        Route::post('services', [ServiceController::class, 'store'])->name('services.store');
        Route::post('services/{service}/price-options', [PriceOptionController::class, 'store'])
            ->name('services.price-options.store');
        Route::get('users', [UserController::class, 'index'])->name('users.index');
        Route::post('users', [UserController::class, 'store'])->name('users.store');
    });
});

require __DIR__.'/settings.php';
