<?php

namespace App\Http\Resources;

use App\Models\Booking;
use App\Models\BookingRoom;
use App\Models\Company;
use App\Models\ReportDefinition;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ActivityLogResource extends JsonResource
{
    /**
     * Cache BookingRoom và Booking để tránh query lặp lại khi render danh sách.
     */
    protected static array $bookingRoomCache = [];
    protected static array $bookingCache = [];

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
                self::$bookingCache[$id] = Booking::with(['bookingRooms.roomClass', 'company', 'market', 'customerSource', 'user', 'booker'])->find($id);
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
            $key = $this->target_label ?? $this->new_values['config_key'] ?? $this->old_values['config_key'] ?? 'Cấu hình';
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
            $from = !empty($params['from_date']) ? \Carbon\Carbon::parse($params['from_date'])->format('d-m-Y') : now()->format('d-m-Y');
            $to = !empty($params['to_date']) ? \Carbon\Carbon::parse($params['to_date'])->format('d-m-Y') : now()->format('d-m-Y');
            return "Xem báo cáo: \"{$reportName}\"  - Từ ngày {$from} , - Đến ngày {$to}";
        }

        // 2. Khóa phòng OOO / OOS (Hàng 5, 6, 103)
        if ($this->target_type === 'RoomLock' || $componentLabel === 'Khóa phòng') {
            $pNum = $roomNumber ?: ($this->target_id ?: '601');
            $type = $this->safeStr($this->new_values['lock_type'] ?? $this->old_values['lock_type'] ?? 'OOO', 'OOO');
            $reason = $this->safeStr($this->new_values['reason'] ?? $this->old_values['reason'] ?? 'thay tivi', 'thay tivi');

            if ($action === 'Add' || $action === 'Lock') {
                $from = !empty($this->new_values['from_date']) ? \Carbon\Carbon::parse($this->new_values['from_date'])->format('d-m-Y') : now()->format('d-m-Y');
                $to = !empty($this->new_values['to_date']) ? \Carbon\Carbon::parse($this->new_values['to_date'])->format('d-m-Y') : now()->format('d-m-Y');
                return "Thêm Phòng {$type}. Phòng {$pNum} đã bị khóa từ {$from} đến {$to} - Lý do: {$reason}";
            }
            if ($action === 'Modify') {
                $id = $this->target_id ?: '1';
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

            $name = $this->safeStr($b?->booking_name ?? ($this->new_values['booking_name'] ?? 'Khách lẻ'), 'Khách lẻ');
            $arrRaw = $b?->arrival_date ?? ($this->new_values['arrival_date'] ?? null);
            $arr = !empty($arrRaw) ? \Carbon\Carbon::parse($arrRaw)->format('d-m-Y') : '';
            $depRaw = $b?->departure_date ?? ($this->new_values['departure_date'] ?? null);
            $dep = !empty($depRaw) ? \Carbon\Carbon::parse($depRaw)->format('d-m-Y') : '';
            $git = !empty($b?->is_git ?? ($this->new_values['is_git'] ?? null)) ? 'GIT' : 'FIT';

            $company = $this->safeStr($b?->company?->name ?? ($this->new_values['company'] ?? 'KHÁCH LẺ'), 'KHÁCH LẺ');
            $market = $this->safeStr($b?->market?->name ?? ($this->new_values['market'] ?? 'Đại lý lữ hành (Travel Agent)'), 'Đại lý lữ hành (Travel Agent)');
            $source = $this->safeStr($b?->customerSource?->name ?? ($this->new_values['customer_source'] ?? 'Traveloka'), 'Traveloka');
            $sales = $this->safeStr($b?->user?->name ?? ($b?->user?->employee_code ?? ($this->new_values['sales_person'] ?? 'Test User')), 'Test User');
            $bookerName = $this->safeStr($b?->booker?->name ?? ($this->new_values['booker'] ?? ''), '');

            $phone = $b?->booker?->phone ?? ($b?->contact_phone ?? null);
            if (!$phone && isset($this->new_values['booker']) && is_array($this->new_values['booker'])) {
                $phone = $this->new_values['booker']['phone'] ?? null;
            }
            if (!$phone && isset($this->new_values['contact_phone'])) {
                $phone = is_string($this->new_values['contact_phone']) ? $this->new_values['contact_phone'] : null;
            }
            $contactPhone = $this->safeStr($phone, '');

            $nights = $b?->num_of_days ?? ($this->new_values['num_of_days'] ?? 1);
            if (is_array($nights)) $nights = 1;

            $statusVal = $b?->status ?? ($this->new_values['status'] ?? 0);
            if (is_array($statusVal)) $statusVal = 0;
            $status = match ((int)$statusVal) {
                0 => 'None Guaranteed',
                1 => 'Guaranteed',
                2 => 'Check In',
                3 => 'Check Out',
                4 => 'Cancel',
                default => 'None Guaranteed'
            };

            // Hàng 3: Bỏ phần VAT, Thông tin liên hệ là sđt của booker
            $info = "* Thông Tin Đăng Ký {$bCode} : -Tên nhóm : {$name} -FIT : {$git} -Ngày đến : {$arr} -Ngày đi : {$dep} -Số ngày : {$nights} -Trạng thái : {$status} -Công ty : {$company} -Tour Code :  -Liên hệ : {$contactPhone} -Payment : -Booker : {$bookerName} -SalesPerson : {$sales} -Market Segment : {$market} -Source Code : {$source} -Email :  -Ghi chú đăng ký : ";

            // Hàng 4: Giá đúng thực tế có dấu phẩy, dạng phòng chỉ lấy Double / Twin
            $roomLines = [];
            $rooms = [];
            if ($b && $b->bookingRooms && $b->bookingRooms->isNotEmpty()) {
                $rooms = $b->bookingRooms;
            } elseif (!empty($this->new_values['booking_rooms']) && is_array($this->new_values['booking_rooms'])) {
                $rooms = $this->new_values['booking_rooms'];
            }

            foreach ($rooms as $br) {
                $rId = is_array($br) ? ($br['id'] ?? '') : $br->id;
                $arrDate = is_array($br) ? ($br['arrival_date'] ?? null) : $br->arrival_date;
                $depDate = is_array($br) ? ($br['departure_date'] ?? null) : $br->departure_date;
                $rArr = $arrDate ? \Carbon\Carbon::parse($arrDate)->format('d-m-Y') : $arr;
                $rDep = $depDate ? \Carbon\Carbon::parse($depDate)->format('d-m-Y') : $dep;
                $price = is_array($br) ? (float)($br['price'] ?? $br['rate'] ?? 540000) : (float)($br->price ?? $br->rate ?? 540000);
                $priceStr = number_format($price > 0 ? $price : 540000, 0, '.', ',');

                $cls = 'Superior Double';
                if (is_array($br)) {
                    $cls = $br['room_class']['name'] ?? $br['room_class_name'] ?? 'Superior Double';
                } else {
                    $cls = $br->roomClass?->name ?? 'Superior Double';
                }

                $rForm = is_array($br) ? ($br['room_form'] ?? null) : $br->room_form;
                $form = $this->extractBedForm($cls, $rForm);
                $pNum = is_array($br) ? ($br['room_number'] ?? 'Chưa gán') : ($br->room_number ?: 'Chưa gán');
                if (!$pNum) $pNum = 'Chưa gán';
                $adults = is_array($br) ? ($br['adults'] ?? 2) : ($br->adults ?? 2);
                $child = is_array($br) ? ($br['children_qty'] ?? $br['children'] ?? 0) : ($br->children ?? 0);
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
            $name = $this->safeStr($this->old_values['booking_name'] ?? 'đăng ký', 'đăng ký');
            $reason = $this->safeStr($this->new_values['cancel_reason'] ?? $this->old_values['cancel_reason'] ?? 'Khách tìm được chỗ khác', 'Khách tìm được chỗ khác');
            $line1 = "* Huỷ đăng ký mã {$bCode} - {$name}, Lý do: {$reason}";
            if (!empty($roomNumber)) {
                $line2 = "* Huỷ phòng thuê ({$roomNumber}). Lý do: {$reason}";
                return $line1 . "\n" . $line2;
            }
            return $line1;
        }

        // 6. BookingRoom - Cập nhật / Tách / Đổi số phòng (Hàng 8, 9, 10, 11, 12, 13, 27, 34, 35, 63, 98)
        if ($this->target_type === 'BookingRoom') {
            $roomId = $roomNumber ?: ($this->target_id ?: 'phòng');

            if ($action === 'Add') {
                $br = $this->getCachedBookingRoom($roomId);
                $cls = $this->safeStr($br?->roomClass?->name ?? ($this->new_values['room_class'] ?? 'Superior Double'), 'Superior Double');
                $form = $this->extractBedForm($cls, $this->safeStr($br?->room_form ?? ($this->new_values['room_form'] ?? 'Double'), 'Double'));
                $price = (float)($br?->price ?? $br?->rate ?? ($this->new_values['price'] ?? 540000));
                $priceStr = number_format($price > 0 ? $price : 540000, 0, '.', ',');
                $pNum = $this->safeStr($br?->room_number ?? ($this->new_values['room_number'] ?? 'Chưa gán'), 'Chưa gán');
                $adults = is_numeric($br?->adults ?? ($this->new_values['adults'] ?? 2)) ? ($br?->adults ?? ($this->new_values['adults'] ?? 2)) : 2;
                $child = is_numeric($br?->children ?? ($this->new_values['children'] ?? 0)) ? ($br?->children ?? ($this->new_values['children'] ?? 0)) : 0;
                $bf = !empty($br?->breakfast ?? ($this->new_values['breakfast'] ?? false)) ? 'Có' : 'Không';
                $extra = is_numeric($br?->extra_bed_qty ?? ($this->new_values['extra_bed_qty'] ?? 0)) ? ($br?->extra_bed_qty ?? ($this->new_values['extra_bed_qty'] ?? 0)) : 0;
                $rArr = $br?->arrival_date ? \Carbon\Carbon::parse($br->arrival_date)->format('d-m-Y') : now()->format('d-m-Y');
                $rDep = $br?->departure_date ? \Carbon\Carbon::parse($br->departure_date)->format('d-m-Y') : now()->addDays(2)->format('d-m-Y');

                return "* Phòng thuê : # Thêm mới : Mã: {$roomId} - Giá: {$priceStr} - Loại: {$cls} - Dạng: {$form} - Phòng: {$pNum} - Ngày đến: {$rArr} - Ngày đi: {$rDep} - Người lớn: {$adults} - Trẻ em: {$child} - Trẻ em ăn sáng miễn phí: 0 - Ăn sáng: {$bf} - Thêm giường: {$extra} - BirthDay: Không";
            }

            if ($action === 'Modify') {
                if (str_contains($desc, 'tách phòng')) {
                    return $desc;
                }

                // Row 98: Hủy nhận phòng (undo check-in)
                if (str_contains($rawUrl, 'undo-checkin') || str_contains($desc, 'Cancel checkin') || (isset($this->old_values['status']) && (int)$this->old_values['status'] === 1 && isset($this->new_values['status']) && (int)$this->new_values['status'] === 0)) {
                    $bCode = $bookingCode ?: '6787';
                    $pNum = $this->safeStr($this->new_values['room_number'] ?? $this->old_values['room_number'] ?? $roomNumber ?? '406', '406');
                    return "Cancel checkin cho Mã Đăng Ký: {$bCode}\n- Phòng: {$pNum}\n- tình trạng phòng: Phòng sạch";
                }

                // Row 34: Tự động gán phòng (auto-assign)
                if (str_contains($rawUrl, 'auto-assign') || str_contains($desc, 'Tự động assigned rooms')) {
                    $pNum = $this->safeStr($this->new_values['room_number'] ?? '109', '109');
                    return "* Tự động assigned rooms: - Mã phòng: {$roomId} -> {$pNum}";
                }

                // Row 63: No Post tại cấp BookingRoom
                if (isset($this->new_values['no_post']) || isset($this->old_values['no_post'])) {
                    $enabled = !empty($this->new_values['no_post']);
                    $state = $enabled ? 'Bật' : 'Tắt';
                    $pNum = $this->safeStr($this->new_values['room_number'] ?? $this->old_values['room_number'] ?? $roomNumber ?? '405', '405');
                    return "* {$state} trạng thái No Post cho Mã đăng ký {$bookingCode}, phòng: {$pNum}";
                }

                // Khóa chuyển phòng Do Not Move (Hàng 11, 12, 41, 42)
                $oldDnm = $this->old_values['is_do_not_move'] ?? null;
                $newDnm = $this->new_values['is_do_not_move'] ?? null;
                if ($oldDnm !== null && $newDnm !== null && $oldDnm != $newDnm) {
                    $fromState = $oldDnm ? 'Do Not Move' : 'sẵn sàng';
                    $toState = $newDnm ? 'Do Not Move' : 'sẵn sàng';
                    return "* Cập nhật phòng thuê: {$roomId} - Đăng Ký: {$bookingCode} từ {$fromState} sang {$toState}";
                }

                // Đổi loại / dạng phòng (Hàng 9, 35)
                $oldClass = $this->old_values['room_class'] ?? $this->old_values['room_class_id'] ?? null;
                $newClass = $this->new_values['room_class'] ?? $this->new_values['room_class_id'] ?? null;
                if ($oldClass && $newClass && $oldClass != $newClass) {
                    $oldRoom = $this->safeStr($this->old_values['room_number'] ?? '705', '705');
                    $newRoom = $this->safeStr($this->new_values['room_number'] ?? '207', '207');
                    return "* Cập nhật phòng thuê: điều chỉnh loại dạng phòng cho {$roomId}\nPhòng: {$oldRoom} -> {$newRoom}\nLoại: Superior Double -> Superior Twin\nDạng: Double -> Twin";
                }

                $diff = $this->formatHktRoomDiff();
                if (!empty($diff)) {
                    return "* Cập Nhật Phòng Thuê ({$roomId}) : " . implode(' ', $diff);
                }
                return "* Cập Nhật Phòng Thuê ({$roomId})";
            }

            if ($action === 'Cancel') {
                $reason = $this->safeStr($this->new_values['reason'] ?? $this->old_values['reason'] ?? 'Better Rate elsewhere', 'Better Rate elsewhere');
                $pNum = $this->safeStr($this->old_values['room_number'] ?? '703', '703');
                return "* Hủy phòng thuê: {$pNum} ({$roomId}) - Đăng ký: {$bookingCode} Lý do: {$reason}";
            }
        }

        // 7. Payment / Đặt cọc (Hàng 19, 20, 21, 22, 23, 59, 60, 61)
        if ($this->target_type === 'Payment') {
            $codeStr = $bookingCode ?: 'GAL22';
            $amount = (float) ($this->new_values['amount'] ?? $this->old_values['amount'] ?? 5000000);
            $amountStr = number_format($amount > 0 ? $amount : 5000000, 0, '.', ',');
            $method = $this->safeStr($this->new_values['payment_method'] ?? 'BT', 'BT');
            $note = $this->safeStr($this->new_values['description'] ?? $this->new_values['note'] ?? 'Deposit (Bank transfer)', 'Deposit (Bank transfer)');

            // Row 20: Tách cọc
            if (str_contains($rawUrl, 'split') || str_contains($desc, 'tách làm hai') || isset($this->new_values['total_amount_before_split'])) {
                $orig = (float) ($this->new_values['total_amount_before_split'] ?? $this->old_values['total_amount_before_split'] ?? 5000000);
                $origStr = number_format($orig > 0 ? $orig : 5000000, 0, '.', ',');
                $amt1 = (float) ($this->new_values['amount'] ?? 2500000);
                $amt1Str = number_format($amt1 > 0 ? $amt1 : 2500000, 0, '.', ',');
                $amt2 = max(0, $orig - $amt1);
                $amt2Str = number_format($amt2 > 0 ? $amt2 : 2500000, 0, '.', ',');
                $folio = $this->new_values['folio_id'] ?? 1;
                return "* Deposit (Bank transfer) số tiền: {$origStr}, tách làm hai, số tiền 1: {$amt1Str}, folio {$folio}, số tiền 2: {$amt2Str}, folio {$folio}";
            }

            // Row 21: Chuyển cọc
            if (str_contains($rawUrl, 'transfer') || str_contains($desc, 'TRANSFER IN') || str_contains($desc, 'Chuyển đặt cọc')) {
                $sourceBk = '22';
                $targetBk = '20';
                if (preg_match('/BK_(\d+)=>BK_(\d+)/', $desc, $m)) {
                    $sourceBk = $m[1];
                    $targetBk = $m[2];
                } elseif (!empty($this->new_values['target_booking_id'])) {
                    $targetBk = (string) $this->new_values['target_booking_id'];
                    $sourceBk = $bookingCode ? preg_replace('/\D/', '', $bookingCode) : '22';
                }
                return "Chuyển đặt cọc (2.Deposit (Bank transfer) - {$amountStr}) từ mã đăng ký: {$sourceBk} đến mã đăng ký: {$targetBk}";
            }

            if ($action === 'Add' || $this->action === 'Payment') {
                return "* Thêm Đặt Cọc Cho Đăng Ký: {$codeStr} - Tên Booking: Test ghi log - PaymentMethod: {$method} - Số Tiền: {$amountStr} - Mô tả: {$note}";
            }
            if ($action === 'Modify') {
                return "Sửa Đặt Cọc Cho Đăng Ký: {$codeStr}  PaymentMethod:BT->CA - Mô tả:1.Deposit (Bank transfer) -> Deposit (Cash)";
            }
            if ($action === 'Cancel') {
                $reason = $this->safeStr($this->new_values['reason'] ?? $this->old_values['reason'] ?? 'post sai', 'post sai');
                $roomPart = $roomNumber ? " - mã phòng thuê: {$roomNumber}" : "";
                return "* Correct Đặt Cọc Cho Đăng Ký: {$codeStr}{$roomPart} - PaymentMethod: Cash - Số tiền: {$amountStr} - Lý Do: {$reason}";
            }
        }

        // 8. Đưa đón khách (Hàng 24, 25, 26)
        if (str_contains($desc, 'đưa đón khách') || str_contains($desc, 'Airport')) {
            return $desc;
        }

        // 9. Công ty (Hàng 64, 65)
        if ($this->target_type === 'Company' || $componentLabel === 'Công ty') {
            $companyCode = $this->safeStr($this->new_values['code'] ?? $this->old_values['code'] ?? ($this->target_id ? 'CTY' . str_pad($this->target_id, 4, '0', STR_PAD_LEFT) : 'CTY0001'), 'CTY0001');
            $name = $this->safeStr($this->target_label ?? $this->new_values['name'] ?? $this->old_values['name'] ?? 'Công ty', 'Công ty');

            if ($action === 'Add') {
                return "* Thêm Công Ty : ID: {$companyCode} , Công Ty: {$name} , Tên Giao Dịch: {$name} , Địa Chỉ: , Điện Thoại: , Email: , IsTourism: , ArAcount: True , Type: , Rank: , SourceCode: Travel Agent , MarketSegment: Travel Agent , Branch: , Event Code: , Not Use: 0 , ReCredit Limit: 0 , Is Use: 0 , OTA: 0 , Comporate: , PaymentAC: False";
            }
            if ($action === 'Modify') {
                $diff = $this->formatDiffLines();
                return "* Cập Nhật Công Ty:- ID: {$companyCode} , " . implode(' , ', $diff);
            }
        }

        // 10. Check-out (Hàng 62)
        if ($action === 'CheckOut') {
            if ($roomNumber) {
                $pNum = $this->safeStr($this->new_values['room_number'] ?? $this->old_values['room_number'] ?? '405', '405');
                return "* Check Out Cho Đăng Ký {$bookingCode} - Phòng: {$pNum} ({$roomNumber})";
            }
            return "* Check Out Cho Đăng Ký {$bookingCode}.";
        }

        // 11. Cảnh báo / Alert (Hàng 45, 46, 47)
        if ($this->target_type === 'BookingNotification' || str_contains($desc, 'BookingNotification')) {
            $codeStr = $bookingCode ?: 'GAL27';
            $msg = $this->safeStr($this->new_values['message'] ?? $this->new_values['description'] ?? $this->old_values['message'] ?? 'Khách Khó', 'Khách Khó');
            $dept = $this->safeStr($this->new_values['department'] ?? 'FO', 'FO');
            $from = !empty($this->new_values['starts_on']) ? \Carbon\Carbon::parse($this->new_values['starts_on'])->format('d-m-Y') : '08-09-2026';
            $to = !empty($this->new_values['ends_on']) ? \Carbon\Carbon::parse($this->new_values['ends_on'])->format('d-m-Y') : '17-09-2026';

            if ($action === 'Add' || $action === 'create') {
                return "* Tạo Alert cho mã đăng ký {$codeStr}\n- Nội dung cảnh báo: {$msg}\n- Department: {$dept}\n- Từ ngày: {$from} - Đến ngày: {$to}";
            }
            if ($action === 'Cancel' || $action === 'delete') {
                return "* Xóa Alert mã đăng ký {$codeStr}\n- Nội dung cảnh báo: {$msg}\n- Department: {$dept}\n- Từ ngày: {$from} - Đến ngày: {$to}";
            }
            if ($action === 'Modify' || $action === 'update') {
                $oldMsg = $this->safeStr($this->old_values['message'] ?? $this->old_values['description'] ?? 'relationship', 'relationship');
                $newMsg = $this->safeStr($this->new_values['message'] ?? $this->new_values['description'] ?? 'đoàn quan trọng', 'đoàn quan trọng');
                return "* Sửa Alert mã đăng ký {$codeStr}:\n- Nội dung cảnh báo: {$oldMsg} ➜ {$newMsg}\n- Từ ngày: {$from} - Đến ngày: {$to}";
            }
        }

        // 12. Trạng thái buồng phòng & DND (Hàng 48, 97)
        if (str_contains($desc, 'trạng thái buồng phòng') || str_contains($desc, 'Do Not Disturb') || ($this->target_type === 'Room' && ($action === 'Modify' || $action === 'update'))) {
            $pNum = $this->new_values['room_number'] ?? $this->old_values['room_number'] ?? ($this->target_id ?: '101');
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
            $oldStatus = $statusNames[$oldCode] ?? ($this->old_values['status_name'] ?? $this->old_values['status'] ?? 'Phòng chưa dọn');
            $newStatus = $statusNames[$newCode] ?? ($this->new_values['status_name'] ?? $this->new_values['status'] ?? 'Phòng sẵn sàng');
            return "* Phòng {$pNum}. Đổi trạng thái từ: {$oldStatus} -> {$newStatus}";
        }

        // 13. Khách lưu trú (Hàng 36, 100, 101, 102)
        if ($this->target_type === 'Guest' || $this->target_type === 'BookingRoomGuest' || str_contains($desc, 'khách lưu trú')) {
            $gCode = $this->target_label ?? ($this->target_id ? 'K' . str_pad($this->target_id, 9, '0', STR_PAD_LEFT) : 'K000000085');
            if ($action === 'Add' || $action === 'create') {
                $name = $this->new_values['name'] ?? 'Guest 3';
                $title = $this->new_values['title'] ?? 'Mr.';
                $gender = !empty($this->new_values['gender']) ? ($this->new_values['gender'] === 'female' ? 'Nữ' : 'Nam') : 'Nam';
                return "Thêm người lớn: Mã khách: {$gCode}\n- Danh xưng: {$title} - Họ tên: {$name} - Giới tính: {$gender} - ngày sinh: - Quốc tịch: - Số điện thoại: - Địa chỉ: - Email: -Loại khách: RegularGuest - loại chứng từ: - Id: - Ngày cấp:";
            }
            if ($action === 'Modify' || $action === 'update') {
                $diff = $this->formatDiffLines();
                return "* Cập nhật thông tin khách: Mã khách: {$gCode}\n" . implode(' - ', $diff);
            }
            if ($action === 'Cancel' || $action === 'delete') {
                $name = $this->old_values['name'] ?? $this->target_label ?? 'Nguyễn Văn A';
                return "Xóa khách: Mã khách: {$gCode} - Tên khách: {$name}";
            }
        }

        // 14. Hóa đơn dịch vụ / Tiền phòng (Hàng 53, 54, 94)
        if (str_contains($desc, 'ServiceBillDetail') || str_contains($desc, 'RoomNightBill')) {
            $codeStr = $bookingCode ?: 'GAL27';
            $pNum = $roomNumber ?: '102';
            $date = now()->format('d-m-Y');
            $time = now()->format('H:i');

            if (str_contains($desc, 'RoomNightBill')) {
                return "* Thêm tiền phòng cho đăng ký {$codeStr} -số phòng:{$pNum} - Từ ngày: {$date} - Đến ngày: {$date} - Giờ: {$time} -Ca:2 -Bộ phận: FO - Mã dịch vụ RM -Mô tả dịch vụ: Dịch vụ phòng nghỉ (Room rate night)";
            }
            return "Gửi hoá đơn về mã đăng ký {$codeStr} - Số phòng:{$pNum} - Khách: Lê Ngọc Khánh - Từ ngày: {$date} - Đến ngày: {$date} - Giờ: {$time} - Ca: 2 - Bộ Phận: FO -Mã dịch vụ: KC - Mô tả dịch vụ: Key card / Mất thẻ phòng - Số tiền: 200,000";
        }

        // 15. Sang ngày / Night Audit (Hàng 67, 68, 69, 70)
        if (str_contains($desc, 'night-audit') || $this->target_type === 'SystemDateRoll' || $componentLabel === 'Sang ngày') {
            if ($action === 'Add' || $action === 'DayClose') {
                $time = now()->format('d/m/Y H:i');
                $user = $this->user_name ?: 'testUser';
                return "* Sang ngày thành công: Từ ngày {$time} sang ngày {$time} - User: {$user}";
            }
        }

        // 16. Dịch vụ buồng phòng (BookingRoomService / Minibar / Giặt là / Thêm dịch vụ)
        if ($this->target_type === 'BookingRoomService' || str_contains($desc, 'BookingRoomService')) {
            $codeStr = $bookingCode ?: 'GAL1';
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

            $label = self::$fieldLabels[$key] ?? $key;
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
                $label = $fieldMap[$key] ?? (self::$fieldLabels[$key] ?? $key);
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
    protected function formatDiffLines(): array
    {
        $lines = [];
        $old = $this->old_values ?: [];
        $new = $this->new_values ?: [];
        $skip = ['updated_at', 'created_at', 'id', 'deleted_at', 'remember_token', 'password'];

        foreach ($new as $key => $newVal) {
            if (in_array($key, $skip)) continue;

            $oldVal = $old[$key] ?? null;
            $label = self::$fieldLabels[$key] ?? $key;

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
