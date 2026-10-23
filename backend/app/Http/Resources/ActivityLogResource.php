<?php

namespace App\Http\Resources;

use App\Models\Booking;
use App\Models\BookingRoom;
use App\Models\Company;
use App\Models\ReportDefinition;
use App\Support\ActivityLogFormatter as Fmt;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ActivityLogResource extends JsonResource
{
    /**
     * Cache BookingRoom và Booking để tránh query lặp lại khi render danh sách.
     */
    protected static array $bookingRoomCache = [];
    protected static array $bookingCache = [];

    public static function flushCache(): void
    {
        self::$bookingRoomCache = [];
        self::$bookingCache = [];
    }

    /**
     * Component translation mapping (Đồng bộ chuẩn 100% với Cột B trong File Excel).
     */
    protected static array $componentMap = [
        'RoomSchedule' => 'RoomSchedule',
        'RoomPlanPage' => 'RoomSchedule',
        'CreateRegistrationPage' => 'Tạo đăng ký',
        'Booking' => 'Tạo đăng ký',
        'bookings' => 'Tạo đăng ký',
        'booking-rooms' => 'Tạo đăng ký',
        'RoomMapPage' => 'Room map',
        'RoomMap' => 'Room map',
        'rooms' => 'Room map',
        'CheckoutPage' => 'Trả phòng',
        'Checkout' => 'Trả phòng',
        'payments' => 'Trả phòng',
        'cashier' => 'Trả phòng',
        'LockRoomPage' => 'Khóa phòng',
        'RoomLock' => 'Khóa phòng',
        'room-locks' => 'Khóa phòng',
        'CompanyInfoTab' => 'Công ty',
        'CompanySettingsPage' => 'Công ty',
        'companies' => 'Công ty',
        'DayClosePage' => 'Sang ngày',
        'NightAudit' => 'Sang ngày',
        'night-audit' => 'Sang ngày',
        'ReportsPage' => 'Báo cáo',
        'report-definitions' => 'Báo cáo',
        'ReportDefinition' => 'Báo cáo',
        'reports' => 'Báo cáo',
        'HousekeepingStaffAssignments' => 'In Phân công phòng',
        'hk-assignments' => 'In Phân công phòng',
        'hk-config' => 'In Phân công phòng',
        'LostAndFound' => 'QUản lý đồ thất lạc',
        'lost-and-found' => 'QUản lý đồ thất lạc',
        'hk-lost-found' => 'QUản lý đồ thất lạc',
        'InventoryTab' => 'Tồn kho',
        'inventory' => 'Tồn kho',
        'inventories' => 'Tồn kho',
        'warehouses' => 'Tồn kho',
        'HousekeepingMenuDefinition' => 'Tạo Menu',
        'hk-menu' => 'Tạo Menu',
        'products' => 'Tạo Menu',
        'product-categories' => 'Tạo Menu',
        'ServicesModal' => 'Thêm DV Buồng phòng',
        'hk-services' => 'Thêm DV Buồng phòng',
        'hk' => 'Buồng phòng',
        'housekeeping' => 'Buồng phòng',
        'booking-children' => 'Tạo đăng ký',
        'special-requests' => 'Yêu cầu đặc biệt',
        'booking-room-services' => 'Trả phòng',
        'service-bills' => 'Trả phòng',
        'night-audit' => 'Sang ngày',
        'system-date' => 'Sang ngày',
        'shift-work' => 'Bàn giao ca',
        'users' => 'Quản lý Nhân viên',
        'EmployeeTab' => 'Quản lý Nhân viên',
        'roles' => 'Phân quyền',
        'permission-screens' => 'Phân quyền',
        'BranchManageTab' => 'Quản lý Chi nhánh',
        'system-branches' => 'Quản lý Chi nhánh',
        'branches' => 'Quản lý Chi nhánh',
        'departments' => 'Phòng ban',
        'organization' => 'Cơ cấu Tổ chức',
        'portal-wallpaper' => 'Hình nền Hệ thống',
        'user-settings' => 'Cài đặt Người dùng',
        'hotel-configs' => 'Cấu hình Khách sạn',
        'HotelDefinition' => 'Cài đặt Khách sạn',
        'hotel-settings' => 'Cài đặt Khách sạn',
        'hotel-services' => 'Dịch vụ Khách sạn',
        'shifts' => 'Ca làm việc',
        'outlets' => 'Điểm bán hàng (Outlets)',
        'geo' => 'Địa giới hành chính',
        'RoomDefinition' => 'Định nghĩa Phòng',
        'RateSetup' => 'Cài đặt Giá phòng',
        'DesignTemplateTab' => 'Mẫu in & Chứng từ',
        'print-template-slots' => 'Mẫu in & Chứng từ',
        'SystemDefinition' => 'Danh mục Hệ thống',
        'report-definitions' => 'Báo cáo',
        'report-data-sources' => 'Nguồn dữ liệu Báo cáo',
        'report-procedure-samples' => 'Mẫu Báo cáo',
        'fb-locations' => 'Khu vực F&B',
        'fb-parties' => 'Tiệc F&B',
        'fb-printers' => 'Máy in F&B',
        'fb-product-categories' => 'Nhóm món F&B',
        'fb-products' => 'Món ăn & Đồ uống F&B',
        'fb-promotions' => 'Khuyến mãi F&B',
        'fb-tables' => 'Bàn F&B',
        'fnb' => 'Bán hàng F&B',
        'residence-declarations' => 'Khai báo tạm trú',
        'folio' => 'Trả phòng',
        'split-folio' => 'Trả phòng',
        'quick-transfer' => 'Trả phòng',
        'quick-transfer-candidates' => 'Trả phòng',
        'LoginPage' => 'Đăng nhập',
        'auth' => 'Đăng nhập',
        'me' => 'Tài khoản cá nhân',
    ];

    /**
     * Module translation mapping.
     */
    protected static array $moduleMap = [
        'reservation' => 'Đặt phòng',
        'frontdesk' => 'Lễ tân',
        'housekeeping' => 'Buồng phòng',
        'fnb' => 'Nhà hàng & F&B',
        'config' => 'Cài đặt',
        'system' => 'Hệ thống',
        'auth' => 'Xác thực',
        'reports' => 'Báo cáo',
        'other' => 'Khác',
    ];

    /**
     * Field name to Vietnamese label map.
     */
    protected static array $fieldLabels = [
        'status' => 'Trạng thái',
        'registration_status_id' => 'Trạng thái ĐK',
        'arrival_date' => 'Ngày đến',
        'departure_date' => 'Ngày đi',
        'num_of_days' => 'Số ngày',
        'booking_name' => 'Tên nhóm',
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
        'rate' => 'Giá',
        'base_price' => 'Giá gốc',
        'extra_bed_qty' => 'Thêm giường',
        'extra_bed_rate' => 'Giá thêm giường',
        'room_class_id' => 'Loại',
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
        // Bổ sung đầy đủ cho toàn bộ phân hệ:
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
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        $action = $this->resolveAction();
        $bookingCode = $this->resolveBookingCode();
        $roomNumber = $this->resolveRoomNumber();
        $componentLabel = $this->resolveComponentLabel();
        $moduleLabel = $this->resolveModuleLabel();
        $description = $this->resolveDescription($bookingCode, $roomNumber, $componentLabel);

        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'user_name' => $this->user_name,
            'employee_code' => $this->employee_code,
            'action' => $action,
            'module' => $this->module,
            'module_label' => $moduleLabel,
            'component' => $componentLabel,
            'raw_component' => $this->component,
            'description' => $description,
            'target_type' => $this->target_type,
            'target_id' => $this->target_id,
            'target_label' => $this->target_label,
            'booking_code' => $bookingCode,
            'room_number' => $roomNumber,
            'old_values' => $this->old_values,
            'new_values' => $this->new_values,
            'ip_address' => $this->ip_address,
            'user_agent' => $this->user_agent,
            'request_method' => $this->request_method,
            'request_url' => $this->request_url,
            'response_status' => $this->response_status,
            'duration_ms' => $this->duration_ms,
            'created_at' => $this->created_at?->toIso8601String(),
            'created_at_human' => $this->created_at?->timezone('Asia/Ho_Chi_Minh')->diffForHumans(),
            'created_date' => $this->created_at?->timezone('Asia/Ho_Chi_Minh')->format('d/m/Y'),
            'created_time' => $this->created_at?->timezone('Asia/Ho_Chi_Minh')->format('H:i'),
            'created_time_full' => $this->created_at?->timezone('Asia/Ho_Chi_Minh')->format('H:i:s'),
        ];
    }

    /**
     * Chuẩn hóa Action code theo format HKT.
     */
    protected function resolveAction(): string
    {
        return match ($this->action) {
            'create', 'New', 'Add' => 'Add',
            'update', 'Modify' => 'Modify',
            'delete', 'Cancel' => 'Cancel',
            'CheckIn', 'check_in' => 'CheckIn',
            'CheckOut', 'check_out' => 'CheckOut',
            'Payment', 'payment' => 'Payment',
            'Refund', 'refund' => 'Refund',
            'Lock', 'lock' => 'Lock',
            'Unlock', 'unlock' => 'Unlock',
            'NoShow', 'no_show' => 'NoShow',
            'DayClose', 'day_close' => 'DayClose',
            'Inventory', 'inventory' => 'Inventory',
            'login' => 'Login',
            'logout' => 'Logout',
            'login_failed' => 'Login Failed',
            default => $this->action ?: 'Modify',
        };
    }

    /**
     * Lookup BookingRoom with caching.
     */
    protected function getCachedBookingRoom(string $bookingRoomId): ?BookingRoom
    {
        if (!array_key_exists($bookingRoomId, self::$bookingRoomCache)) {
            try {
                self::$bookingRoomCache[$bookingRoomId] = BookingRoom::withTrashed()->find($bookingRoomId);
            } catch (\Throwable $e) {
                self::$bookingRoomCache[$bookingRoomId] = null;
            }
        }
        return self::$bookingRoomCache[$bookingRoomId];
    }

    /**
     * Lookup Booking with caching.
     */
    protected function getCachedBooking(mixed $bookingId): ?Booking
    {
        $id = (int) $bookingId;
        if ($id <= 0) return null;
        if (!array_key_exists($id, self::$bookingCache)) {
            try {
                self::$bookingCache[$id] = Booking::with(['bookingRooms.roomClass', 'company', 'market', 'customerSource', 'creator', 'booker', 'registrationStatus'])->find($id);
            } catch (\Throwable $e) {
                self::$bookingCache[$id] = null;
            }
        }
        return self::$bookingCache[$id];
    }

    /**
     * Trích xuất mã đăng ký chính xác (e.g. GAL7, GAL14, GAL20).
     */
    protected function resolveBookingCode(): ?string
    {
        // 1. Kiểm tra target_label nếu có dạng GAL... hoặc số
        if (!empty($this->target_label)) {
            if (preg_match('/^(GAL\d+)/i', $this->target_label, $m)) {
                return strtoupper($m[1]);
            }
            if (in_array($this->target_type, ['Booking', 'Payment']) && is_numeric($this->target_label)) {
                return 'GAL' . $this->target_label;
            }
        }

        // 2. Nếu target_type là Booking
        if ($this->target_type === 'Booking') {
            if (!empty($this->target_id) && is_numeric($this->target_id)) {
                return 'GAL' . $this->target_id;
            }
            if (!empty($this->new_values['id'])) {
                return 'GAL' . $this->new_values['id'];
            }
            if (!empty($this->old_values['id'])) {
                return 'GAL' . $this->old_values['id'];
            }
        }

        // 3. Nếu new_values hoặc old_values có booking_code trực tiếp
        $directCode = $this->new_values['booking_code'] ?? $this->old_values['booking_code'] ?? null;
        if ($directCode) {
            return str_starts_with(strtoupper($directCode), 'GAL') ? strtoupper($directCode) : ('GAL' . $directCode);
        }

        // 4. Nếu có booking_id trong new_values hoặc old_values
        $bookingId = $this->new_values['booking_id'] 
            ?? $this->old_values['booking_id'] 
            ?? null;
        if ($bookingId) {
            return 'GAL' . $bookingId;
        }

        // 5. Nếu có booking_room_id trong new_values hoặc old_values
        $brId = $this->new_values['booking_room_id'] 
            ?? $this->old_values['booking_room_id'] 
            ?? null;
        if ($brId) {
            $br = $this->getCachedBookingRoom((string) $brId);
            if ($br?->booking_id) {
                return 'GAL' . $br->booking_id;
            }
        }

        // 6. Tìm qua booking-room id (G\d+ hoặc số) trong request_url hoặc target_id
        if (!empty($this->request_url) && preg_match('#/(?:rooms|booking-rooms)/(G\d+|\d+)#i', $this->request_url, $m)) {
            $br = $this->getCachedBookingRoom($m[1]);
            if ($br?->booking_id) {
                return 'GAL' . $br->booking_id;
            }
        }
        if (!empty($this->target_id) && preg_match('/^(G\d+|\d+)$/i', (string) $this->target_id, $m)) {
            $br = $this->getCachedBookingRoom($m[1]);
            if ($br?->booking_id) {
                return 'GAL' . $br->booking_id;
            }
        }

        // 7. Tìm trong request_url: /api/bookings/(\d+) hoặc ?booking_id=(\d+)
        if (!empty($this->request_url)) {
            if (preg_match('#/api/bookings/(\d+)#', $this->request_url, $m)) {
                return 'GAL' . $m[1];
            }
            if (preg_match('#[?&]booking_id=(\d+)#', $this->request_url, $m)) {
                return 'GAL' . $m[1];
            }
        }

        // 8. Tìm trong description
        if (!empty($this->description) && preg_match('/(?:Đ[Kk]|đăng ký|GAL|BK_?)\s*(GAL\d+|\d+)/iu', $this->description, $m)) {
            $val = $m[1];
            return str_starts_with(strtoupper($val), 'GAL') ? strtoupper($val) : ('GAL' . $val);
        }

        return null;
    }

    /**
     * Quy tắc 1 (Hàng 1): Cột Mã phòng: phòng của bk lấy đồng bộ bằng mã G.. của phòng thuê.
     */
    protected function resolveRoomNumber(): ?string
    {
        // 1. Tuyệt đối không lấy số phòng cho các đối tượng cấu hình, nhân viên, công ty, báo cáo, kho bãi
        $nonRoomTypes = ['HotelConfig', 'HotelSetting', 'User', 'Company', 'Branch', 'SystemDefinition', 'SystemDateRoll', 'Inventory', 'Warehouse', 'Product', 'ProductCategory', 'Role', 'ReportDefinition', 'PersonalAccessToken'];
        if (in_array($this->target_type, $nonRoomTypes)) {
            return null;
        }

        $nonRoomComponents = ['HotelDefinition', 'EmployeeTab', 'BranchManageTab', 'CompanyInfoTab', 'hotel-configs', 'hotel-settings', 'users', 'reports', 'ReportsPage', 'Báo cáo', 'Tồn kho', 'Kho hàng'];
        if (in_array($this->component, $nonRoomComponents)) {
            return null;
        }

        // 2. Tìm tất cả các mã G trong description trước (ví dụ: G0000043, G0000044)
        if (!empty($this->description) && preg_match_all('/(G\d{6,8})/i', $this->description, $matches)) {
            $uniqueG = array_values(array_unique($matches[1]));
            if (!empty($uniqueG)) {
                return implode(', ', $uniqueG);
            }
        }

        // 3. Nếu là BookingRoom (Hàng 90: chỉ hiển thị mã G, số phòng chi tiết nằm trong nội dung mô tả)
        if ($this->target_type === 'BookingRoom') {
            if (!empty($this->target_id) && preg_match('/^G\d+/i', (string) $this->target_id)) {
                return (string) $this->target_id;
            }
            if (!empty($this->new_values['id']) && preg_match('/^G\d+/i', (string) $this->new_values['id'])) {
                return (string) $this->new_values['id'];
            }
            if (!empty($this->old_values['id']) && preg_match('/^G\d+/i', (string) $this->old_values['id'])) {
                return (string) $this->old_values['id'];
            }
            if (!empty($this->request_url) && preg_match('#/(?:rooms|booking-rooms)/(G\d+)#i', $this->request_url, $m)) {
                return $m[1];
            }
            if (!empty($this->target_label) && preg_match('/^G\d+/i', (string) $this->target_label)) {
                return (string) $this->target_label;
            }
            return null;
        }

        // 4. Nếu là Booking
        if ($this->target_type === 'Booking') {
            // Row 63: Nếu thao tác No Post ở Booking thì cột Mã phòng để trống
            if (isset($this->new_values['no_post']) || isset($this->old_values['no_post'])) {
                return null;
            }
            if (!empty($this->new_values['rooms']) && is_array($this->new_values['rooms'])) {
                $gRooms = array_values(array_filter($this->new_values['rooms'], fn($r) => preg_match('/^G\d+/i', (string)$r)));
                if (!empty($gRooms)) {
                    return implode(', ', $gRooms);
                }
            }
            $bId = $this->target_id ?: ($this->new_values['id'] ?? null);
            if ($bId) {
                $b = $this->getCachedBooking($bId);
                if ($b && $b->bookingRooms && $b->bookingRooms->isNotEmpty()) {
                    $gIds = $b->bookingRooms->pluck('id')->filter(fn($id) => preg_match('/^G\d+/i', (string)$id))->all();
                    if (!empty($gIds)) {
                        return implode(', ', $gIds);
                    }
                }
            }
            return null;
        }

        // 5. BookingRoomService / BookingRoomGuest / ServiceBill / SpecialRequest
        if (in_array($this->target_type, ['BookingRoomService', 'BookingRoomGuest', 'ServiceBill', 'BookingRoomSpecialRequest', 'SpecialRequest'])) {
            if (!empty($this->request_url) && preg_match('#/(?:rooms|booking-rooms)/(G\d+)#i', $this->request_url, $m)) {
                return $m[1];
            }
            $brId = $this->new_values['booking_room_id'] ?? $this->old_values['booking_room_id'] ?? null;
            if ($brId) {
                if (preg_match('/^G\d+/i', (string)$brId)) {
                    return (string) $brId;
                }
                $br = $this->getCachedBookingRoom((string)$brId);
                if ($br?->id && preg_match('/^G\d+/i', (string)$br->id)) {
                    return (string) $br->id;
                }
            }
            if (!empty($this->target_id) && preg_match('/^G\d+/i', (string)$this->target_id)) {
                return (string) $this->target_id;
            }
            return null;
        }

        // 6. Payment
        if ($this->target_type === 'Payment') {
            $brId = $this->new_values['booking_room_id'] ?? $this->old_values['booking_room_id'] ?? null;
            if ($brId && preg_match('/^G\d+/i', (string)$brId)) {
                return (string) $brId;
            }
            return null;
        }

        // 7. Room hoặc RoomLock (khóa phòng vật lý OOO/OOS)
        if (in_array($this->target_type, ['Room', 'RoomLock'])) {
            return $this->new_values['room_number'] 
                ?? $this->old_values['room_number'] 
                ?? (!empty($this->target_id) && (!is_numeric($this->target_id) || strlen((string)$this->target_id) >= 3) ? (string)$this->target_id : null)
                ?? $this->target_label;
        }

        return null;
    }

    /**
     * Dịch tên màn hình thao tác theo đúng các tab trong file Excel.
     */
    protected function resolveComponentLabel(): string
    {
        $comp = $this->component ?: '';
        $rawUrl = $this->request_url ?: '';
        $desc = $this->description ?: '';
        $target = $this->target_type ?: '';

        // 1. RoomSchedule (Kế hoạch phòng - Khách yêu cầu: Đổi tên Màn hình thao tác về RoomSchedule)
        if (
            $comp === 'RoomSchedule' ||
            $comp === 'RoomPlanPage' ||
            str_contains($rawUrl, 'schedule') ||
            str_contains($rawUrl, 'room-plan') ||
            str_contains($desc, 'RoomSchedule') ||
            str_contains($desc, 'tách phòng') ||
            str_contains($desc, 'Do Not Move') ||
            str_contains($desc, 'đổi loại dạng phòng')
        ) {
            return 'RoomSchedule';
        }

        // 2. Room map (Sơ đồ phòng)
        if (
            $comp === 'RoomMapPage' ||
            $comp === 'RoomMap' ||
            $comp === 'rooms' ||
            str_contains($rawUrl, '/api/rooms') ||
            str_contains($rawUrl, 'room-map') ||
            str_contains($desc, 'Room map') ||
            str_contains($desc, 'Giao phòng nhanh') ||
            str_contains($desc, 'Đổi trạng thái từ:') ||
            str_contains($desc, 'Do Not Disturb')
        ) {
            return 'Room map';
        }

        // 3. Trả phòng (Checkout / Thu ngân)
        if (
            $comp === 'CheckoutPage' ||
            $comp === 'Checkout' ||
            $comp === 'payments' ||
            $comp === 'cashier' ||
            str_contains($rawUrl, 'checkout') ||
            str_contains($rawUrl, 'payments') ||
            str_contains($desc, 'Check Out Cho Đăng Ký') ||
            str_contains($desc, 'Thanh Toán cho đăng ký') ||
            str_contains($desc, 'Gửi hoá đơn về mã đăng ký') ||
            str_contains($desc, 'Tập hợp các bill') ||
            str_contains($desc, 'Chuyển bill') ||
            str_contains($desc, 'Correct Hóa Đơn')
        ) {
            return 'Trả phòng';
        }

        // 4. Tạo đăng ký (CreateRegistration)
        if (
            $comp === 'CreateRegistrationPage' ||
            $comp === 'Booking' ||
            $comp === 'bookings' ||
            $comp === 'booking-rooms' ||
            $target === 'Booking' ||
            str_contains($rawUrl, 'bookings') ||
            str_contains($rawUrl, 'reservation') ||
            str_starts_with(trim($desc), '* Thông Tin Đăng Ký') ||
            str_starts_with(trim($desc), '* Thêm Đặt Cọc') ||
            str_contains($desc, 'đưa đón khách')
        ) {
            return 'Tạo đăng ký';
        }

        // 5. Khóa phòng
        if (
            $comp === 'LockRoomPage' ||
            $comp === 'RoomLock' ||
            $comp === 'room-locks' ||
            $target === 'RoomLock' ||
            str_contains($rawUrl, 'room-locks') ||
            str_contains($desc, 'phòng khóa') ||
            str_contains($desc, 'Phòng OOO') ||
            str_contains($desc, 'Phòng OOS')
        ) {
            return 'Khóa phòng';
        }

        // 6. Sang ngày
        if (
            $comp === 'DayClosePage' ||
            $comp === 'NightAudit' ||
            $comp === 'night-audit' ||
            $target === 'SystemDateRoll' ||
            str_contains($rawUrl, 'day-close') ||
            str_contains($rawUrl, 'night-audit') ||
            str_contains($desc, 'sang ngày') ||
            str_contains($desc, 'Noshow') ||
            str_contains($desc, 'Gia hạn phòng')
        ) {
            return 'Sang ngày';
        }

        // 7. Báo cáo
        if (
            $comp === 'ReportsPage' ||
            $comp === 'report-definitions' ||
            $target === 'ReportDefinition' ||
            $this->module === 'reports' ||
            str_contains($rawUrl, 'report') ||
            str_contains($desc, 'báo cáo') ||
            str_contains($desc, 'report-definitions')
        ) {
            return 'Báo cáo';
        }

        // 8. In Phân công phòng
        if (
            $comp === 'HousekeepingStaffAssignments' ||
            $comp === 'hk-assignments' ||
            str_contains($desc, 'phân công phòng') ||
            str_contains($desc, 'lịch phân công')
        ) {
            return 'In Phân công phòng';
        }

        // 9. QUản lý đồ thất lạc
        if (
            $comp === 'LostAndFound' ||
            $comp === 'hk-lost-found' ||
            str_contains($desc, 'Lost & Found')
        ) {
            return 'QUản lý đồ thất lạc';
        }

        // 10. Tồn kho
        if (
            $comp === 'InventoryTab' ||
            $comp === 'inventories' ||
            $comp === 'warehouses' ||
            str_contains($desc, 'phiếu kiểm kê') ||
            str_contains($desc, 'tồn kho') ||
            str_contains($desc, 'kho hóa chất')
        ) {
            return 'Tồn kho';
        }

        // 11. Tạo Menu
        if (
            $comp === 'HousekeepingMenuDefinition' ||
            $comp === 'hk-menu' ||
            $comp === 'products' ||
            $comp === 'product-categories' ||
            str_contains($desc, 'nhóm sản phẩm') ||
            str_contains($desc, 'sản phẩm:')
        ) {
            return 'Tạo Menu';
        }

        // 12. Thêm DV Buồng phòng
        if (
            $comp === 'ServicesModal' ||
            $comp === 'hk-services' ||
            str_contains($desc, 'Minibar')
        ) {
            return 'Thêm DV Buồng phòng';
        }

        // 13. Công ty
        if (
            $comp === 'CompanyInfoTab' ||
            $comp === 'CompanySettingsPage' ||
            $comp === 'companies' ||
            $target === 'Company' ||
            str_contains($rawUrl, 'companies') ||
            str_contains($desc, 'Công Ty')
        ) {
            return 'Công ty';
        }

        return self::$componentMap[$comp] ?? ($comp ?: 'Hệ thống');
    }

    /**
     * Dịch tên module sang tiếng Việt thân thiện.
     */
    protected function resolveModuleLabel(): string
    {
        return self::$moduleMap[$this->module] ?? $this->module ?? 'Khác';
    }

    /**
     * Tách lấy Dạng giường đơn giản (Double / Twin / Triple / Single).
     */
    protected function extractBedForm(?string $className, ?string $formName): string
    {
        $raw = ($formName ?: '') . ' ' . ($className ?: '');
        if (preg_match('/(Double|Twin|Triple|Single|King|Queen)/i', $raw, $m)) {
            return ucfirst(strtolower($m[1]));
        }
        return $formName ?: ($className ?: 'Double');
    }

    /**
     * Tự động hoàn thiện mô tả chi tiết theo đúng format 100% của file Excel.
     */
    protected function resolveDescription(?string $bookingCode, ?string $roomNumber, string $componentLabel): string
    {
        $desc = $this->description ?: '';
        $action = $this->resolveAction();
        $rawUrl = $this->request_url ?: '';

        // 0. Cấu hình Khách sạn (HotelConfig / HotelSetting)
        if ($this->target_type === 'HotelConfig' || $this->target_type === 'HotelSetting') {
            $key = $this->new_values['name'] ?? $this->old_values['name'] ?? $this->configName() ?? $this->target_label ?? $this->new_values['config_key'] ?? $this->old_values['config_key'] ?? 'Cấu hình';
            $oldVals = $this->old_values ?: [];
            $newVals = $this->new_values ?: [];

            $hasOld = array_key_exists('config_value', $oldVals) || array_key_exists('value', $oldVals);
            $hasNew = array_key_exists('config_value', $newVals) || array_key_exists('value', $newVals);

            if ($hasOld || $hasNew) {
                $oldVal = $oldVals['config_value'] ?? $oldVals['value'] ?? null;
                $newVal = $newVals['config_value'] ?? $newVals['value'] ?? null;
                $oldDisplay = ($oldVal === null || $oldVal === '') ? '(trống)' : (is_bool($oldVal) ? ($oldVal ? '1' : '0') : (string) $oldVal);
                $newDisplay = ($newVal === null || $newVal === '') ? '(trống)' : (is_bool($newVal) ? ($newVal ? '1' : '0') : (string) $newVal);
                return "* Cập nhật cấu hình khách sạn '{$key}': {$oldDisplay} ➜ {$newDisplay}";
            }
            return "* Cập nhật cấu hình khách sạn '{$key}'";
        }

        // 1. Quy tắc 2 (Hàng 1 & 71): Báo cáo -> Không log 'auth_token', log tên báo cáo và khoảng thời gian
        if ($this->target_type === 'ReportDefinition' || $componentLabel === 'Báo cáo' || str_contains($desc, 'report-definitions')) {
            $reportName = $this->target_label ?? 'Báo cáo';
            $params = $this->new_values['parameters'] ?? $this->old_values['parameters'] ?? [];
            $from = !empty($params['from_date']) ? \Carbon\Carbon::parse($params['from_date'])->format('d-m-Y') : '';
            $to = !empty($params['to_date']) ? \Carbon\Carbon::parse($params['to_date'])->format('d-m-Y') : '';
            if ($from === '' && $to === '') {
                return "Xem báo cáo: \"{$reportName}\"";
            }
            return "Xem báo cáo: \"{$reportName}\"  - Từ ngày {$from} , - Đến ngày {$to}";
        }

        // 2. Khóa phòng OOO / OOS (Hàng 5, 6, 103)
        if ($this->target_type === 'RoomLock' || $componentLabel === 'Khóa phòng') {
            $pNum = $roomNumber ?: ($this->new_values['room_number'] ?? $this->old_values['room_number'] ?? $this->target_id);
            $type = $this->safeStr($this->new_values['lock_type'] ?? $this->old_values['lock_type'] ?? null);
            $reason = $this->safeStr($this->new_values['reason'] ?? $this->old_values['reason'] ?? null);
            $reasonText = $reason !== '' ? " - Lý do: {$reason}" : '';

            if ($action === 'Add' || $action === 'Lock') {
                $fromRaw = $this->new_values['from_date'] ?? $this->new_values['start_date'] ?? null;
                $toRaw = $this->new_values['to_date'] ?? $this->new_values['end_date'] ?? null;
                $from = !empty($fromRaw) ? \Carbon\Carbon::parse($fromRaw)->format('d-m-Y') : '';
                $to = !empty($toRaw) ? \Carbon\Carbon::parse($toRaw)->format('d-m-Y') : '';
                $period = ($from !== '' ? " từ {$from}" : '') . ($to !== '' ? " đến {$to}" : '');
                return trim("Thêm Phòng {$type}") . ". Phòng {$pNum} đã bị khóa{$period}{$reasonText}";
            }
            if ($action === 'Modify') {
                $id = $this->target_id;
                $diff = $this->formatDiffLines();
                return "Cập nhật phòng khóa {$type} mã {$id}, phòng {$pNum}:\n" . implode("\n", $diff);
            }
            if ($action === 'Unlock' || $action === 'Cancel') {
                return "Mở khóa phòng {$pNum}";
            }
        }

        // 3. Tạo Booking mới (Hàng 3, 4, 14, 15, 16)
        if ($this->target_type === 'Booking' && $action === 'Add') {
            $bookingId = $this->target_id ?: ($bookingCode ? preg_replace('/\D/', '', $bookingCode) : '0');
            $b = $this->getCachedBooking($bookingId);
            $bCode = $bookingCode ?: ('GAL' . $bookingId);

            $name = $this->safeStr($this->new_values['booking_name'] ?? $b?->booking_name);
            $arrRaw = $this->new_values['arrival_date'] ?? $b?->arrival_date;
            $arr = !empty($arrRaw) ? \Carbon\Carbon::parse($arrRaw)->format('d-m-Y') : '';
            $depRaw = $this->new_values['departure_date'] ?? $b?->departure_date;
            $dep = !empty($depRaw) ? \Carbon\Carbon::parse($depRaw)->format('d-m-Y') : '';
            $git = !empty($b?->is_git ?? ($this->new_values['is_git'] ?? null)) ? 'GIT' : 'FIT';

            $companyName = $this->new_values['company'] ?? $this->lookup('company_id', $this->new_values['company_id'] ?? null) ?? $b?->company?->name;
            $company = $this->safeStr($companyName ?? (($b || array_key_exists('company_id', $this->new_values ?? [])) ? 'KHÁCH LẺ' : null));
            $market = $this->safeStr($this->new_values['market'] ?? $this->lookup('market_id', $this->new_values['market_id'] ?? null) ?? $b?->market?->name);
            $source = $this->safeStr($this->new_values['customer_source'] ?? $this->lookup('customer_source_id', $this->new_values['customer_source_id'] ?? null) ?? $b?->customerSource?->name);
            $sales = $this->safeStr($b?->sales_person ?? ($this->new_values['sales_person'] ?? ($b?->creator?->name ?? null)));
            $bookerName = $this->safeStr($this->new_values['booker'] ?? $this->lookup('booker_id', $this->new_values['booker_id'] ?? null) ?? $b?->booker?->name);

            $phone = $b?->booker?->phone ?? ($b?->contact_phone ?? null);
            if (!$phone && isset($this->new_values['booker']) && is_array($this->new_values['booker'])) {
                $phone = $this->new_values['booker']['phone'] ?? null;
            }
            if (!$phone && isset($this->new_values['contact_phone'])) {
                $phone = is_string($this->new_values['contact_phone']) ? $this->new_values['contact_phone'] : null;
            }
            $contactPhone = $this->safeStr($phone, '');

            $nights = $this->safeStr($b?->num_of_days ?? ($this->new_values['num_of_days'] ?? null));

            // Trạng thái theo tình trạng đăng ký thật của booking
            $status = $this->safeStr($b?->registrationStatus?->name
                ?? ($this->new_values['registration_status'] ?? $this->lookup('registration_status_id', $this->new_values['registration_status_id'] ?? null)));

            // Hàng 3: Bỏ phần VAT, Thông tin liên hệ là sđt của booker
            $email = $this->safeStr($b?->contact_email ?? ($this->new_values['contact_email'] ?? null));
            $note = $this->safeStr($b?->note ?? ($this->new_values['note'] ?? null));
            $tourCode = $this->safeStr($b?->event_code ?? ($this->new_values['event_code'] ?? null));
            $info = "* Thông Tin Đăng Ký {$bCode} : -Tên nhóm : {$name} -FIT : {$git} -Ngày đến : {$arr} -Ngày đi : {$dep} -Số ngày : {$nights} -Trạng thái : {$status} -Công ty : {$company} -Tour Code : {$tourCode} -Liên hệ : {$contactPhone} -Payment : -Booker : {$bookerName} -SalesPerson : {$sales} -Market Segment : {$market} -Source Code : {$source} -Email : {$email} -Ghi chú đăng ký : {$note}";

            // Hàng 4: Giá đúng thực tế có dấu phẩy, dạng phòng chỉ lấy Double / Twin
            $roomLines = [];
            $rooms = [];
            if (!empty($this->new_values['booking_rooms']) && is_array($this->new_values['booking_rooms'])) {
                $rooms = $this->new_values['booking_rooms'];
            } elseif ($b && $b->bookingRooms && $b->bookingRooms->isNotEmpty()) {
                $rooms = $b->bookingRooms;
            }

            foreach ($rooms as $br) {
                $rId = is_array($br) ? ($br['id'] ?? '') : $br->id;
                $arrDate = is_array($br) ? ($br['arrival_date'] ?? null) : $br->arrival_date;
                $depDate = is_array($br) ? ($br['departure_date'] ?? null) : $br->departure_date;
                $rArr = $arrDate ? \Carbon\Carbon::parse($arrDate)->format('d-m-Y') : $arr;
                $rDep = $depDate ? \Carbon\Carbon::parse($depDate)->format('d-m-Y') : $dep;
                $price = is_array($br) ? ($br['rate'] ?? $br['price'] ?? null) : ($br->rate ?? $br->price ?? null);
                $priceStr = is_numeric($price) ? number_format((float) $price, 0, '.', ',') : '';

                if (is_array($br)) {
                    $cls = $br['room_class']['name'] ?? $br['room_class_name'] ?? $this->lookup('room_class_id', $br['room_class_id'] ?? null) ?? '';
                } else {
                    $cls = $br->roomClass?->name ?? '';
                }

                $rForm = is_array($br) ? ($br['room_form'] ?? null) : $br->room_form;
                $form = $this->extractBedForm($cls, $rForm);
                $pNum = is_array($br) ? ($br['room_number'] ?? 'Chưa gán') : ($br->room_number ?: 'Chưa gán');
                if (!$pNum) $pNum = 'Chưa gán';
                $adults = is_array($br) ? ($br['adults'] ?? '') : ($br->adults ?? '');
                $child = is_array($br) ? ($br['children_qty'] ?? $br['children'] ?? 0) : ($br->children_qty ?? 0);
                $hasBf = is_array($br) ? (!empty($br['breakfast'])) : (!empty($br->breakfast));
                $bf = $hasBf ? 'Có' : 'Không';
                $extra = is_array($br) ? ($br['extra_bed_qty'] ?? 0) : ($br->extra_bed_qty ?? 0);

                $roomLines[] = "* Phòng thuê : # Thêm mới : Mã: {$rId} - Giá: {$priceStr} - Loại: {$cls} - Dạng: {$form} - Phòng: {$pNum} - Ngày đến: {$rArr} - Ngày đi: {$rDep} - Người lớn: {$adults} - Trẻ em: {$child} - Trẻ em ăn sáng miễn phí: 0 - Ăn sáng: {$bf} - Thêm giường: {$extra} - BirthDay: Không";
            }

            if (!empty($roomLines)) {
                return $info . "\n" . implode("\n", $roomLines);
            }
            return $info;
        }

        // 4. Cập nhật Booking (Hàng 17, 18, 33, 63)
        if ($this->target_type === 'Booking' && $action === 'Modify') {
            $bookingId = $this->target_id ?: ($bookingCode ? preg_replace('/\D/', '', $bookingCode) : '0');
            $bCode = $bookingCode ?: ('GAL' . $bookingId);

            // Row 63: No Post tại cấp Booking
            if (isset($this->new_values['no_post']) || isset($this->old_values['no_post'])) {
                $enabled = !empty($this->new_values['no_post']);
                $state = $enabled ? 'Bật' : 'Tắt';
                return "* {$state} trạng thái No Post cho Mã đăng ký {$bCode}";
            }

            $diff = $this->formatHktBookingDiff();
            if (!empty($diff)) {
                return "* Cập Nhật Thông Tin Đăng Ký {$bCode} : " . implode(' ', $diff);
            }
            return "* Cập Nhật Thông Tin Đăng Ký {$bCode}";
        }

        // 5. Hủy Booking (Hàng 44)
        if ($this->target_type === 'Booking' && $action === 'Cancel') {
            $bookingId = $this->target_id ?: ($bookingCode ? preg_replace('/\D/', '', $bookingCode) : '0');
            $bCode = $bookingCode ?: ('GAL' . $bookingId);
            $name = $this->safeStr($this->old_values['booking_name'] ?? null);
            $reason = $this->safeStr($this->new_values['cancel_reason'] ?? $this->old_values['cancel_reason'] ?? $this->new_values['reason'] ?? $this->old_values['reason'] ?? null);
            $reasonText = $reason !== '' ? ", Lý do: {$reason}" : '';
            $line1 = "* Huỷ đăng ký mã {$bCode}" . ($name !== '' ? " - {$name}" : '') . $reasonText;
            if (!empty($roomNumber)) {
                $line2 = "* Huỷ phòng thuê ({$roomNumber})" . ($reason !== '' ? ". Lý do: {$reason}" : '');
                return $line1 . "\n" . $line2;
            }
            return $line1;
        }

        // 6. BookingRoom - Cập nhật / Tách / Đổi số phòng (Hàng 8, 9, 10, 11, 12, 13, 27, 34, 35, 63, 98)
        if ($this->target_type === 'BookingRoom') {
            $roomId = $roomNumber ?: ($this->target_id ?: '');
            $brModel = $roomId !== '' && !str_contains($roomId, ',') ? $this->getCachedBookingRoom($roomId) : null;
            // Số phòng vật lý của phòng thuê (mã G... là mã phòng thuê)
            $physicalRoom = $this->safeStr($this->new_values['room_number'] ?? $this->old_values['room_number'] ?? $brModel?->room_number);
            $bkCode = $bookingCode ?: (($this->new_values['booking_id'] ?? $this->old_values['booking_id'] ?? $brModel?->booking_id) ? 'GAL' . ($this->new_values['booking_id'] ?? $this->old_values['booking_id'] ?? $brModel?->booking_id) : '');

            if ($action === 'Add') {
                // Nhiều phòng ghi chung một dòng (lúc tạo đăng ký): giữ mô tả đã ghi
                if (isset($this->new_values['rooms']) || str_contains($roomId, ',')) {
                    return $desc;
                }
                $br = $brModel;
                $cls = $this->safeStr($this->new_values['room_class'] ?? $this->lookup('room_class_id', $this->new_values['room_class_id'] ?? null) ?? $br?->roomClass?->name);
                $form = $this->safeStr($this->new_values['room_form'] ?? $br?->room_form);
                $price = $this->new_values['rate'] ?? $this->new_values['price'] ?? $br?->rate;
                $priceStr = is_numeric($price) ? number_format((float) $price, 0, '.', ',') : '';
                $pNum = $physicalRoom !== '' ? $physicalRoom : 'Chưa gán';
                $adults = $this->safeStr($this->new_values['adults'] ?? $br?->adults);
                $child = $this->safeStr($this->new_values['children_qty'] ?? $this->new_values['children'] ?? $br?->children_qty);
                $bfRaw = $this->new_values['breakfast'] ?? $br?->breakfast;
                $bf = $bfRaw === null ? '' : ($bfRaw ? 'Có' : 'Không');
                $extra = $this->safeStr($this->new_values['extra_bed_qty'] ?? $br?->extra_bed_qty);
                $arrRaw = $this->new_values['arrival_date'] ?? $br?->arrival_date;
                $depRaw = $this->new_values['departure_date'] ?? $br?->departure_date;
                $rArr = $arrRaw ? \Carbon\Carbon::parse($arrRaw)->format('d-m-Y') : '';
                $rDep = $depRaw ? \Carbon\Carbon::parse($depRaw)->format('d-m-Y') : '';
                $bkText = $bkCode !== '' ? " - Đăng ký: {$bkCode}" : '';

                return "* Phòng thuê : # Thêm mới : Mã: {$roomId}{$bkText} - Giá: {$priceStr} - Loại: {$cls} - Dạng: {$form} - Phòng: {$pNum} - Ngày đến: {$rArr} - Ngày đi: {$rDep} - Người lớn: {$adults} - Trẻ em: {$child} - Ăn sáng: {$bf} - Thêm giường: {$extra}";
            }

            if ($action === 'Modify') {
                if (str_contains($desc, 'tách phòng')) {
                    return $desc;
                }

                // Row 98: Hủy nhận phòng (undo check-in)
                if (str_contains($rawUrl, 'undo-checkin') || str_contains($desc, 'Cancel checkin') || (isset($this->old_values['status']) && (int)$this->old_values['status'] === 1 && isset($this->new_values['status']) && (int)$this->new_values['status'] === 0)) {
                    $pNum = $physicalRoom !== '' ? $physicalRoom : $roomId;
                    return "Cancel checkin cho Mã Đăng Ký: {$bkCode}\n- Phòng: {$pNum}";
                }

                // Row 34: Tự động gán phòng (auto-assign)
                if (str_contains($rawUrl, 'auto-assign') || str_contains($desc, 'Tự động assigned rooms')) {
                    $pNum = $this->safeStr($this->new_values['room_number'] ?? $brModel?->room_number);
                    return "* Tự động assigned rooms: - Mã phòng: {$roomId} -> {$pNum}" . ($bkCode !== '' ? " - Đăng ký: {$bkCode}" : '');
                }

                // Row 63: No Post tại cấp BookingRoom
                if (isset($this->new_values['no_post']) || isset($this->old_values['no_post'])) {
                    $enabled = !empty($this->new_values['no_post']);
                    $state = $enabled ? 'Bật' : 'Tắt';
                    $pNum = $physicalRoom !== '' ? $physicalRoom : $roomId;
                    return "* {$state} trạng thái No Post cho Mã đăng ký {$bkCode}, phòng: {$pNum}";
                }

                // Khóa chuyển phòng Do Not Move (Hàng 11, 12, 41, 42)
                $oldDnm = $this->old_values['is_do_not_move'] ?? null;
                $newDnm = $this->new_values['is_do_not_move'] ?? null;
                if ($oldDnm !== null && $newDnm !== null && $oldDnm != $newDnm) {
                    $fromState = $oldDnm ? 'Do Not Move' : 'sẵn sàng';
                    $toState = $newDnm ? 'Do Not Move' : 'sẵn sàng';
                    return "* Cập nhật phòng thuê: {$roomId}" . ($physicalRoom !== '' ? " (phòng {$physicalRoom})" : '') . " - Đăng Ký: {$bkCode} từ {$fromState} sang {$toState}";
                }

                // Đổi loại / dạng phòng (Hàng 9, 35)
                $oldClass = $this->old_values['room_class'] ?? $this->old_values['room_class_id'] ?? null;
                $newClass = $this->new_values['room_class'] ?? $this->new_values['room_class_id'] ?? null;
                if ($oldClass && $newClass && $oldClass != $newClass) {
                    $lines = ["* Cập nhật phòng thuê: điều chỉnh loại dạng phòng cho {$roomId}" . ($bkCode !== '' ? " - Đăng ký: {$bkCode}" : '')];
                    if (array_key_exists('room_number', $this->new_values ?? []) || array_key_exists('room_number', $this->old_values ?? [])) {
                        $lines[] = 'Phòng: ' . $this->safeStr($this->old_values['room_number'] ?? null, '(trống)') . ' -> ' . $this->safeStr($this->new_values['room_number'] ?? null, '(trống)');
                    }
                    $lines[] = 'Loại: ' . $this->classLabel($oldClass) . ' -> ' . $this->classLabel($newClass);
                    $oldForm = $this->old_values['room_form'] ?? null;
                    $newForm = $this->new_values['room_form'] ?? null;
                    if ($oldForm !== null || $newForm !== null) {
                        $lines[] = 'Dạng: ' . $this->safeStr($oldForm, '(trống)') . ' -> ' . $this->safeStr($newForm, '(trống)');
                    }
                    return implode("\n", $lines);
                }

                // Dòng 325: luôn kèm mã đăng ký và số phòng để tra cứu
                $ref = $roomId . ($physicalRoom !== '' && $physicalRoom !== $roomId ? " - Phòng {$physicalRoom}" : '') . ($bkCode !== '' ? " - Đăng ký {$bkCode}" : '');
                $diff = $this->formatHktRoomDiff();
                if (!empty($diff)) {
                    return "* Cập Nhật Phòng Thuê ({$ref}) : " . implode(' ', $diff);
                }
                return "* Cập Nhật Phòng Thuê ({$ref})";
            }

            if ($action === 'Cancel') {
                $reason = $this->safeStr($this->new_values['reason'] ?? $this->old_values['reason'] ?? null);
                $pNum = $physicalRoom !== '' ? $physicalRoom : 'Chưa gán';
                return "* Hủy phòng thuê: {$pNum} ({$roomId}) - Đăng ký: {$bkCode}" . ($reason !== '' ? " Lý do: {$reason}" : '');
            }
        }

        // 7. Payment / Đặt cọc (Hàng 19, 20, 21, 22, 23, 59, 60, 61)
        if ($this->target_type === 'Payment') {
            $codeStr = $bookingCode ?: '(không rõ)';
            $amountRaw = $this->new_values['amount'] ?? $this->old_values['amount'] ?? null;
            $amountStr = is_numeric($amountRaw) ? number_format((float) $amountRaw, 0, '.', ',') : '';
            $method = $this->safeStr($this->new_values['payment_method'] ?? $this->old_values['payment_method']
                ?? $this->lookup('payment_method_id', $this->new_values['payment_method_id'] ?? $this->old_values['payment_method_id'] ?? null));
            $note = $this->safeStr($this->new_values['description'] ?? $this->new_values['note'] ?? $this->old_values['description'] ?? $this->old_values['note'] ?? null);
            $paymentBooking = $bookingCode ? $this->getCachedBooking(preg_replace('/\D/', '', $bookingCode)) : null;

            // Row 20: Tách cọc
            if (str_contains($rawUrl, 'split') || str_contains($desc, 'tách làm hai') || isset($this->new_values['total_amount_before_split'])) {
                $origRaw = $this->new_values['total_amount_before_split'] ?? $this->old_values['total_amount_before_split'] ?? null;
                $amt1Raw = $this->new_values['amount'] ?? null;
                if (!is_numeric($origRaw) || !is_numeric($amt1Raw)) {
                    return $desc;
                }
                $orig = (float) $origRaw;
                $amt1 = (float) $amt1Raw;
                $origStr = number_format($orig, 0, '.', ',');
                $amt1Str = number_format($amt1, 0, '.', ',');
                $amt2Str = number_format(max(0, $orig - $amt1), 0, '.', ',');
                $folio = $this->safeStr($this->new_values['folio_id'] ?? $this->new_values['folio'] ?? null);
                $folioText = $folio !== '' ? ", folio {$folio}" : '';
                $label = $note !== '' ? $note : 'Đặt cọc';
                return "* {$label} số tiền: {$origStr}, tách làm hai, số tiền 1: {$amt1Str}{$folioText}, số tiền 2: {$amt2Str}{$folioText}";
            }

            // Row 21: Chuyển cọc
            if (str_contains($rawUrl, 'transfer') || str_contains($desc, 'TRANSFER IN') || str_contains($desc, 'Chuyển đặt cọc')) {
                if (preg_match('/BK_(\d+)=>BK_(\d+)/', $desc, $m)) {
                    [$sourceBk, $targetBk] = [$m[1], $m[2]];
                } elseif (!empty($this->new_values['target_booking_id']) && $bookingCode) {
                    $targetBk = (string) $this->new_values['target_booking_id'];
                    $sourceBk = preg_replace('/\D/', '', $bookingCode);
                } else {
                    return $desc;
                }
                $label = $note !== '' ? $note : 'Đặt cọc';
                return "Chuyển đặt cọc ({$label} - {$amountStr}) từ mã đăng ký: {$sourceBk} đến mã đăng ký: {$targetBk}";
            }

            if ($action === 'Add' || $this->action === 'Payment') {
                $bookingName = $this->safeStr($paymentBooking?->booking_name);
                return "* Thêm Đặt Cọc Cho Đăng Ký: {$codeStr} - Tên Booking: {$bookingName} - PaymentMethod: {$method} - Số Tiền: {$amountStr} - Mô tả: {$note}";
            }
            if ($action === 'Modify') {
                $diff = $this->formatDiffLines();
                return "Sửa Đặt Cọc Cho Đăng Ký: {$codeStr}" . ($diff ? " :\n" . implode("\n", $diff) : '');
            }
            if ($action === 'Cancel') {
                $reason = $this->safeStr($this->new_values['reason'] ?? $this->old_values['reason'] ?? null);
                $roomPart = $roomNumber ? " - mã phòng thuê: {$roomNumber}" : "";
                return "* Correct Đặt Cọc Cho Đăng Ký: {$codeStr}{$roomPart} - PaymentMethod: {$method} - Số tiền: {$amountStr}" . ($reason !== '' ? " - Lý Do: {$reason}" : '');
            }
        }

        // 8. Đưa đón khách (Hàng 24, 25, 26)
        if (str_contains($desc, 'đưa đón khách') || str_contains($desc, 'Airport')) {
            return $desc;
        }

        // 9. Công ty (Hàng 64, 65)
        if ($this->target_type === 'Company' || $componentLabel === 'Công ty') {
            $companyCode = $this->safeStr($this->new_values['code'] ?? $this->old_values['code'] ?? null);
            $name = $this->safeStr($this->new_values['name'] ?? $this->old_values['name'] ?? $this->target_label);

            if ($action === 'Add') {
                $details = $this->formatCreationDetails();
                return "* Thêm Công Ty : ID: {$companyCode} , Công Ty: {$name}" . ($details ? " :\n" . implode("\n", $details) : '');
            }
            if ($action === 'Modify') {
                $diff = $this->formatDiffLines(['code', 'name']);
                if (($this->old_values['name'] ?? null) !== ($this->new_values['name'] ?? null) && array_key_exists('name', $this->old_values ?? [])) {
                    array_unshift($diff, '• Tên: ' . $this->safeStr($this->old_values['name']) . ' ➜ ' . $this->safeStr($this->new_values['name'] ?? null));
                }
                return "* Cập Nhật Công Ty:- ID: {$companyCode} - {$name} , " . implode(' , ', $diff);
            }
        }

        // 10. Check-out (Hàng 62)
        if ($action === 'CheckOut') {
            if ($roomNumber) {
                $br = preg_match('/^G\d+$/i', (string) $roomNumber) ? $this->getCachedBookingRoom($roomNumber) : null;
                $pNum = $this->safeStr($this->new_values['room_number'] ?? $this->old_values['room_number'] ?? $br?->room_number);
                if ($pNum === '' || $pNum === $roomNumber) {
                    return "* Check Out Cho Đăng Ký {$bookingCode} - Phòng: {$roomNumber}";
                }
                return "* Check Out Cho Đăng Ký {$bookingCode} - Phòng: {$pNum} ({$roomNumber})";
            }
            return "* Check Out Cho Đăng Ký {$bookingCode}.";
        }

        // 11. Cảnh báo / Alert (Hàng 45, 46, 47)
        if ($this->target_type === 'BookingNotification' || str_contains($desc, 'BookingNotification')) {
            $codeStr = $bookingCode ?: '';
            $msg = $this->safeStr($this->new_values['message'] ?? $this->new_values['description'] ?? $this->old_values['message'] ?? $this->old_values['description'] ?? null);
            $dept = $this->safeStr($this->new_values['department'] ?? $this->old_values['department'] ?? null);
            $fromRaw = $this->new_values['starts_on'] ?? $this->old_values['starts_on'] ?? null;
            $toRaw = $this->new_values['ends_on'] ?? $this->old_values['ends_on'] ?? null;
            $from = !empty($fromRaw) ? \Carbon\Carbon::parse($fromRaw)->format('d-m-Y') : '';
            $to = !empty($toRaw) ? \Carbon\Carbon::parse($toRaw)->format('d-m-Y') : '';

            if ($action === 'Add' || $action === 'create') {
                return "* Tạo Alert cho mã đăng ký {$codeStr}\n- Nội dung cảnh báo: {$msg}\n- Department: {$dept}\n- Từ ngày: {$from} - Đến ngày: {$to}";
            }
            if ($action === 'Cancel' || $action === 'delete') {
                return "* Xóa Alert mã đăng ký {$codeStr}\n- Nội dung cảnh báo: {$msg}\n- Department: {$dept}\n- Từ ngày: {$from} - Đến ngày: {$to}";
            }
            if ($action === 'Modify' || $action === 'update') {
                $oldMsg = $this->safeStr($this->old_values['message'] ?? $this->old_values['description'] ?? null, '(trống)');
                $newMsg = $this->safeStr($this->new_values['message'] ?? $this->new_values['description'] ?? null, '(trống)');
                return "* Sửa Alert mã đăng ký {$codeStr}:\n- Nội dung cảnh báo: {$oldMsg} ➜ {$newMsg}\n- Từ ngày: {$from} - Đến ngày: {$to}";
            }
        }

        // 12. Trạng thái buồng phòng & DND (Hàng 48, 97)
        if (str_contains($desc, 'trạng thái buồng phòng') || str_contains($desc, 'Do Not Disturb') || ($this->target_type === 'Room' && ($action === 'Modify' || $action === 'update'))) {
            $pNum = $this->new_values['room_number'] ?? $this->old_values['room_number'] ?? ($this->target_label ?: $this->target_id);
            $oldCode = $this->old_values['room_status_code'] ?? null;
            $newCode = $this->new_values['room_status_code'] ?? null;

            if ($newCode === 'dnd' || str_contains($desc, 'Bật trạng thái Do Not Disturb')) {
                return "* Phòng {$pNum}. Bật trạng thái Do Not Disturb (Không làm phiền)";
            }
            if (($oldCode === 'dnd' && $newCode !== 'dnd') || str_contains($desc, 'Hủy trạng thái Do Not Disturb')) {
                return "* Phòng {$pNum}. Hủy trạng thái Do Not Disturb (Không làm phiền)";
            }

            $statusNames = [
                'vacant_dirty' => 'Phòng chưa dọn',
                'vacant_clean' => 'Phòng sạch',
                'vacant_ready' => 'Phòng sẵn sàng',
                'occupied_dirty' => 'Phòng có khách chưa dọn',
                'occupied_clean' => 'Phòng có khách đã dọn',
                'occupied_ready' => 'Phòng có khách sẵn sàng',
                'ooo' => 'Phòng hỏng (OOO)',
                'oos' => 'Phòng khóa (OOS)',
                'dnd' => 'Do Not Disturb (Không làm phiền)',
            ];
            $oldStatus = $statusNames[$oldCode] ?? $this->safeStr($this->old_values['status_name'] ?? $this->old_values['status'] ?? null, '(trống)');
            $newStatus = $statusNames[$newCode] ?? $this->safeStr($this->new_values['status_name'] ?? $this->new_values['status'] ?? null, '(trống)');
            return "* Phòng {$pNum}. Đổi trạng thái từ: {$oldStatus} -> {$newStatus}";
        }

        // 13. Khách lưu trú (Hàng 36, 100, 101, 102)
        if ($this->target_type === 'Guest' || $this->target_type === 'BookingRoomGuest' || str_contains($desc, 'khách lưu trú')) {
            $gCode = $this->target_label ?? ($this->target_id ? 'K' . str_pad($this->target_id, 9, '0', STR_PAD_LEFT) : '');
            if ($action === 'Add' || $action === 'create') {
                $details = $this->formatCreationDetails();
                return "Thêm khách: Mã khách: {$gCode}" . ($details ? "\n" . implode("\n", $details) : '');
            }
            if ($action === 'Modify' || $action === 'update') {
                $diff = $this->formatDiffLines();
                return "* Cập nhật thông tin khách: Mã khách: {$gCode}\n" . implode(' - ', $diff);
            }
            if ($action === 'Cancel' || $action === 'delete') {
                $name = $this->safeStr($this->old_values['full_name'] ?? $this->old_values['name'] ?? $this->target_label);
                return "Xóa khách: Mã khách: {$gCode} - Tên khách: {$name}";
            }
        }

        // 14. Hóa đơn dịch vụ / Tiền phòng: giữ mô tả ghi lúc thao tác (không dựng lại bằng dữ liệu mẫu)
        if (str_contains($desc, 'ServiceBillDetail') || str_contains($desc, 'RoomNightBill')) {
            return $desc;
        }

        // 15. Sang ngày / Night Audit (Hàng 67, 68, 69, 70)
        if (str_contains($desc, 'night-audit') || $this->target_type === 'SystemDateRoll' || $componentLabel === 'Sang ngày') {
            if (($action === 'Add' || $action === 'DayClose') && $desc === '') {
                $oldDate = $this->safeStr($this->old_values['system_date'] ?? null);
                $newDate = $this->safeStr($this->new_values['system_date'] ?? null);
                return "* Sang ngày thành công" . ($oldDate !== '' ? ": Từ ngày {$oldDate}" : '') . ($newDate !== '' ? " sang ngày {$newDate}" : '') . ($this->user_name ? " - User: {$this->user_name}" : '');
            }
        }

        // 16. Dịch vụ buồng phòng (BookingRoomService / Minibar / Giặt là / Thêm dịch vụ)
        if ($this->target_type === 'BookingRoomService' || str_contains($desc, 'BookingRoomService')) {
            $codeStr = $bookingCode ?: '';
            $pNum = $roomNumber ?: ($this->safeStr($this->new_values['room_number'] ?? $this->old_values['room_number'] ?? ''));
            $sName = $this->safeStr($this->new_values['service_name'] ?? (!str_starts_with($this->target_label ?? '', 'GAL') ? $this->target_label : null) ?? $this->new_values['description'] ?? 'Dịch vụ phòng');
            $qty = $this->new_values['quantity'] ?? $this->old_values['quantity'] ?? 1;
            $price = (float)($this->new_values['price'] ?? $this->new_values['unit_price'] ?? 0);
            $total = (float)($this->new_values['total_amount'] ?? $this->new_values['subtotal'] ?? ($price * $qty));
            $priceStr = number_format($price, 0, '.', ',');
            $totalStr = number_format($total, 0, '.', ',');
            $roomPart = $pNum ? "phòng {$pNum}" : "phòng";

            if ($action === 'Add' || $action === 'create' || $action === 'AddService' || str_contains($desc, 'Thêm mới') || empty($desc)) {
                return "* Thêm dịch vụ {$roomPart} (ĐK {$codeStr}): {$sName} (SL: {$qty}, Đơn giá: {$priceStr} đ, Thành tiền: {$totalStr} đ)";
            }
            if ($action === 'Cancel' || $action === 'delete' || $action === 'DeleteService' || str_contains($desc, 'Xóa')) {
                return "* Xóa dịch vụ {$roomPart} (ĐK {$codeStr}): {$sName}";
            }
            if ($action === 'Modify' || $action === 'update') {
                $diff = $this->formatDiffLines();
                if (!empty($diff)) {
                    return "* Sửa dịch vụ {$roomPart} (ĐK {$codeStr}): {$sName} :\n" . implode("\n", $diff);
                }
                return "* Sửa dịch vụ {$roomPart} (ĐK {$codeStr}): {$sName} (SL: {$qty}, Thành tiền: {$totalStr} đ)";
            }
        }

        // 17. Fallback: Nếu mô tả còn chung chung dạng "* Thêm mới..." hoặc "* Cập nhật..."
        if (($action === 'Add' || $action === 'create') && !empty($this->new_values)) {
            $details = $this->formatCreationDetails();
            if (!empty($details) && (str_starts_with($desc, '* Thêm mới') || empty($desc))) {
                $objName = $this->target_label ? "'{$this->target_label}'" : ($this->target_type ?: $componentLabel);
                return "* Thêm mới {$componentLabel} {$objName} :\n" . implode("\n", $details);
            }
        }

        if (($action === 'Modify' || $action === 'update') && !empty($this->old_values) && !empty($this->new_values)) {
            $diffLines = $this->formatDiffLines();
            if (!empty($diffLines) && (str_starts_with($desc, '* Cập nhật') || empty($desc) || (!str_contains($desc, '•') && !str_contains($desc, '->')))) {
                $objName = $this->target_label ? "'{$this->target_label}'" : ($this->target_type ?: $componentLabel);
                return "* Cập nhật {$componentLabel} {$objName} :\n" . implode("\n", $diffLines);
            }
        }

        return $desc;
    }

    /**
     * Format thông số chi tiết khi tạo mới để phục vụ tra cứu vận hành và tái hiện bug.
     */
    protected function formatCreationDetails(): array
    {
        $lines = [];
        $new = $this->new_values ?: [];
        $skip = ['updated_at', 'created_at', 'id', 'deleted_at', 'remember_token', 'password', 'branch_id', 'created_by_user_id', 'updated_by_user_id', 'created_by', 'updated_by'];

        foreach ($new as $key => $val) {
            if (in_array($key, $skip)) continue;
            if ($val === null || $val === '') continue;

            $label = self::$fieldLabels[$key] ?? Fmt::label($key);
            $formatted = $this->formatVal($key, $val);
            if ($formatted !== '' && $formatted !== '(trống)') {
                $lines[] = "• {$label}: {$formatted}";
            }
        }
        return $lines;
    }

    /**
     * Format các trường thay đổi của Booking theo chuẩn HKT (không dump JSON).
     */
    protected function formatHktBookingDiff(): array
    {
        $lines = [];
        $old = $this->old_values ?: [];
        $new = $this->new_values ?: [];
        $skip = ['updated_at', 'created_at', 'id', 'deleted_at', 'remember_token', 'password'];

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
            'booker_id' => 'Booker',
        ];

        foreach ($new as $key => $newVal) {
            if (in_array($key, $skip)) continue;
            $oldVal = $old[$key] ?? null;
            if ($oldVal != $newVal) {
                $label = $fieldMap[$key] ?? (self::$fieldLabels[$key] ?? Fmt::label($key));
                $oldStr = $this->formatVal($key, $oldVal);
                $newStr = $this->formatVal($key, $newVal);
                $lines[] = "- {$label} : {$oldStr} -> {$newStr}";
            }
        }
        return $lines;
    }

    /**
     * Format các trường thay đổi của Phòng thuê theo chuẩn HKT.
     */
    protected function formatHktRoomDiff(): array
    {
        $lines = [];
        $old = $this->old_values ?: [];
        $new = $this->new_values ?: [];

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

        foreach ($fieldMap as $key => $label) {
            if (array_key_exists($key, $new) && array_key_exists($key, $old)) {
                $oldV = $old[$key];
                $newV = $new[$key];
                if ($oldV != $newV) {
                    if ($key === 'breakfast') {
                        $oldV = $oldV ? 'Có' : 'Không';
                        $newV = $newV ? 'Có' : 'Không';
                    } elseif (str_contains($key, 'date') && $oldV && $newV) {
                        try {
                            $oldV = \Carbon\Carbon::parse($oldV)->format('d-m-Y');
                            $newV = \Carbon\Carbon::parse($newV)->format('d-m-Y');
                        } catch (\Throwable $e) {}
                    } elseif (in_array($key, ['price', 'rate'])) {
                        $oldV = number_format((float)$oldV, 0, '.', ',');
                        $newV = number_format((float)$newV, 0, '.', ',');
                    }
                    $lines[] = "- {$label} {$oldV} -> {$newV}";
                }
            }
        }
        return $lines;
    }

    /**
     * Format các trường diff chung (không dump JSON thô).
     */
    protected function formatDiffLines(array $extraSkip = []): array
    {
        $lines = [];
        $old = $this->old_values ?: [];
        $new = $this->new_values ?: [];
        $skip = array_merge(['updated_at', 'created_at', 'id', 'deleted_at', 'remember_token', 'password'], $extraSkip);

        foreach ($new as $key => $newVal) {
            if (in_array($key, $skip)) continue;

            $oldVal = $old[$key] ?? null;
            $label = self::$fieldLabels[$key] ?? Fmt::label($key);

            $oldStr = $this->formatVal($key, $oldVal);
            $newStr = $this->formatVal($key, $newVal);

            if ($oldStr !== $newStr) {
                $lines[] = "• {$label}: {$oldStr} ➜ {$newStr}";
            }
        }

        return $lines;
    }

    /**
     * Format giá trị hiển thị thân thiện (không dump JSON thô).
     */
    protected function formatVal(string $key, mixed $val): string
    {
        if ($val === null || $val === '') {
            return '(trống)';
        }

        // Dòng 325: id danh mục (công ty, thị trường, nguồn khách, loại phòng...) hiển thị bằng tên
        if (is_scalar($val) && !is_bool($val) && ($name = $this->lookup($key, $val)) !== null && !str_starts_with($name, '#')) {
            return $name;
        }

        if (is_bool($val)) {
            return $val ? 'Có' : 'Không';
        }

        if (str_contains($key, 'date') && is_string($val) && preg_match('/^\d{4}-\d{2}-\d{2}/', $val)) {
            try {
                return \Carbon\Carbon::parse($val)->format('d-m-Y');
            } catch (\Throwable $e) {}
        }

        if (in_array($key, ['total_amount', 'deposit_amount', 'price', 'rate', 'base_price', 'amount', 'extra_bed_rate']) && is_numeric($val)) {
            return number_format((float) $val, 0, '.', ',');
        }

        // Tránh dump JSON thô nếu là mảng/object: trích xuất trường name hoặc code
        if (is_array($val)) {
            if (isset($val['name'])) return (string) $val['name'];
            if (isset($val['code'])) return (string) $val['code'];
            if (isset($val['id'])) return '#' . $val['id'];
            return '(dữ liệu)';
        }

        return (string) $val;
    }

    /** Tên bản ghi danh mục theo id (null nếu trường không phải id danh mục hoặc không có giá trị). */
    protected function lookup(string $key, mixed $id): ?string
    {
        if ($id === null || $id === '' || is_array($id) || !Fmt::isLookupField($key)) {
            return null;
        }

        return Fmt::lookupName($key, $id);
    }

    protected function classLabel(mixed $val): string
    {
        if (is_numeric($val)) {
            return $this->lookup('room_class_id', $val) ?? '#' . $val;
        }

        return $this->safeStr($val, '(trống)');
    }

    /** Tên cấu hình theo id khi log cũ chỉ lưu mô tả làm nhãn. */
    protected function configName(): ?string
    {
        if ($this->target_type !== 'HotelConfig' || !is_numeric($this->target_id)) {
            return null;
        }
        try {
            return \App\Models\HotelConfig::where('id', $this->target_id)->value('name');
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Chuyển đổi an toàn giá trị bất kỳ sang chuỗi, tránh lỗi Array to string conversion.
     */
    protected function safeStr(mixed $val, string $default = ''): string
    {
        if ($val === null || $val === '') {
            return $default;
        }
        if (is_string($val) || is_numeric($val)) {
            return (string) $val;
        }
        if (is_bool($val)) {
            return $val ? 'Có' : 'Không';
        }
        if (is_array($val)) {
            if (isset($val['name']) && is_scalar($val['name'])) return (string) $val['name'];
            if (isset($val['code']) && is_scalar($val['code'])) return (string) $val['code'];
            if (isset($val['title']) && is_scalar($val['title'])) return (string) $val['title'];
            if (isset($val['phone']) && is_scalar($val['phone'])) return (string) $val['phone'];
            if (isset($val['id']) && is_scalar($val['id'])) return '#' . $val['id'];
            return $default;
        }
        if (is_object($val)) {
            if (isset($val->name) && is_scalar($val->name)) return (string) $val->name;
            if (isset($val->code) && is_scalar($val->code)) return (string) $val->code;
            return $default;
        }
        return $default;
    }
}
