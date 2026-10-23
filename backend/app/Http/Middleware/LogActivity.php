<?php

namespace App\Http\Middleware;

use App\Models\ActivityLog;
use App\Support\ActivityLogFormatter as Fmt;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class LogActivity
{
    /**
     * URL pattern → [module, component] mapping.
     */
    protected array $routeMap = [
        // Auth
        'login' => ['auth', 'Đăng nhập'],
        'logout' => ['auth', 'Đăng xuất'],
        'me' => ['auth', 'Tài khoản cá nhân'],

        // Reservation & Frontdesk
        'bookings' => ['reservation', 'Quản lý Đặt phòng'],
        'booking-rooms' => ['reservation', 'Phòng Đặt'],
        'booking-children' => ['reservation', 'Tạo đăng ký'],
        'room-allocations' => ['reservation', 'Phân bổ phòng'],
        'special-requests' => ['reservation', 'Yêu cầu đặc biệt'],
        'rooms' => ['frontdesk', 'Sơ đồ phòng'],
        'room-locks' => ['reservation', 'Khóa phòng'],
        'guests' => ['reservation', 'Thông tin Khách lưu trú'],
        'payments' => ['frontdesk', 'Thu ngân & Thanh toán'],
        'booking-room-services' => ['frontdesk', 'Trả phòng'],
        'service-bills' => ['frontdesk', 'Trả phòng'],
        'folio' => ['frontdesk', 'Trả phòng'],
        'split-folio' => ['frontdesk', 'Trả phòng'],
        'quick-transfer' => ['frontdesk', 'Trả phòng'],
        'quick-transfer-candidates' => ['frontdesk', 'Trả phòng'],
        'residence-declarations' => ['frontdesk', 'Khai báo tạm trú'],
        'night-audit' => ['frontdesk', 'Sang ngày'],
        'system-date' => ['frontdesk', 'Sang ngày'],
        'shift-work' => ['frontdesk', 'Bàn giao ca'],

        // Housekeeping & Inventory
        'hk' => ['housekeeping', 'Buồng phòng'],
        'housekeeping' => ['housekeeping', 'Buồng phòng'],
        'hk-config' => ['housekeeping', 'In Phân công phòng'],
        'lost-and-found' => ['housekeeping', 'QUản lý đồ thất lạc'],
        'inventories' => ['housekeeping', 'Kho bãi'],
        'inventory' => ['housekeeping', 'Tồn kho'],
        'warehouses' => ['housekeeping', 'Kho hàng'],
        'products' => ['housekeeping', 'Sản phẩm & Minibar'],
        'product-categories' => ['housekeeping', 'Nhóm sản phẩm'],

        // Food & Beverage (F&B)
        'fb-locations' => ['fnb', 'Khu vực F&B'],
        'fb-parties' => ['fnb', 'Tiệc F&B'],
        'fb-printers' => ['fnb', 'Máy in F&B'],
        'fb-product-categories' => ['fnb', 'Nhóm món F&B'],
        'fb-products' => ['fnb', 'Món ăn & Đồ uống F&B'],
        'fb-promotions' => ['fnb', 'Khuyến mãi F&B'],
        'fb-tables' => ['fnb', 'Bàn F&B'],
        'fnb' => ['fnb', 'Bán hàng F&B'],

        // System Administration
        'users' => ['system', 'Quản lý Nhân viên'],
        'roles' => ['system', 'Phân quyền Vai trò'],
        'system-branches' => ['system', 'Quản lý Chi nhánh'],
        'info-business' => ['system', 'Thông tin Doanh nghiệp'],
        'departments' => ['system', 'Phòng ban'],
        'organization' => ['system', 'Cơ cấu Tổ chức'],
        'permission-screens' => ['system', 'Phân quyền'],
        'portal-wallpaper' => ['system', 'Hình nền Hệ thống'],
        'user-settings' => ['system', 'Cài đặt Người dùng'],
        'system' => ['system', 'Hệ thống'],

        // Config - Hotel
        'hotel-settings' => ['config', 'Cài đặt Khách sạn'],
        'hotel-services' => ['config', 'Dịch vụ Khách sạn'],
        'hotel-configs' => ['config', 'Cấu hình Khách sạn'],
        'shifts' => ['config', 'Quản lý Ca làm việc'],
        'outlets' => ['config', 'Điểm bán hàng (Outlets)'],
        'geo' => ['config', 'Địa giới hành chính'],

        // Config - Room
        'room-classes' => ['config', 'Hạng phòng'],
        'room-class-groups' => ['config', 'Nhóm hạng phòng'],
        'room-forms' => ['config', 'Hình thức phòng'],
        'standard-rates' => ['config', 'Giá phòng tiêu chuẩn'],
        'room-rate-codes' => ['config', 'Mã giá phòng'],

        // Config - Templates
        'templates' => ['config', 'Mẫu in & Chứng từ'],
        'print-template-slots' => ['config', 'Mẫu in & Chứng từ'],

        // Config - Company & Partners
        'companies' => ['config', 'Công ty đối tác'],
        'markets' => ['config', 'Thị trường'],
        'customer-sources' => ['config', 'Nguồn khách'],
        'branches' => ['config', 'Chi nhánh'],
        'branches-total' => ['config', 'Tổng hợp Chi nhánh'],
        'bookers' => ['config', 'Người đặt phòng'],

        // Config - System Definition
        'payment-methods' => ['config', 'Phương thức thanh toán'],
        'currencies' => ['config', 'Tiền tệ'],
        'units-of-measure' => ['config', 'Đơn vị tính'],
        'registration-statuses' => ['config', 'Trạng thái Đăng ký'],
        'bank-accounts' => ['config', 'Tài khoản ngân hàng'],

        // Reports
        'report-definitions' => ['reports', 'Báo cáo'],
        'report-data-sources' => ['reports', 'Nguồn dữ liệu Báo cáo'],
        'report-procedure-samples' => ['reports', 'Mẫu Báo cáo'],
    ];

    /**
     * HTTP method → action mapping.
     */
    protected array $methodActionMap = [
        'POST' => 'create',
        'PUT' => 'update',
        'PATCH' => 'update',
        'DELETE' => 'delete',
    ];

    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Ghi timestamp bắt đầu
        $request->attributes->set('_log_start_time', microtime(true));

        return $next($request);
    }

    /**
     * Terminable middleware - ghi log SAU khi response đã gửi cho client.
     * Không ảnh hưởng performance response.
     */
    public function terminate(Request $request, Response $response): void
    {
        try {
            // Skip nếu request đã được ghi log thủ công trước đó (tránh trùng lặp)
            if ($request->attributes->get('_activity_logged')) {
                return;
            }

            // Skip GET requests (chỉ log thao tác thay đổi dữ liệu + auth)
            if ($request->isMethod('GET')) {
                return;
            }

            // Skip nếu URL là activity-logs (tránh vòng lặp)
            if (str_contains($request->path(), 'activity-logs')) {
                return;
            }

            // Skip login/logout - đã ghi log riêng trong AuthController
            $path = $request->path();
            if (preg_match('#^api/(login|logout)$#', $path)) {
                return;
            }

            // Skip các module đã có chức năng ghi log thủ công (F&B)
            if (preg_match('#^api/(fnb|fb-)#', $path)) {
                return;
            }

            // Skip user-settings - cấu hình hiển thị giao diện cá nhân, không phải audit nghiệp vụ
            if (str_contains($path, 'user-settings')) {
                return;
            }

            // Skip report-definitions execute/render/exports (đã ghi log qua ReportDefinitionController, tránh log * Thêm mới report-definitions 'auth_token')
            if (preg_match('#^api/report-definitions/\d+/(execute|render|exports)#', $path)) {
                return;
            }

            $user = $request->user();
            $statusCode = $response->getStatusCode();

            // Detect module và component từ URL và Referer
            [$module, $component] = $this->detectModuleComponent($path, $request);

            // Detect action từ HTTP method
            $action = $this->methodActionMap[$request->method()] ?? 'unknown';

            // Nhận diện đối tượng tác động và dữ liệu thay đổi
            $targetType = null;
            $targetId = null;
            $targetLabel = null;
            $oldValues = null;
            $newValues = null;

            if ($statusCode < 400) {
                // Ưu tiên chọn model phù hợp nhất theo ngữ cảnh route
                $modelChange = $this->resolveBestModelChange($request, $path);

                if ($modelChange) {
                    $targetType = $modelChange['target_type'];
                    $targetId = $modelChange['target_id'];
                    $targetLabel = $modelChange['target_label'];
                    $oldValues = $modelChange['old_values'];
                    $newValues = $modelChange['new_values'];
                }
            }

            // Build mô tả thông minh, chi tiết toàn bộ thông tin
            $description = $this->buildDetailedDescription(
                $action,
                $module,
                $component,
                $targetType,
                $targetId,
                $targetLabel,
                $oldValues,
                $newValues,
                $request,
                $response
            );

            // Đổi action code và component cho đúng chuẩn HKT
            $displayAction = match ($action) {
                'create' => 'Add',
                'update' => 'Modify',
                'delete' => 'Cancel',
                default => $action,
            };
            if (in_array($targetType, ['Booking', 'BookingRoom', 'BookingRoomService'])) {
                $component = 'Booking';
            }

            // Tính thời gian xử lý
            $startTime = $request->attributes->get('_log_start_time');
            $durationMs = $startTime ? (int) ((microtime(true) - $startTime) * 1000) : null;

            ActivityLog::create([
                'user_id' => $user?->id,
                'user_name' => $user?->name ?? '',
                'employee_code' => $user?->employee_code,
                'action' => $displayAction,
                'module' => $module,
                'component' => $component,
                'description' => $description,
                'target_type' => $targetType,
                'target_id' => $targetId,
                'target_label' => $targetLabel,
                'old_values' => $oldValues,
                'new_values' => $newValues,
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'request_method' => $request->method(),
                'request_url' => $request->fullUrl(),
                'response_status' => $statusCode,
                'duration_ms' => $durationMs,
                'created_at' => now(),
            ]);
        } catch (\Throwable $e) {
            // Không để lỗi logging làm crash ứng dụng
            \Log::warning('ActivityLog middleware error: ' . $e->getMessage());
        }
    }

    /**
     * Chọn model thay đổi phù hợp nhất từ request attributes.
     */
    protected function resolveBestModelChange(Request $request, string $path): ?array
    {
        $allChanges = $request->attributes->get('_model_changes', []);
        $lastChange = $request->attributes->get('_last_model_change');

        if (empty($allChanges)) {
            return $lastChange;
        }

        // Nếu request liên quan đến bookings, ưu tiên Booking -> BookingRoom -> Payment
        if (str_contains($path, 'bookings')) {
            if (isset($allChanges['Booking'])) return $allChanges['Booking'];
            if (isset($allChanges['BookingRoom'])) return $allChanges['BookingRoom'];
            if (isset($allChanges['Payment'])) return $allChanges['Payment'];
        }

        // Nếu request liên quan đến payments
        if (str_contains($path, 'payments')) {
            if (isset($allChanges['Payment'])) return $allChanges['Payment'];
        }

        // Nếu request liên quan đến hotel-configs
        if (str_contains($path, 'hotel-configs')) {
            if (isset($allChanges['HotelConfig'])) return $allChanges['HotelConfig'];
        }

        // Nếu request liên quan đến rooms hoặc room-locks
        if (str_contains($path, 'rooms') || str_contains($path, 'room-locks')) {
            if (isset($allChanges['RoomLock'])) return $allChanges['RoomLock'];
            if (isset($allChanges['Room'])) return $allChanges['Room'];
        }

        return $lastChange;
    }

    /**
     * Detect module và component từ URL path.
     */
    /**
     * Detect module và component từ URL path và request referer.
     */
    protected function detectModuleComponent(string $path, ?Request $request = null): array
    {
        // 1. Kiểm tra HTTP Referer từ frontend để nhận diện chính xác màn hình người dùng thao tác
        if ($request) {
            $referer = strtolower((string) $request->header('referer', ''));
            if (str_contains($referer, 'room-plan') || str_contains($referer, 'schedule')) {
                return ['reservation', 'RoomSchedule'];
            }
            if (str_contains($referer, 'create-registration') || str_contains($referer, 'registration')) {
                return ['reservation', 'Tạo đăng ký'];
            }
            if (str_contains($referer, 'room-map')) {
                return ['frontdesk', 'Room map'];
            }
            if (str_contains($referer, 'checkout') || str_contains($referer, 'cashier')) {
                return ['frontdesk', 'Trả phòng'];
            }
            if (str_contains($referer, 'company') || str_contains($referer, 'companies')) {
                return ['config', 'Công ty'];
            }
            if (str_contains($referer, 'night-audit') || str_contains($referer, 'day-close')) {
                return ['frontdesk', 'Sang ngày'];
            }
            if (str_contains($referer, 'reports')) {
                return ['reports', 'Báo cáo'];
            }
            if (str_contains($referer, 'hk-assignments') || str_contains($referer, 'assignments')) {
                return ['housekeeping', 'In Phân công phòng'];
            }
            if (str_contains($referer, 'lost-found') || str_contains($referer, 'hk-lost-found')) {
                return ['housekeeping', 'QUản lý đồ thất lạc'];
            }
            if (str_contains($referer, 'inventory') || str_contains($referer, 'warehouses')) {
                return ['housekeeping', 'Tồn kho'];
            }
            if (str_contains($referer, 'hk-menu') || str_contains($referer, 'products')) {
                return ['housekeeping', 'Tạo Menu'];
            }
            if (str_contains($referer, 'hk-services')) {
                return ['housekeeping', 'Thêm DV Buồng phòng'];
            }
            if (str_contains($referer, 'room-locks') || str_contains($referer, 'lock-room')) {
                return ['reservation', 'Khóa phòng'];
            }
        }

        // Remove 'api/' prefix
        $cleanPath = preg_replace('#^api/#', '', $path);

        // Lấy segment đầu tiên của URL (resource name)
        $segments = explode('/', $cleanPath);
        $resource = $segments[0] ?? '';

        if (isset($this->routeMap[$resource])) {
            return $this->routeMap[$resource];
        }

        return ['other', $resource ?: 'Hệ thống'];
    }

    /**
     * Build mô tả chi tiết toàn diện cho từng nghiệp vụ.
     */
    protected function buildDetailedDescription(
        string $action,
        string $module,
        string $component,
        ?string $targetType,
        ?string $targetId,
        ?string $targetLabel,
        ?array $oldValues,
        ?array $newValues,
        Request $request,
        Response $response
    ): string {
        $statusCode = $response->getStatusCode();
        $actionText = match ($action) {
            'create' => 'Thêm mới',
            'update' => 'Cập nhật',
            'delete' => 'Xóa',
            default => $action,
        };

        if ($statusCode >= 400) {
            return "Thao tác {$actionText} trên {$component} thất bại (HTTP {$statusCode})";
        }

        $old = $oldValues ?? [];
        $new = $newValues ?? [];
        $value = fn (string $key) => $new[$key] ?? $old[$key] ?? null;

        // 1. ĐĂNG KÝ (BOOKING)
        if ($targetType === 'Booking') {
            $bookingCode = 'GAL' . ($targetId ?: preg_replace('/\D/', '', (string) $targetLabel));

            if ($action === 'create') {
                return '* Thông Tin Đăng Ký ' . $bookingCode . ' : ' . $this->joinParts([
                    'Tên nhóm' => $value('booking_name'),
                    'FIT/GIT' => !empty($value('is_git')) ? 'GIT' : 'FIT',
                    'Ngày đến' => Fmt::date($value('arrival_date')),
                    'Ngày đi' => Fmt::date($value('departure_date')),
                    'Số đêm' => $value('num_of_days'),
                    'Trạng thái ĐK' => $this->lookup('registration_status_id', $value('registration_status_id')),
                    'Công ty' => $this->lookup('company_id', $value('company_id')),
                    'Thị trường' => $this->lookup('market_id', $value('market_id')),
                    'Nguồn khách' => $this->lookup('customer_source_id', $value('customer_source_id')),
                    'Người đặt phòng' => $this->lookup('booker_id', $value('booker_id')),
                    'Liên hệ' => $value('contact_name'),
                    'SĐT' => $value('contact_phone'),
                    'Email' => $value('contact_email'),
                    'Ghi chú' => $value('note'),
                ]);
            }
            if ($action === 'update') {
                $lines = Fmt::diffLines($old, $new);
                return "* Cập Nhật Thông Tin Đăng Ký {$bookingCode}" . ($lines ? " :\n" . implode("\n", $lines) : '');
            }
            if ($action === 'delete') {
                $name = $old['booking_name'] ?? '';
                return "* Huỷ đăng ký mã {$bookingCode}" . ($name ? " - {$name}" : '');
            }
        }

        // 2. PHÒNG THUÊ (BOOKING ROOM)
        if ($targetType === 'BookingRoom') {
            $roomRef = $this->bookingRoomRef($targetId, $value('booking_id'), $value('room_number'));

            if ($action === 'create') {
                return "* Phòng thuê : # Thêm mới {$roomRef} : " . $this->joinParts([
                    'Loại phòng' => $this->lookup('room_class_id', $value('room_class_id')),
                    'Giá' => Fmt::money($value('rate') ?? $value('price')),
                    'Mã giá' => $value('rate_code'),
                    'Ngày đến' => Fmt::date($value('arrival_date')),
                    'Ngày đi' => Fmt::date($value('departure_date')),
                    'Người lớn' => $value('adults'),
                    'Trẻ em' => $value('children_qty') ?? $value('children'),
                    'Ăn sáng' => $value('breakfast') === null ? null : (!empty($value('breakfast')) ? 'Có' : 'Không'),
                    'Thêm giường' => $value('extra_bed_qty'),
                ]);
            }
            if ($action === 'update') {
                $lines = Fmt::diffLines($old, $new);
                return "* Cập Nhật Phòng Thuê {$roomRef}" . ($lines ? " :\n" . implode("\n", $lines) : '');
            }
            if ($action === 'delete') {
                $reason = $value('reason');
                return "* Huỷ phòng thuê {$roomRef}" . ($reason ? " - Lý do: {$reason}" : '');
            }
        }

        // 3. THANH TOÁN / ĐẶT CỌC (PAYMENT)
        if ($targetType === 'Payment') {
            $bookingId = $value('booking_id');
            $ref = $bookingId ? 'GAL' . $bookingId : 'đăng ký (không rõ)';
            if ($value('booking_room_id')) {
                $ref .= ' - mã phòng thuê ' . $value('booking_room_id');
            }
            $parts = [
                'Tên booking' => $bookingId ? DB::table('bookings')->where('id', $bookingId)->value('booking_name') : null,
                'Hình thức' => $this->lookup('payment_method_id', $value('payment_method_id')),
                'Số tiền' => Fmt::money($value('amount')),
                'Mô tả' => $value('description') ?? $value('note'),
            ];

            if ($action === 'create') {
                return "* Thêm Thanh Toán/Đặt Cọc Cho Đăng Ký {$ref} : " . $this->joinParts($parts);
            }
            if ($action === 'update') {
                $lines = Fmt::diffLines($old, $new);
                return "* Sửa Thanh Toán/Đặt Cọc Cho Đăng Ký {$ref}" . ($lines ? " :\n" . implode("\n", $lines) : '');
            }
            if ($action === 'delete') {
                $parts['Lý do'] = $value('reason');
                return "* Xóa Thanh Toán/Đặt Cọc Cho Đăng Ký {$ref} : " . $this->joinParts($parts);
            }
        }

        // 4. CẤU HÌNH KHÁCH SẠN (HOTEL CONFIG / HOTEL SETTING)
        if ($targetType === 'HotelConfig' || $targetType === 'HotelSetting') {
            $key = $targetLabel ?? $value('name') ?? $value('config_key') ?? 'Cấu hình';
            $oldVal = $old['config_value'] ?? $old['value'] ?? null;
            $newVal = $new['config_value'] ?? $new['value'] ?? null;

            if ($oldVal !== null || $newVal !== null) {
                return "* Cập nhật cấu hình khách sạn '{$key}': " . Fmt::value('value', $oldVal) . ' ➜ ' . Fmt::value('value', $newVal);
            }
            return "* Cập nhật cấu hình khách sạn '{$key}'";
        }

        // 5. KHÓA PHÒNG (ROOM LOCK)
        if ($targetType === 'RoomLock') {
            $roomNum = $value('room_number') ?? $targetLabel ?? $targetId;
            $type = $value('lock_type') ?? '';
            $reason = $value('reason');
            $reasonText = $reason ? " - Lý do: {$reason}" : '';

            if ($action === 'create') {
                $from = Fmt::date($value('from_date') ?? $value('start_date'));
                $to = Fmt::date($value('to_date') ?? $value('end_date'));
                return trim("Thêm Phòng {$type}") . ". Phòng {$roomNum} bị khóa" . ($from ? " từ {$from}" : '') . ($to ? " đến {$to}" : '') . $reasonText;
            }
            if ($action === 'update') {
                return "Cập nhật phòng khóa {$type} mã {$targetId}, phòng {$roomNum}:\n" . implode("\n", Fmt::diffLines($old, $new));
            }
            if ($action === 'delete') {
                return "Mở khóa phòng {$roomNum}" . ($type ? " ({$type})" : '');
            }
        }

        // 6. PHÒNG (ROOM)
        if ($targetType === 'Room') {
            $roomNum = $value('room_number') ?? $targetLabel ?? $targetId;
            if ($action === 'update') {
                $lines = Fmt::diffLines($old, $new);
                if ($lines) {
                    return "* Cập nhật phòng {$roomNum} :\n" . implode("\n", $lines);
                }
            }
            return "* Cập nhật thông tin phòng {$roomNum}";
        }

        // 7. CÁC ĐỐI TƯỢNG KHÁC (USER, COMPANY, ROOM CLASS, ...)
        $objName = $targetLabel ? "'{$targetLabel}'" : ($targetType ?: $component);

        if ($action === 'create') {
            $lines = Fmt::creationLines($new);
            if ($lines) {
                return "* {$actionText} {$component} {$objName} :\n" . implode("\n", $lines);
            }
        }
        if ($action === 'update') {
            $lines = Fmt::diffLines($old, $new);
            if ($lines) {
                return "* {$actionText} {$component} {$objName} :\n" . implode("\n", $lines);
            }
        }
        if ($action === 'delete') {
            $reason = $value('reason');
            return "* Xóa {$component} {$objName}" . ($reason ? " - Lý do: {$reason}" : '');
        }

        return "* {$actionText} {$component} {$objName}";
    }

    /** "GAL12 - P.301 (G0000005)" hoặc "GAL12 - G0000005" khi chưa gán số phòng. */
    protected function bookingRoomRef(?string $bookingRoomId, mixed $bookingId, mixed $roomNumber): string
    {
        $parts = [];
        if ($bookingId) {
            $parts[] = 'GAL' . $bookingId;
        }
        if ($roomNumber) {
            $parts[] = 'P.' . $roomNumber . ($bookingRoomId ? " ({$bookingRoomId})" : '');
        } elseif ($bookingRoomId) {
            $parts[] = $bookingRoomId;
        }

        return $parts ? '(' . implode(' - ', $parts) . ')' : '';
    }

    protected function lookup(string $key, mixed $id): ?string
    {
        return $id === null || $id === '' ? null : Fmt::value($key, $id);
    }

    /** "-Nhãn : giá trị" cho các phần có giá trị, bỏ qua phần trống. */
    protected function joinParts(array $parts): string
    {
        $text = [];
        foreach ($parts as $label => $val) {
            if ($val === null || $val === '' || (is_string($val) && trim($val) === '')) {
                continue;
            }
            $text[] = "-{$label} : " . (is_scalar($val) ? $val : Fmt::value($label, $val));
        }

        return implode(' ', $text);
    }
}
