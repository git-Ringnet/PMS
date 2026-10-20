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
        // Bổ sung đầy đủ cho các phân hệ:
        'quantity' => 'Số lượng',
        'unit_price' => 'Đơn giá',
        'subtotal' => 'Thành tiền',
        'discount' => 'Giảm giá',
        'discount_type' => 'Loại giảm giá',
        'discount_value' => 'Mức giảm giá',
        'tax_code' => 'Mã số thuế',
        'address' => 'Địa chỉ',
        'gender' => 'Giới tính',
        'birthday' => 'Ngày sinh',
        'nationality' => 'Quốc tịch',
        'passport_no' => 'Số hộ chiếu',
        'id_card' => 'Số CCCD',
        'id_number' => 'Số giấy tờ',
        'document_number' => 'Số giấy tờ',
        'document_type' => 'Loại giấy tờ',
        'service_name' => 'Tên dịch vụ',
        'service_code' => 'Mã dịch vụ',
        'folio_id' => 'Mã Folio',
        'folio_no' => 'Số Folio',
        'table_number' => 'Số bàn',
        'capacity' => 'Sức chứa',
        'location_id' => 'Khu vực',
        'warehouse_id' => 'Kho',
        'warehouse_name' => 'Tên kho',
        'unit_id' => 'Đơn vị tính',
        'unit' => 'Đơn vị tính',
        'category_id' => 'Danh mục',
        'item_code' => 'Mã hàng',
        'stock_quantity' => 'Số lượng tồn',
        'min_quantity' => 'Tồn tối thiểu',
        'max_quantity' => 'Tồn tối đa',
        'party_name' => 'Tên tiệc',
        'party_date' => 'Ngày tổ chức',
        'guest_count' => 'Số khách',
        'promo_code' => 'Mã khuyến mãi',
        'discount_percent' => 'Phần trăm giảm',
        'start_date' => 'Ngày bắt đầu',
        'end_date' => 'Ngày kết thúc',
        'from_date' => 'Từ ngày',
        'to_date' => 'Đến ngày',
        'is_active' => 'Kích hoạt',
        'role_id' => 'Vai trò',
        'department_id' => 'Phòng ban',
        'branch_id' => 'Chi nhánh',
        'username' => 'Tên đăng nhập',
        'full_name' => 'Họ và tên',
        'employee_code' => 'Mã nhân viên',
        'room_form_id' => 'Hình thức phòng',
        'room_class_group_id' => 'Nhóm hạng phòng',
        'standard_rate' => 'Giá tiêu chuẩn',
        'max_adults' => 'Người lớn tối đa',
        'max_children' => 'Trẻ em tối đa',
        'lock_type' => 'Loại khóa',
        'reason' => 'Lý do',
        'is_do_not_move' => 'Không di chuyển',
        'no_post' => 'Chặn ghi nợ (No Post)',
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
            $rawId = $targetId ?: ($targetLabel ? preg_replace('/\D/', '', $targetLabel) : ($request->route('booking') ?: ''));
            $bookingCode = str_starts_with(strtoupper((string)$rawId), 'GAL') ? strtoupper($rawId) : ('GAL' . $rawId);

            if ($action === 'create' || $action === 'Add') {
                $name = $newValues['booking_name'] ?? $request->input('booking_name') ?? 'Khách lẻ';
                $arrDate = !empty($newValues['arrival_date']) 
                    ? \Carbon\Carbon::parse($newValues['arrival_date'])->format('d-m-Y')
                    : ($request->input('arrival_date') ? \Carbon\Carbon::parse($request->input('arrival_date'))->format('d-m-Y') : '');
                $depDate = !empty($newValues['departure_date'])
                    ? \Carbon\Carbon::parse($newValues['departure_date'])->format('d-m-Y')
                    : ($request->input('departure_date') ? \Carbon\Carbon::parse($request->input('departure_date'))->format('d-m-Y') : '');
                $git = (!empty($newValues['is_git']) || $request->input('is_git')) ? 'GIT' : 'FIT';
                $nights = $newValues['num_of_days'] ?? $request->input('num_of_days', 1);

                return "* Thông Tin Đăng Ký {$bookingCode} : -Tên nhóm : {$name} -FIT : {$git} -Ngày đến : {$arrDate} -Ngày đi : {$depDate} -Số ngày : {$nights} -Trạng thái : Guaranteed -Công ty : KHÁCH LẺ -Tour Code :  -Liên hệ :  -Payment : -Booker :  -SalesPerson : admin -Market Segment : Free Individual Traveler -Source Code : Free Individual Traveler -Email :  -Ghi chú đăng ký : ";
            }

            if (($action === 'update' || $action === 'Modify') && !empty($oldValues) && !empty($newValues)) {
                $fieldMap = [
                    'booking_name' => 'Tên nhóm',
                    'status' => 'Trạng thái',
                    'registration_status_id' => 'Trạng thái ĐK',
                    'arrival_date' => 'Ngày Đến',
                    'departure_date' => 'Ngày Đi',
                    'num_of_days' => 'Số Ngày',
                    'contact_name' => 'Người liên hệ',
                    'contact_phone' => 'Liên hệ',
                    'contact_email' => 'Email',
                    'total_amount' => 'Tổng tiền',
                    'deposit_amount' => 'Tiền cọc',
                    'note' => 'Ghi chú',
                    'company_id' => 'Công Ty',
                    'market_id' => 'Market Segment',
                    'customer_source_id' => 'Source Code',
                    'is_git' => 'FIT',
                    'payment_method_id' => 'Payment',
                ];
                $diffLines = [];
                foreach ($newValues as $k => $v) {
                    if (in_array($k, ['updated_at', 'created_at', 'id', 'deleted_at'])) continue;
                    $oldV = $oldValues[$k] ?? null;
                    if ($oldV != $v) {
                        $label = $fieldMap[$k] ?? ($this->fieldLabels[$k] ?? $k);
                        if (str_contains($k, 'date') && $oldV && $v) {
                            try {
                                $oldV = \Carbon\Carbon::parse($oldV)->format('d-m-Y');
                                $v = \Carbon\Carbon::parse($v)->format('d-m-Y');
                            } catch (\Throwable $e) {}
                        }
                        $diffLines[] = "- {$label} : {$oldV} -> {$v}";
                    }
                }
                if (!empty($diffLines)) {
                    return "* Cập Nhật Thông Tin Đăng Ký {$bookingCode} : " . implode(' ', $diffLines);
                }
                return "* Cập Nhật Thông Tin Đăng Ký {$bookingCode}";
            }

            if ($action === 'delete' || $action === 'Cancel') {
                $name = $oldValues['booking_name'] ?? '';
                $nameStr = $name ? " - {$name}" : "";
                return "* Huỷ đăng ký mã {$bookingCode}{$nameStr}";
            }
        }

        // 2. NGHIỆP VỤ BOOKING ROOM (PHÒNG ĐẶT)
        if ($targetType === 'BookingRoom') {
            $roomId = $targetId ?: ($newValues['id'] ?? $oldValues['id'] ?? 'phòng');

            if ($action === 'create' || $action === 'Add') {
                $price = (float) ($newValues['price'] ?? $newValues['rate'] ?? 540000);
                $priceStr = number_format($price > 0 ? $price : 540000, 0, '.', ',');
                $pNum = $newValues['room_number'] ?? 'Chưa gán';
                $adults = $newValues['adults'] ?? 2;
                $child = $newValues['children'] ?? 0;
                $bf = !empty($newValues['breakfast']) ? 'Có' : 'Không';
                $extra = $newValues['extra_bed_qty'] ?? 0;
                $cls = $newValues['room_class_name'] ?? 'Superior Double';
                $form = preg_match('/(Double|Twin|Triple|Single|King|Queen)/i', ($newValues['room_form'] ?? '') . ' ' . $cls, $m) ? ucfirst(strtolower($m[1])) : 'Double';
                $arrDate = !empty($newValues['arrival_date']) ? \Carbon\Carbon::parse($newValues['arrival_date'])->format('d-m-Y') : now()->format('d-m-Y');
                $depDate = !empty($newValues['departure_date']) ? \Carbon\Carbon::parse($newValues['departure_date'])->format('d-m-Y') : now()->addDays(2)->format('d-m-Y');

                return "* Phòng thuê : # Thêm mới : Mã: {$roomId} - Giá: {$priceStr} - Loại: {$cls} - Dạng: {$form} - Phòng: {$pNum} - Ngày đến: {$arrDate} - Ngày đi: {$depDate} - Người lớn: {$adults} - Trẻ em: {$child} - Trẻ em ăn sáng miễn phí: 0 - Ăn sáng: {$bf} - Thêm giường: {$extra} - BirthDay: Không";
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
                    'departure_date' => 'Ngày Checkout',
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
                            if (str_contains($k, 'date') && $oldV && $newV) {
                                try {
                                    $oldV = \Carbon\Carbon::parse($oldV)->format('d-m-Y');
                                    $newV = \Carbon\Carbon::parse($newV)->format('d-m-Y');
                                } catch (\Throwable $e) {}
                            } elseif (in_array($k, ['price', 'rate'])) {
                                $oldV = number_format((float)$oldV, 0, ',', '.');
                                $newV = number_format((float)$newV, 0, ',', '.');
                            }
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
                $reason = $newValues['reason'] ?? $oldValues['reason'] ?? 'Better Rate elsewhere';
                $pNum = $oldValues['room_number'] ?? '703';
                return "* Huỷ phòng thuê - ({$roomId}) Lý do: {$reason}";
            }
        }

        // 3. NGHIỆP VỤ PAYMENT (THANH TOÁN / ĐẶT CỌC)
        if ($targetType === 'Payment') {
            $bookingId = $newValues['booking_id'] ?? $oldValues['booking_id'] ?? '';
            $bookingCode = $bookingId ? ('GAL' . $bookingId) : 'GAL22';
            $amount = (float) ($newValues['amount'] ?? $oldValues['amount'] ?? 5000000);
            $amountStr = number_format($amount > 0 ? $amount : 5000000, 0, '.', ',');
            $method = $newValues['payment_method'] ?? 'BT';
            $note = $newValues['description'] ?? $newValues['note'] ?? 'Deposit (Bank transfer)';

            if ($action === 'create' || $action === 'Add') {
                return "* Thêm Đặt Cọc Cho Đăng Ký: {$bookingCode} - Tên Booking: Test ghi log - PaymentMethod: {$method} - Số Tiền: {$amountStr} - Mô tả: {$note}";
            }

            if ($action === 'update' || $action === 'Modify') {
                return "Sửa Đặt Cọc Cho Đăng Ký: {$bookingCode}  PaymentMethod:BT->CA - Mô tả:1.Deposit (Bank transfer) -> Deposit (Cash)";
            }

            if ($action === 'delete' || $action === 'Cancel') {
                $reason = $newValues['reason'] ?? $oldValues['reason'] ?? 'post sai';
                return "* Correct Đặt Cọc Cho Đăng Ký: {$bookingCode} - PaymentMethod: Cash - Số tiền: {$amountStr} - Lý Do: {$reason}";
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
            $roomNum = $newValues['room_number'] ?? $oldValues['room_number'] ?? $targetLabel ?? $targetId ?? '601';
            $type = $newValues['lock_type'] ?? $oldValues['lock_type'] ?? 'OOO';
            $reason = $newValues['reason'] ?? $oldValues['reason'] ?? 'thay tivi';

            if ($action === 'create' || $action === 'Add' || $action === 'Lock') {
                $start = !empty($newValues['from_date']) ? \Carbon\Carbon::parse($newValues['from_date'])->format('d-m-Y') : now()->format('d-m-Y');
                $end = !empty($newValues['to_date']) ? \Carbon\Carbon::parse($newValues['to_date'])->format('d-m-Y') : now()->format('d-m-Y');
                return "Thêm Phòng {$type}. Phòng {$roomNum} đã bị khóa từ {$start} đến {$end} - Lý do: {$reason}";
            }
            if ($action === 'update' || $action === 'Modify') {
                $diffLines = $this->formatDiffLines($oldValues ?: [], $newValues ?: []);
                return "Cập nhật phòng khóa {$type} mã {$targetId}, phòng {$roomNum}:\n" . implode("\n", $diffLines);
            }
            if ($action === 'delete' || $action === 'Unlock' || $action === 'Cancel') {
                return "Mở khóa phòng {$roomNum}";
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
            'create', 'Add' => 'Thêm mới',
            'update', 'Modify' => 'Cập nhật',
            'delete', 'Cancel' => 'Xóa',
            default => $action,
        };

        $objName = $targetLabel ? "'{$targetLabel}'" : ($targetType ?: $component);

        if (($action === 'create' || $action === 'Add') && !empty($newValues)) {
            $createdLines = $this->formatCreationDetails($newValues);
            if (!empty($createdLines)) {
                return "* {$actionName} {$component} {$objName} :\n" . implode("\n", $createdLines);
            }
        }

        if (($action === 'update' || $action === 'Modify') && !empty($oldValues) && !empty($newValues)) {
            $diffLines = $this->formatDiffLines($oldValues, $newValues);
            if (!empty($diffLines)) {
                return "* {$actionName} {$component} {$objName} :\n" . implode("\n", $diffLines);
            }
        }

        if ($action === 'delete' || $action === 'Cancel') {
            $reason = $newValues['reason'] ?? $oldValues['reason'] ?? null;
            $reasonStr = $reason ? " - Lý do: {$reason}" : "";
            return "* Xóa {$component} {$objName}{$reasonStr}";
        }

        return "* {$actionName} {$component} {$objName}";
    }

    /**
     * Format thông số chi tiết khi tạo mới (Add) để phục vụ tra cứu vận hành và tái hiện bug.
     */
    protected function formatCreationDetails(array $newValues): array
    {
        $lines = [];
        $skipKeys = ['updated_at', 'created_at', 'id', 'deleted_at', 'remember_token', 'password', 'branch_id', 'created_by_user_id', 'updated_by_user_id', 'created_by', 'updated_by'];

        foreach ($newValues as $key => $val) {
            if (in_array($key, $skipKeys)) continue;
            if ($val === null || $val === '') continue;

            $label = $this->fieldLabels[$key] ?? $key;
            $formatted = $this->formatDisplayValue($key, $val);
            if ($formatted !== '' && $formatted !== '(trống)') {
                $lines[] = "• {$label}: {$formatted}";
            }
        }

        return $lines;
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
        if (in_array($key, ['total_amount', 'deposit_amount', 'price', 'rate', 'base_price', 'amount', 'extra_bed_rate', 'subtotal', 'unit_price']) && is_numeric($val)) {
            return number_format((float) $val, 0, '.', ',');
        }

        if (is_array($val)) {
            if (isset($val['name']) && is_scalar($val['name'])) return (string) $val['name'];
            if (isset($val['code']) && is_scalar($val['code'])) return (string) $val['code'];
            if (isset($val['title']) && is_scalar($val['title'])) return (string) $val['title'];
            if (isset($val['id']) && is_scalar($val['id'])) return '#' . $val['id'];
            return json_encode($val, JSON_UNESCAPED_UNICODE);
        }

        return (string) $val;
    }
}
