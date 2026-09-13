<?php

use App\Http\Controllers\Api\V1\NearestBranchController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->name('api.v1.')->group(function (): void {
    Route::get('organizations/{organization:slug}/branches/nearest', NearestBranchController::class)
        ->name('organizations.branches.nearest');
});

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');
