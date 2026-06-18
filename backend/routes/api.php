<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\RoomRateCodeController;
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

    // Room Rate Codes (Mapped to SP1340)
    Route::apiResource('room-rate-codes', RoomRateCodeController::class)->parameters([
        'room-rate-codes' => 'ma'
    ]);
    Route::post('room-rate-codes/{ma}/plans', [RoomRateCodeController::class, 'saveRatePlan']);
    Route::post('room-rate-codes/{ma}/daily-mappings', [RoomRateCodeController::class, 'saveDailyMappings']);

    // Hotel settings
    Route::get('/hotel-settings', [\App\Http\Controllers\Api\HotelSettingController::class, 'show']);
    Route::put('/hotel-settings', [\App\Http\Controllers\Api\HotelSettingController::class, 'update']);
    Route::post('/hotel-settings/logo', [\App\Http\Controllers\Api\HotelSettingController::class, 'uploadLogo']);
    Route::delete('/hotel-settings/logo', [\App\Http\Controllers\Api\HotelSettingController::class, 'deleteLogo']);
    Route::post('/hotel-settings/qr-code', [\App\Http\Controllers\Api\HotelSettingController::class, 'uploadQrCode']);
    Route::delete('/hotel-settings/qr-code', [\App\Http\Controllers\Api\HotelSettingController::class, 'deleteQrCode']);

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
    Route::post('room-rate-codes/{ma}/plans', [\App\Http\Controllers\Api\RoomRateCodeController::class, 'saveRatePlan']);
    Route::delete('room-rate-codes/{ma}/plans/{code}', [\App\Http\Controllers\Api\RoomRateCodeController::class, 'deleteRatePlan']);
    Route::post('room-rate-codes/{ma}/daily-mappings', [\App\Http\Controllers\Api\RoomRateCodeController::class, 'saveDailyMappings']);

    // System Administration routes
    Route::apiResource('system-branches', \App\Http\Controllers\Api\SystemBranchController::class);
    Route::apiResource('users', \App\Http\Controllers\Api\UserController::class);
    Route::post('/users/{id}/signature', [\App\Http\Controllers\Api\UserController::class, 'uploadSignature']);
    Route::delete('/users/{id}/signature', [\App\Http\Controllers\Api\UserController::class, 'deleteSignature']);
    Route::get('/info-business', [\App\Http\Controllers\Api\InfoBusinessController::class, 'show']);
    Route::put('/info-business', [\App\Http\Controllers\Api\InfoBusinessController::class, 'update']);
    Route::post('/info-business/logo', [\App\Http\Controllers\Api\InfoBusinessController::class, 'uploadLogo']);
    Route::delete('/info-business/logo', [\App\Http\Controllers\Api\InfoBusinessController::class, 'deleteLogo']);
});

