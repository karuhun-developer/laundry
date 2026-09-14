<?php

use App\Http\Controllers\Api\V1\BranchCatalogController;
use App\Http\Controllers\Api\V1\CustomerIndexController;
use App\Http\Controllers\Api\V1\CustomerNearestBranchController;
use App\Http\Controllers\Api\V1\CustomerOrderIndexController;
use App\Http\Controllers\Api\V1\DetachCustomerController;
use App\Http\Controllers\Api\V1\MobileContextController;
use App\Http\Controllers\Api\V1\MobileOrderIndexController;
use App\Http\Controllers\Api\V1\NearestBranchController;
use App\Http\Controllers\Api\V1\OrderController;
use App\Http\Controllers\Api\V1\OrderPricingController;
use App\Http\Controllers\Api\V1\OrderReceiptController;
use App\Http\Controllers\Api\V1\OrderStatusEventController;
use App\Http\Controllers\Api\V1\OrderTrackingController;
use App\Http\Controllers\Api\V1\OrderWhatsAppSuggestionController;
use App\Http\Controllers\Api\V1\PaymentController;
use App\Http\Controllers\Api\V1\PaymentGatewayMethodController;
use App\Http\Controllers\Api\V1\PaymentGatewayWebhookController;
use App\Http\Controllers\Api\V1\PaymentStatusEventController;
use App\Http\Controllers\Api\V1\PriceQuoteController;
use App\Http\Controllers\Api\V1\ResetCustomerPinController;
use App\Http\Controllers\Api\V1\StoreCustomerController;
use App\Http\Controllers\Api\V1\UpdateCustomerController;
use App\Http\Controllers\Api\V1\UpdateCustomerPinController;
use App\Http\Middleware\EnsureCustomerSessionIsCurrent;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->name('api.v1.')->middleware(EnsureCustomerSessionIsCurrent::class)->group(function (): void {
    Route::post('payment-gateways/paywuz/webhook', PaymentGatewayWebhookController::class)
        ->name('payment-gateways.paywuz.webhook');
    Route::get('payment-gateway-methods', PaymentGatewayMethodController::class)
        ->middleware('auth:sanctum')
        ->name('payment-gateway-methods.index');
    Route::get('mobile/context', MobileContextController::class)
        ->middleware('auth:sanctum')
        ->name('mobile.context');
    Route::get('mobile/branches/nearest', CustomerNearestBranchController::class)
        ->middleware('auth:sanctum')
        ->name('mobile.branches.nearest');
    Route::get('mobile/orders', CustomerOrderIndexController::class)
        ->middleware('auth:sanctum')
        ->name('mobile.orders.index');
    Route::put('mobile/account/pin', UpdateCustomerPinController::class)
        ->middleware('auth:sanctum')
        ->name('mobile.account.pin.update');
    Route::post('mobile/customers', StoreCustomerController::class)
        ->middleware('auth:sanctum')
        ->name('mobile.customers.store');
    Route::patch('mobile/customers/{customer}', UpdateCustomerController::class)
        ->middleware('auth:sanctum')
        ->name('mobile.customers.update');
    Route::post('mobile/customers/{customer}/reset-pin', ResetCustomerPinController::class)
        ->middleware('auth:sanctum')
        ->name('mobile.customers.reset-pin');
    Route::delete('mobile/customers/{customer}', DetachCustomerController::class)
        ->middleware('auth:sanctum')
        ->name('mobile.customers.destroy');
    Route::get('organizations/{organization:slug}/orders', MobileOrderIndexController::class)
        ->middleware('auth:sanctum')
        ->name('organizations.orders.index');
    Route::get('organizations/{organization:slug}/customers', CustomerIndexController::class)
        ->middleware('auth:sanctum')
        ->name('organizations.customers.index');
    Route::get('organizations/{organization:slug}/branches/nearest', NearestBranchController::class)
        ->name('organizations.branches.nearest');
    Route::get('organizations/{organization:slug}/branches/{branch}/catalog', BranchCatalogController::class)
        ->name('organizations.branches.catalog');
    Route::post(
        'organizations/{organization:slug}/branches/{branch}/catalog/{priceOption}/quote',
        PriceQuoteController::class,
    )->name('organizations.branches.catalog.quote');
    Route::post(
        'organizations/{organization:slug}/branches/{branch}/orders',
        OrderController::class,
    )->middleware('auth:sanctum')
        ->scopeBindings()
        ->name('organizations.branches.orders.store');
    Route::get(
        'organizations/{organization:slug}/branches/{branch}/orders/{order}',
        OrderTrackingController::class,
    )->middleware('auth:sanctum')
        ->scopeBindings()
        ->name('organizations.branches.orders.show');
    Route::post(
        'organizations/{organization:slug}/branches/{branch}/orders/{order}/status-events',
        OrderStatusEventController::class,
    )->middleware('auth:sanctum')
        ->scopeBindings()
        ->name('organizations.branches.orders.status-events.store');
    Route::post(
        'organizations/{organization:slug}/branches/{branch}/orders/{order}/pricing',
        OrderPricingController::class,
    )->middleware('auth:sanctum')
        ->scopeBindings()
        ->name('organizations.branches.orders.pricing.store');
    Route::get(
        'organizations/{organization:slug}/branches/{branch}/orders/{order}/whatsapp-suggestion',
        OrderWhatsAppSuggestionController::class,
    )->middleware('auth:sanctum')
        ->scopeBindings()
        ->name('organizations.branches.orders.whatsapp-suggestion.show');
    Route::post(
        'organizations/{organization:slug}/branches/{branch}/orders/{order}/payments',
        PaymentController::class,
    )->middleware('auth:sanctum')
        ->scopeBindings()
        ->name('organizations.branches.orders.payments.store');
    Route::post(
        'organizations/{organization:slug}/branches/{branch}/orders/{order}/payments/{payment}/status-events',
        PaymentStatusEventController::class,
    )->middleware('auth:sanctum')
        ->scopeBindings()
        ->name('organizations.branches.orders.payments.status-events.store');
    Route::get(
        'organizations/{organization:slug}/branches/{branch}/orders/{order}/receipt',
        OrderReceiptController::class,
    )->middleware('auth:sanctum')
        ->scopeBindings()
        ->name('organizations.branches.orders.receipt.show');
});

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');
