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

    // System date (ngày nghiệp vụ từ system_date_rolls)
    Route::get('/system-date', function () {
        $latest = \App\Models\SystemDateRoll::latest('id')->first();
        $systemDate = $latest
            ? \Carbon\Carbon::parse($latest->system_date)->toDateString()
            : now()->timezone('Asia/Ho_Chi_Minh')->toDateString();
        $shift = $latest ? $latest->shift : '1';
        return response()->json([
            'success' => true,
            'data'    => [
                'system_date' => $systemDate,
                'shift'       => $shift
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
            'shift'       => $latest ? $latest->shift : '1',
            'username'    => auth()->user() ? auth()->user()->username : 'admin',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Rolled system date successfully to ' . $nextSystemDate->toDateString(),
            'data'    => [
                'system_date' => $nextSystemDate->toDateString(),
                'shift'       => $newRoll->shift
            ]
        ]);
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
    Route::get('/rooms/vacant', [\App\Http\Controllers\Api\RoomController::class, 'vacant']);
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
    Route::apiResource('users', \App\Http\Controllers\Api\UserController::class);
    Route::post('/users/{id}/signature', [\App\Http\Controllers\Api\UserController::class, 'uploadSignature']);
    Route::delete('/users/{id}/signature', [\App\Http\Controllers\Api\UserController::class, 'deleteSignature']);
    Route::get('/info-business', [\App\Http\Controllers\Api\InfoBusinessController::class, 'show']);
    Route::put('/info-business', [\App\Http\Controllers\Api\InfoBusinessController::class, 'update']);
    Route::post('/info-business/logo', [\App\Http\Controllers\Api\InfoBusinessController::class, 'uploadLogo']);
    Route::delete('/info-business/logo', [\App\Http\Controllers\Api\InfoBusinessController::class, 'deleteLogo']);

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

    // #19 — Nhân bản booking
    Route::post('bookings/{id}/copy',    [\App\Http\Controllers\Api\BookingController::class, 'copy']);
    // #22 — Khôi phục booking đã hủy
    Route::post('bookings/{id}/restore', [\App\Http\Controllers\Api\BookingController::class, 'restore']);

    // --- Booking Rooms (SP2100) ---
    Route::prefix('bookings/{bookingId}/rooms')->group(function () {
        Route::get('/',              [\App\Http\Controllers\Api\BookingRoomController::class, 'index']);
        Route::post('/',             [\App\Http\Controllers\Api\BookingRoomController::class, 'store']);
        Route::put('/{roomId}',      [\App\Http\Controllers\Api\BookingRoomController::class, 'update']);
        Route::post('/bulk-update',  [\App\Http\Controllers\Api\BookingRoomController::class, 'bulkUpdate']);
        // Epic 5 - Check-in
        Route::patch('/{roomId}/check-in',   [\App\Http\Controllers\Api\BookingRoomController::class, 'checkIn']);
        Route::post('/{roomId}/undo-checkin', [\App\Http\Controllers\Api\BookingRoomController::class, 'undoCheckIn']);
        // Epic 6 - Nâng hạng phòng
        Route::patch('/{roomId}/upgrade',    [\App\Http\Controllers\Api\BookingRoomController::class, 'upgrade']);
        // Epic 8 - Gỡ số phòng
        Route::patch('/{roomId}/unassign',   [\App\Http\Controllers\Api\BookingRoomController::class, 'unassign']);
        // Epic 9 - Hủy phòng
        Route::delete('/{roomId}/cancel',    [\App\Http\Controllers\Api\BookingRoomController::class, 'cancel']);
        // Tách phòng
        Route::post('/{roomId}/split',        [\App\Http\Controllers\Api\BookingRoomController::class, 'split']);
        // Epic 3 - Auto assign room number
        Route::post('/{roomId}/auto-assign', [\App\Http\Controllers\Api\BookingRoomController::class, 'autoAssign']);
        // Epic 11 - Do Not Move
        Route::post('/{roomId}/lock-move',   [\App\Http\Controllers\Api\BookingRoomController::class, 'lockMove']);
        Route::delete('/{roomId}/lock-move', [\App\Http\Controllers\Api\BookingRoomController::class, 'unlockMove']);
    });

    // --- Booking Room Services (SP2102) — Epic 4, 10, 14 ---
    Route::prefix('booking-rooms/{roomId}/services')->group(function () {
        Route::get('/',        [\App\Http\Controllers\Api\BookingRoomServiceController::class, 'index']);
        Route::post('/',       [\App\Http\Controllers\Api\BookingRoomServiceController::class, 'store']);
        Route::delete('/bulk', [\App\Http\Controllers\Api\BookingRoomServiceController::class, 'bulkDelete']);
    });
    Route::get('/booking-services/extra-bed-rate', [\App\Http\Controllers\Api\BookingRoomServiceController::class, 'defaultExtraBedRate']);
    // Danh sách dịch vụ FO (dùng cho dropdown chọn dịch vụ)
    Route::get('/booking-services/fo-list', [\App\Http\Controllers\Api\BookingRoomServiceController::class, 'foServiceList']);

    // --- Special Requests (SP2107, SP1325) — Epic 15 ---
    Route::get('/special-requests', [\App\Http\Controllers\Api\BookingRoomSpecialRequestController::class, 'catalog']);
    Route::post('/special-requests', [\App\Http\Controllers\Api\BookingRoomSpecialRequestController::class, 'storeMaster']);
    Route::delete('/special-requests/{id}', [\App\Http\Controllers\Api\BookingRoomSpecialRequestController::class, 'destroyMaster']);
    Route::prefix('booking-rooms/{roomId}/special-requests')->group(function () {
        Route::get('/',        [\App\Http\Controllers\Api\BookingRoomSpecialRequestController::class, 'index']);
        Route::post('/',       [\App\Http\Controllers\Api\BookingRoomSpecialRequestController::class, 'store']);
        Route::post('/sync',   [\App\Http\Controllers\Api\BookingRoomSpecialRequestController::class, 'sync']);
        Route::delete('/{id}', [\App\Http\Controllers\Api\BookingRoomSpecialRequestController::class, 'destroy']);
    });

    // --- Guests & Children — Epic 7, 13 ---
    Route::get('/guests/search', [\App\Http\Controllers\Api\GuestController::class, 'searchGuests']);
    Route::get('/bookings/{bookingId}/guests',   [\App\Http\Controllers\Api\GuestController::class, 'bookingGuests']);
    Route::post('/bookings/{bookingId}/init-guests', [\App\Http\Controllers\Api\GuestController::class, 'initGuests']);
    Route::post('/bookings/{bookingId}/bulk-update-guests', [\App\Http\Controllers\Api\GuestController::class, 'bulkUpdate']);
    Route::prefix('booking-rooms/{roomId}/guests')->group(function () {
        Route::get('/',             [\App\Http\Controllers\Api\GuestController::class, 'roomGuests']);
        Route::post('/',            [\App\Http\Controllers\Api\GuestController::class, 'addGuest']);
        Route::put('/{guestId}',    [\App\Http\Controllers\Api\GuestController::class, 'updateGuest']);
        Route::delete('/{guestId}', [\App\Http\Controllers\Api\GuestController::class, 'removeGuest']);
    });
    Route::get('/bookings/{bookingId}/children',              [\App\Http\Controllers\Api\GuestController::class, 'bookingChildren']);
    Route::post('/bookings/{bookingId}/children',             [\App\Http\Controllers\Api\GuestController::class, 'addChild']);
    Route::put('/booking-children/{childId}',                 [\App\Http\Controllers\Api\GuestController::class, 'updateChild']);
    Route::delete('/bookings/{bookingId}/children/{childId}', [\App\Http\Controllers\Api\GuestController::class, 'removeChild']);

    // Breakfast details (Epic 13)
    Route::get('/booking-children/{childId}/breakfast-details',              [\App\Http\Controllers\Api\GuestController::class, 'breakfastDetails']);
    Route::patch('/booking-children/{childId}/breakfast-details/{detailId}', [\App\Http\Controllers\Api\GuestController::class, 'updateBreakfastDetail']);

    // Cancel Reasons catalog
    Route::get('/cancel-reasons', [\App\Http\Controllers\Api\GuestController::class, 'cancelReasons']);

    // =====================================================================
    // #18 — PAYMENTS (Đặt cọc / Deposit) routes
    // =====================================================================
    Route::get('/bookings/{bookingId}/payments',  [\App\Http\Controllers\Api\PaymentController::class, 'index']);
    Route::post('/bookings/{bookingId}/payments', [\App\Http\Controllers\Api\PaymentController::class, 'store']);
    Route::put('/payments/{id}',                  [\App\Http\Controllers\Api\PaymentController::class, 'update']);
    Route::delete('/payments/{id}',               [\App\Http\Controllers\Api\PaymentController::class, 'destroy']);
    Route::post('/payments/{id}/split',           [\App\Http\Controllers\Api\PaymentController::class, 'split']);
    Route::post('/payments/{id}/transfer',        [\App\Http\Controllers\Api\PaymentController::class, 'transfer']);

    // Availability
    Route::get('/availability',       [\App\Http\Controllers\Api\AvailabilityController::class, 'index']);
    Route::get('/availability/check', [\App\Http\Controllers\Api\AvailabilityController::class, 'check']);
});
