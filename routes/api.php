<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\User\UserController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

// ============================================
// Public Routes (sin autenticación)
// ============================================
Route::group(['prefix' => 'auth'], function () {
    Route::post('/login', [AuthController::class, 'login'])->name('auth.login');
    Route::post('/register', [AuthController::class, 'register'])->name('auth.register');
});

// ============================================
// Protected Routes (requiere autenticación)
// ============================================
Route::middleware('auth:sanctum')->group(function () {
    // Auth routes
    Route::group(['prefix' => 'auth'], function () {
        Route::post('/logout', [AuthController::class, 'logout'])->name('auth.logout');
    });

    // User routes (CRUD)
    Route::apiResource('users', UserController::class);

    // User middleware (authorization)
    Route::middleware('can:view-users')->group(function () {
        Route::get('/users', [UserController::class, 'index']);
    });
});

// ============================================
// Health check
// ============================================
Route::get('/health', function () {
    return response()->json([
        'status' => 'ok',
        'message' => 'API is running',
    ]);
});
