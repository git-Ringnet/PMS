<?php

namespace App\Http\Middleware;

use App\Models\ActivityLog;
use Closure;
use Illuminate\Http\Request;
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

        // Reservation & Frontdesk
        'bookings' => ['reservation', 'Quản lý Đặt phòng'],
        'booking-rooms' => ['reservation', 'Phòng Đặt'],
        'room-allocations' => ['reservation', 'Phân bổ phòng'],
        'rooms' => ['frontdesk', 'Sơ đồ phòng'],
        'room-locks' => ['reservation', 'Khóa phòng'],
        'guests' => ['reservation', 'Thông tin Khách lưu trú'],
        'payments' => ['frontdesk', 'Thu ngân & Thanh toán'],
        'hk' => ['housekeeping', 'Buồng phòng'],
        'housekeeping' => ['housekeeping', 'Buồng phòng'],

        // System Administration
        'users' => ['system', 'Quản lý Nhân viên'],
        'roles' => ['system', 'Phân quyền Vai trò'],
        'system-branches' => ['system', 'Quản lý Chi nhánh'],
        'info-business' => ['system', 'Thông tin Doanh nghiệp'],

        // Config - Hotel
        'hotel-settings' => ['config', 'Cài đặt Khách sạn'],
        'hotel-services' => ['config', 'Dịch vụ Khách sạn'],
        'hotel-configs' => ['config', 'Cấu hình Khách sạn'],
        'shifts' => ['config', 'Quản lý Ca làm việc'],

        // Config - Room
        'room-classes' => ['config', 'Hạng phòng'],
        'room-class-groups' => ['config', 'Nhóm hạng phòng'],
        'room-forms' => ['config', 'Hình thức phòng'],
        'standard-rates' => ['config', 'Giá phòng tiêu chuẩn'],
        'room-rate-codes' => ['config', 'Mã giá phòng'],

        // Config - Templates
        'templates' => ['config', 'Mẫu in & Chứng từ'],

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
        'products' => ['housekeeping', 'Sản phẩm & Minibar'],
        'product-categories' => ['housekeeping', 'Nhóm sản phẩm'],
        'inventories' => ['housekeeping', 'Kho bãi'],
        'warehouses' => ['housekeeping', 'Kho hàng'],
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
     * Field name to Vietnamese label map for detail comparison diff.
     */
    protected array $fieldLabels = [
        'status' => 'Trạng thái',
        'registration_status_id' => 'Trạng thái ĐK',
        'arrival_date' => 'Ngày đến',
        'departure_date' => 'Ngày đi',
        'num_of_days' => 'Số đêm',
        'booking_name' => 'Tên khách/đoàn',
        'contact_name' => 'Người liên hệ',
        'contact_phone' => 'SĐT liên hệ',
        'contact_email' => 'Email',
        'total_amount' => 'Tổng tiền',
        'deposit_amount' => 'Tiền cọc',
        'payment_value' => 'Giá trị thanh toán',
        'note' => 'Ghi chú',
        'special_requests' => 'Yêu cầu đặc biệt',
        'company_id' => 'Công ty',
        'market_id' => 'Thị trường',
        'customer_source_id' => 'Nguồn khách',
        'breakfast_included' => 'Ăn sáng',
        'breakfast' => 'Ăn sáng',
        'is_day_use' => 'Trong ngày',
        'color' => 'Màu hiển thị',
        'room_number' => 'Số phòng',
        'price' => 'Đơn giá',
        'rate' => 'Đơn giá',
        'base_price' => 'Giá gốc',
        'extra_bed_qty' => 'Số lượng giường phụ',
        'extra_bed_rate' => 'Giá giường phụ',
        'room_class_id' => 'Hạng phòng',
        'config_key' => 'Tên cấu hình',
        'config_value' => 'Giá trị',
        'is_visible' => 'Hiển thị',
        'description' => 'Mô tả',
        'amount' => 'Số tiền',
        'payment_method_id' => 'Phương thức TT',
        'name' => 'Tên',
        'phone' => 'SĐT',
        'email' => 'Email',
        'code' => 'Mã',
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

            $user = $request->user();
            $statusCode = $response->getStatusCode();

            // Detect module và component từ URL
            [$module, $component] = $this->detectModuleComponent($path);

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
    protected function detectModuleComponent(string $path): array
    {
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

        if ($statusCode >= 400) {
            $actionText = match ($action) {
                'create' => 'Thêm mới',
                'update' => 'Cập nhật',
                'delete' => 'Xóa',
                default => $action,
            };
            return "Thao tác {$actionText} trên {$component} thất bại (HTTP {$statusCode})";
        }

        // 1. NGHIỆP VỤ BOOKING
        if ($targetType === 'Booking') {
            $bookingId = $targetId ?: ($targetLabel ? preg_replace('/\D/', '', $targetLabel) : ($request->route('booking') ?: ''));

            if ($action === 'create' || $action === 'Add') {
                $name = $newValues['booking_name'] ?? $request->input('booking_name') ?? 'Khách lẻ';
                $arrDate = !empty($newValues['arrival_date']) 
                    ? \Carbon\Carbon::parse($newValues['arrival_date'])->format('d-m-Y')
                    : ($request->input('arrival_date') ? \Carbon\Carbon::parse($request->input('arrival_date'))->format('d-m-Y') : '');
                $depDate = !empty($newValues['departure_date'])
                    ? \Carbon\Carbon::parse($newValues['departure_date'])->format('d-m-Y')
                    : ($request->input('departure_date') ? \Carbon\Carbon::parse($request->input('departure_date'))->format('d-m-Y') : '');
                $git = (!empty($newValues['is_git']) || $request->input('is_git')) ? 'GIT' : 'FIT';
                $vat = (!empty($newValues['has_vat']) || $request->input('has_vat')) ? 'Có' : '';
                $nights = $newValues['num_of_days'] ?? $request->input('num_of_days', 1);

                return "* Thông Tin Đăng Ký {$bookingId} : -Tên nhóm : {$name} -FIT : {$git} -VAT : {$vat} -Ngày đến : {$arrDate} -Ngày đi : {$depDate} -Số ngày : {$nights} -Trạng thái : Guaranteed -Công ty : KHÁCH LẺ -Tour Code : -Liên hệ : -Payment : -Booker : -SalesPerson : admin -Market Segment : Free Individual Traveler -Source Code : Free Individual Traveler -Email : -Ghi chú đăng ký : ";
            }

            if (($action === 'update' || $action === 'Modify') && !empty($oldValues) && !empty($newValues)) {
                $fieldMap = [
                    'booking_name' => 'Tên nhóm',
                    'status' => 'Trạng thái',
                    'registration_status_id' => 'Trạng thái ĐK',
                    'arrival_date' => 'Ngày đến',
                    'departure_date' => 'Ngày đi',
                    'num_of_days' => 'Số ngày',
                    'contact_name' => 'Liên hệ',
                    'contact_phone' => 'SĐT',
                    'contact_email' => 'Email',
                    'total_amount' => 'Tổng tiền',
                    'deposit_amount' => 'Tiền cọc',
                    'note' => 'Ghi chú',
                    'company_id' => 'Công ty',
                    'market_id' => 'Thị trường',
                    'customer_source_id' => 'Nguồn',
                    'has_vat' => 'VAT',
                    'is_git' => 'FIT',
                ];
                $diffLines = [];
                foreach ($newValues as $k => $v) {
                    if (in_array($k, ['updated_at', 'created_at', 'id', 'deleted_at'])) continue;
                    $oldV = $oldValues[$k] ?? null;
                    if ($oldV != $v) {
                        $label = $fieldMap[$k] ?? ($this->fieldLabels[$k] ?? $k);
                        $diffLines[] = "- {$label} : {$oldV} -> {$v}";
                    }
                }
                if (!empty($diffLines)) {
                    return "* Cập Nhật Thông Tin Đăng Ký {$bookingId} : " . implode(' ', $diffLines);
                }
                return "* Cập Nhật Thông Tin Đăng Ký {$bookingId}";
            }

            if ($action === 'delete' || $action === 'Cancel') {
                $name = $oldValues['booking_name'] ?? '';
                $nameStr = $name ? " ({$name})" : "";
                return "* Hủy Đăng Ký {$bookingId}{$nameStr}";
            }
        }

        // 2. NGHIỆP VỤ BOOKING ROOM (PHÒNG ĐẶT)
        if ($targetType === 'BookingRoom') {
            $roomId = $targetId ?: ($newValues['id'] ?? $oldValues['id'] ?? 'phòng');

            if ($action === 'create' || $action === 'Add') {
                $price = (float) ($newValues['price'] ?? $newValues['rate'] ?? 0);
                $pNum = $newValues['room_number'] ?? 'Chưa gán';
                $adults = $newValues['adults'] ?? 1;
                $child = $newValues['children'] ?? 0;
                $bf = !empty($newValues['breakfast']) ? 'Có' : 'Không';
                $extra = $newValues['extra_bed_qty'] ?? 0;
                return "* Phòng thuê : # Thêm mới : Mã: {$roomId} - Giá: {$price} - Loại: Superior - Dạng: Superior - Phòng:{$pNum} - Người lớn: {$adults} - Trẻ em: {$child} - Trẻ em ăn sáng miễn phí: 0 - Ăn sáng: {$bf} - Thêm giường: {$extra} - BirthDay: Không";
            }

            if (($action === 'update' || $action === 'Modify') && !empty($oldValues) && !empty($newValues)) {
                $fieldMap = [
                    'adults' => 'Người Lớn',
                    'children' => 'Trẻ Em',
                    'children_qty' => 'Trẻ Em',
                    'price' => 'Giá',
                    'rate' => 'Giá',
                    'room_number' => 'Phòng',
                    'arrival_date' => 'Ngày đến',
                    'departure_date' => 'Ngày đi',
                    'breakfast' => 'Ăn Sáng',
                    'extra_bed_qty' => 'Thêm Giường',
                    'room_class_id' => 'Loại',
                    'status' => 'Trạng thái',
                ];
                $diffLines = [];
                foreach ($fieldMap as $k => $label) {
                    if (array_key_exists($k, $newValues) && array_key_exists($k, $oldValues)) {
                        $oldV = $oldValues[$k];
                        $newV = $newValues[$k];
                        if ($oldV != $newV) {
                            $diffLines[] = "- {$label} {$oldV} -> {$newV}";
                        }
                    }
                }
                if (!empty($diffLines)) {
                    return "* Cập Nhật Phòng Thuê ({$roomId}) : " . implode(' ', $diffLines);
                }
                return "* Cập Nhật Phòng Thuê ({$roomId})";
            }

            if ($action === 'delete' || $action === 'Cancel') {
                return "* Xóa/Hủy phòng thuê ({$roomId})";
            }
        }

        // 3. NGHIỆP VỤ PAYMENT (THANH TOÁN / ĐẶT CỌC)
        if ($targetType === 'Payment') {
            $bookingId = $newValues['booking_id'] ?? $oldValues['booking_id'] ?? '';
            $bookingCode = $bookingId ? ('GAL' . $bookingId) : '';
            $codeStr = $bookingCode ? " Đăng ký {$bookingCode}" : "";
            $amount = (float) ($newValues['amount'] ?? $oldValues['amount'] ?? 0);
            $amountStr = number_format($amount, 0, ',', '.') . ' đ';

            if ($action === 'create') {
                $note = $newValues['description'] ?? $newValues['note'] ?? '';
                $noteStr = $note ? " - Diễn giải: {$note}" : "";
                return "* Thanh toán cho{$codeStr}: Số tiền: {$amountStr}{$noteStr}";
            }

            if ($action === 'update') {
                $oldAmount = (float) ($oldValues['amount'] ?? 0);
                $oldAmountStr = number_format($oldAmount, 0, ',', '.') . ' đ';
                return "* Cập nhật thanh toán{$codeStr}: Số tiền {$oldAmountStr} ➜ {$amountStr}";
            }

            if ($action === 'delete') {
                return "* Xóa khoản thanh toán của{$codeStr}: Số tiền: {$amountStr}";
            }
        }

        // 4. CẤU HÌNH KHÁCH SẠN (HOTEL CONFIG / HOTEL SETTING)
        if ($targetType === 'HotelConfig' || $targetType === 'HotelSetting') {
            $key = $targetLabel ?? $newValues['config_key'] ?? $oldValues['config_key'] ?? 'Cấu hình';
            $oldVal = $oldValues['config_value'] ?? $oldValues['value'] ?? null;
            $newVal = $newValues['config_value'] ?? $newValues['value'] ?? null;

            if ($oldVal !== null && $newVal !== null) {
                return "* Cập nhật cấu hình khách sạn '{$key}': {$oldVal} ➜ {$newVal}";
            }
            return "* Cập nhật cấu hình khách sạn '{$key}'";
        }

        // 5. KHÓA PHÒNG (ROOM LOCK)
        if ($targetType === 'RoomLock') {
            $roomNum = $newValues['room_number'] ?? $oldValues['room_number'] ?? $targetLabel ?? $targetId;
            if ($action === 'create') {
                $type = $newValues['lock_type'] ?? 'Khóa';
                $start = !empty($newValues['start_date']) ? \Carbon\Carbon::parse($newValues['start_date'])->format('d/m/Y') : '-';
                $end = !empty($newValues['end_date']) ? \Carbon\Carbon::parse($newValues['end_date'])->format('d/m/Y') : '-';
                $reason = $newValues['reason'] ?? '';
                $reasonStr = $reason ? ", Lý do: {$reason}" : "";
                return "* Khóa phòng {$roomNum}: Loại: {$type}, Từ {$start} đến {$end}{$reasonStr}";
            }
            if ($action === 'delete') {
                return "* Mở khóa phòng {$roomNum}";
            }
        }

        // 6. PHÒNG (ROOM)
        if ($targetType === 'Room') {
            $roomNum = $newValues['room_number'] ?? $oldValues['room_number'] ?? $targetLabel ?? $targetId;
            if ($action === 'update' && !empty($oldValues) && !empty($newValues)) {
                $diffLines = $this->formatDiffLines($oldValues, $newValues);
                if (!empty($diffLines)) {
                    return "* Cập nhật phòng {$roomNum} :\n" . implode("\n", $diffLines);
                }
            }
            return "* Cập nhật thông tin phòng {$roomNum}";
        }

        // 7. CÁC ĐỐI TƯỢNG KHÁC (USER, COMPANY, ROOM CLASS, ETC.)
        $actionName = match ($action) {
            'create' => 'Thêm mới',
            'update' => 'Cập nhật',
            'delete' => 'Xóa',
            default => $action,
        };

        $objName = $targetLabel ? "'{$targetLabel}'" : ($targetType ?: $component);

        if ($action === 'update' && !empty($oldValues) && !empty($newValues)) {
            $diffLines = $this->formatDiffLines($oldValues, $newValues);
            if (!empty($diffLines)) {
                return "* {$actionName} {$component} {$objName} :\n" . implode("\n", $diffLines);
            }
        }

        return "* {$actionName} {$component} {$objName}";
    }

    /**
     * Chuyển đổi mảng diff thành các dòng liệt kê tiếng Việt thân thiện.
     */
    protected function formatDiffLines(array $oldValues, array $newValues): array
    {
        $lines = [];
        $skipKeys = ['updated_at', 'created_at', 'id', 'deleted_at', 'remember_token', 'password'];

        foreach ($newValues as $key => $newVal) {
            if (in_array($key, $skipKeys)) continue;

            $oldVal = $oldValues[$key] ?? null;
            $label = $this->fieldLabels[$key] ?? $key;

            $formattedOld = $this->formatDisplayValue($key, $oldVal);
            $formattedNew = $this->formatDisplayValue($key, $newVal);

            if ($formattedOld !== $formattedNew) {
                $lines[] = "• {$label}: {$formattedOld} ➜ {$formattedNew}";
            }
        }

        return $lines;
    }

    /**
     * Format giá trị hiển thị thân thiện (ngày tháng, tiền tệ, boolean).
     */
    protected function formatDisplayValue(string $key, mixed $val): string
    {
        if ($val === null || $val === '') {
            return '(trống)';
        }

        if (is_bool($val)) {
            return $val ? 'Có' : 'Không';
        }

        // Format ngày tháng nếu trường kết thúc bằng date
        if (str_contains($key, 'date') && is_string($val) && preg_match('/^\d{4}-\d{2}-\d{2}/', $val)) {
            try {
                return \Carbon\Carbon::parse($val)->format('d/m/Y');
            } catch (\Throwable $e) {}
        }

        // Format tiền tệ nếu là số tiền
        if (in_array($key, ['total_amount', 'deposit_amount', 'price', 'rate', 'base_price', 'amount', 'extra_bed_rate']) && is_numeric($val)) {
            return number_format((float) $val, 0, ',', '.') . ' đ';
        }

        if (is_array($val)) {
            return json_encode($val, JSON_UNESCAPED_UNICODE);
        }

        return (string) $val;
    }
}
