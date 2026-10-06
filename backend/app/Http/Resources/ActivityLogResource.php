<?php

namespace App\Http\Resources;

use App\Models\Booking;
use App\Models\BookingRoom;
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
     * Component translation mapping.
     */
    protected static array $componentMap = [
        'bookings' => 'Quản lý Đặt phòng',
        'CreateRegistrationPage' => 'Tạo Đăng ký',
        'booking-rooms' => 'Phòng Đặt',
        'RoomMapPage' => 'Sơ đồ phòng',
        'LockRoomPage' => 'Khóa phòng',
        'rooms' => 'Sơ đồ phòng',
        'room-locks' => 'Khóa phòng',
        'payments' => 'Thu ngân & Thanh toán',
        'CheckoutPage' => 'Trả phòng & Thanh toán',
        'ServicesModal' => 'Dịch vụ phòng',
        'GuestInfoModal' => 'Khách lưu trú',
        'DayClosePage' => 'Chạy sang ngày',
        'InventoryTab' => 'Quản lý Kho',
        'hotel-configs' => 'Cấu hình Khách sạn',
        'HotelDefinition' => 'Cài đặt Khách sạn',
        'hotel-settings' => 'Cài đặt Khách sạn',
        'hotel-services' => 'Dịch vụ Khách sạn',
        'shifts' => 'Ca làm việc',
        'EmployeeTab' => 'Quản lý Nhân viên',
        'BranchManageTab' => 'Quản lý Chi nhánh',
        'CompanyInfoTab' => 'Thông tin Doanh nghiệp',
        'RoomDefinition' => 'Định nghĩa Phòng',
        'RateSetup' => 'Cài đặt Giá phòng',
        'DesignTemplateTab' => 'Mẫu in & Chứng từ',
        'SystemDefinition' => 'Danh mục Hệ thống',
        'CompanySettingsPage' => 'Công ty đối tác',
        'LoginPage' => 'Đăng nhập',
        'hk' => 'Buồng phòng',
        'housekeeping' => 'Buồng phòng',
        'users' => 'Quản lý Nhân viên',
        'guests' => 'Khách lưu trú',
    ];

    /**
     * Module translation mapping.
     */
    protected static array $moduleMap = [
        'reservation' => 'Đặt phòng',
        'frontdesk' => 'Lễ tân',
        'housekeeping' => 'Buồng phòng',
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
        'num_of_days' => 'Số đêm',
        'booking_name' => 'Tên khách/đoàn',
        'contact_name' => 'Người liên hệ',
        'contact_phone' => 'SĐT liên hệ',
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
        'extra_bed_qty' => 'Số giường phụ',
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
        'code' => 'Mã',
    ];

    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        $action = $this->resolveAction();
        $bookingCode = $this->resolveBookingCode();
        $roomNumber = $this->resolveRoomNumber();
        $description = $this->resolveDescription($bookingCode, $roomNumber);
        $componentLabel = $this->resolveComponentLabel();
        $moduleLabel = $this->resolveModuleLabel();

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
                self::$bookingCache[$id] = Booking::with(['bookingRooms.roomClass', 'company', 'market', 'customerSource', 'user'])->find($id);
            } catch (\Throwable $e) {
                self::$bookingCache[$id] = null;
            }
        }
        return self::$bookingCache[$id];
    }

    /**
     * Trích xuất mã đăng ký chính xác (e.g. GAL7, GAL14).
     */
    protected function resolveBookingCode(): ?string
    {
        // 1. Kiểm tra target_label nếu có dạng GAL...
        if (!empty($this->target_label) && preg_match('/^(GAL\d+)/i', $this->target_label, $m)) {
            return strtoupper($m[1]);
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

        // 3. Nếu là BookingRoom, Payment, BookingRoomService
        if (in_array($this->target_type, ['BookingRoom', 'Payment', 'BookingRoomService', 'BookingRoomGuest', 'Guest'])) {
            $bookingId = $this->new_values['booking_id'] 
                ?? $this->old_values['booking_id'] 
                ?? null;
            if ($bookingId) {
                return 'GAL' . $bookingId;
            }
        }

        // 4. Tìm trong description
        if (!empty($this->description) && preg_match('/(?:Đ[Kk]|đăng ký|GAL)\s*(GAL\d+|\d+)/iu', $this->description, $m)) {
            $val = $m[1];
            return str_starts_with(strtoupper($val), 'GAL') ? strtoupper($val) : ('GAL' . $val);
        }

        // 5. Tìm trong request_url: /api/bookings/(\d+)
        if (!empty($this->request_url) && preg_match('#/api/bookings/(\d+)#', $this->request_url, $m)) {
            return 'GAL' . $m[1];
        }

        // 6. Tìm qua booking-room id (G\d+) trong request_url hoặc target_id
        if (!empty($this->request_url) && preg_match('#/(?:rooms|booking-rooms)/(G\d+)#', $this->request_url, $m)) {
            $br = $this->getCachedBookingRoom($m[1]);
            if ($br?->booking_id) {
                return 'GAL' . $br->booking_id;
            }
        }
        if (!empty($this->target_id) && preg_match('/^(G\d+)$/', (string) $this->target_id, $m)) {
            $br = $this->getCachedBookingRoom($m[1]);
            if ($br?->booking_id) {
                return 'GAL' . $br->booking_id;
            }
        }

        return null;
    }

    /**
     * Trích xuất mã phòng / mã phòng thuê theo chuẩn HKT.
     */
    protected function resolveRoomNumber(): ?string
    {
        // 1. Tuyệt đối không lấy số phòng cho các đối tượng cấu hình, nhân viên, công ty
        $nonRoomTypes = ['HotelConfig', 'HotelSetting', 'User', 'Company', 'Branch', 'SystemDefinition', 'SystemDateRoll', 'Inventory', 'Product', 'Role'];
        if (in_array($this->target_type, $nonRoomTypes)) {
            return null;
        }

        $nonRoomComponents = ['HotelDefinition', 'EmployeeTab', 'BranchManageTab', 'CompanyInfoTab', 'hotel-configs', 'hotel-settings', 'users'];
        if (in_array($this->component, $nonRoomComponents)) {
            return null;
        }

        // 2. Nếu là Booking thuần (Thông Tin Đăng Ký) -> để trống null
        if ($this->target_type === 'Booking') {
            if (str_starts_with(trim($this->description ?? ''), '* Thông Tin Đăng Ký')) {
                return null;
            }
            if (!empty($this->new_values['rooms']) && is_array($this->new_values['rooms'])) {
                return implode(',', $this->new_values['rooms']);
            }
            return null;
        }

        // 3. Nếu là BookingRoom
        if ($this->target_type === 'BookingRoom') {
            // Ưu tiên target_id nếu dạng G... (ví dụ G0013083, G0021633,G0021634)
            if (!empty($this->target_id) && preg_match('/^G\d+/i', (string) $this->target_id)) {
                return (string) $this->target_id;
            }
            if (!empty($this->new_values['id']) && preg_match('/^G\d+/i', (string) $this->new_values['id'])) {
                return (string) $this->new_values['id'];
            }
            if (!empty($this->old_values['id']) && preg_match('/^G\d+/i', (string) $this->old_values['id'])) {
                return (string) $this->old_values['id'];
            }
            $r = $this->new_values['room_number'] ?? $this->old_values['room_number'] ?? null;
            if ($r) return (string) $r;
            if (!empty($this->target_id)) return (string) $this->target_id;
        }

        // 4. Nếu là Room hoặc RoomLock
        if (in_array($this->target_type, ['Room', 'RoomLock'])) {
            return $this->new_values['room_number'] 
                ?? $this->old_values['room_number'] 
                ?? (!empty($this->target_id) && (!is_numeric($this->target_id) || strlen((string)$this->target_id) >= 3) ? (string)$this->target_id : null)
                ?? $this->target_label;
        }

        // 5. BookingRoomService
        if ($this->target_type === 'BookingRoomService') {
            if (!empty($this->description) && preg_match('/(?:phòng|P\.)\s*([A-Za-z0-9_-]{2,6})/iu', $this->description, $m)) {
                return $m[1];
            }
            if (!empty($this->request_url) && preg_match('#/(?:rooms|booking-rooms)/(G\d+)#', $this->request_url, $m)) {
                return $m[1];
            }
            return null;
        }

        // 6. Tìm mã G trong description
        if (!empty($this->description) && preg_match('/Mã:\s*(G\d+)/i', $this->description, $m)) {
            return $m[1];
        }
        if (!empty($this->description) && preg_match('/\(G\d+\)/i', $this->description, $m)) {
            return trim($m[0], '()');
        }

        return null;
    }

    /**
     * Tự động hoàn thiện mô tả chi tiết theo đúng format HKT.
     */
    protected function resolveDescription(?string $bookingCode, ?string $roomNumber): string
    {
        $desc = $this->description ?: '';

        // Nếu mô tả đã có định dạng HKT chuẩn, giữ nguyên
        if (
            str_starts_with(trim($desc), '* Thông Tin Đăng Ký') ||
            str_starts_with(trim($desc), '* Phòng thuê :') ||
            str_starts_with(trim($desc), '* Cập Nhật Phòng Thuê')
        ) {
            return $desc;
        }

        $action = $this->resolveAction();

        // 1. NGHIỆP VỤ BOOKING
        if ($this->target_type === 'Booking') {
            $bookingId = $this->target_id ?: ($bookingCode ? preg_replace('/\D/', '', $bookingCode) : null);
            $b = $this->getCachedBooking($bookingId);

            if ($action === 'Add') {
                if ($b) {
                    $arr = $b->arrival_date ? \Carbon\Carbon::parse($b->arrival_date)->format('d-m-Y') : '';
                    $dep = $b->departure_date ? \Carbon\Carbon::parse($b->departure_date)->format('d-m-Y') : '';
                    $git = $b->is_git ? 'GIT' : 'FIT';
                    $vat = $b->has_vat ? 'Có' : '';
                    $company = $b->company?->name ?? 'KHÁCH LẺ';
                    $market = $b->market?->name ?? 'Free Individual Traveler';
                    $source = $b->customerSource?->name ?? 'Free Individual Traveler';
                    $sales = $b->user?->employee_code ?? $b->user?->name ?? 'admin';
                    $status = match ((int)$b->status) {
                        0 => 'None Guaranteed',
                        1 => 'Guaranteed',
                        2 => 'Check In',
                        3 => 'Check Out',
                        4 => 'Cancel',
                        default => (string)$b->status
                    };

                    $info = "* Thông Tin Đăng Ký {$b->id} : -Tên nhóm : {$b->booking_name} -FIT : {$git} -VAT : {$vat} -Ngày đến : {$arr} -Ngày đi : {$dep} -Số ngày : {$b->num_of_days} -Trạng thái : {$status} -Công ty : {$company} -Tour Code : {$b->event_code} -Liên hệ : {$b->contact_name} -Payment : -Booker : {$b->booker?->name} -SalesPerson : {$sales} -Market Segment : {$market} -Source Code : {$source} -Email : {$b->contact_email} -Ghi chú đăng ký : {$b->note}";

                    $roomLines = [];
                    foreach ($b->bookingRooms as $br) {
                        $rArr = $br->arrival_date ? \Carbon\Carbon::parse($br->arrival_date)->format('d-m-Y') : $arr;
                        $rDep = $br->departure_date ? \Carbon\Carbon::parse($br->departure_date)->format('d-m-Y') : $dep;
                        $price = (float)($br->price ?? 0);
                        $cls = $br->roomClass?->name ?? 'Superior';
                        $form = $cls;
                        $pNum = $br->room_number ?: 'Chưa gán';
                        $adults = $br->adults ?? 1;
                        $child = is_numeric($br->children) ? $br->children : 0;
                        $bf = $br->breakfast ? 'Có' : 'Không';
                        $extra = $br->extra_bed_qty ?? 0;

                        $roomLines[] = "* Phòng thuê : # Thêm mới : Mã: {$br->id} - Giá: {$price} - Loại: {$cls} - Dạng: {$form} - Phòng:{$pNum} - Ngày đến: {$rArr} - Ngày đi: {$rDep} - Người lớn: {$adults} - Trẻ em: {$child} - Trẻ em ăn sáng miễn phí: 0 - Ăn sáng: {$bf} - Thêm giường: {$extra} - BirthDay: Không";
                    }

                    if (!empty($roomLines)) {
                        return $info . "\n" . implode("\n", $roomLines);
                    }
                    return $info;
                }

                // Fallback từ new_values
                $id = $bookingId ?: '0';
                $name = $this->new_values['booking_name'] ?? 'Khách lẻ';
                $arr = !empty($this->new_values['arrival_date']) ? \Carbon\Carbon::parse($this->new_values['arrival_date'])->format('d-m-Y') : '';
                $dep = !empty($this->new_values['departure_date']) ? \Carbon\Carbon::parse($this->new_values['departure_date'])->format('d-m-Y') : '';
                $git = !empty($this->new_values['is_git']) ? 'GIT' : 'FIT';
                $vat = !empty($this->new_values['has_vat']) ? 'Có' : '';
                $nights = $this->new_values['num_of_days'] ?? 1;
                $status = $this->new_values['status'] ?? 'Guaranteed';
                $company = $this->new_values['company'] ?? 'KHÁCH LẺ';
                return "* Thông Tin Đăng Ký {$id} : -Tên nhóm : {$name} -FIT : {$git} -VAT : {$vat} -Ngày đến : {$arr} -Ngày đi : {$dep} -Số ngày : {$nights} -Trạng thái : {$status} -Công ty : {$company} -Tour Code : -Liên hệ : -Payment : -Booker : -SalesPerson : admin -Market Segment : Free Individual Traveler -Source Code : Free Individual Traveler -Email : -Ghi chú đăng ký : ";
            }

            if ($action === 'Modify') {
                $id = $bookingId ?: '0';
                $diff = $this->formatHktBookingDiff();
                if (!empty($diff)) {
                    return "* Cập Nhật Thông Tin Đăng Ký {$id} : " . implode(' ', $diff);
                }
                return "* Cập Nhật Thông Tin Đăng Ký {$id}";
            }

            if ($action === 'Cancel') {
                $id = $bookingId ?: '0';
                $name = $this->old_values['booking_name'] ?? '';
                $nameStr = $name ? " ({$name})" : "";
                return "* Hủy Đăng Ký {$id}{$nameStr}";
            }
        }

        // 2. NGHIỆP VỤ BOOKING ROOM
        if ($this->target_type === 'BookingRoom') {
            $roomId = $this->target_id ?: ($this->new_values['id'] ?? $this->old_values['id'] ?? 'phòng');

            if ($action === 'Modify') {
                $diff = $this->formatHktRoomDiff();
                if (!empty($diff)) {
                    return "* Cập Nhật Phòng Thuê ({$roomId}) : " . implode(' ', $diff);
                }
                return "* Cập Nhật Phòng Thuê ({$roomId})";
            }

            if ($action === 'Add') {
                $br = $this->getCachedBookingRoom($roomId);
                if ($br) {
                    $rArr = $br->arrival_date ? \Carbon\Carbon::parse($br->arrival_date)->format('d-m-Y') : '';
                    $rDep = $br->departure_date ? \Carbon\Carbon::parse($br->departure_date)->format('d-m-Y') : '';
                    $price = (float)($br->price ?? 0);
                    $cls = $br->roomClass?->name ?? 'Superior';
                    $form = $cls;
                    $pNum = $br->room_number ?: 'Chưa gán';
                    $adults = $br->adults ?? 1;
                    $child = is_numeric($br->children) ? $br->children : 0;
                    $bf = $br->breakfast ? 'Có' : 'Không';
                    $extra = $br->extra_bed_qty ?? 0;
                    return "* Phòng thuê : # Thêm mới : Mã: {$br->id} - Giá: {$price} - Loại: {$cls} - Dạng: {$form} - Phòng:{$pNum} - Ngày đến: {$rArr} - Ngày đi: {$rDep} - Người lớn: {$adults} - Trẻ em: {$child} - Trẻ em ăn sáng miễn phí: 0 - Ăn sáng: {$bf} - Thêm giường: {$extra} - BirthDay: Không";
                }
                $price = (float)($this->new_values['price'] ?? 0);
                $cls = $this->new_values['room_class'] ?? 'Superior';
                $pNum = $this->new_values['room_number'] ?? 'Chưa gán';
                $adults = $this->new_values['adults'] ?? 1;
                $child = $this->new_values['children'] ?? 0;
                $bf = !empty($this->new_values['breakfast']) ? 'Có' : 'Không';
                $extra = $this->new_values['extra_bed_qty'] ?? 0;
                return "* Phòng thuê : # Thêm mới : Mã: {$roomId} - Giá: {$price} - Loại: {$cls} - Dạng: {$cls} - Phòng:{$pNum} - Người lớn: {$adults} - Trẻ em: {$child} - Trẻ em ăn sáng miễn phí: 0 - Ăn sáng: {$bf} - Thêm giường: {$extra} - BirthDay: Không";
            }

            if ($action === 'Cancel') {
                return "* Xóa/Hủy phòng thuê ({$roomId})";
            }
        }

        // 2.5. NGHIỆP VỤ BOOKING ROOM SERVICE (Dịch vụ phòng / tiền phòng)
        if ($this->target_type === 'BookingRoomService') {
            $roomStr = $roomNumber ? " (Phòng {$roomNumber})" : "";
            $codeStr = $bookingCode ? " (ĐK {$bookingCode})" : "";

            if ($action === 'Modify') {
                $diff = $this->formatDiffLines();
                if (!empty($diff)) {
                    return "* Cập nhật dịch vụ / tiền{$roomStr}{$codeStr} :\n" . implode("\n", $diff);
                }
                return "* Cập nhật dịch vụ{$roomStr}{$codeStr}";
            }

            if ($action === 'Add') {
                $rate = (float) ($this->new_values['rate'] ?? $this->new_values['total_amount'] ?? 0);
                $rStr = $rate > 0 ? " (Số tiền: " . number_format($rate, 0, ',', '.') . " đ)" : "";
                return "* Thêm mới dịch vụ{$roomStr}{$codeStr}{$rStr}";
            }

            if ($action === 'Cancel') {
                return "* Xóa dịch vụ{$roomStr}{$codeStr}";
            }
        }

        // 3. NGHIỆP VỤ PAYMENT
        if ($this->target_type === 'Payment') {
            $codeStr = $bookingCode ? " Đăng ký {$bookingCode}" : "";
            $amount = (float) ($this->new_values['amount'] ?? $this->old_values['amount'] ?? 0);
            $amountStr = number_format($amount, 0, ',', '.') . ' đ';

            if ($action === 'Add' || $this->action === 'Payment') {
                $note = $this->new_values['description'] ?? $this->new_values['note'] ?? '';
                $noteStr = $note ? " - Diễn giải: {$note}" : "";
                return "* Thanh toán cho{$codeStr}: Số tiền: {$amountStr}{$noteStr}";
            }

            if ($action === 'Cancel') {
                return "* Xóa khoản thanh toán của{$codeStr}: Số tiền: {$amountStr}";
            }
        }

        // 4. CẤU HÌNH KHÁCH SẠN
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

        // 5. Buồng phòng
        if ($this->component === 'hk' || $this->module === 'housekeeping') {
            $room = $roomNumber ?: 'phòng';
            if ($action === 'Add') {
                return "* Thêm mới dữ liệu buồng phòng ({$room})";
            }
            if ($action === 'Modify') {
                return "* Cập nhật trạng thái buồng phòng ({$room})";
            }
        }

        // 6. Khách lưu trú (Guest)
        if ($this->target_type === 'Guest') {
            $guestName = $this->target_label ?? $this->new_values['name'] ?? $this->old_values['name'] ?? 'Khách';
            $roomStr = $roomNumber ? " (Phòng {$roomNumber})" : "";
            $codeStr = $bookingCode ? " (ĐK {$bookingCode})" : "";

            if (str_contains($this->request_url ?? '', 'check-in')) {
                return "* Nhận phòng (Check-in) cho khách '{$guestName}'{$roomStr}{$codeStr}";
            }
            if (str_contains($this->request_url ?? '', 'checkout')) {
                return "* Trả phòng (Check-out) cho khách '{$guestName}'{$roomStr}{$codeStr}";
            }

            if ($action === 'Add') {
                return "* Thêm mới khách lưu trú '{$guestName}'{$roomStr}{$codeStr}";
            }
            if ($action === 'Modify') {
                $diff = $this->formatDiffLines();
                if (!empty($diff)) {
                    return "* Cập nhật thông tin khách '{$guestName}'{$roomStr}{$codeStr} :\n" . implode("\n", $diff);
                }
                return "* Cập nhật thông tin khách '{$guestName}'{$roomStr}{$codeStr}";
            }
            if ($action === 'Cancel') {
                return "* Xóa thông tin khách '{$guestName}'{$roomStr}{$codeStr}";
            }
        }

        return $desc;
    }

    /**
     * Dịch tên component sang tiếng Việt thân thiện / chuẩn hóa Booking.
     */
    protected function resolveComponentLabel(): string
    {
        if (
            in_array($this->target_type, ['Booking', 'BookingRoom', 'BookingRoomService', 'BookingRoomGuest']) ||
            $this->module === 'reservation' ||
            in_array($this->component, ['Booking', 'bookings', 'CreateRegistrationPage', 'booking-rooms', 'RoomMapPage', 'RoomPlanPage', 'QuickAssignModal'])
        ) {
            return 'Booking';
        }
        return self::$componentMap[$this->component] ?? $this->component ?? 'Hệ thống';
    }

    /**
     * Dịch tên module sang tiếng Việt thân thiện.
     */
    protected function resolveModuleLabel(): string
    {
        return self::$moduleMap[$this->module] ?? $this->module ?? 'Khác';
    }

    /**
     * Format các trường thay đổi của Booking theo chuẩn HKT.
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
            'departure_date' => 'Ngày đi',
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
                    }
                    $lines[] = "- {$label} {$oldV} -> {$newV}";
                }
            }
        }
        return $lines;
    }

    /**
     * Format các trường diff chung.
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
     * Format giá trị hiển thị.
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
            return number_format((float) $val, 0, ',', '.') . ' đ';
        }

        if (is_array($val)) {
            return json_encode($val, JSON_UNESCAPED_UNICODE);
        }

        return (string) $val;
    }
}
