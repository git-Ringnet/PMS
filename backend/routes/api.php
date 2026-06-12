<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

// Hotel settings
Route::get('/hotel-settings', [\App\Http\Controllers\Api\HotelSettingController::class, 'show']);
Route::put('/hotel-settings', [\App\Http\Controllers\Api\HotelSettingController::class, 'update']);

// Room configurations
Route::get('/room-classes', [\App\Http\Controllers\Api\RoomClassController::class, 'index']);
Route::get('/room-forms', [\App\Http\Controllers\Api\RoomFormController::class, 'index']);
Route::get('/standard-rates', [\App\Http\Controllers\Api\StandardRateController::class, 'index']);

// Rooms management
Route::get('/rooms/stats', [\App\Http\Controllers\Api\RoomController::class, 'stats']);
Route::put('/rooms/{id}/status', [\App\Http\Controllers\Api\RoomController::class, 'updateStatus']);
Route::apiResource('rooms', \App\Http\Controllers\Api\RoomController::class);

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


