<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;

// Public Authentication routes
Route::post('/login', [AuthController::class, 'login']);

// Protected routes (Sanctum)
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);
    
    Route::get('/user', function (Request $request) {
        return $request->user();
    });

    // Hotel settings
    Route::get('/hotel-settings', [\App\Http\Controllers\Api\HotelSettingController::class, 'show']);
    Route::put('/hotel-settings', [\App\Http\Controllers\Api\HotelSettingController::class, 'update']);

    // Room configurations
    Route::get('/room-classes', [\App\Http\Controllers\Api\RoomClassController::class, 'index']);
    Route::post('/room-classes', [\App\Http\Controllers\Api\RoomClassController::class, 'store']);
    Route::put('/room-classes/{id}', [\App\Http\Controllers\Api\RoomClassController::class, 'update']);
    Route::delete('/room-classes/{id}', [\App\Http\Controllers\Api\RoomClassController::class, 'destroy']);
    Route::get('/room-forms', [\App\Http\Controllers\Api\RoomFormController::class, 'index']);
    Route::post('/room-forms', [\App\Http\Controllers\Api\RoomFormController::class, 'store']);
    Route::put('/room-forms/{id}', [\App\Http\Controllers\Api\RoomFormController::class, 'update']);
    Route::delete('/room-forms/{id}', [\App\Http\Controllers\Api\RoomFormController::class, 'destroy']);
    Route::get('/standard-rates', [\App\Http\Controllers\Api\StandardRateController::class, 'index']);
    Route::post('/standard-rates', [\App\Http\Controllers\Api\StandardRateController::class, 'store']);
    Route::put('/standard-rates/{id}', [\App\Http\Controllers\Api\StandardRateController::class, 'update']);
    Route::delete('/standard-rates/{id}', [\App\Http\Controllers\Api\StandardRateController::class, 'destroy']);

    // Rooms management
    Route::get('/rooms/stats', [\App\Http\Controllers\Api\RoomController::class, 'stats']);
    Route::put('/rooms/{id}/status', [\App\Http\Controllers\Api\RoomController::class, 'updateStatus']);
    Route::apiResource('rooms', \App\Http\Controllers\Api\RoomController::class);

    // Room locks management
    Route::get('/room-locks/history/{room_id}', [\App\Http\Controllers\Api\RoomLockController::class, 'history']);
    Route::post('/room-locks/bulk-lock', [\App\Http\Controllers\Api\RoomLockController::class, 'bulkLock']);
    Route::post('/room-locks/bulk-unlock', [\App\Http\Controllers\Api\RoomLockController::class, 'bulkUnlock']);
    Route::apiResource('room-locks', \App\Http\Controllers\Api\RoomLockController::class);

    // Company settings
    Route::apiResource('markets', \App\Http\Controllers\Api\MarketController::class);
    Route::apiResource('customer-sources', \App\Http\Controllers\Api\CustomerSourceController::class);
    Route::apiResource('branches', \App\Http\Controllers\Api\BranchController::class);
    Route::apiResource('branches-total', \App\Http\Controllers\Api\BranchTotalController::class);
    Route::apiResource('bookers', \App\Http\Controllers\Api\BookerController::class);
    Route::apiResource('companies', \App\Http\Controllers\Api\CompanyController::class);

    // Hotel details configuration routes
    Route::apiResource('hotel-services', \App\Http\Controllers\Api\HotelServiceController::class);
    Route::apiResource('shifts', \App\Http\Controllers\Api\ShiftController::class);
    Route::apiResource('hotel-configs', \App\Http\Controllers\Api\HotelConfigController::class);
    Route::apiResource('templates', \App\Http\Controllers\Api\TemplateController::class);

    // System configuration routes
    Route::apiResource('payment-methods', \App\Http\Controllers\Api\PaymentMethodController::class);
    Route::apiResource('currencies', \App\Http\Controllers\Api\CurrencyController::class);
    Route::apiResource('units-of-measure', \App\Http\Controllers\Api\UnitOfMeasureController::class);
    Route::apiResource('room-rate-codes', \App\Http\Controllers\Api\RoomRateCodeController::class);
    Route::apiResource('registration-statuses', \App\Http\Controllers\Api\RegistrationStatusController::class);
});


