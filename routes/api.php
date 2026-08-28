<?php

use App\Http\Controllers\Admin\BookingController as AdminBookingController;
use App\Http\Controllers\Admin\CategoryController as AdminCategoryController;
use App\Http\Controllers\Admin\EquipmentController as AdminEquipmentController;
use App\Http\Controllers\Admin\PaymentController as AdminPaymentController;
use App\Http\Controllers\Admin\SettingController as AdminSettingController;
use App\Http\Controllers\BookingController;
use App\Http\Controllers\EquipmentController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\SettingController;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/equipment', [EquipmentController::class, 'index']);
Route::get('/equipment/{equipment}/availability', [EquipmentController::class, 'availability']);
Route::get('/equipment/{equipment}', [EquipmentController::class, 'show']);
Route::get('/settings', [SettingController::class, 'index']);

Route::post('/register', function (RegisterRequest $request) {
    $user = User::create(array_merge($request->validated(), [
        'role' => User::ROLE_PELANGGAN,
    ]));

    return response()->json([
        'data' => new UserResource($user),
        'token' => $user->createToken('auth-token')->plainTextToken,
    ], 201);
})->middleware('throttle:10,1');

Route::post('/login', function (LoginRequest $request) {
    $user = $request->authenticate();

    return response()->json([
        'data' => new UserResource($user),
        'token' => $user->createToken('auth-token')->plainTextToken,
    ]);
})->middleware('throttle:10,1');

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/me', fn (Request $request) => new UserResource($request->user()));

    Route::post('/logout', function (Request $request) {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Logout berhasil.']);
    });

    Route::get('/bookings', [BookingController::class, 'index']);
    Route::post('/bookings', [BookingController::class, 'store'])
        ->middleware('throttle:20,1');
    Route::get('/bookings/{booking}', [BookingController::class, 'show']);
    Route::post('/bookings/{booking}/payments', [PaymentController::class, 'store']);

    Route::middleware('role:admin')->prefix('admin')->group(function () {
        Route::apiResource('categories', AdminCategoryController::class)->only([
            'index', 'store', 'show', 'update', 'destroy',
        ]);

        Route::apiResource('equipment', AdminEquipmentController::class)->only([
            'store', 'update', 'destroy',
        ]);

        Route::post('settings/hero-image', [AdminSettingController::class, 'updateHeroImage']);
        Route::delete('settings/hero-image', [AdminSettingController::class, 'destroyHeroImage']);
        Route::post('settings/maps-query', [AdminSettingController::class, 'updateMapsQuery']);
        Route::delete('settings/maps-query', [AdminSettingController::class, 'destroyMapsQuery']);

        Route::get('payments', [AdminPaymentController::class, 'index']);
        Route::post('payments/{payment}/verify', [AdminPaymentController::class, 'verify']);
        Route::post('payments/{payment}/reject', [AdminPaymentController::class, 'reject']);

        Route::get('bookings', [AdminBookingController::class, 'index']);
        Route::post('bookings/{booking}/confirm', [AdminBookingController::class, 'confirm']);
        Route::post('bookings/{booking}/return', [AdminBookingController::class, 'returnEquipment']);
    });
});
