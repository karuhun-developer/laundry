<?php

use App\Http\Controllers\AppUpdateDownloadController;
use App\Http\Controllers\Cms\BranchController;
use App\Http\Controllers\Cms\CashflowCategoryController;
use App\Http\Controllers\Cms\CashflowController;
use App\Http\Controllers\Cms\CityController;
use App\Http\Controllers\Cms\MessageTemplateController;
use App\Http\Controllers\Cms\OrderController;
use App\Http\Controllers\Cms\OrganizationController;
use App\Http\Controllers\Cms\PriceOptionController;
use App\Http\Controllers\Cms\ServiceController;
use App\Http\Controllers\Cms\UserController;
use App\Http\Controllers\CustomerLoginController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\LandingPageController;
use App\Http\Controllers\MobileAppController;
use App\Http\Controllers\PublicOrderPaymentCheckController;
use App\Http\Controllers\PublicOrderPaymentController;
use App\Http\Controllers\PublicOrderPaymentRegenerateController;
use App\Http\Controllers\PublicOrderReceiptController;
use App\Http\Controllers\PublicOrderTrackingController;
use App\Http\Controllers\ReportExportController;
use App\Http\Controllers\RoleRedirectController;
use Illuminate\Support\Facades\Route;

Route::get('/', LandingPageController::class)->name('home');
Route::post('login/customer', CustomerLoginController::class)
    ->middleware('guest')
    ->name('customer-login.store');
Route::get('pesanan/{order:order_number}', PublicOrderTrackingController::class)
    ->middleware(['signed', 'throttle:60,1'])
    ->name('orders.public.show');
Route::get('struk/{order}', PublicOrderReceiptController::class)
    ->middleware(['signed', 'throttle:60,1'])
    ->name('receipts.public.show');
Route::post('pesanan/{order:order_number}/bayar', PublicOrderPaymentController::class)
    ->middleware(['signed', 'throttle:10,1'])
    ->name('orders.public.payment.store');
Route::post('pesanan/{order:order_number}/payment/check', PublicOrderPaymentCheckController::class)
    ->middleware(['signed', 'throttle:120,1'])
    ->name('orders.public.payment.check');
Route::post('pesanan/{order:order_number}/payment/regenerate', PublicOrderPaymentRegenerateController::class)
    ->middleware(['signed', 'throttle:20,1'])
    ->name('orders.public.payment.regenerate');
Route::get('app-updates/{asset}/{filename}', AppUpdateDownloadController::class)
    ->whereNumber('asset')
    ->where('filename', 'laundryhub-v[0-9]+\.[0-9]+\.[0-9]+\.apk')
    ->middleware(['signed', 'throttle:10,1'])
    ->name('app-updates.download');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', RoleRedirectController::class)->name('dashboard');
    Route::get('app', MobileAppController::class)->name('mobile.app');

    Route::prefix('cms')->name('cms.')->group(function () {
        Route::get('/', DashboardController::class)->name('dashboard');
        Route::get('export', ReportExportController::class)->name('dashboard.export');
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
        Route::get('services', [ServiceController::class, 'index'])->name('services.index');
        Route::post('services', [ServiceController::class, 'store'])->name('services.store');
        Route::patch('services/{service}', [ServiceController::class, 'update'])->name('services.update');
        Route::delete('services/{service}', [ServiceController::class, 'destroy'])->name('services.destroy');
        Route::post('services/{service}/price-options', [PriceOptionController::class, 'store'])
            ->name('services.price-options.store');
        Route::patch('price-options/{priceOption}', [PriceOptionController::class, 'update'])
            ->name('price-options.update');
        Route::delete('price-options/{priceOption}', [PriceOptionController::class, 'destroy'])
            ->name('price-options.destroy');
        Route::get('users', [UserController::class, 'index'])->name('users.index');
        Route::post('users', [UserController::class, 'store'])->name('users.store');
        Route::patch('users/{user}', [UserController::class, 'update'])->name('users.update');
        Route::delete('users/{user}', [UserController::class, 'destroy'])->name('users.destroy');
        Route::get('orders', [OrderController::class, 'index'])->name('orders.index');
        Route::get('orders/export', [OrderController::class, 'export'])->name('orders.export');
        Route::get('orders/import/template', [OrderController::class, 'template'])->name('orders.template');
        Route::post('orders/import', [OrderController::class, 'import'])->name('orders.import');
        Route::get('orders/import/{importId}/status', [OrderController::class, 'importStatus'])->name('orders.import.status');
        Route::delete('orders/{order}', [OrderController::class, 'destroy'])->name('orders.destroy');
        Route::get('cashflow', [CashflowController::class, 'index'])->name('cashflow.index');
        Route::get('cashflow/export', [CashflowController::class, 'export'])->name('cashflow.export');
        Route::get('cashflow/import/template', [CashflowController::class, 'template'])->name('cashflow.template');
        Route::post('cashflow/import', [CashflowController::class, 'import'])->name('cashflow.import');
        Route::post('cashflow', [CashflowController::class, 'store'])->name('cashflow.store');
        Route::patch('cashflow/{cashflowEntry}', [CashflowController::class, 'update'])->name('cashflow.update');
        Route::delete('cashflow/{cashflowEntry}', [CashflowController::class, 'destroy'])->name('cashflow.destroy');
        Route::post('cashflow/categories', [CashflowCategoryController::class, 'store'])->name('cashflow.categories.store');
        Route::patch('cashflow/categories/{cashflowCategory}', [CashflowCategoryController::class, 'update'])->name('cashflow.categories.update');
        Route::delete('cashflow/categories/{cashflowCategory}', [CashflowCategoryController::class, 'destroy'])->name('cashflow.categories.destroy');
        Route::get('message-templates', [MessageTemplateController::class, 'index'])
            ->name('message-templates.index');
        Route::post('message-templates', [MessageTemplateController::class, 'store'])
            ->name('message-templates.store');
        Route::patch('message-templates/{messageTemplate}', [MessageTemplateController::class, 'update'])
            ->name('message-templates.update');
        Route::delete('message-templates/{messageTemplate}', [MessageTemplateController::class, 'destroy'])
            ->name('message-templates.destroy');
    });
});

require __DIR__.'/settings.php';
