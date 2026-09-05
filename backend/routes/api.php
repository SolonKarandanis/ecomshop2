<?php

use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\CartController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\CheckoutController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ReviewController;
use App\Http\Controllers\StripeRedirectController;
use App\Http\Controllers\SupplierOrderController;
use Illuminate\Support\Facades\Route;

Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);
Route::post('/forgot-password', [AuthController::class, 'forgotPassword']);
Route::post('/reset-password', [AuthController::class, 'resetPassword']);

Route::get('/products', [ProductController::class, 'index']);
Route::get('/products/{slug}', [ProductController::class, 'show']);
Route::get('/products/{product}/reviews', [ReviewController::class, 'index']);
Route::get('/categories', [CategoryController::class, 'index']);

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
    Route::get('/orders/{order}', [OrderController::class, 'show'])->name('orders.show');
    Route::get('/supplier-orders', [SupplierOrderController::class, 'index']);
    Route::post('/supplier-orders/{order}/ship', [SupplierOrderController::class, 'ship']);
    Route::post('/supplier-orders/{order}/deliver', [SupplierOrderController::class, 'deliver']);
    Route::post('/supplier-orders/{order}/cancel', [SupplierOrderController::class, 'cancel']);

    Route::get('/notifications', [NotificationController::class, 'index']);
    Route::get('/notifications/unread', [NotificationController::class, 'unread']);
    Route::get('/notifications/unread-count', [NotificationController::class, 'unreadCount']);

    Route::post('/products/{product}/reviews', [ReviewController::class, 'store']);
    Route::patch('/products/{product}/reviews/{review}/hide', [ReviewController::class, 'hide']);
});
