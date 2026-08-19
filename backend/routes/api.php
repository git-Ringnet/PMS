<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\RoomRateCodeController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\NightAuditController;

// Public routes (không cần đăng nhập)
Route::post('/login', [AuthController::class, 'login']);
// GET hotel-settings public để App.vue đọc is_night_audit_running trước khi login
Route::get('/hotel-settings', [\App\Http\Controllers\Api\HotelSettingController::class, 'show']);

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

    // System date (ngày nghiệp vụ từ system_date_rolls)
    Route::get('/system-date', function () {
        $latest = \App\Models\SystemDateRoll::latest('id')->first();
        $systemDate = $latest
            ? \Carbon\Carbon::parse($latest->system_date)->toDateString()
            : now()->timezone('Asia/Ho_Chi_Minh')->toDateString();
        $shift = $latest ? $latest->shift : '1';
        return response()->json([
            'success' => true,
            'data' => [
                'system_date' => $systemDate,
                'shift' => $shift
            ],
        ]);
    });

    Route::post('/system-date/roll', function (Request $request) {
        $latest = \App\Models\SystemDateRoll::latest('id')->first();
        $currentSystemDate = $latest
            ? \Carbon\Carbon::parse($latest->system_date)
            : now()->timezone('Asia/Ho_Chi_Minh');

        $nextSystemDate = $currentSystemDate->copy()->addDay();

        $newRoll = \App\Models\SystemDateRoll::create([
            'system_date' => $nextSystemDate->toDateTimeString(),
            'actual_date' => now()->timezone('Asia/Ho_Chi_Minh')->toDateTimeString(),
            'shift' => $latest ? $latest->shift : '1',
            'username' => auth()->user() ? auth()->user()->username : 'admin',
        ]);

        try {
            \App\Services\ActivityLogService::logDayClose(
                $currentSystemDate->toDateString(),
                $nextSystemDate->toDateString(),
                $request
            );
        } catch (\Throwable $e) {}

        return response()->json([
            'success' => true,
            'message' => 'Rolled system date successfully to ' . $nextSystemDate->toDateString(),
            'data' => [
                'system_date' => $nextSystemDate->toDateString(),
                'shift' => $newRoll->shift
            ]
        ]);
    });

    // API Day Close / Night Audit
    Route::prefix('night-audit')->group(function () {
        Route::get('/check-status', [NightAuditController::class, 'checkStatus']);
        Route::post('/late-check-in', [NightAuditController::class, 'lateCheckIn']);
        Route::post('/no-show', [NightAuditController::class, 'noShowRoom']);
        Route::post('/extend-stay', [NightAuditController::class, 'extendStay']); // Bug B

        Route::post('/run', [NightAuditController::class, 'runNightAudit']);
    });


    // User settings (thiết lập cá nhân kế hoạch phòng)
    Route::get('/user-settings', [\App\Http\Controllers\Api\UserSettingController::class, 'show']);
    Route::put('/user-settings', [\App\Http\Controllers\Api\UserSettingController::class, 'update']);

    // Room Rate Codes (Mapped to SP1340)
    Route::apiResource('room-rate-codes', RoomRateCodeController::class)->parameters([
        'room-rate-codes' => 'ma'
    ]);
    Route::post('room-rate-codes/{ma}/plans', [RoomRateCodeController::class, 'saveRatePlan']);
    Route::post('room-rate-codes/{ma}/daily-mappings', [RoomRateCodeController::class, 'saveDailyMappings']);

    // Hotel settings (write only - GET moved to public routes)
    // Route::get('/hotel-settings', [\App\Http\Controllers\Api\HotelSettingController::class, 'show']);
    Route::put('/hotel-settings', [\App\Http\Controllers\Api\HotelSettingController::class, 'update']);
    Route::post('/hotel-settings/logo', [\App\Http\Controllers\Api\HotelSettingController::class, 'uploadLogo']);
    Route::delete('/hotel-settings/logo', [\App\Http\Controllers\Api\HotelSettingController::class, 'deleteLogo']);
    Route::post('/hotel-settings/qr-code', [\App\Http\Controllers\Api\HotelSettingController::class, 'uploadQrCode']);
    Route::delete('/hotel-settings/qr-code', [\App\Http\Controllers\Api\HotelSettingController::class, 'deleteQrCode']);

    // Shifts (ca làm việc - Định nghĩa khách sạn)
    Route::apiResource('shifts', \App\Http\Controllers\Api\ShiftController::class);

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
    Route::get('/rooms/permissions', [\App\Http\Controllers\Api\RoomController::class, 'permissions']);
    Route::get('/rooms/vacant', [\App\Http\Controllers\Api\RoomController::class, 'vacant']);
    Route::get('/rooms/stats', [\App\Http\Controllers\Api\RoomController::class, 'stats']);
    Route::post('/rooms/bulk-status', [\App\Http\Controllers\Api\RoomController::class, 'bulkUpdateStatus']);
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
    Route::post('companies/sync', [\App\Http\Controllers\Api\CompanyController::class, 'sync']);
    Route::get('companies/export', [\App\Http\Controllers\Api\CompanyController::class, 'export']);
    Route::post('companies/import', [\App\Http\Controllers\Api\CompanyController::class, 'import']);
    Route::get('companies/template', [\App\Http\Controllers\Api\CompanyController::class, 'template']);
    Route::apiResource('companies', \App\Http\Controllers\Api\CompanyController::class);

    // Hotel details configuration routes
    Route::apiResource('hotel-services', \App\Http\Controllers\Api\HotelServiceController::class);
    Route::post('departments/{department}/services', [\App\Http\Controllers\Api\DepartmentController::class, 'attachService']);
    Route::put('departments/{department}/services/{hotelService}', [\App\Http\Controllers\Api\DepartmentController::class, 'updateService']);
    Route::delete('departments/{department}/services/{hotelService}', [\App\Http\Controllers\Api\DepartmentController::class, 'detachService']);
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
    Route::get('nationalities', [\App\Http\Controllers\Api\NationalityController::class, 'index']);
    Route::apiResource('payment-methods', \App\Http\Controllers\Api\PaymentMethodController::class);
    Route::apiResource('currencies', \App\Http\Controllers\Api\CurrencyController::class);
    Route::apiResource('units-of-measure', \App\Http\Controllers\Api\UnitOfMeasureController::class);
    Route::apiResource('room-rate-codes', RoomRateCodeController::class);
    Route::apiResource('registration-statuses', \App\Http\Controllers\Api\RegistrationStatusController::class);
    Route::post('room-rate-codes/{ma}/plans', [RoomRateCodeController::class, 'saveRatePlan']);
    Route::delete('room-rate-codes/{ma}/plans/{code}', [RoomRateCodeController::class, 'deleteRatePlan']);
    Route::post('room-rate-codes/{ma}/daily-mappings', [RoomRateCodeController::class, 'saveDailyMappings']);

    // System Administration routes
    Route::get('/system/database/export', [\App\Http\Controllers\Api\DatabaseBackupController::class, 'exportDatabase']);
    Route::post('/system/database/import', [\App\Http\Controllers\Api\DatabaseBackupController::class, 'importDatabase']);
    Route::apiResource('system-branches', \App\Http\Controllers\Api\SystemBranchController::class);
    Route::apiResource('lost-and-found', \App\Http\Controllers\Api\LostAndFoundController::class);
    Route::apiResource('users', \App\Http\Controllers\Api\UserController::class);
    Route::apiResource('product-categories', \App\Http\Controllers\Api\ProductCategoryController::class);
    Route::post('/housekeeping/outlets/reorder', [\App\Http\Controllers\Api\HousekeepingOutletController::class, 'reorder']);
    Route::delete('/housekeeping/outlets/{housekeepingOutlet}/force', [\App\Http\Controllers\Api\HousekeepingOutletController::class, 'forceDestroy'])->name('housekeeping.outlets.force-destroy');
    Route::get('/housekeeping/outlets', [\App\Http\Controllers\Api\HousekeepingOutletController::class, 'index'])->name('housekeeping.outlets.index');
    Route::post('/housekeeping/outlets', [\App\Http\Controllers\Api\HousekeepingOutletController::class, 'store'])->name('housekeeping.outlets.store');
    Route::get('/housekeeping/outlets/{housekeepingOutlet}', [\App\Http\Controllers\Api\HousekeepingOutletController::class, 'show'])->name('housekeeping.outlets.show');
    Route::put('/housekeeping/outlets/{housekeepingOutlet}', [\App\Http\Controllers\Api\HousekeepingOutletController::class, 'update'])->name('housekeeping.outlets.update');
    Route::patch('/housekeeping/outlets/{housekeepingOutlet}', [\App\Http\Controllers\Api\HousekeepingOutletController::class, 'update'])->name('housekeeping.outlets.patch');
    Route::delete('/housekeeping/outlets/{housekeepingOutlet}', [\App\Http\Controllers\Api\HousekeepingOutletController::class, 'destroy'])->name('housekeeping.outlets.destroy');

    // HK Config: ký hiệu phòng + cột mẫu in
    Route::get('/hk-config', [\App\Http\Controllers\Api\HkConfigController::class, 'index']);
    Route::put('/hk-config/symbols', [\App\Http\Controllers\Api\HkConfigController::class, 'updateSymbols']);
    Route::put('/hk-config/print-cols', [\App\Http\Controllers\Api\HkConfigController::class, 'updatePrintCols']);
    Route::post('/hk-config/reset', [\App\Http\Controllers\Api\HkConfigController::class, 'reset']);


    Route::post('/products/bulk-toggle-active', [\App\Http\Controllers\Api\ProductController::class, 'bulkToggleActive']);
    Route::get('/products/export', [\App\Http\Controllers\Api\ProductController::class, 'exportExcel']);
    Route::post('/products/import', [\App\Http\Controllers\Api\ProductController::class, 'importExcel']);
    Route::apiResource('products', \App\Http\Controllers\Api\ProductController::class);
    Route::apiResource('inventories', \App\Http\Controllers\Api\InventoryController::class);

    // ─── Quản lý Kho (Warehouses) ────────────────────────────────────
    Route::get('/warehouses', [\App\Http\Controllers\Api\WarehouseController::class, 'index']);
    Route::post('/warehouses', [\App\Http\Controllers\Api\WarehouseController::class, 'store']);
    Route::put('/warehouses/{id}', [\App\Http\Controllers\Api\WarehouseController::class, 'update']);
    Route::delete('/warehouses/{id}', [\App\Http\Controllers\Api\WarehouseController::class, 'destroy']);

    // ─── Kiểm kê tồn kho định kỳ (Inventory Checks) ─────────────────
    Route::get('/inventory/checks', [\App\Http\Controllers\Api\InventoryCheckController::class, 'index']);
    Route::post('/inventory/checks', [\App\Http\Controllers\Api\InventoryCheckController::class, 'store']);
    Route::post('/inventory/checks/sync-previous-month', [\App\Http\Controllers\Api\InventoryCheckController::class, 'syncPreviousMonth']);
    Route::delete('/inventory/checks/{id}', [\App\Http\Controllers\Api\InventoryCheckController::class, 'destroy']);
    Route::post('/inventory/checks/{id}/items', [\App\Http\Controllers\Api\InventoryCheckController::class, 'addItems']);
    Route::put('/inventory/checks/{id}/items/{itemId}', [\App\Http\Controllers\Api\InventoryCheckController::class, 'updateItem']);
    Route::get('/inventory/checks/{id}/export', [\App\Http\Controllers\Api\InventoryCheckController::class, 'exportExcel']);
    Route::get('/inventory/products-in-stock', [\App\Http\Controllers\Api\InventoryCheckController::class, 'productsInStock']);

    // ─── Nhật ký nhập/xuất/chuyển kho từng ngày (Daily Logs) ─────────
    Route::get('/inventory/logs', [\App\Http\Controllers\Api\InventoryLogController::class, 'index']);
    Route::get('/inventory/logs/export', [\App\Http\Controllers\Api\InventoryLogController::class, 'exportExcel']);
    Route::put('/inventory/logs', [\App\Http\Controllers\Api\InventoryLogController::class, 'upsert']);
    Route::post('/inventory/get-bill', [\App\Http\Controllers\Api\InventoryLogController::class, 'getBill']);

    // ─── Chuyển kho (Transfer) ────────────────────────────────────────
    Route::post('/inventory/transfer', [\App\Http\Controllers\Api\InventoryTransferController::class, 'store']);
    Route::post('/users/{id}/signature', [\App\Http\Controllers\Api\UserController::class, 'uploadSignature']);
    Route::delete('/users/{id}/signature', [\App\Http\Controllers\Api\UserController::class, 'deleteSignature']);
    Route::get('/info-business', [\App\Http\Controllers\Api\InfoBusinessController::class, 'show']);
    Route::put('/info-business', [\App\Http\Controllers\Api\InfoBusinessController::class, 'update']);
    Route::post('/info-business/logo', [\App\Http\Controllers\Api\InfoBusinessController::class, 'uploadLogo']);
    Route::delete('/info-business/logo', [\App\Http\Controllers\Api\InfoBusinessController::class, 'deleteLogo']);
    Route::get('/departments', [\App\Http\Controllers\Api\DepartmentController::class, 'index']);
    Route::post('/outlets/reorder', [\App\Http\Controllers\Api\OutletController::class, 'reorder']);
    Route::get('/outlets/hk', [\App\Http\Controllers\Api\OutletController::class, 'listHK']); // HK outlets cho Get Bill
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
    Route::apiResource('fb-parties', \App\Http\Controllers\Api\FbPartyController::class);
    Route::post('fb-parties/{id}/cancel', [\App\Http\Controllers\Api\FbPartyController::class, 'cancel']);
    Route::post('fb-parties/check-conflict', [\App\Http\Controllers\Api\FbPartyController::class, 'checkConflict']);
    Route::post('fb-parties/{partyId}/sub-parties/{subPartyId}/complete', [\App\Http\Controllers\Api\FbPartyController::class, 'completeSubParty']);

    // F&B Orders (Bills)
    Route::get('/fnb/orders', [\App\Http\Controllers\FbOrderController::class, 'search']);
    Route::get('/fnb/tables/{tableId}/active-orders', [\App\Http\Controllers\FbOrderController::class, 'getActiveOrders']);
    Route::post('/fnb/tables/{tableId}/orders/sync', [\App\Http\Controllers\FbOrderController::class, 'syncOrders']);
    Route::get('/fnb/orders/{orderId}/print-logs', [\App\Http\Controllers\FbPrintLogController::class, 'getByOrder']);


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
    Route::apiResource('fb-parties', \App\Http\Controllers\Api\FbPartyController::class);
    Route::post('fb-parties/{id}/cancel', [\App\Http\Controllers\Api\FbPartyController::class, 'cancel']);
    Route::post('fb-parties/check-conflict', [\App\Http\Controllers\Api\FbPartyController::class, 'checkConflict']);
    Route::post('fb-parties/{partyId}/sub-parties/{subPartyId}/complete', [\App\Http\Controllers\Api\FbPartyController::class, 'completeSubParty']);

    // F&B Orders (Bills)
    Route::get('/fnb/orders', [\App\Http\Controllers\FbOrderController::class, 'search']);
    Route::get('/fnb/tables/{tableId}/active-orders', [\App\Http\Controllers\FbOrderController::class, 'getActiveOrders']);
    Route::post('/fnb/tables/{tableId}/orders/sync', [\App\Http\Controllers\FbOrderController::class, 'syncOrders']);
    Route::get('/fnb/orders/{orderId}/print-logs', [\App\Http\Controllers\FbPrintLogController::class, 'getByOrder']);

    // Activity Log routes
    Route::get('/activity-logs', [\App\Http\Controllers\Api\ActivityLogController::class, 'index']);
    Route::get('/activity-logs/stats', [\App\Http\Controllers\Api\ActivityLogController::class, 'stats']);

    // =====================================================================
    // BOOKING (Đăng ký phòng) routes
    // =====================================================================

    // #12 — Xuất Excel (đặt TRƯỚC apiResource để không bị override)
    Route::get('bookings/init-dropdowns', [\App\Http\Controllers\Api\BookingController::class, 'initDropdowns']);
    Route::get('bookings/export', [\App\Http\Controllers\Api\BookingController::class, 'export']);
    Route::apiResource('bookings', \App\Http\Controllers\Api\BookingController::class);
    Route::patch('bookings/{bookingId}/no-post', [\App\Http\Controllers\Api\BookingNoPostController::class, 'updateBooking']);

    // #19 — Nhân bản booking
    Route::post('bookings/{id}/copy', [\App\Http\Controllers\Api\BookingController::class, 'copy']);
    // #22 — Khôi phục booking đã hủy
    Route::post('bookings/{id}/restore', [\App\Http\Controllers\Api\BookingController::class, 'restore']);
    // Khôi phục booking noshow
    Route::post('bookings/{id}/revert-noshow', [\App\Http\Controllers\Api\BookingController::class, 'revertNoshow']);

    // --- Booking Rooms (SP2100) ---
    Route::prefix('bookings/{bookingId}/rooms')->group(function () {
        Route::get('/', [\App\Http\Controllers\Api\BookingRoomController::class, 'index']);
        Route::post('/', [\App\Http\Controllers\Api\BookingRoomController::class, 'store']);
        Route::put('/{roomId}', [\App\Http\Controllers\Api\BookingRoomController::class, 'update']);
        Route::post('/bulk-update', [\App\Http\Controllers\Api\BookingRoomController::class, 'bulkUpdate']);
        // Epic 5 - Check-in
        Route::patch('/{roomId}/check-in', [\App\Http\Controllers\Api\BookingRoomController::class, 'checkIn']);
        Route::post('/{roomId}/undo-checkin', [\App\Http\Controllers\Api\BookingRoomController::class, 'undoCheckIn']);
        // Epic 6 - Nâng hạng phòng
        Route::patch('/{roomId}/upgrade', [\App\Http\Controllers\Api\BookingRoomController::class, 'upgrade']);
        // Epic 8 - Gỡ số phòng
        Route::patch('/{roomId}/unassign', [\App\Http\Controllers\Api\BookingRoomController::class, 'unassign']);
        // Epic 9 - Hủy phòng
        Route::delete('/{roomId}/cancel', [\App\Http\Controllers\Api\BookingRoomController::class, 'cancel']);
        // Khôi phục phòng noshow
        Route::post('/{roomId}/revert-noshow', [\App\Http\Controllers\Api\BookingRoomController::class, 'revertNoshow']);
        // Charge noshow
        Route::post('/{roomId}/charge-noshow', [\App\Http\Controllers\Api\BookingRoomServiceController::class, 'chargeNoshow']);
        // Tách phòng
        Route::post('/{roomId}/split', [\App\Http\Controllers\Api\BookingRoomController::class, 'split']);
        // Epic 3 - Auto assign room number
        Route::post('/{roomId}/auto-assign', [\App\Http\Controllers\Api\BookingRoomController::class, 'autoAssign']);
        // Epic 11 - Do Not Move
        Route::post('/{roomId}/lock-move', [\App\Http\Controllers\Api\BookingRoomController::class, 'lockMove']);
        Route::delete('/{roomId}/lock-move', [\App\Http\Controllers\Api\BookingRoomController::class, 'unlockMove']);
        // Chuyển phòng & Gộp phòng
        Route::get('/{roomId}/move-target-rooms', [\App\Http\Controllers\Api\BookingRoomController::class, 'getMoveTargetRooms']);
        Route::post('/{roomId}/move', [\App\Http\Controllers\Api\BookingRoomController::class, 'moveRoom']);
    });

    // --- Booking Room Services (SP2102) — Epic 4, 10, 14 ---
    Route::prefix('booking-rooms/{roomId}/services')->group(function () {
        Route::get('/', [\App\Http\Controllers\Api\BookingRoomServiceController::class, 'index']);
        Route::post('/', [\App\Http\Controllers\Api\BookingRoomServiceController::class, 'store']);
        Route::delete('/bulk', [\App\Http\Controllers\Api\BookingRoomServiceController::class, 'bulkDelete']);
        Route::post('/cancel', [\App\Http\Controllers\Api\BookingRoomServiceController::class, 'cancel']);
        Route::patch('/folio', [\App\Http\Controllers\Api\BookingRoomServiceController::class, 'transferFolio']);
        Route::get('/quick-transfer-candidates', [\App\Http\Controllers\Api\BookingRoomServiceController::class, 'quickTransferCandidates']);
        Route::post('/quick-transfer', [\App\Http\Controllers\Api\BookingRoomServiceController::class, 'quickTransfer']);
        Route::post('/split-folio', [\App\Http\Controllers\Api\BookingRoomServiceController::class, 'splitFolio']);
    });
    Route::patch('booking-rooms/{roomId}/no-post', [\App\Http\Controllers\Api\BookingNoPostController::class, 'updateRoom']);
    Route::get('/booking-services/extra-bed-rate', [\App\Http\Controllers\Api\BookingRoomServiceController::class, 'defaultExtraBedRate']);
    Route::get('/service-bills/{billId}/details', [\App\Http\Controllers\Api\BookingRoomServiceController::class, 'billDetails']);
    // Danh sách dịch vụ FO (dùng cho dropdown chọn dịch vụ)
    Route::get('/booking-services/fo-list', [\App\Http\Controllers\Api\BookingRoomServiceController::class, 'foServiceList']);
    Route::post('/booking-room-services/post-housekeeping-bill', [\App\Http\Controllers\Api\BookingRoomServiceController::class, 'postHousekeepingBill']);
    Route::get('/housekeeping/service-bills', [\App\Http\Controllers\Api\BookingRoomServiceController::class, 'searchHousekeepingInvoices']);
    Route::post('/housekeeping/service-bills/{billId}/cancel', [\App\Http\Controllers\Api\BookingRoomServiceController::class, 'cancelHousekeepingInvoice']);
    Route::post('/booking-room-services/post-fo-service-bill', [\App\Http\Controllers\Api\BookingRoomServiceController::class, 'postFoServiceBill']);
    Route::post('/booking-room-services/post-room-charge', [\App\Http\Controllers\Api\BookingRoomServiceController::class, 'postRoomCharge']);
    Route::post('/bookings/{bookingId}/adjust-room-rate', [\App\Http\Controllers\Api\BookingRoomServiceController::class, 'adjustRoomRate']);

    // --- Special Requests (SP2107, SP1325) — Epic 15 ---
    Route::get('/special-requests', [\App\Http\Controllers\Api\BookingRoomSpecialRequestController::class, 'catalog']);
    Route::post('/special-requests', [\App\Http\Controllers\Api\BookingRoomSpecialRequestController::class, 'storeMaster']);
    Route::delete('/special-requests/{id}', [\App\Http\Controllers\Api\BookingRoomSpecialRequestController::class, 'destroyMaster']);
    Route::prefix('booking-rooms/{roomId}/special-requests')->group(function () {
        Route::get('/', [\App\Http\Controllers\Api\BookingRoomSpecialRequestController::class, 'index']);
        Route::post('/', [\App\Http\Controllers\Api\BookingRoomSpecialRequestController::class, 'store']);
        Route::post('/sync', [\App\Http\Controllers\Api\BookingRoomSpecialRequestController::class, 'sync']);
        Route::delete('/{id}', [\App\Http\Controllers\Api\BookingRoomSpecialRequestController::class, 'destroy']);
    });

    // --- Guests & Children — Epic 7, 13 ---
    Route::get('/guests/search', [\App\Http\Controllers\Api\GuestController::class, 'searchGuests']);
    Route::post('/guests/{id}/avatar', [\App\Http\Controllers\Api\GuestController::class, 'uploadAvatar']);
    Route::get('/bookings/{bookingId}/guests', [\App\Http\Controllers\Api\GuestController::class, 'bookingGuests']);
    Route::post('/bookings/{bookingId}/init-guests', [\App\Http\Controllers\Api\GuestController::class, 'initGuests']);
    Route::post('/bookings/{bookingId}/bulk-update-guests', [\App\Http\Controllers\Api\GuestController::class, 'bulkUpdate']);
    Route::prefix('booking-rooms/{roomId}/guests')->group(function () {
        Route::get('/', [\App\Http\Controllers\Api\GuestController::class, 'roomGuests']);
        Route::get('/on-date', [\App\Http\Controllers\Api\GuestController::class, 'getGuestsOnDate']);
        Route::post('/', [\App\Http\Controllers\Api\GuestController::class, 'addGuest']);
        Route::post('/{guestId}/checkout', [\App\Http\Controllers\Api\GuestController::class, 'checkoutGuest']);
        Route::put('/{guestId}', [\App\Http\Controllers\Api\GuestController::class, 'updateGuest']);
        Route::delete('/{guestId}', [\App\Http\Controllers\Api\GuestController::class, 'removeGuest']);
    });
    Route::post('/booking-rooms/{roomId}/checkout', [\App\Http\Controllers\Api\GuestController::class, 'checkoutRoom']);
    Route::post('/bookings/{bookingId}/checkout-preview', [\App\Http\Controllers\Api\GuestController::class, 'previewCheckoutRooms']);
    Route::post('/bookings/{bookingId}/checkout', [\App\Http\Controllers\Api\GuestController::class, 'checkoutBooking']);
    Route::post('/booking-rooms/{roomId}/children/{childId}/checkout', [\App\Http\Controllers\Api\GuestController::class, 'checkoutChild']);
    Route::post('/booking-rooms/{roomId}/restore-checkout', [\App\Http\Controllers\Api\GuestController::class, 'restoreRoomCheckout']);
    Route::post('/bookings/{bookingId}/restore-checkout', [\App\Http\Controllers\Api\GuestController::class, 'restoreBookingCheckout']);
    Route::get('/bookings/{bookingId}/children', [\App\Http\Controllers\Api\GuestController::class, 'bookingChildren']);
    Route::post('/bookings/{bookingId}/children', [\App\Http\Controllers\Api\GuestController::class, 'addChild']);
    Route::put('/booking-children/{childId}', [\App\Http\Controllers\Api\GuestController::class, 'updateChild']);
    Route::delete('/bookings/{bookingId}/children/{childId}', [\App\Http\Controllers\Api\GuestController::class, 'removeChild']);

    // Breakfast details (Epic 13)
    Route::get('/booking-children/{childId}/breakfast-details', [\App\Http\Controllers\Api\GuestController::class, 'breakfastDetails']);
    Route::patch('/booking-children/{childId}/breakfast-details/{detailId}', [\App\Http\Controllers\Api\GuestController::class, 'updateBreakfastDetail']);

    // Cancel Reasons catalog
    Route::get('/cancel-reasons', [\App\Http\Controllers\Api\GuestController::class, 'cancelReasons']);

    // =====================================================================
    // #18 — PAYMENTS (Đặt cọc / Deposit) routes
    // =====================================================================
    Route::get('/bookings/{bookingId}/payments', [\App\Http\Controllers\Api\PaymentController::class, 'index']);
    Route::post('/bookings/{bookingId}/payments', [\App\Http\Controllers\Api\PaymentController::class, 'store']);
    Route::post('/bookings/{bookingId}/settle-payment', [\App\Http\Controllers\Api\PaymentController::class, 'settlePayment']);
    Route::put('/payments/{id}', [\App\Http\Controllers\Api\PaymentController::class, 'update']);
    Route::patch('/payments/{id}/folio', [\App\Http\Controllers\Api\PaymentController::class, 'transferFolio']);
    Route::delete('/payments/{id}', [\App\Http\Controllers\Api\PaymentController::class, 'destroy']);
    Route::post('/payments/{id}/split', [\App\Http\Controllers\Api\PaymentController::class, 'split']);
    Route::post('/payments/transfer', [\App\Http\Controllers\Api\PaymentController::class, 'transferMany']);
    Route::post('/payments/{id}/transfer', [\App\Http\Controllers\Api\PaymentController::class, 'transfer']);
    Route::get('/payments/{id}/debt-settlements', [\App\Http\Controllers\Api\PaymentController::class, 'debtSettlements']);
    Route::post('/payments/{id}/debt-settlements', [\App\Http\Controllers\Api\PaymentController::class, 'storeDebtSettlement']);
    Route::delete('/payments/{id}/debt-settlements/{settlementId}', [\App\Http\Controllers\Api\PaymentController::class, 'destroyDebtSettlement']);

    // Availability
    Route::get('/availability', [\App\Http\Controllers\Api\AvailabilityController::class, 'index']);

    // =====================================================================
    // HK — Phân Công Phòng (Housekeeping Room Assignment)
    // =====================================================================
    // Staff
    Route::get('/hk/staff', [\App\Http\Controllers\Api\HkAssignmentController::class, 'staffIndex']);
    Route::post('/hk/staff', [\App\Http\Controllers\Api\HkAssignmentController::class, 'staffStore']);
    Route::put('/hk/staff/{id}', [\App\Http\Controllers\Api\HkAssignmentController::class, 'staffUpdate']);
    Route::delete('/hk/staff/{id}', [\App\Http\Controllers\Api\HkAssignmentController::class, 'staffDestroy']);
    // Assignment (ngày + ca)
    Route::get('/hk/assignments', [\App\Http\Controllers\Api\HkAssignmentController::class, 'index']);
    Route::post('/hk/assignments', [\App\Http\Controllers\Api\HkAssignmentController::class, 'store']);
    // Groups
    Route::post('/hk/assignments/{assignmentId}/groups', [\App\Http\Controllers\Api\HkAssignmentController::class, 'storeGroup']);
    Route::put('/hk/assignments/groups/{groupId}', [\App\Http\Controllers\Api\HkAssignmentController::class, 'updateGroup']);
    Route::delete('/hk/assignments/groups/{groupId}', [\App\Http\Controllers\Api\HkAssignmentController::class, 'destroyGroup']);
    // Rooms in group
    Route::post('/hk/assignments/groups/{groupId}/rooms', [\App\Http\Controllers\Api\HkAssignmentController::class, 'addRooms']);
    Route::delete('/hk/assignments/groups/{groupId}/rooms/{roomId}', [\App\Http\Controllers\Api\HkAssignmentController::class, 'removeRoom']);
    Route::get('/availability/details', [\App\Http\Controllers\Api\AvailabilityController::class, 'details']);
    Route::get('/availability/check', [\App\Http\Controllers\Api\AvailabilityController::class, 'check']);

    // =====================================================================
    // IN PHIẾU ĂN SÁNG (Breakfast Coupon - sp_035)
    // =====================================================================
    Route::get('/breakfast/list', [\App\Http\Controllers\Api\BreakfastController::class, 'list']);

    // =====================================================================
    // DANH SÁCH CÔNG VIỆC (Shift Work / Frontdesk Work)
    // =====================================================================
    Route::prefix('shift-work')->group(function () {
        Route::get('/arrivals', [\App\Http\Controllers\Api\ShiftWorkController::class, 'arrivals']);
        Route::get('/departures', [\App\Http\Controllers\Api\ShiftWorkController::class, 'departures']);
        Route::get('/pending', [\App\Http\Controllers\Api\ShiftWorkController::class, 'pending']);
        Route::put('/pending/{bookingId}/note', [\App\Http\Controllers\Api\ShiftWorkController::class, 'updatePendingNote']);
        Route::get('/shuttle', [\App\Http\Controllers\Api\ShiftWorkController::class, 'shuttle']);
        Route::get('/noshow', [\App\Http\Controllers\Api\ShiftWorkController::class, 'noshow']);
        Route::get('/birthdays', [\App\Http\Controllers\Api\ShiftWorkController::class, 'birthdays']);
    });
});

Route::post('/test-log', function (Illuminate\Http\Request $request) {
    \Log::info('TEST PAYLOAD', $request->all());
    return 'ok';
});
