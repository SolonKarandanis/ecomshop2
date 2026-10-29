<?php

use App\Http\Controllers\AttributeController;
use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\BrandController;
use App\Http\Controllers\CartController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\CheckoutController;
use App\Http\Controllers\ConfigController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\PaymentMethodController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ReviewController;
use App\Http\Controllers\SavedAddressController;
use App\Http\Controllers\StripeRedirectController;
use App\Http\Controllers\StripeWebhookController;
use App\Http\Controllers\SupplierOrderController;
use App\Http\Controllers\SupplierProductController;
use Illuminate\Support\Facades\Route;

Route::get('/config', [ConfigController::class, 'show']);

Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);
Route::post('/forgot-password', [AuthController::class, 'forgotPassword']);
Route::post('/reset-password', [AuthController::class, 'resetPassword']);

Route::get('/products', [ProductController::class, 'index']);
Route::get('/products/{slug}', [ProductController::class, 'show']);
Route::get('/products/{product}/reviews', [ReviewController::class, 'index']);
Route::get('/categories', [CategoryController::class, 'index']);
Route::get('/brands', [BrandController::class, 'index']);
Route::get('/attributes', [AttributeController::class, 'index']);
Route::get('/payment-methods', [PaymentMethodController::class, 'index']);

// Authenticated by Stripe's signature, not the session (ADR-0004).
Route::post('/stripe/webhook', StripeWebhookController::class)->name('stripe.webhook');

Route::get('/cart', [CartController::class, 'index']);
Route::post('/cart/items', [CartController::class, 'store']);
Route::patch('/cart/items/{cartItemId}', [CartController::class, 'update']);
Route::delete('/cart/items/{cartItemId}', [CartController::class, 'destroy']);

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/user', [AuthController::class, 'me']);
    Route::post('/checkout', [CheckoutController::class, 'store']);
    Route::get('/success', [StripeRedirectController::class, 'success'])->name('success');
    Route::get('/cancel', [StripeRedirectController::class, 'cancel'])->name('cancel');
    Route::get('/orders', [OrderController::class, 'index']);
    Route::get('/orders/export', [OrderController::class, 'export']);
    Route::get('/orders/{order}', [OrderController::class, 'show'])->name('orders.show');
    Route::get('/supplier-orders', [SupplierOrderController::class, 'index']);
    Route::post('/supplier-orders/{order}/ship', [SupplierOrderController::class, 'ship']);
    Route::post('/supplier-orders/{order}/deliver', [SupplierOrderController::class, 'deliver']);
    Route::post('/supplier-orders/{order}/cancel', [SupplierOrderController::class, 'cancel']);
    Route::get('/supplier-products', [SupplierProductController::class, 'index']);
    Route::post('/supplier-products', [SupplierProductController::class, 'store']);
    Route::get('/supplier-products/{product}', [SupplierProductController::class, 'show']);
    Route::patch('/supplier-products/{product}', [SupplierProductController::class, 'update']);
    Route::delete('/supplier-products/{product}', [SupplierProductController::class, 'destroy']);
    Route::post('/supplier-products/{product}/images', [SupplierProductController::class, 'storeImages']);
    Route::delete('/supplier-products/{product}/images/{media}', [SupplierProductController::class, 'destroyImage']);

    Route::get('/notifications', [NotificationController::class, 'index']);
    Route::get('/notifications/unread', [NotificationController::class, 'unread']);
    Route::get('/notifications/unread-count', [NotificationController::class, 'unreadCount']);

    Route::post('/products/{product}/reviews', [ReviewController::class, 'store']);
    Route::patch('/products/{product}/reviews/{review}/hide', [ReviewController::class, 'hide']);

    Route::get('/profile', [ProfileController::class, 'show']);
    Route::patch('/profile', [ProfileController::class, 'update']);
    Route::patch('/profile/password', [ProfileController::class, 'changePassword']);
    Route::get('/profile/addresses', [SavedAddressController::class, 'index']);
    Route::post('/profile/addresses', [SavedAddressController::class, 'store']);
    Route::patch('/profile/addresses/{address}', [SavedAddressController::class, 'update']);
    Route::delete('/profile/addresses/{address}', [SavedAddressController::class, 'destroy']);
    Route::post('/profile/addresses/{address}/default', [SavedAddressController::class, 'makeDefault']);
});
