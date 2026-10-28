<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\RoomRateCodeController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\NightAuditController;

// Public routes (không cần đăng nhập)
Route::post('/login', [AuthController::class, 'login'])->name('login');
// GET hotel-settings public để App.vue đọc is_night_audit_running trước khi login
Route::get('/hotel-settings', [\App\Http\Controllers\Api\HotelSettingController::class, 'show']);
// GET night-audit/check-status public để mọi client/tab/màn hình login đều kiểm tra được trạng thái tiến trình
Route::get('/night-audit/check-status', [NightAuditController::class, 'checkStatus']);
// Danh sách ngôn ngữ và từ điển dịch giao diện (public để màn hình đăng nhập cũng dịch được)
Route::get('/i18n/languages', [\App\Http\Controllers\Api\TranslationController::class, 'languages']);
Route::get('/i18n/dictionary/{lang}', [\App\Http\Controllers\Api\TranslationController::class, 'dictionary'])
    ->where('lang', '[a-zA-Z-]{2,10}');

// Protected routes (Sanctum)
Route::middleware(['auth:sanctum', \App\Http\Middleware\EnforceAccessRestrictions::class, \App\Http\Middleware\EnsureBranchAccess::class])->group(function () {
    // Dòng 406: quyền ghi (thêm / sửa / xóa) theo từng màn hình. Đọc danh mục vẫn mở vì các màn hình
    // nghiệp vụ cần dữ liệu tra cứu. 'screen.*' = các quyền add/create/edit/delete của màn hình đó.
    $canWrite = function (string ...$codes): string {
        $expanded = ['admin', 'system.setting'];
        foreach ($codes as $code) {
            array_push($expanded, ...(str_ends_with($code, '.*')
                ? array_map(fn ($action) => substr($code, 0, -1) . $action, ['add', 'create', 'edit', 'delete'])
                : [$code]));
        }

        return 'permission.write:' . implode(',', $expanded);
    };

    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);
    Route::post('/me/change-password', [AuthController::class, 'changePassword']);
    Route::post('/me/verify-password', [AuthController::class, 'verifyPassword']);

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
    })->middleware('permission:fo.night_audit,config.date_roll.roll');

    // API Day Close / Night Audit
    Route::prefix('night-audit')->group(function () {
        Route::post('/late-check-in', [NightAuditController::class, 'lateCheckIn'])->middleware('permission:fo.night_audit');
        Route::post('/no-show', [NightAuditController::class, 'noShowRoom'])->middleware('permission:fo.night_audit,fo.booking.noshow');
        Route::post('/extend-stay', [NightAuditController::class, 'extendStay'])->middleware('permission:fo.night_audit');
        Route::post('/split-old-services', [NightAuditController::class, 'splitOldServices'])->middleware('permission:fo.night_audit');

        Route::post('/run', [NightAuditController::class, 'runNightAudit'])->middleware('permission:fo.night_audit');
    });


    // User settings (thiết lập cá nhân kế hoạch phòng)
    Route::get('/user-settings', [\App\Http\Controllers\Api\UserSettingController::class, 'show']);
    Route::put('/user-settings', [\App\Http\Controllers\Api\UserSettingController::class, 'update']);

    // Theme hệ thống dùng chung; quyền cập nhật được kiểm tra lại trong controller.
    Route::get('/system-theme', [\App\Http\Controllers\Api\SystemThemeController::class, 'show']);
    Route::put('/system-theme', [\App\Http\Controllers\Api\SystemThemeController::class, 'update']);

    // Room Rate Codes (Mapped to SP1340)
    Route::apiResource('room-rate-codes', RoomRateCodeController::class)->parameters([
        'room-rate-codes' => 'ma'
    ])->middleware($canWrite('config.rates.*'));
    Route::post('room-rate-codes/{ma}/plans', [RoomRateCodeController::class, 'saveRatePlan'])->middleware($canWrite('config.rates.*'));
    Route::post('room-rate-codes/{ma}/daily-mappings', [RoomRateCodeController::class, 'saveDailyMappings'])->middleware($canWrite('config.rates.*'));

    // Hotel settings (write only - GET moved to public routes)
    // Route::get('/hotel-settings', [\App\Http\Controllers\Api\HotelSettingController::class, 'show']);
    Route::put('/hotel-settings', [\App\Http\Controllers\Api\HotelSettingController::class, 'update'])->middleware($canWrite('config.hotel.*'));
    Route::post('/hotel-settings/logo', [\App\Http\Controllers\Api\HotelSettingController::class, 'uploadLogo'])->middleware($canWrite('config.hotel.*'));
    Route::delete('/hotel-settings/logo', [\App\Http\Controllers\Api\HotelSettingController::class, 'deleteLogo'])->middleware($canWrite('config.hotel.*'));
    Route::post('/hotel-settings/qr-code', [\App\Http\Controllers\Api\HotelSettingController::class, 'uploadQrCode'])->middleware($canWrite('config.hotel.*'));
    Route::delete('/hotel-settings/qr-code', [\App\Http\Controllers\Api\HotelSettingController::class, 'deleteQrCode'])->middleware($canWrite('config.hotel.*'));

    // Shifts (ca làm việc - Định nghĩa khách sạn)
    Route::apiResource('shifts', \App\Http\Controllers\Api\ShiftController::class)->middleware($canWrite('config.shifts.*'));

    // Room configurations
    Route::get('/room-class-groups', [\App\Http\Controllers\Api\RoomClassGroupController::class, 'index'])->middleware($canWrite('config.room_classes.*'));
    Route::post('/room-class-groups', [\App\Http\Controllers\Api\RoomClassGroupController::class, 'store'])->middleware($canWrite('config.room_classes.*'));
    Route::put('/room-class-groups/{id}', [\App\Http\Controllers\Api\RoomClassGroupController::class, 'update'])->middleware($canWrite('config.room_classes.*'));
    Route::delete('/room-class-groups/{id}', [\App\Http\Controllers\Api\RoomClassGroupController::class, 'destroy'])->middleware($canWrite('config.room_classes.*'));

    Route::get('/room-classes', [\App\Http\Controllers\Api\RoomClassController::class, 'index'])->middleware($canWrite('config.room_classes.*'));
    Route::post('/room-classes', [\App\Http\Controllers\Api\RoomClassController::class, 'store'])->middleware($canWrite('config.room_classes.*'));
    Route::put('/room-classes/{id}', [\App\Http\Controllers\Api\RoomClassController::class, 'update'])->middleware($canWrite('config.room_classes.*'));
    Route::delete('/room-classes/{id}', [\App\Http\Controllers\Api\RoomClassController::class, 'destroy'])->middleware($canWrite('config.room_classes.*'));
    Route::get('/room-forms', [\App\Http\Controllers\Api\RoomFormController::class, 'index'])->middleware($canWrite('config.room_classes.*'));
    Route::post('/room-forms', [\App\Http\Controllers\Api\RoomFormController::class, 'store'])->middleware($canWrite('config.room_classes.*'));
    Route::put('/room-forms/{id}', [\App\Http\Controllers\Api\RoomFormController::class, 'update'])->middleware($canWrite('config.room_classes.*'));
    Route::delete('/room-forms/{id}', [\App\Http\Controllers\Api\RoomFormController::class, 'destroy'])->middleware($canWrite('config.room_classes.*'));
    Route::get('/standard-rates', [\App\Http\Controllers\Api\StandardRateController::class, 'index'])->middleware($canWrite('config.rates.*'));
    Route::post('/standard-rates', [\App\Http\Controllers\Api\StandardRateController::class, 'store'])->middleware($canWrite('config.rates.*'));
    Route::put('/standard-rates/{id}', [\App\Http\Controllers\Api\StandardRateController::class, 'update'])->middleware($canWrite('config.rates.*'));
    Route::delete('/standard-rates/{id}', [\App\Http\Controllers\Api\StandardRateController::class, 'destroy'])->middleware($canWrite('config.rates.*'));

    // Rooms management
    Route::get('/rooms/permissions', [\App\Http\Controllers\Api\RoomController::class, 'permissions']);
    Route::get('/rooms/vacant', [\App\Http\Controllers\Api\RoomController::class, 'vacant']);
    Route::get('/rooms/stats', [\App\Http\Controllers\Api\RoomController::class, 'stats']);
    Route::post('/rooms/bulk-status', [\App\Http\Controllers\Api\RoomController::class, 'bulkUpdateStatus']);
    Route::post('/rooms/bulk-housekeeping-flags', [\App\Http\Controllers\Api\RoomController::class, 'bulkUpdateHousekeepingFlags']);
    Route::put('/rooms/{id}/status', [\App\Http\Controllers\Api\RoomController::class, 'updateStatus']);
    Route::put('/rooms/{id}/housekeeping-flags', [\App\Http\Controllers\Api\RoomController::class, 'updateHousekeepingFlags']);
    Route::post('/rooms/{room_number}/service-folio', [\App\Http\Controllers\Api\RoomController::class, 'ensureVirtualServiceFolio'])
        ->middleware('permission:fo.service.add,fo.service.edit');
    // Folio phòng ảo (không có booking): danh sách cho Hóa đơn và thanh toán theo phòng.
    Route::get('/virtual-room-folios', [\App\Http\Controllers\Api\VirtualRoomFolioController::class, 'index']);
    Route::post('/virtual-room-folios/{roomId}/settle-payment', [\App\Http\Controllers\Api\PaymentController::class, 'settleVirtualRoomPayment'])
        ->middleware('permission:fo.payment.create');
    Route::apiResource('rooms', \App\Http\Controllers\Api\RoomController::class)->middleware($canWrite('config.rooms.*'));

    // Room locks management
    Route::get('/room-locks/history/{room_id}', [\App\Http\Controllers\Api\RoomLockController::class, 'history']);
    Route::post('/room-locks/bulk-lock', [\App\Http\Controllers\Api\RoomLockController::class, 'bulkLock']);
    Route::post('/room-locks/bulk-unlock', [\App\Http\Controllers\Api\RoomLockController::class, 'bulkUnlock']);
    Route::post('/room-locks/bulk-update', [\App\Http\Controllers\Api\RoomLockController::class, 'bulkUpdate']);
    Route::apiResource('room-locks', \App\Http\Controllers\Api\RoomLockController::class);

    // Company settings
    Route::apiResource('markets', \App\Http\Controllers\Api\MarketController::class)->middleware($canWrite('config.markets.*'));
    Route::apiResource('customer-sources', \App\Http\Controllers\Api\CustomerSourceController::class)->middleware($canWrite('config.markets.*'));
    Route::apiResource('branches', \App\Http\Controllers\Api\BranchController::class)->middleware($canWrite('fo.company.*'));
    Route::apiResource('branches-total', \App\Http\Controllers\Api\BranchTotalController::class)->middleware($canWrite('fo.company.*'));
    Route::apiResource('bookers', \App\Http\Controllers\Api\BookerController::class)->middleware($canWrite('fo.company.*', 'fo.booking.create', 'fo.booking.edit'));
    Route::post('companies/sync', [\App\Http\Controllers\Api\CompanyController::class, 'sync'])->middleware($canWrite('fo.company.*'));
    Route::get('companies/export', [\App\Http\Controllers\Api\CompanyController::class, 'export']);
    Route::post('companies/import', [\App\Http\Controllers\Api\CompanyController::class, 'import'])->middleware($canWrite('fo.company.*'));
    Route::get('companies/template', [\App\Http\Controllers\Api\CompanyController::class, 'template']);
    Route::apiResource('companies', \App\Http\Controllers\Api\CompanyController::class)->middleware($canWrite('fo.company.*'));

    // Hotel details configuration routes
    Route::apiResource('hotel-services', \App\Http\Controllers\Api\HotelServiceController::class)->middleware($canWrite('config.services.*'));
    Route::post('departments/{department}/services', [\App\Http\Controllers\Api\DepartmentController::class, 'attachService'])->middleware($canWrite('config.services.*'));
    Route::put('departments/{department}/services/{hotelService}', [\App\Http\Controllers\Api\DepartmentController::class, 'updateService'])->middleware($canWrite('config.services.*'));
    Route::delete('departments/{department}/services/{hotelService}', [\App\Http\Controllers\Api\DepartmentController::class, 'detachService'])->middleware($canWrite('config.services.*'));
    Route::apiResource('shifts', \App\Http\Controllers\Api\ShiftController::class)->middleware($canWrite('config.shifts.*'));
    Route::apiResource('hotel-configs', \App\Http\Controllers\Api\HotelConfigController::class)->middleware($canWrite('config.system.*', 'config.hotel.*'));
    Route::get('portal-wallpaper', [\App\Http\Controllers\Api\PortalWallpaperController::class, 'getWallpaper']);
    Route::post('portal-wallpaper', [\App\Http\Controllers\Api\PortalWallpaperController::class, 'updateWallpaper'])->middleware('permission:admin,system.setting,config.hotel.edit');
    Route::delete('portal-wallpaper', [\App\Http\Controllers\Api\PortalWallpaperController::class, 'resetWallpaper'])->middleware('permission:admin,system.setting,config.hotel.edit');
    Route::post('templates/{id}/duplicate', [\App\Http\Controllers\Api\TemplateController::class, 'duplicate'])->middleware($canWrite('config.template.*'));
    Route::post('templates/{id}/make-default', [\App\Http\Controllers\Api\TemplateController::class, 'makeDefault'])->middleware($canWrite('config.template.*'));
    Route::post('templates/{id}/remove-default', [\App\Http\Controllers\Api\TemplateController::class, 'removeDefault'])->middleware($canWrite('config.template.*'));
    Route::get('templates/{id}/versions', [\App\Http\Controllers\Api\TemplateController::class, 'versions']);
    Route::post('templates/{id}/rollback', [\App\Http\Controllers\Api\TemplateController::class, 'rollback'])->middleware($canWrite('config.template.*'));
    Route::match(['get', 'post'], 'templates/{id}/preview', [\App\Http\Controllers\Api\TemplateController::class, 'preview']);
    Route::post('templates/{id}/render', [\App\Http\Controllers\Api\TemplateController::class, 'render']);
    Route::post('templates/upload-image', [\App\Http\Controllers\Api\TemplateController::class, 'uploadImage'])->middleware($canWrite('config.template.*'));
    Route::apiResource('templates', \App\Http\Controllers\Api\TemplateController::class)->middleware($canWrite('config.template.*'));

    // Operational print slots (Breakfast Ticket, Invoice, Registration Card...).
    Route::post('print-template-slots/payment-receipt', [\App\Http\Controllers\Api\PrintTemplateSlotController::class, 'paymentReceipt']);
    Route::post('print-template-slots/{printTemplateSlot}/render', [\App\Http\Controllers\Api\PrintTemplateSlotController::class, 'render']);
    Route::get('print-template-slots', [\App\Http\Controllers\Api\PrintTemplateSlotController::class, 'index']);
    Route::put('print-template-slots/{printTemplateSlot}', [\App\Http\Controllers\Api\PrintTemplateSlotController::class, 'update'])->middleware($canWrite('config.template.*'));

    // Dynamic MySQL Stored Procedure report data sources
    Route::get('report-procedures', [\App\Http\Controllers\Api\ReportProcedureController::class, 'index']);
    Route::get('report-procedures/{procedure}', [\App\Http\Controllers\Api\ReportProcedureController::class, 'show']);
    Route::post('report-procedure-samples', [\App\Http\Controllers\Api\ReportProcedureController::class, 'sample'])->middleware($canWrite('config.template.*'));
    Route::post('report-data-sources/{reportDataSource}/samples', [\App\Http\Controllers\Api\ReportDataSourceController::class, 'sample'])->middleware($canWrite('config.template.*'));
    Route::post('report-data-sources/{reportDataSource}/schema-refreshes', [\App\Http\Controllers\Api\ReportDataSourceController::class, 'refreshSchema'])->middleware($canWrite('config.template.*'));
    Route::apiResource('report-data-sources', \App\Http\Controllers\Api\ReportDataSourceController::class)
        ->parameters(['report-data-sources' => 'reportDataSource'])->middleware($canWrite('config.template.*'));

    Route::get('report-lookups/{lookup}', [\App\Http\Controllers\Api\ReportLookupController::class, 'index']);

    // Business report catalogue: one Store, configurable filters and many output templates.
    Route::post('report-definitions/{reportDefinition}/execute', [\App\Http\Controllers\Api\ReportDefinitionController::class, 'execute']);
    Route::post('report-definitions/{reportDefinition}/render', [\App\Http\Controllers\Api\ReportDefinitionController::class, 'render']);
    Route::post('report-definitions/{reportDefinition}/exports', [\App\Http\Controllers\Api\ReportDefinitionController::class, 'export']);
    // Dòng 327, 328: cấu hình menu báo cáo theo module và quyền xem từng báo cáo
    Route::get('report-menu/navigation', [\App\Http\Controllers\Api\ReportMenuController::class, 'navigation']);
    Route::get('report-menu', [\App\Http\Controllers\Api\ReportMenuController::class, 'index'])->middleware('permission:admin,system.setting,config.hotel.edit');
    Route::put('report-menu/{location}', [\App\Http\Controllers\Api\ReportMenuController::class, 'update'])->middleware('permission:admin,system.setting,config.hotel.edit');
    Route::get('report-definitions/{reportDefinition}/role-access', [\App\Http\Controllers\Api\ReportMenuController::class, 'roleAccess'])->middleware('permission:system.user.view');
    Route::put('report-definitions/{reportDefinition}/role-access', [\App\Http\Controllers\Api\ReportMenuController::class, 'updateRoleAccess'])->middleware('permission:system.user.manage');
    Route::apiResource('report-definitions', \App\Http\Controllers\Api\ReportDefinitionController::class)
        ->parameters(['report-definitions' => 'reportDefinition'])->middleware($canWrite('config.template.*'));

    // System configuration routes
    Route::get('nationalities', [\App\Http\Controllers\Api\NationalityController::class, 'index']);
    // Guest definitions routes (SP8015, SP8017, SP8019, SP8042, SP8055)
    Route::get('guest-definitions', [\App\Http\Controllers\Api\GuestDefinitionController::class, 'index']);
    Route::get('guest-titles', [\App\Http\Controllers\Api\GuestDefinitionController::class, 'titles']);
    Route::get('border-gates', [\App\Http\Controllers\Api\GuestDefinitionController::class, 'borderGates']);
    Route::get('entry-purposes', [\App\Http\Controllers\Api\GuestDefinitionController::class, 'entryPurposes']);
    Route::get('guest-types', [\App\Http\Controllers\Api\GuestDefinitionController::class, 'guestTypes']);
    Route::get('id-types', [\App\Http\Controllers\Api\GuestDefinitionController::class, 'idTypes']);
    // Khai báo lưu trú - danh sách khách đã khai báo (SP8007)
    Route::get('residence-declarations', [\App\Http\Controllers\Api\ResidenceDeclarationController::class, 'index']);
    Route::post('residence-declarations', [\App\Http\Controllers\Api\ResidenceDeclarationController::class, 'store'])->middleware('permission:fo.guest.create,fo.guest.edit,fo.checkin');
    Route::get('provinces', [\App\Http\Controllers\Api\GuestDefinitionController::class, 'provinces']);
    Route::get('districts', [\App\Http\Controllers\Api\GuestDefinitionController::class, 'districts']);
    Route::get('wards', [\App\Http\Controllers\Api\GuestDefinitionController::class, 'wards']);
    Route::post('geo/sync', [\App\Http\Controllers\Api\GuestDefinitionController::class, 'syncGeo'])->middleware($canWrite('config.system.*'));
    Route::apiResource('payment-methods', \App\Http\Controllers\Api\PaymentMethodController::class)->middleware($canWrite('config.system.*'));
    Route::apiResource('currencies', \App\Http\Controllers\Api\CurrencyController::class)->middleware($canWrite('config.system.*'));
    Route::get('bank-accounts/lookups', [\App\Http\Controllers\Api\BankAccountController::class, 'lookups'])
        ->middleware('permission:system.user.view,fo.payment.create');
    Route::apiResource('bank-accounts', \App\Http\Controllers\Api\BankAccountController::class)
        ->only(['index', 'show'])
        ->middleware('permission:system.user.view,fo.payment.create');
    Route::apiResource('bank-accounts', \App\Http\Controllers\Api\BankAccountController::class)
        ->only(['store', 'update', 'destroy'])
        ->middleware('permission:system.user.manage');
    Route::apiResource('units-of-measure', \App\Http\Controllers\Api\UnitOfMeasureController::class)->middleware($canWrite('config.system.*', 'config.housekeeping.*'));
    Route::apiResource('room-rate-codes', RoomRateCodeController::class)->middleware($canWrite('config.rates.*'));
    Route::apiResource('registration-statuses', \App\Http\Controllers\Api\RegistrationStatusController::class)->middleware($canWrite('config.system.*'));
    Route::post('room-rate-codes/{ma}/plans', [RoomRateCodeController::class, 'saveRatePlan'])->middleware($canWrite('config.rates.*'));
    Route::delete('room-rate-codes/{ma}/plans/{code}', [RoomRateCodeController::class, 'deleteRatePlan'])->middleware($canWrite('config.rates.*'));
    Route::post('room-rate-codes/{ma}/daily-mappings', [RoomRateCodeController::class, 'saveDailyMappings'])->middleware($canWrite('config.rates.*'));

    // System Administration routes
    // Dòng 406: sao lưu / khôi phục database chỉ admin, xuất & nhập phải gửi kèm mật khẩu xác nhận
    Route::get('/system/database/info', [\App\Http\Controllers\Api\DatabaseBackupController::class, 'getDatabaseInfo'])->middleware('permission:admin');
    Route::post('/system/database/export', [\App\Http\Controllers\Api\DatabaseBackupController::class, 'exportDatabase'])->middleware('permission:admin');
    Route::post('/system/database/import', [\App\Http\Controllers\Api\DatabaseBackupController::class, 'importDatabase'])->middleware('permission:admin');
    Route::post('system-branches/{id}/provision', [\App\Http\Controllers\Api\SystemBranchController::class, 'provision'])->middleware('permission:system.branch.manage');
    Route::get('system-branches/list', [\App\Http\Controllers\Api\UserPermissionController::class, 'listBranches']);
    Route::get('system-branches', [\App\Http\Controllers\Api\SystemBranchController::class, 'index']);
    Route::post('system-branches', [\App\Http\Controllers\Api\SystemBranchController::class, 'store'])->middleware('permission:system.branch.manage');
    Route::get('system-branches/{systemBranch}', [\App\Http\Controllers\Api\SystemBranchController::class, 'show']);
    Route::put('system-branches/{systemBranch}', [\App\Http\Controllers\Api\SystemBranchController::class, 'update'])->middleware('permission:system.branch.manage');
    Route::delete('system-branches/{systemBranch}', [\App\Http\Controllers\Api\SystemBranchController::class, 'destroy'])->middleware('permission:system.branch.manage');
    Route::apiResource('lost-and-found', \App\Http\Controllers\Api\LostAndFoundController::class)->middleware($canWrite('hk.lost_found.*', 'hk.lost_found.manage'));
    
    // User & Employee routes
    Route::get('users', [\App\Http\Controllers\Api\UserController::class, 'index'])->middleware('permission:system.user.view');
    Route::post('users', [\App\Http\Controllers\Api\UserController::class, 'store'])->middleware('permission:system.user.manage');
    Route::get('users/{user}', [\App\Http\Controllers\Api\UserController::class, 'show'])->middleware('permission:system.user.view');
    Route::put('users/{user}', [\App\Http\Controllers\Api\UserController::class, 'update'])->middleware('permission:system.user.manage');
    Route::delete('users/{user}', [\App\Http\Controllers\Api\UserController::class, 'destroy'])->middleware('permission:system.user.manage');
    Route::post('users/{user}/reset-password', [\App\Http\Controllers\Api\UserController::class, 'resetPassword'])->middleware('permission:system.user.manage');
    Route::post('users/{id}/signature', [\App\Http\Controllers\Api\UserController::class, 'uploadSignature'])->middleware('permission:system.user.manage');
    Route::delete('users/{id}/signature', [\App\Http\Controllers\Api\UserController::class, 'deleteSignature'])->middleware('permission:system.user.manage');

    // User Roles & Branch Sync
    Route::get('users/{id}/permissions', [\App\Http\Controllers\Api\UserPermissionController::class, 'getUserPermissions'])->middleware('permission:system.user.view');
    Route::post('users/{id}/branches/sync', [\App\Http\Controllers\Api\UserPermissionController::class, 'syncBranches'])->middleware('permission:system.user.manage');
    Route::post('users/{id}/roles/sync', [\App\Http\Controllers\Api\UserPermissionController::class, 'syncRoles'])->middleware('permission:system.user.manage');

    // Cơ cấu tổ chức và phân quyền theo từng chi nhánh
    Route::get('organization', [\App\Http\Controllers\Api\OrganizationController::class, 'index'])->middleware('permission:system.user.view');
    Route::post('organization/departments', [\App\Http\Controllers\Api\OrganizationController::class, 'storeDepartment'])->middleware('permission:system.user.manage');
    Route::put('organization/departments/{department}', [\App\Http\Controllers\Api\OrganizationController::class, 'updateDepartment'])->middleware('permission:system.user.manage');
    Route::post('organization/positions', [\App\Http\Controllers\Api\OrganizationController::class, 'storePosition'])->middleware('permission:system.user.manage');
    Route::put('organization/positions/{position}', [\App\Http\Controllers\Api\OrganizationController::class, 'updatePosition'])->middleware('permission:system.user.manage');
    Route::delete('organization/positions/{position}', [\App\Http\Controllers\Api\OrganizationController::class, 'destroyPosition'])->middleware('permission:system.user.manage');
    Route::post('organization/positions/{position}/branches/sync', [\App\Http\Controllers\Api\OrganizationController::class, 'syncPositionBranches'])->middleware('permission:system.user.manage');

    Route::get('users/{user}/organization', [\App\Http\Controllers\Api\UserOrganizationController::class, 'show'])->middleware('permission:system.user.view');
    Route::post('users/{user}/organization/sync', [\App\Http\Controllers\Api\UserOrganizationController::class, 'sync'])->middleware('permission:system.user.manage');
    Route::post('users/{user}/warehouses/sync', [\App\Http\Controllers\Api\UserOrganizationController::class, 'syncWarehouses'])->middleware('permission:system.user.manage');

    // Roles & Permissions Management
    Route::get('roles', [\App\Http\Controllers\Api\RoleController::class, 'index'])->middleware('permission:system.user.view');
    Route::post('roles', [\App\Http\Controllers\Api\RoleController::class, 'store'])->middleware('permission:system.user.manage');
    Route::put('roles/{id}', [\App\Http\Controllers\Api\RoleController::class, 'update'])->middleware('permission:system.user.manage');
    Route::delete('roles/{id}', [\App\Http\Controllers\Api\RoleController::class, 'destroy'])->middleware('permission:system.user.manage');
    Route::get('roles/{id}/permissions', [\App\Http\Controllers\Api\RoleController::class, 'getPermissions'])->middleware('permission:system.user.view');
    Route::post('roles/{id}/permissions/sync', [\App\Http\Controllers\Api\RoleController::class, 'syncPermissions'])->middleware('permission:system.user.manage');
    Route::get('permissions', [\App\Http\Controllers\Api\RoleController::class, 'allPermissions'])->middleware('permission:system.user.view');
    Route::get('roles/{role}/branch-permissions', [\App\Http\Controllers\Api\BranchRolePermissionController::class, 'matrix'])->middleware('permission:system.user.view');
    Route::post('roles/{role}/branch-permissions/sync', [\App\Http\Controllers\Api\BranchRolePermissionController::class, 'sync'])->middleware('permission:system.user.manage');
    Route::post('roles/{sourceRole}/copy', [\App\Http\Controllers\Api\BranchRolePermissionController::class, 'copyRole'])->middleware('permission:system.user.manage');
    Route::post('permission-screens', [\App\Http\Controllers\Api\BranchRolePermissionController::class, 'storeScreen'])->middleware('permission:system.user.manage');
    Route::apiResource('product-categories', \App\Http\Controllers\Api\ProductCategoryController::class)->middleware($canWrite('config.housekeeping.*', 'hk.warehouse.manage'));
    Route::post('/housekeeping/outlets/reorder', [\App\Http\Controllers\Api\HousekeepingOutletController::class, 'reorder'])->middleware($canWrite('config.housekeeping.*'));
    Route::delete('/housekeeping/outlets/{housekeepingOutlet}/force', [\App\Http\Controllers\Api\HousekeepingOutletController::class, 'forceDestroy'])->name('housekeeping.outlets.force-destroy')->middleware($canWrite('config.housekeeping.*'));

    Route::get('/housekeeping/outlets', [\App\Http\Controllers\Api\HousekeepingOutletController::class, 'index'])->name('housekeeping.outlets.index')->middleware($canWrite('config.housekeeping.*'));
    Route::post('/housekeeping/outlets', [\App\Http\Controllers\Api\HousekeepingOutletController::class, 'store'])->name('housekeeping.outlets.store')->middleware($canWrite('config.housekeeping.*'));
    Route::get('/housekeeping/outlets/{housekeepingOutlet}', [\App\Http\Controllers\Api\HousekeepingOutletController::class, 'show'])->name('housekeeping.outlets.show')->middleware($canWrite('config.housekeeping.*'));
    Route::put('/housekeeping/outlets/{housekeepingOutlet}', [\App\Http\Controllers\Api\HousekeepingOutletController::class, 'update'])->name('housekeeping.outlets.update')->middleware($canWrite('config.housekeeping.*'));
    Route::patch('/housekeeping/outlets/{housekeepingOutlet}', [\App\Http\Controllers\Api\HousekeepingOutletController::class, 'update'])->name('housekeeping.outlets.patch')->middleware($canWrite('config.housekeeping.*'));
    Route::delete('/housekeeping/outlets/{housekeepingOutlet}', [\App\Http\Controllers\Api\HousekeepingOutletController::class, 'destroy'])->name('housekeeping.outlets.destroy')->middleware($canWrite('config.housekeeping.*'));

    // HK Config: ký hiệu phòng + cột mẫu in
    Route::get('/hk-config', [\App\Http\Controllers\Api\HkConfigController::class, 'index']);
    Route::put('/hk-config/symbols', [\App\Http\Controllers\Api\HkConfigController::class, 'updateSymbols'])->middleware($canWrite('config.housekeeping.*'));
    Route::put('/hk-config/print-cols', [\App\Http\Controllers\Api\HkConfigController::class, 'updatePrintCols'])->middleware($canWrite('config.housekeeping.*'));
    Route::post('/hk-config/reset', [\App\Http\Controllers\Api\HkConfigController::class, 'reset'])->middleware($canWrite('config.housekeeping.*'));


    Route::get('/products/next-code', [\App\Http\Controllers\Api\ProductController::class, 'nextCode']);
    Route::post('/products/bulk-toggle-active', [\App\Http\Controllers\Api\ProductController::class, 'bulkToggleActive'])->middleware($canWrite('config.housekeeping.*', 'hk.warehouse.manage'));
    Route::get('/products/export', [\App\Http\Controllers\Api\ProductController::class, 'exportExcel']);
    Route::post('/products/import', [\App\Http\Controllers\Api\ProductController::class, 'importExcel'])->middleware($canWrite('config.housekeeping.*', 'hk.warehouse.manage'));
    Route::apiResource('products', \App\Http\Controllers\Api\ProductController::class)->middleware($canWrite('config.housekeeping.*', 'hk.warehouse.manage'));
    Route::apiResource('inventories', \App\Http\Controllers\Api\InventoryController::class)->middleware($canWrite('hk.warehouse.*', 'hk.warehouse.import', 'hk.warehouse.manage', 'fb.menu.manage'));

    // ─── Quản lý Kho (Warehouses) ────────────────────────────────────
    Route::get('/warehouses', [\App\Http\Controllers\Api\WarehouseController::class, 'index'])->middleware($canWrite('hk.warehouse.*', 'hk.warehouse.manage', 'config.housekeeping.*'));
    Route::post('/warehouses', [\App\Http\Controllers\Api\WarehouseController::class, 'store'])->middleware($canWrite('hk.warehouse.*', 'hk.warehouse.manage', 'config.housekeeping.*'));
    Route::put('/warehouses/{id}', [\App\Http\Controllers\Api\WarehouseController::class, 'update'])->middleware($canWrite('hk.warehouse.*', 'hk.warehouse.manage', 'config.housekeeping.*'));
    Route::delete('/warehouses/{id}', [\App\Http\Controllers\Api\WarehouseController::class, 'destroy'])->middleware($canWrite('hk.warehouse.*', 'hk.warehouse.manage', 'config.housekeeping.*'));

    // ─── Kiểm kê tồn kho định kỳ (Inventory Checks) ─────────────────
    Route::get('/inventory/checks', [\App\Http\Controllers\Api\InventoryCheckController::class, 'index'])->middleware($canWrite('hk.warehouse.*', 'hk.warehouse.import', 'hk.warehouse.manage', 'fb.menu.manage'));
    Route::post('/inventory/checks', [\App\Http\Controllers\Api\InventoryCheckController::class, 'store'])->middleware($canWrite('hk.warehouse.*', 'hk.warehouse.import', 'hk.warehouse.manage', 'fb.menu.manage'));
    Route::post('/inventory/checks/sync-previous-month', [\App\Http\Controllers\Api\InventoryCheckController::class, 'syncPreviousMonth'])->middleware($canWrite('hk.warehouse.*', 'hk.warehouse.import', 'hk.warehouse.manage', 'fb.menu.manage'));
    Route::post('/inventory/checks/save', [\App\Http\Controllers\Api\InventoryCheckController::class, 'save'])->middleware($canWrite('hk.warehouse.*', 'hk.warehouse.import', 'hk.warehouse.manage', 'fb.menu.manage'));
    Route::delete('/inventory/checks/{id}', [\App\Http\Controllers\Api\InventoryCheckController::class, 'destroy'])->middleware($canWrite('hk.warehouse.*', 'hk.warehouse.import', 'hk.warehouse.manage', 'fb.menu.manage'));
    Route::post('/inventory/checks/{id}/items', [\App\Http\Controllers\Api\InventoryCheckController::class, 'addItems'])->middleware($canWrite('hk.warehouse.*', 'hk.warehouse.import', 'hk.warehouse.manage', 'fb.menu.manage'));
    Route::put('/inventory/checks/{id}/items/{itemId}', [\App\Http\Controllers\Api\InventoryCheckController::class, 'updateItem'])->middleware($canWrite('hk.warehouse.*', 'hk.warehouse.import', 'hk.warehouse.manage', 'fb.menu.manage'));
    Route::get('/inventory/checks/{id}/export', [\App\Http\Controllers\Api\InventoryCheckController::class, 'exportExcel'])->middleware($canWrite('hk.warehouse.*', 'hk.warehouse.import', 'hk.warehouse.manage', 'fb.menu.manage'));
    Route::get('/inventory/products-in-stock', [\App\Http\Controllers\Api\InventoryCheckController::class, 'productsInStock']);

    // ─── Nhật ký nhập/xuất/chuyển kho từng ngày (Daily Logs) ─────────
    Route::get('/inventory/logs', [\App\Http\Controllers\Api\InventoryLogController::class, 'index']);
    Route::get('/inventory/logs/export', [\App\Http\Controllers\Api\InventoryLogController::class, 'exportExcel']);
    Route::put('/inventory/logs', [\App\Http\Controllers\Api\InventoryLogController::class, 'upsert'])->middleware($canWrite('hk.warehouse.*', 'hk.warehouse.import', 'hk.warehouse.manage', 'fb.menu.manage'));
    Route::post('/inventory/get-bill', [\App\Http\Controllers\Api\InventoryLogController::class, 'getBill']);

    // ─── Chuyển kho (Transfer) ────────────────────────────────────────
    Route::post('/inventory/transfer', [\App\Http\Controllers\Api\InventoryTransferController::class, 'store'])->middleware($canWrite('hk.warehouse.*', 'hk.warehouse.import', 'hk.warehouse.manage', 'fb.menu.manage'));
    Route::post('/users/{id}/signature', [\App\Http\Controllers\Api\UserController::class, 'uploadSignature'])
        ->middleware('permission:system.user.manage');
    Route::delete('/users/{id}/signature', [\App\Http\Controllers\Api\UserController::class, 'deleteSignature'])
        ->middleware('permission:system.user.manage');
    Route::get('/info-business', [\App\Http\Controllers\Api\InfoBusinessController::class, 'show']);
    Route::put('/info-business', [\App\Http\Controllers\Api\InfoBusinessController::class, 'update'])->middleware($canWrite('config.hotel.*'));
    Route::post('/info-business/logo', [\App\Http\Controllers\Api\InfoBusinessController::class, 'uploadLogo'])->middleware($canWrite('config.hotel.*'));
    Route::delete('/info-business/logo', [\App\Http\Controllers\Api\InfoBusinessController::class, 'deleteLogo'])->middleware($canWrite('config.hotel.*'));
    Route::get('/departments', [\App\Http\Controllers\Api\DepartmentController::class, 'index']);
    Route::post('/outlets/reorder', [\App\Http\Controllers\Api\OutletController::class, 'reorder'])->middleware($canWrite('fb.overview.*', 'fb.menu.manage', 'config.housekeeping.*'));
    Route::get('/outlets/hk', [\App\Http\Controllers\Api\OutletController::class, 'listHK']); // HK outlets cho Get Bill
    Route::apiResource('outlets', \App\Http\Controllers\Api\OutletController::class)->middleware($canWrite('fb.overview.*', 'fb.menu.manage', 'config.housekeeping.*'));
    Route::apiResource('fb-locations', \App\Http\Controllers\Api\FbLocationController::class)->middleware($canWrite('fb.overview.*', 'fb.menu.manage'));
    Route::post('fb-tables/bulk-create', [\App\Http\Controllers\Api\FbTableController::class, 'bulkCreate'])->middleware($canWrite('fb.overview.*', 'fb.menu.manage'));
    Route::post('fb-tables/delete-row', [\App\Http\Controllers\Api\FbTableController::class, 'deleteRow'])->middleware($canWrite('fb.overview.*', 'fb.menu.manage'));
    Route::post('fb-tables/{from_id}/transfer/{to_id}', [\App\Http\Controllers\FbOrderController::class, 'transferTable'])->middleware('permission:fb.order.edit,fb.order.create');
    Route::post('fb-tables/{from_id}/transfer-items/{to_id}', [\App\Http\Controllers\FbOrderController::class, 'transferItems'])->middleware('permission:fb.order.edit,fb.order.create');
    Route::apiResource('fb-tables', \App\Http\Controllers\Api\FbTableController::class)->middleware($canWrite('fb.overview.*', 'fb.menu.manage'));

    // Dedicated F&B Menu definitions routes
    Route::post('/fb-products/bulk-toggle-active', [\App\Http\Controllers\Api\FbProductController::class, 'bulkToggleActive'])->middleware($canWrite('fb.menu.*', 'fb.menu.manage'));
    Route::post('/fb-products/bulk-update-status', [\App\Http\Controllers\Api\FbProductController::class, 'bulkUpdateStatus'])->middleware($canWrite('fb.menu.*', 'fb.menu.manage'));
    Route::apiResource('fb-product-categories', \App\Http\Controllers\Api\FbProductCategoryController::class)->middleware($canWrite('fb.menu.*', 'fb.menu.manage'));
    Route::apiResource('fb-products', \App\Http\Controllers\Api\FbProductController::class)->middleware($canWrite('fb.menu.*', 'fb.menu.manage'));
    Route::apiResource('fb-printers', \App\Http\Controllers\Api\FbPrinterController::class)->middleware($canWrite('fb.overview.*', 'fb.menu.manage'));
    Route::apiResource('fb-promotions', \App\Http\Controllers\FbPromotionController::class)->middleware($canWrite('fb.menu.*', 'fb.menu.manage'));
    Route::apiResource('fb-parties', \App\Http\Controllers\Api\FbPartyController::class)->middleware($canWrite('fb.party.*', 'fb.party.manage'));
    Route::post('fb-parties/{id}/cancel', [\App\Http\Controllers\Api\FbPartyController::class, 'cancel'])->middleware('permission:fb.party.delete,fb.party.manage');
    Route::post('fb-parties/check-conflict', [\App\Http\Controllers\Api\FbPartyController::class, 'checkConflict']);
    Route::post('fb-parties/{partyId}/sub-parties/{subPartyId}/complete', [\App\Http\Controllers\Api\FbPartyController::class, 'completeSubParty'])->middleware('permission:fb.party.manage');

    // F&B Orders (Bills)
    Route::get('/fnb/orders', [\App\Http\Controllers\FbOrderController::class, 'search']);
    Route::get('/fnb/tables/{tableId}/active-orders', [\App\Http\Controllers\FbOrderController::class, 'getActiveOrders']);
    Route::post('/fnb/tables/{tableId}/orders/sync', [\App\Http\Controllers\FbOrderController::class, 'syncOrders'])->middleware('permission:fb.order.create,fb.order.edit,fb.payment');
    Route::get('/fnb/orders/{orderId}/print-logs', [\App\Http\Controllers\FbPrintLogController::class, 'getByOrder']);


    Route::get('/departments', [\App\Http\Controllers\Api\DepartmentController::class, 'index']);
    Route::post('/outlets/reorder', [\App\Http\Controllers\Api\OutletController::class, 'reorder'])->middleware($canWrite('fb.overview.*', 'fb.menu.manage', 'config.housekeeping.*'));
    Route::apiResource('outlets', \App\Http\Controllers\Api\OutletController::class)->middleware($canWrite('fb.overview.*', 'fb.menu.manage', 'config.housekeeping.*'));
    Route::apiResource('fb-locations', \App\Http\Controllers\Api\FbLocationController::class)->middleware($canWrite('fb.overview.*', 'fb.menu.manage'));
    Route::post('fb-tables/bulk-create', [\App\Http\Controllers\Api\FbTableController::class, 'bulkCreate'])->middleware($canWrite('fb.overview.*', 'fb.menu.manage'));
    Route::post('fb-tables/delete-row', [\App\Http\Controllers\Api\FbTableController::class, 'deleteRow'])->middleware($canWrite('fb.overview.*', 'fb.menu.manage'));
    Route::post('fb-tables/{from_id}/transfer/{to_id}', [\App\Http\Controllers\FbOrderController::class, 'transferTable'])->middleware('permission:fb.order.edit,fb.order.create');
    Route::post('fb-tables/{from_id}/transfer-items/{to_id}', [\App\Http\Controllers\FbOrderController::class, 'transferItems'])->middleware('permission:fb.order.edit,fb.order.create');
    Route::apiResource('fb-tables', \App\Http\Controllers\Api\FbTableController::class)->middleware($canWrite('fb.overview.*', 'fb.menu.manage'));

    // Dedicated F&B Menu definitions routes
    Route::post('/fb-products/bulk-toggle-active', [\App\Http\Controllers\Api\FbProductController::class, 'bulkToggleActive'])->middleware($canWrite('fb.menu.*', 'fb.menu.manage'));
    Route::post('/fb-products/bulk-update-status', [\App\Http\Controllers\Api\FbProductController::class, 'bulkUpdateStatus'])->middleware($canWrite('fb.menu.*', 'fb.menu.manage'));
    Route::apiResource('fb-product-categories', \App\Http\Controllers\Api\FbProductCategoryController::class)->middleware($canWrite('fb.menu.*', 'fb.menu.manage'));
    Route::apiResource('fb-products', \App\Http\Controllers\Api\FbProductController::class)->middleware($canWrite('fb.menu.*', 'fb.menu.manage'));
    Route::apiResource('fb-printers', \App\Http\Controllers\Api\FbPrinterController::class)->middleware($canWrite('fb.overview.*', 'fb.menu.manage'));
    Route::apiResource('fb-promotions', \App\Http\Controllers\FbPromotionController::class)->middleware($canWrite('fb.menu.*', 'fb.menu.manage'));
    Route::apiResource('fb-parties', \App\Http\Controllers\Api\FbPartyController::class)->middleware($canWrite('fb.party.*', 'fb.party.manage'));
    Route::post('fb-parties/{id}/cancel', [\App\Http\Controllers\Api\FbPartyController::class, 'cancel'])->middleware('permission:fb.party.delete,fb.party.manage');
    Route::post('fb-parties/check-conflict', [\App\Http\Controllers\Api\FbPartyController::class, 'checkConflict']);
    Route::post('fb-parties/{partyId}/sub-parties/{subPartyId}/complete', [\App\Http\Controllers\Api\FbPartyController::class, 'completeSubParty'])->middleware('permission:fb.party.manage');

    // F&B Orders (Bills)
    Route::get('/fnb/orders', [\App\Http\Controllers\FbOrderController::class, 'search']);
    Route::get('/fnb/tables/{tableId}/active-orders', [\App\Http\Controllers\FbOrderController::class, 'getActiveOrders']);
    Route::post('/fnb/tables/{tableId}/orders/sync', [\App\Http\Controllers\FbOrderController::class, 'syncOrders'])->middleware('permission:fb.order.create,fb.order.edit,fb.payment');
    Route::get('/fnb/orders/{orderId}/print-logs', [\App\Http\Controllers\FbPrintLogController::class, 'getByOrder']);

    // Activity Log routes
    Route::get('/activity-logs', [\App\Http\Controllers\Api\ActivityLogController::class, 'index'])->middleware('permission:mgmt.activity_log,fb.view');
    Route::get('/activity-logs/stats', [\App\Http\Controllers\Api\ActivityLogController::class, 'stats'])->middleware('permission:mgmt.activity_log,fb.view');

    // =====================================================================
    // BOOKING (Đăng ký phòng) routes
    // =====================================================================

    // #12 — Xuất Excel (đặt TRƯỚC apiResource để không bị override)
    Route::get('bookings/init-dropdowns', [\App\Http\Controllers\Api\BookingController::class, 'initDropdowns']);
    Route::get('general-search/options', [\App\Http\Controllers\Api\GeneralSearchController::class, 'options']);
    Route::get('general-search/suggestions', [\App\Http\Controllers\Api\GeneralSearchController::class, 'suggestions']);
    Route::get('general-search', [\App\Http\Controllers\Api\GeneralSearchController::class, 'index']);
    Route::get('general-search/guests/{guest}/history', [\App\Http\Controllers\Api\GeneralSearchController::class, 'guestHistory']);
    Route::get('bookings/export', [\App\Http\Controllers\Api\BookingController::class, 'export']);
    Route::get('bookings/{booking}/notifications', [\App\Http\Controllers\Api\BookingNotificationController::class, 'index']);
    Route::get('bookings/{booking}/notifications/active', [\App\Http\Controllers\Api\BookingNotificationController::class, 'active']);
    Route::post('bookings/{booking}/notifications', [\App\Http\Controllers\Api\BookingNotificationController::class, 'store'])->middleware('permission:fo.booking.edit');
    Route::put('bookings/{booking}/notifications/{notification}', [\App\Http\Controllers\Api\BookingNotificationController::class, 'update'])->middleware('permission:fo.booking.edit');
    Route::delete('bookings/{booking}/notifications/{notification}', [\App\Http\Controllers\Api\BookingNotificationController::class, 'destroy'])->middleware('permission:fo.booking.edit');
    Route::get('bookings/{booking}', [\App\Http\Controllers\Api\BookingController::class, 'show']);
    Route::get('bookings', [\App\Http\Controllers\Api\BookingController::class, 'index']);
    // Các action ghi cần quyền
    Route::post('bookings', [\App\Http\Controllers\Api\BookingController::class, 'store'])->middleware('permission:fo.booking.create');
    Route::post('bookings/{booking}/add-rooms', [\App\Http\Controllers\Api\BookingController::class, 'addRooms'])->middleware('permission:fo.booking.edit');
    Route::put('bookings/{booking}', [\App\Http\Controllers\Api\BookingController::class, 'update'])->middleware('permission:fo.booking.edit');
    Route::delete('bookings/{booking}', [\App\Http\Controllers\Api\BookingController::class, 'destroy'])->middleware('permission:fo.booking.cancel');
    Route::patch('bookings/{bookingId}/no-post', [\App\Http\Controllers\Api\BookingNoPostController::class, 'updateBooking'])->middleware(['permission:fo.booking.edit', \App\Http\Middleware\RequireFrontDeskInvoiceMutation::class]);
    Route::get('bookings/{id}/print-data', [\App\Http\Controllers\Api\BookingPrintController::class, 'getPrintData']);

    // #19 — Nhân bản booking
    Route::post('bookings/{id}/copy', [\App\Http\Controllers\Api\BookingController::class, 'copy'])->middleware('permission:fo.booking.create');
    // #22 — Khôi phục booking đã hủy
    Route::get('bookings/{id}/cancelled-rooms', [\App\Http\Controllers\Api\BookingController::class, 'cancelledRooms']);
    Route::post('bookings/{id}/restore', [\App\Http\Controllers\Api\BookingController::class, 'restore'])->middleware('permission:fo.booking.edit');
    // Khôi phục booking noshow
    Route::post('bookings/{id}/revert-noshow', [\App\Http\Controllers\Api\BookingController::class, 'revertNoshow'])->middleware('permission:fo.booking.edit');

    // --- Booking Rooms (SP2100) ---
    Route::prefix('bookings/{bookingId}/rooms')->group(function () {
        Route::get('/', [\App\Http\Controllers\Api\BookingRoomController::class, 'index']);
        Route::post('/', [\App\Http\Controllers\Api\BookingRoomController::class, 'store'])->middleware('permission:fo.booking.create');
        Route::put('/{roomId}', [\App\Http\Controllers\Api\BookingRoomController::class, 'update'])->middleware('permission:fo.booking.edit');
        Route::put('/{roomId}/room-plan-stay', [\App\Http\Controllers\Api\RoomPlanBookingRoomController::class, 'updateStay'])->middleware('permission:fo.booking.edit');
        Route::post('/bulk-update', [\App\Http\Controllers\Api\BookingRoomController::class, 'bulkUpdate'])->middleware('permission:fo.booking.edit');
        // Epic 5 - Check-in
        Route::patch('/{roomId}/check-in', [\App\Http\Controllers\Api\BookingRoomController::class, 'checkIn'])->middleware('permission:fo.checkin');
        Route::post('/{roomId}/undo-checkin', [\App\Http\Controllers\Api\BookingRoomController::class, 'undoCheckIn'])->middleware('permission:fo.checkin');
        // Epic 6 - Nâng hạng phòng
        Route::patch('/{roomId}/upgrade', [\App\Http\Controllers\Api\BookingRoomController::class, 'upgrade'])->middleware('permission:fo.room.move');
        // Epic 8 - Gỡ số phòng
        Route::patch('/{roomId}/unassign', [\App\Http\Controllers\Api\BookingRoomController::class, 'unassign'])->middleware('permission:fo.booking.edit');
        // Epic 9 - Hủy phòng
        Route::delete('/{roomId}/cancel', [\App\Http\Controllers\Api\BookingRoomController::class, 'cancel'])->middleware('permission:fo.booking.cancel');
        // Khôi phục phòng noshow
        Route::post('/{roomId}/revert-noshow', [\App\Http\Controllers\Api\BookingRoomController::class, 'revertNoshow'])->middleware('permission:fo.booking.edit');
        // Charge noshow
        Route::post('/{roomId}/charge-noshow', [\App\Http\Controllers\Api\BookingRoomServiceController::class, 'chargeNoshow'])->middleware('permission:fo.booking.noshow');
        // Tách phòng
        Route::post('/{roomId}/split', [\App\Http\Controllers\Api\BookingRoomController::class, 'split'])->middleware('permission:fo.booking.edit');
        // Epic 3 - Auto assign room number
        Route::post('/{roomId}/auto-assign', [\App\Http\Controllers\Api\BookingRoomController::class, 'autoAssign'])->middleware('permission:fo.booking.edit');
        // Epic 11 - Do Not Move
        Route::post('/{roomId}/lock-move', [\App\Http\Controllers\Api\BookingRoomController::class, 'lockMove'])->middleware('permission:fo.booking.edit');
        Route::delete('/{roomId}/lock-move', [\App\Http\Controllers\Api\BookingRoomController::class, 'unlockMove'])->middleware('permission:fo.booking.edit');
        // Chuyển phòng & Gộp phòng
        Route::get('/{roomId}/move-target-rooms', [\App\Http\Controllers\Api\BookingRoomController::class, 'getMoveTargetRooms']);
        Route::post('/{roomId}/move', [\App\Http\Controllers\Api\BookingRoomController::class, 'moveRoom'])->middleware('permission:fo.room.move');
    });

    // --- Booking Room Services (SP2102) — Epic 4, 10, 14 ---
    Route::prefix('booking-rooms/{roomId}/services')->group(function () {
        Route::get('/', [\App\Http\Controllers\Api\BookingRoomServiceController::class, 'index'])->middleware('permission:fo.service.view');
        Route::post('/', [\App\Http\Controllers\Api\BookingRoomServiceController::class, 'store'])->middleware('permission:fo.service.add,fo.service.edit');
        Route::delete('/bulk', [\App\Http\Controllers\Api\BookingRoomServiceController::class, 'bulkDelete'])->middleware('permission:fo.service.delete');
        Route::post('/cancel', [\App\Http\Controllers\Api\BookingRoomServiceController::class, 'cancel'])->middleware('permission:fo.service.delete');
        Route::patch('/folio', [\App\Http\Controllers\Api\BookingRoomServiceController::class, 'transferFolio'])->middleware('permission:fo.service.edit');
        Route::get('/quick-transfer-candidates', [\App\Http\Controllers\Api\BookingRoomServiceController::class, 'quickTransferCandidates']);
        Route::post('/quick-transfer', [\App\Http\Controllers\Api\BookingRoomServiceController::class, 'quickTransfer'])->middleware('permission:fo.service.edit');
        Route::post('/split-folio', [\App\Http\Controllers\Api\BookingRoomServiceController::class, 'splitFolio'])->middleware('permission:fo.service.edit');
    });
    Route::patch('booking-rooms/{roomId}/no-post', [\App\Http\Controllers\Api\BookingNoPostController::class, 'updateRoom'])->middleware(['permission:fo.booking.edit', \App\Http\Middleware\RequireFrontDeskInvoiceMutation::class]);
    Route::get('/booking-services/extra-bed-rate', [\App\Http\Controllers\Api\BookingRoomServiceController::class, 'defaultExtraBedRate']);
    Route::get('/service-bills/{billId}/details', [\App\Http\Controllers\Api\BookingRoomServiceController::class, 'billDetails']);
    Route::patch('/service-bills/{billId}/description', [\App\Http\Controllers\Api\BookingRoomServiceController::class, 'updateDescription'])->middleware('permission:fo.service.edit,fo.service.add');
    // Danh sách dịch vụ FO (dùng cho dropdown chọn dịch vụ)
    Route::get('/booking-services/fo-list', [\App\Http\Controllers\Api\BookingRoomServiceController::class, 'foServiceList']);
    Route::post('/booking-room-services/post-housekeeping-bill', [\App\Http\Controllers\Api\BookingRoomServiceController::class, 'postHousekeepingBill'])->middleware('permission:hk.service.bill,fo.service.add,fo.service.edit');
    Route::get('/housekeeping/service-bills', [\App\Http\Controllers\Api\BookingRoomServiceController::class, 'searchHousekeepingInvoices']);
    Route::post('/housekeeping/service-bills/{billId}/cancel', [\App\Http\Controllers\Api\BookingRoomServiceController::class, 'cancelHousekeepingInvoice'])->middleware('permission:hk.service.delete');
    Route::post('/booking-room-services/post-fo-service-bill', [\App\Http\Controllers\Api\BookingRoomServiceController::class, 'postFoServiceBill'])->middleware('permission:fo.service.add,fo.service.edit');
    Route::post('/booking-room-services/post-room-charge', [\App\Http\Controllers\Api\BookingRoomServiceController::class, 'postRoomCharge'])->middleware('permission:fo.service.add,fo.service.edit');
    Route::post('/bookings/{bookingId}/adjust-room-rate', [\App\Http\Controllers\Api\BookingRoomServiceController::class, 'adjustRoomRate'])->middleware('permission:fo.service.edit,fo.service.add');

    // --- Special Requests (SP2107, SP1325) — Epic 15 ---
    Route::get('/special-requests', [\App\Http\Controllers\Api\BookingRoomSpecialRequestController::class, 'catalog']);
    Route::post('/special-requests', [\App\Http\Controllers\Api\BookingRoomSpecialRequestController::class, 'storeMaster'])->middleware('permission:fo.booking.edit,config.system.edit');
    Route::delete('/special-requests/{id}', [\App\Http\Controllers\Api\BookingRoomSpecialRequestController::class, 'destroyMaster'])->middleware('permission:fo.booking.edit,config.system.delete');
    Route::prefix('booking-rooms/{roomId}/special-requests')->group(function () {
        Route::get('/', [\App\Http\Controllers\Api\BookingRoomSpecialRequestController::class, 'index']);
        Route::post('/', [\App\Http\Controllers\Api\BookingRoomSpecialRequestController::class, 'store'])->middleware('permission:fo.booking.edit');
        Route::post('/sync', [\App\Http\Controllers\Api\BookingRoomSpecialRequestController::class, 'sync'])->middleware('permission:fo.booking.edit');
        Route::delete('/{id}', [\App\Http\Controllers\Api\BookingRoomSpecialRequestController::class, 'destroy'])->middleware('permission:fo.booking.edit');
    });

    // --- Guests & Children — Epic 7, 13 ---
    Route::get('/guests/search', [\App\Http\Controllers\Api\GuestController::class, 'searchGuests']);
    Route::post('/guests/{id}/avatar', [\App\Http\Controllers\Api\GuestController::class, 'uploadAvatar'])->middleware('permission:fo.guest.edit,fo.guest.create,fo.booking.edit,fo.checkin');
    Route::get('/bookings/{bookingId}/guests', [\App\Http\Controllers\Api\GuestController::class, 'bookingGuests']);
    Route::post('/bookings/{bookingId}/init-guests', [\App\Http\Controllers\Api\GuestController::class, 'initGuests'])->middleware('permission:fo.guest.edit,fo.guest.create,fo.booking.edit,fo.checkin');
    Route::post('/bookings/{bookingId}/bulk-update-guests', [\App\Http\Controllers\Api\GuestController::class, 'bulkUpdate'])->middleware('permission:fo.guest.edit,fo.guest.create,fo.booking.edit,fo.checkin');
    Route::prefix('booking-rooms/{roomId}/guests')->group(function () {
        Route::get('/', [\App\Http\Controllers\Api\GuestController::class, 'roomGuests']);
        Route::get('/on-date', [\App\Http\Controllers\Api\GuestController::class, 'getGuestsOnDate']);
        Route::post('/', [\App\Http\Controllers\Api\GuestController::class, 'addGuest'])->middleware('permission:fo.guest.edit,fo.guest.create,fo.booking.edit,fo.checkin');
        Route::post('/{guestId}/checkout', [\App\Http\Controllers\Api\GuestController::class, 'checkoutGuest'])->middleware(['permission:fo.checkout', \App\Http\Middleware\RequireFrontDeskInvoiceMutation::class]);
        Route::put('/{guestId}', [\App\Http\Controllers\Api\GuestController::class, 'updateGuest'])->middleware('permission:fo.guest.edit,fo.booking.edit,fo.checkin');
        Route::delete('/{guestId}', [\App\Http\Controllers\Api\GuestController::class, 'removeGuest'])->middleware('permission:fo.guest.edit,fo.booking.edit');
    });
    Route::post('/booking-rooms/{roomId}/checkout', [\App\Http\Controllers\Api\GuestController::class, 'checkoutRoom'])->middleware(['permission:fo.checkout', \App\Http\Middleware\RequireFrontDeskInvoiceMutation::class]);
    Route::post('/bookings/{bookingId}/checkout-preview', [\App\Http\Controllers\Api\GuestController::class, 'previewCheckoutRooms'])->middleware(['permission:fo.checkout', \App\Http\Middleware\RequireFrontDeskInvoiceMutation::class]);
    Route::post('/bookings/{bookingId}/checkout', [\App\Http\Controllers\Api\GuestController::class, 'checkoutBooking'])->middleware(['permission:fo.checkout', \App\Http\Middleware\RequireFrontDeskInvoiceMutation::class]);
    Route::post('/booking-rooms/{roomId}/children/{childId}/checkout', [\App\Http\Controllers\Api\GuestController::class, 'checkoutChild'])->middleware(['permission:fo.checkout', \App\Http\Middleware\RequireFrontDeskInvoiceMutation::class]);
    Route::post('/booking-rooms/{roomId}/restore-checkout', [\App\Http\Controllers\Api\GuestController::class, 'restoreRoomCheckout'])->middleware(['permission:fo.checkout', \App\Http\Middleware\RequireFrontDeskInvoiceMutation::class]);
    Route::post('/bookings/{bookingId}/restore-checkout', [\App\Http\Controllers\Api\GuestController::class, 'restoreBookingCheckout'])->middleware(['permission:fo.checkout', \App\Http\Middleware\RequireFrontDeskInvoiceMutation::class]);
    Route::get('/bookings/{bookingId}/children', [\App\Http\Controllers\Api\GuestController::class, 'bookingChildren']);

    Route::post('/bookings/{bookingId}/children', [\App\Http\Controllers\Api\GuestController::class, 'addChild'])->middleware('permission:fo.booking.edit');
    Route::put('/booking-children/{childId}', [\App\Http\Controllers\Api\GuestController::class, 'updateChild'])->middleware('permission:fo.booking.edit');
    Route::delete('/bookings/{bookingId}/children/{childId}', [\App\Http\Controllers\Api\GuestController::class, 'removeChild'])->middleware('permission:fo.booking.edit');

    // Breakfast details (Epic 13)
    Route::get('/booking-children/{childId}/breakfast-details', [\App\Http\Controllers\Api\GuestController::class, 'breakfastDetails']);
    Route::patch('/booking-children/{childId}/breakfast-details/{detailId}', [\App\Http\Controllers\Api\GuestController::class, 'updateBreakfastDetail'])->middleware('permission:fo.booking.edit');

    // Cancel Reasons catalog
    Route::get('/cancel-reasons', [\App\Http\Controllers\Api\GuestController::class, 'cancelReasons']);

    // =====================================================================
    // #18 — PAYMENTS (Đặt cọc / Deposit) routes
    // =====================================================================
    Route::get('/bookings/{bookingId}/payments', [\App\Http\Controllers\Api\PaymentController::class, 'index']);
    Route::post('/bookings/{bookingId}/payments', [\App\Http\Controllers\Api\PaymentController::class, 'store'])->middleware('permission:fo.payment.create');
    Route::post('/bookings/{bookingId}/settle-payment', [\App\Http\Controllers\Api\PaymentController::class, 'settlePayment'])->middleware('permission:fo.payment.create');
    Route::put('/payments/{id}', [\App\Http\Controllers\Api\PaymentController::class, 'update'])->middleware('permission:fo.payment.create');
    Route::patch('/payments/{id}/folio', [\App\Http\Controllers\Api\PaymentController::class, 'transferFolio'])->middleware('permission:fo.payment.create');
    Route::delete('/payments/{id}', [\App\Http\Controllers\Api\PaymentController::class, 'destroy'])->middleware('permission:fo.payment.create');
    Route::post('/payments/{id}/split', [\App\Http\Controllers\Api\PaymentController::class, 'split'])->middleware('permission:fo.payment.create');
    Route::post('/payments/transfer', [\App\Http\Controllers\Api\PaymentController::class, 'transferMany'])->middleware('permission:fo.payment.create');
    Route::post('/payments/{id}/transfer', [\App\Http\Controllers\Api\PaymentController::class, 'transfer'])->middleware('permission:fo.payment.create');
    Route::get('/payments/{id}/debt-settlements', [\App\Http\Controllers\Api\PaymentController::class, 'debtSettlements']);
    Route::post('/payments/{id}/debt-settlements', [\App\Http\Controllers\Api\PaymentController::class, 'storeDebtSettlement'])->middleware('permission:fo.payment.create');
    Route::delete('/payments/{id}/debt-settlements/{settlementId}', [\App\Http\Controllers\Api\PaymentController::class, 'destroyDebtSettlement'])->middleware('permission:fo.payment.create');

    // =====================================================================
    // HÓA ĐƠN BÁN HÀNG (Sales Invoices) routes
    // =====================================================================
    Route::get('/sales-invoices', [\App\Http\Controllers\Api\SalesInvoiceController::class, 'index'])->middleware('permission:fo.bill.view,fo.payment.view,fb.payment');
    Route::get('/sales-invoices/stats', [\App\Http\Controllers\Api\SalesInvoiceController::class, 'stats'])->middleware('permission:fo.bill.view,fo.payment.view,fb.payment');
    Route::get('/sales-invoices/{id}', [\App\Http\Controllers\Api\SalesInvoiceController::class, 'show'])->middleware('permission:fo.bill.view,fo.payment.view,fb.payment');
    Route::get('/sales-invoices/{id}/print', [\App\Http\Controllers\Api\SalesInvoiceController::class, 'printData'])->middleware('permission:fo.bill.view,fo.payment.view,fb.payment');
    Route::get('/bookings/{bookingId}/sales-invoices', [\App\Http\Controllers\Api\SalesInvoiceController::class, 'byBooking'])->middleware('permission:fo.bill.view,fo.payment.view,fb.payment');

    // Availability
    Route::get('/availability', [\App\Http\Controllers\Api\AvailabilityController::class, 'index']);

    // =====================================================================
    // HK — Phân Công Phòng (Housekeeping Room Assignment)
    // =====================================================================
    // Staff
    Route::get('/hk/staff', [\App\Http\Controllers\Api\HkAssignmentController::class, 'staffIndex']);
    Route::post('/hk/staff', [\App\Http\Controllers\Api\HkAssignmentController::class, 'staffStore'])->middleware('permission:hk.assign');
    Route::put('/hk/staff/{id}', [\App\Http\Controllers\Api\HkAssignmentController::class, 'staffUpdate'])->middleware('permission:hk.assign');
    Route::delete('/hk/staff/{id}', [\App\Http\Controllers\Api\HkAssignmentController::class, 'staffDestroy'])->middleware('permission:hk.assign');
    // Assignment (ngày + ca)
    Route::get('/hk/assignments', [\App\Http\Controllers\Api\HkAssignmentController::class, 'index']);
    Route::post('/hk/assignments', [\App\Http\Controllers\Api\HkAssignmentController::class, 'store'])->middleware('permission:hk.assign');
    // Groups
    Route::post('/hk/assignments/{assignmentId}/groups', [\App\Http\Controllers\Api\HkAssignmentController::class, 'storeGroup'])->middleware('permission:hk.assign');
    Route::put('/hk/assignments/groups/{groupId}', [\App\Http\Controllers\Api\HkAssignmentController::class, 'updateGroup'])->middleware('permission:hk.assign');
    Route::delete('/hk/assignments/groups/{groupId}', [\App\Http\Controllers\Api\HkAssignmentController::class, 'destroyGroup'])->middleware('permission:hk.assign');
    // Rooms in group
    Route::post('/hk/assignments/groups/{groupId}/rooms', [\App\Http\Controllers\Api\HkAssignmentController::class, 'addRooms'])->middleware('permission:hk.assign');
    Route::delete('/hk/assignments/groups/{groupId}/rooms/{roomId}', [\App\Http\Controllers\Api\HkAssignmentController::class, 'removeRoom'])->middleware('permission:hk.assign');
    Route::get('/availability/details', [\App\Http\Controllers\Api\AvailabilityController::class, 'details']);
    Route::get('/availability/check', [\App\Http\Controllers\Api\AvailabilityController::class, 'check']);

    // =====================================================================
    // IN PHIẾU ĂN SÁNG (Breakfast Coupon - sp_035)
    // =====================================================================
    Route::get('/breakfast/list', [\App\Http\Controllers\Api\BreakfastController::class, 'list']);

    // =====================================================================
    // DANH SÁCH CÔNG VIỆC (Shift Work / Frontdesk Work)
    // =====================================================================
    // Bảo mật nâng cao (giới hạn IP / thời gian, tự đăng xuất) - chỉ super admin thay đổi
    Route::prefix('security-settings')->group(function () {
        $controller = \App\Http\Controllers\Api\SecuritySettingController::class;
        Route::get('/', [$controller, 'show']);
        Route::put('/', [$controller, 'update']);
        Route::get('/my-ip', [$controller, 'myIp']);
        Route::post('/ips', [$controller, 'storeIp']);
        Route::put('/ips/{id}', [$controller, 'updateIp']);
        Route::delete('/ips/{id}', [$controller, 'destroyIp']);
        Route::post('/time-windows', [$controller, 'storeWindow']);
        Route::put('/time-windows/{id}', [$controller, 'updateWindow']);
        Route::delete('/time-windows/{id}', [$controller, 'destroyWindow']);
        Route::post('/exempt-users', [$controller, 'addExemptUsers']);
        Route::delete('/exempt-users/{userId}', [$controller, 'removeExemptUser']);
    });

    // Ngôn ngữ & Bản dịch dùng chung toàn hệ thống (Cấu hình hệ thống › Ngôn ngữ)
    Route::prefix('i18n')->group(function () {
        $controller = \App\Http\Controllers\Api\TranslationController::class;
        Route::post('/collect', [$controller, 'collect'])->middleware('permission:system.language.edit');
        Route::get('/admin/areas', [$controller, 'areas'])->middleware('permission:system.language.view');
        Route::get('/admin/entries', [$controller, 'entries'])->middleware('permission:system.language.view');
        Route::get('/admin/lookup', [$controller, 'lookup'])->middleware('permission:system.language.view');
        Route::get('/admin/export', [$controller, 'export'])->middleware('permission:system.language.view');
        Route::get('/admin/languages', [$controller, 'allLanguages'])->middleware('permission:system.language.view');
        Route::post('/admin/entries', [$controller, 'storeEntry'])->middleware('permission:system.language.create');
        Route::put('/admin/entries', [$controller, 'saveEntries'])->middleware('permission:system.language.edit');
        Route::delete('/admin/entries/{id}', [$controller, 'destroyEntry'])->middleware('permission:system.language.delete');
        Route::delete('/admin/entries/{id}/locations', [$controller, 'clearLocations'])->middleware('permission:system.language.edit');
        Route::post('/admin/sync-defaults', [$controller, 'syncDefaults'])->middleware('permission:system.language.edit');
        Route::post('/admin/import', [$controller, 'import'])->middleware('permission:system.language.edit');
        Route::post('/admin/languages', [$controller, 'storeLanguage'])->middleware('permission:system.language.create');
        Route::put('/admin/languages/{id}', [$controller, 'updateLanguage'])->middleware('permission:system.language.edit');
        Route::delete('/admin/languages/{id}', [$controller, 'destroyLanguage'])->middleware('permission:system.language.delete');
    });

    // Alert sinh nhật / special request và nội dung cập nhật hệ thống (icon lịch, icon chuông)
    Route::get('/alerts', [\App\Http\Controllers\Api\AlertListController::class, 'index']);
    Route::put('/alerts/status', [\App\Http\Controllers\Api\AlertListController::class, 'updateStatus']);
    Route::get('/system-updates', [\App\Http\Controllers\Api\SystemUpdateController::class, 'index']);
    Route::post('/system-updates/read', [\App\Http\Controllers\Api\SystemUpdateController::class, 'markRead']);
    Route::post('/system-updates', [\App\Http\Controllers\Api\SystemUpdateController::class, 'store']);
    Route::delete('/system-updates/{id}', [\App\Http\Controllers\Api\SystemUpdateController::class, 'destroy']);

    Route::prefix('shift-work')->group(function () {
        Route::get('/arrivals', [\App\Http\Controllers\Api\ShiftWorkController::class, 'arrivals']);
        Route::get('/departures', [\App\Http\Controllers\Api\ShiftWorkController::class, 'departures']);
        Route::get('/export', [\App\Http\Controllers\Api\ShiftWorkController::class, 'export']);
        Route::get('/pending', [\App\Http\Controllers\Api\ShiftWorkController::class, 'pending']);
        Route::put('/pending/{bookingId}/note', [\App\Http\Controllers\Api\ShiftWorkController::class, 'updatePendingNote'])->middleware('permission:fo.booking.edit');
        Route::get('/shuttle', [\App\Http\Controllers\Api\ShiftWorkController::class, 'shuttle']);
        Route::get('/noshow', [\App\Http\Controllers\Api\ShiftWorkController::class, 'noshow']);
        Route::get('/birthdays', [\App\Http\Controllers\Api\ShiftWorkController::class, 'birthdays']);
    });

    // ── Roles & Permissions ─────────────────────────────────────
    Route::prefix('roles')->middleware('permission:system.user.manage')->group(function () {
        Route::get('/', [\App\Http\Controllers\Api\RoleController::class, 'index']);
        Route::post('/', [\App\Http\Controllers\Api\RoleController::class, 'store']);
        Route::put('/{id}', [\App\Http\Controllers\Api\RoleController::class, 'update']);
        Route::delete('/{id}', [\App\Http\Controllers\Api\RoleController::class, 'destroy']);
        Route::get('/{id}/permissions', [\App\Http\Controllers\Api\RoleController::class, 'getPermissions']);
        Route::post('/{id}/permissions/sync', [\App\Http\Controllers\Api\RoleController::class, 'syncPermissions']);
    });
    Route::get('/permissions', [\App\Http\Controllers\Api\RoleController::class, 'allPermissions'])
        ->middleware('permission:system.user.view');

    // ── User Permissions & Branches ─────────────────────────────
    Route::get('/system-branches/list', [\App\Http\Controllers\Api\UserPermissionController::class, 'listBranches'])
        ->middleware('permission:system.user.view');
    Route::prefix('users/{userId}')->middleware('permission:system.user.manage')->group(function () {
        Route::get('/permissions', [\App\Http\Controllers\Api\UserPermissionController::class, 'getUserPermissions']);
        Route::post('/branches/sync', [\App\Http\Controllers\Api\UserPermissionController::class, 'syncBranches']);
        Route::post('/roles/sync', [\App\Http\Controllers\Api\UserPermissionController::class, 'syncRoles']);
    });

    // ── Departments & Modules (Cơ cấu tổ chức & Ứng dụng) ───────
    Route::get('/departments', [\App\Http\Controllers\Api\DepartmentController::class, 'index'])
        ->middleware('permission:system.user.view');
    Route::post('/departments', [\App\Http\Controllers\Api\DepartmentController::class, 'store'])
        ->middleware('permission:system.user.manage');
    Route::get('/modules', function () {
        $modules = \App\Models\Module::where('is_active', true)->orderBy('sort_order')->get();
        return response()->json(['success' => true, 'data' => $modules]);
    })->middleware('permission:system.user.view');
});

Route::post('/test-log', function (Illuminate\Http\Request $request) {
    \Log::info('TEST PAYLOAD', $request->all());
    return 'ok';
});
