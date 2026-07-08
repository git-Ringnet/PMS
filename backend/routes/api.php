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

    Route::get('/system-time', function () {
        return response()->json([
            'time' => now()->timezone('Asia/Ho_Chi_Minh')->toIso8601String()
        ]);
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
    Route::get('/room-class-groups', [\App\Http\Controllers\Api\RoomClassGroupController::class, 'index']);
    Route::post('/room-class-groups', [\App\Http\Controllers\Api\RoomClassGroupController::class, 'store']);
    Route::put('/room-class-groups/{id}', [\App\Http\Controllers\Api\RoomClassGroupController::class, 'update']);
    Route::delete('/room-class-groups/{id}', [\App\Http\Controllers\Api\RoomClassGroupController::class, 'destroy']);

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

    // Room availability grid
    Route::get('/availability', [\App\Http\Controllers\Api\AvailabilityController::class, 'index']);

    // Company settings
    Route::apiResource('markets', \App\Http\Controllers\Api\MarketController::class);
    Route::apiResource('customer-sources', \App\Http\Controllers\Api\CustomerSourceController::class);
    Route::apiResource('branches', \App\Http\Controllers\Api\BranchController::class);
    Route::apiResource('branches-total', \App\Http\Controllers\Api\BranchTotalController::class);
    Route::apiResource('bookers', \App\Http\Controllers\Api\BookerController::class);
    Route::post('companies/sync', [\App\Http\Controllers\Api\CompanyController::class, 'sync']);
    Route::get('companies/export', [\App\Http\Controllers\Api\CompanyController::class, 'export']);
    Route::post('companies/import', [\App\Http\Controllers\Api\CompanyController::class, 'import']);
    Route::get('companies/template', [\App\Http\Controllers\Api\CompanyController::class, 'template']);
    Route::apiResource('companies', \App\Http\Controllers\Api\CompanyController::class);

    // Hotel details configuration routes
    Route::apiResource('hotel-services', \App\Http\Controllers\Api\HotelServiceController::class);
    Route::apiResource('shifts', \App\Http\Controllers\Api\ShiftController::class);
    Route::apiResource('hotel-configs', \App\Http\Controllers\Api\HotelConfigController::class);
    Route::post('templates/{id}/duplicate', [\App\Http\Controllers\Api\TemplateController::class, 'duplicate']);
    Route::post('templates/{id}/make-default', [\App\Http\Controllers\Api\TemplateController::class, 'makeDefault']);
    Route::post('templates/{id}/remove-default', [\App\Http\Controllers\Api\TemplateController::class, 'removeDefault']);
    Route::get('templates/{id}/versions', [\App\Http\Controllers\Api\TemplateController::class, 'versions']);
    Route::post('templates/{id}/rollback', [\App\Http\Controllers\Api\TemplateController::class, 'rollback']);
    Route::match(['get', 'post'], 'templates/{id}/preview', [\App\Http\Controllers\Api\TemplateController::class, 'preview']);
    Route::post('templates/{id}/render', [\App\Http\Controllers\Api\TemplateController::class, 'render']);
    Route::post('templates/upload-image', [\App\Http\Controllers\Api\TemplateController::class, 'uploadImage']);
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
    Route::apiResource('lost-and-found', \App\Http\Controllers\Api\LostAndFoundController::class);
    Route::apiResource('users', \App\Http\Controllers\Api\UserController::class);
    Route::apiResource('product-categories', \App\Http\Controllers\Api\ProductCategoryController::class);
    Route::post('/products/bulk-toggle-active', [\App\Http\Controllers\Api\ProductController::class, 'bulkToggleActive']);
    Route::get('/products/export', [\App\Http\Controllers\Api\ProductController::class, 'exportExcel']);
    Route::post('/products/import', [\App\Http\Controllers\Api\ProductController::class, 'importExcel']);
    Route::apiResource('products', \App\Http\Controllers\Api\ProductController::class);
    Route::apiResource('inventories', \App\Http\Controllers\Api\InventoryController::class);
    Route::post('/users/{id}/signature', [\App\Http\Controllers\Api\UserController::class, 'uploadSignature']);
    Route::delete('/users/{id}/signature', [\App\Http\Controllers\Api\UserController::class, 'deleteSignature']);
    Route::get('/info-business', [\App\Http\Controllers\Api\InfoBusinessController::class, 'show']);
    Route::put('/info-business', [\App\Http\Controllers\Api\InfoBusinessController::class, 'update']);
    Route::post('/info-business/logo', [\App\Http\Controllers\Api\InfoBusinessController::class, 'uploadLogo']);
    Route::delete('/info-business/logo', [\App\Http\Controllers\Api\InfoBusinessController::class, 'deleteLogo']);


    Route::get('/departments', [\App\Http\Controllers\Api\DepartmentController::class, 'index']);
    Route::post('/outlets/reorder', [\App\Http\Controllers\Api\OutletController::class, 'reorder']);
    Route::apiResource('outlets', \App\Http\Controllers\Api\OutletController::class);
    Route::apiResource('fb-locations', \App\Http\Controllers\Api\FbLocationController::class);
    Route::post('fb-tables/bulk-create', [\App\Http\Controllers\Api\FbTableController::class, 'bulkCreate']);
    Route::post('fb-tables/delete-row', [\App\Http\Controllers\Api\FbTableController::class, 'deleteRow']);
    Route::post('fb-tables/{from_id}/transfer/{to_id}', [\App\Http\Controllers\FbOrderController::class, 'transferTable']);
    Route::post('fb-tables/{from_id}/transfer-items/{to_id}', [\App\Http\Controllers\FbOrderController::class, 'transferItems']);
    Route::apiResource('fb-tables', \App\Http\Controllers\Api\FbTableController::class);

    // Dedicated F&B Menu definitions routes
    Route::post('/fb-products/bulk-toggle-active', [\App\Http\Controllers\Api\FbProductController::class, 'bulkToggleActive']);
    Route::post('/fb-products/bulk-update-status', [\App\Http\Controllers\Api\FbProductController::class, 'bulkUpdateStatus']);
    Route::apiResource('fb-product-categories', \App\Http\Controllers\Api\FbProductCategoryController::class);
    Route::apiResource('fb-products', \App\Http\Controllers\Api\FbProductController::class);
    Route::apiResource('fb-printers', \App\Http\Controllers\Api\FbPrinterController::class);
    Route::apiResource('fb-promotions', \App\Http\Controllers\FbPromotionController::class);
    
    // F&B Orders (Bills)
    Route::get('/fnb/tables/{tableId}/active-orders', [\App\Http\Controllers\FbOrderController::class, 'getActiveOrders']);
    Route::post('/fnb/tables/{tableId}/orders/sync', [\App\Http\Controllers\FbOrderController::class, 'syncOrders']);
    Route::get('/fnb/orders/{orderId}/print-logs', [\App\Http\Controllers\FbPrintLogController::class, 'getByOrder']);

    // Activity Log routes
    Route::get('/activity-logs', [\App\Http\Controllers\Api\ActivityLogController::class, 'index']);
    Route::get('/activity-logs/stats', [\App\Http\Controllers\Api\ActivityLogController::class, 'stats']);
});


Route::post('/test-log', function(Illuminate\Http\Request $request) { \Log::info('TEST PAYLOAD', $request->all()); return 'ok'; });
