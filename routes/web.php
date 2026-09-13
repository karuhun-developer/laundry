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
        Route::patch('organizations/{organization}', [OrganizationController::class, 'update'])->name('organizations.update');
        Route::delete('organizations/{organization}', [OrganizationController::class, 'destroy'])->name('organizations.destroy');
        Route::get('branches', [BranchController::class, 'index'])->name('branches.index');
        Route::post('cities', [CityController::class, 'store'])->name('cities.store');
        Route::patch('cities/{city}', [CityController::class, 'update'])->name('cities.update');
        Route::delete('cities/{city}', [CityController::class, 'destroy'])->name('cities.destroy');
        Route::post('branches', [BranchController::class, 'store'])->name('branches.store');
        Route::patch('branches/{branch}', [BranchController::class, 'update'])->name('branches.update');
        Route::delete('branches/{branch}', [BranchController::class, 'destroy'])->name('branches.destroy');
        Route::post('services', [ServiceController::class, 'store'])->name('services.store');
        Route::post('services/{service}/price-options', [PriceOptionController::class, 'store'])
            ->name('services.price-options.store');
        Route::get('users', [UserController::class, 'index'])->name('users.index');
        Route::post('users', [UserController::class, 'store'])->name('users.store');
        Route::patch('users/{user}', [UserController::class, 'update'])->name('users.update');
        Route::delete('users/{user}', [UserController::class, 'destroy'])->name('users.destroy');
    });
});

require __DIR__.'/settings.php';
