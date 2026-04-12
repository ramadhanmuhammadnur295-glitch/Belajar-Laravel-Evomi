<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\CartController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\ProductController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Public Routes (Akses Tanpa Login)
|--------------------------------------------------------------------------
*/

// Auth
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

// Produk Evomi
// Route::get('/products', [ProductController::class, 'index']);
// Route::get('/products/{id}', [ProductController::class, 'show']);

Route::prefix('products')->group(function () {
    Route::get('/', [ProductController::class, 'index']);      // Read All
    Route::post('/', [ProductController::class, 'store']);     // Create
    Route::get('{id}', [ProductController::class, 'show']);    // Read Detail
    Route::post('{id}', [ProductController::class, 'update']);  // Update
    Route::delete('{id}', [ProductController::class, 'destroy']); // Delete
});

/*
|--------------------------------------------------------------------------
| Protected Routes (Harus Login / Menggunakan Sanctum)
|--------------------------------------------------------------------------
*/

Route::middleware('auth:sanctum')->group(function () {

    // User Profile & Logout
    Route::get('/user', function (Request $request) {
        return $request->user();
    });
    Route::post('/logout', [AuthController::class, 'logout']);

    // Keranjang Belanja (Cart)
    Route::prefix('cart')->group(function () {
        Route::get('/', [CartController::class, 'index']);           // Lihat isi keranjang
        Route::post('/add', [CartController::class, 'addToCart']);   // Tambah ke keranjang
        Route::delete('/{id}', [CartController::class, 'destroy']);  // Hapus satu item
        Route::delete('/', [CartController::class, 'clear']);        // Kosongkan keranjang
    });

    // Pesanan (Orders)
    Route::prefix('orders')->group(function () {
        Route::get('/', [OrderController::class, 'index']);        // Riwayat pesanan user
        Route::get('/{id}', [OrderController::class, 'show']);     // Detail pesanan tertentu
        Route::post('/checkout', [OrderController::class, 'checkout']); // Proses beli
    });
});
