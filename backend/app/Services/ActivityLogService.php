<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

class ActivityLogService
{
    /**
     * Ghi log tổng quát.
     */
    public static function log(array $data): ActivityLog
    {
        try {
            if (request()) {
                request()->attributes->set('_activity_logged', true);
            }
            return ActivityLog::create(array_merge([
                'created_at' => now(),
            ], $data));
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('ActivityLog failed: ' . $e->getMessage());
            return new ActivityLog();
        }
    }

    /**
     * Ghi log đăng nhập thành công hoặc thất bại.
     */
    public static function logLogin(Request $request, ?User $user, bool $success): ActivityLog
    {
        return self::log([
            'user_id' => $user?->id,
            'user_name' => $user?->name ?? $request->input('username', 'unknown'),
            'employee_code' => $user?->employee_code,
            'action' => $success ? 'login' : 'login_failed',
            'module' => 'auth',
            'component' => 'LoginPage',
            'description' => $success
                ? "Đăng nhập thành công"
                : "Đăng nhập thất bại (tài khoản: {$request->input('username')})",
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'request_method' => 'POST',
            'request_url' => $request->fullUrl(),
            'response_status' => $success ? 200 : 422,
        ]);
    }

    /**
     * Ghi log đăng xuất.
     */
    public static function logLogout(Request $request, User $user): ActivityLog
    {
        return self::log([
            'user_id' => $user->id,
            'user_name' => $user->name,
            'employee_code' => $user->employee_code,
            'action' => 'logout',
            'module' => 'auth',
            'component' => 'LoginPage',
            'description' => "Đăng xuất khỏi hệ thống",
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'request_method' => 'POST',
            'request_url' => $request->fullUrl(),
            'response_status' => 200,
        ]);
    }

    /**
     * Ghi log tạo mới.
     */
    public static function logCreate(
        Request $request,
        ?Model $model,
        string $module,
        string $component,
        string $description,
        ?string $targetLabel = null
    ): ActivityLog {
        $user = $request->user();
        return self::log([
            'user_id' => $user?->id,
            'user_name' => $user?->name ?? '',
            'employee_code' => $user?->employee_code,
            'action' => 'create',
            'module' => $module,
            'component' => $component,
            'description' => $description,
            'target_type' => $model ? class_basename($model) : null,
            'target_id' => $model ? $model->id : null,
            'target_label' => $targetLabel,
            'new_values' => $model ? $model->toArray() : [],
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'request_method' => $request->method(),
            'request_url' => $request->fullUrl(),
            'response_status' => 201,
        ]);
    }

    /**
     * Ghi log cập nhật.
     */
    public static function logUpdate(
        Request $request,
        ?Model $model,
        array $oldValues,
        string $module,
        string $component,
        string $description,
        ?string $targetLabel = null
    ): ActivityLog {
        $user = $request->user();
        $newValues = $model ? ($model->fresh()?->toArray() ?? $model->toArray()) : [];
        $diff = self::diffValues($oldValues, $newValues);

        return self::log([
            'user_id' => $user?->id,
            'user_name' => $user?->name ?? '',
            'employee_code' => $user?->employee_code,
            'action' => 'update',
            'module' => $module,
            'component' => $component,
            'description' => $description,
            'target_type' => $model ? class_basename($model) : null,
            'target_id' => $model ? $model->id : null,
            'target_label' => $targetLabel,
            'old_values' => $diff['old'],
            'new_values' => $diff['new'],
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'request_method' => $request->method(),
            'request_url' => $request->fullUrl(),
            'response_status' => 200,
        ]);
    }

    /**
     * Ghi log xóa.
     */
    public static function logDelete(
        Request $request,
        ?Model $model,
        string $module,
        string $component,
        string $description,
        ?string $targetLabel = null
    ): ActivityLog {
        $user = $request->user();
        return self::log([
            'user_id' => $user?->id,
            'user_name' => $user?->name ?? '',
            'employee_code' => $user?->employee_code,
            'action' => 'delete',
            'module' => $module,
            'component' => $component,
            'description' => $description,
            'target_type' => $model ? class_basename($model) : null,
            'target_id' => $model ? $model->id : null,
            'target_label' => $targetLabel,
            'old_values' => $model ? $model->toArray() : [],
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'request_method' => $request->method(),
            'request_url' => $request->fullUrl(),
            'response_status' => 200,
        ]);
    }

    /**
     * Helper ghi log nghiệp vụ chuyên sâu với fallback user linh hoạt
     */
    public static function logBusiness(array $data, ?Request $request = null): ActivityLog
    {
        $user = $request ? $request->user() : auth()->user();
        $ip = $request ? $request->ip() : request()->ip();
        $userAgent = $request ? $request->userAgent() : request()->userAgent();
        $url = $request ? $request->fullUrl() : request()->fullUrl();
        $method = $request ? $request->method() : request()->method();

        return self::log(array_merge([
            'user_id' => $user?->id,
            'user_name' => $user?->name ?? $user?->username ?? 'admin',
            'employee_code' => $user?->employee_code,
            'ip_address' => $ip ?? '127.0.0.1',
            'user_agent' => $userAgent,
            'request_method' => $method ?? 'POST',
            'request_url' => $url,
            'response_status' => 200,
        ], $data));
    }

    /**
     * Ghi log khi tạo mới Đăng Ký / Booking (Chuẩn format HKT)
     */
    public static function logBookingCreated($booking, ?Request $request = null): ActivityLog
    {
        $bookingCode = $booking->booking_code ?? $booking->code ?? ('GAL' . $booking->id);
        $booking->loadMissing(['company', 'market', 'customerSource', 'booker', 'registrationStatus']);

        // Dòng 325: chỉ ghi dữ liệu thật của đăng ký, trường trống thì bỏ qua (không điền giá trị mẫu)
        $infoDesc = "* Thông Tin Đăng Ký {$bookingCode} : " . self::joinParts([
            'Tên nhóm' => $booking->booking_name,
            'FIT/GIT' => $booking->is_git ? 'GIT' : 'FIT',
            'Ngày đến' => \App\Support\ActivityLogFormatter::date($booking->arrival_date),
            'Ngày đi' => \App\Support\ActivityLogFormatter::date($booking->departure_date),
            'Số đêm' => $booking->num_of_days,
            'Trạng thái ĐK' => $booking->registrationStatus?->name,
            'Công ty' => $booking->company?->name ?? 'KHÁCH LẺ',
            'Tour Code' => $booking->event_code,
            'Người đặt phòng' => $booking->booker?->name,
            'SĐT liên hệ' => $booking->booker?->phone ?? $booking->contact_phone,
            'Người bán' => $booking->sales_person,
            'Thị trường' => $booking->market?->name,
            'Nguồn khách' => $booking->customerSource?->name,
            'Email' => $booking->contact_email,
            'Ghi chú' => $booking->note,
        ]);

        $logBooking = self::logBusiness([
            'action' => 'Add',
            'module' => 'reservation',
            'component' => 'Booking',
            'description' => $infoDesc,
            'target_type' => 'Booking',
            'target_id' => (string) $booking->id,
            'target_label' => $bookingCode,
            'new_values' => $booking->toArray(),
        ], $request);

        $bookingRooms = $booking->relationLoaded('bookingRooms')
            ? $booking->bookingRooms
            : $booking->bookingRooms()->with(['roomClass'])->get();

        if ($bookingRooms && $bookingRooms->isNotEmpty()) {
            $roomLines = [];
            $roomIds = [];
            foreach ($bookingRooms as $br) {
                $roomIds[] = $br->id;
                $roomRef = $br->room_number ? "P.{$br->room_number} ({$br->id})" : "{$br->id} - chưa gán số phòng";
                $roomLines[] = "* Phòng thuê : # Thêm mới ({$bookingCode} - {$roomRef}) : " . self::joinParts([
                    'Loại phòng' => $br->roomClass?->name,
                    'Dạng' => $br->room_form,
                    'Giá' => is_numeric($br->rate) ? number_format((float) $br->rate, 0, '.', ',') : null,
                    'Mã giá' => $br->rate_code,
                    'Ngày đến' => \App\Support\ActivityLogFormatter::date($br->arrival_date),
                    'Ngày đi' => \App\Support\ActivityLogFormatter::date($br->departure_date),
                    'Người lớn' => $br->adults,
                    'Trẻ em' => $br->children_qty,
                    'Ăn sáng' => $br->breakfast === null ? null : ($br->breakfast ? 'Có' : 'Không'),
                    'Thêm giường' => $br->extra_bed_qty,
                ]);
            }

            self::logBusiness([
                'action' => 'Add',
                'module' => 'reservation',
                'component' => 'Booking',
                'description' => implode("\n", $roomLines),
                'target_type' => 'BookingRoom',
                'target_id' => implode(',', $roomIds),
                'target_label' => $bookingCode,
                'new_values' => ['rooms' => $roomIds, 'booking_id' => $booking->id],
            ], $request);
        }

        return $logBooking;
    }

    /** "-Nhãn : giá trị" cho các phần có giá trị, bỏ qua phần trống. */
    private static function joinParts(array $parts): string
    {
        $text = [];
        foreach ($parts as $label => $val) {
            if ($val !== null && is_scalar($val) && trim((string) $val) !== '') {
                $text[] = "-{$label} : {$val}";
            }
        }

        return implode(' ', $text);
    }

    /**
     * Ghi log xem báo cáo (Chuẩn format HKT Row 1 & Row 71)
     */
    public static function logReportView($report, string $from, string $to, ?Request $request = null): ActivityLog
    {
        $reportName = is_object($report) ? ($report->name ?? 'Báo cáo') : (string)$report;
        $desc = "Xem báo cáo: \"{$reportName}\"  - Từ ngày {$from} , - Đến ngày {$to}";

        return self::logBusiness([
            'action' => 'Report',
            'module' => 'reports',
            'component' => 'Báo cáo',
            'description' => $desc,
            'target_type' => 'ReportDefinition',
            'target_id' => is_object($report) ? (string)$report->id : null,
            'target_label' => $reportName,
            'new_values' => ['parameters' => ['from_date' => $from, 'to_date' => $to]],
        ], $request);
    }

    /**
     * Ghi log khi Cập nhật thông tin Đăng ký (Chuẩn format HKT)
     */
    public static function logBookingUpdated($booking, array $changesText = [], ?Request $request = null): ActivityLog
    {
        $bookingCode = $booking->booking_code ?? $booking->code ?? ('GAL' . $booking->id);
        $detail = !empty($changesText) ? implode(' ', $changesText) : '';
        $desc = "* Cập Nhật Thông Tin Đăng Ký {$booking->id}" . ($detail ? " : {$detail}" : "");

        return self::logBusiness([
            'action' => 'Modify',
            'module' => 'reservation',
            'component' => 'Booking',
            'description' => $desc,
            'target_type' => 'Booking',
            'target_id' => (string) $booking->id,
            'target_label' => $bookingCode,
            'new_values' => $booking->toArray(),
        ], $request);
    }

    /**
     * Ghi log khi Cập nhật thông tin Phòng thuê (Chuẩn format HKT)
     */
    public static function logBookingRoomUpdated($bookingRoom, array $oldValues, array $newValues, ?Request $request = null): ActivityLog
    {
        $booking = $bookingRoom->booking;
        $bookingCode = $booking?->booking_code ?? ('GAL' . ($bookingRoom->booking_id ?? ''));

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

        $changes = [];
        foreach ($fieldMap as $key => $label) {
            if (array_key_exists($key, $newValues) && array_key_exists($key, $oldValues)) {
                $oldV = $oldValues[$key];
                $newV = $newValues[$key];
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
                    $changes[] = "- {$label} {$oldV} -> {$newV}";
                }
            }
        }

        $detail = !empty($changes) ? implode(' ', $changes) : '';
        $desc = "* Cập Nhật Phòng Thuê ({$bookingRoom->id})" . ($detail ? " : {$detail}" : "");

        return self::logBusiness([
            'action' => 'Modify',
            'module' => 'reservation',
            'component' => 'Booking',
            'description' => $desc,
            'target_type' => 'BookingRoom',
            'target_id' => $bookingRoom->id,
            'target_label' => $bookingCode,
            'old_values' => $oldValues,
            'new_values' => $newValues,
        ], $request);
    }

    /**
     * Ghi log Check-in
     */
    public static function logCheckIn($bookingCode, $rooms, ?Request $request = null): ActivityLog
    {
        $roomsStr = is_array($rooms) ? implode(', ', $rooms) : (string) $rooms;
        $desc = "Check in cho đăng ký {$bookingCode} - các phòng: {$roomsStr}";

        return self::logBusiness([
            'action' => 'CheckIn',
            'module' => 'frontdesk',
            'component' => 'RoomMapPage',
            'description' => $desc,
            'target_type' => 'BookingRoom',
            'target_id' => is_array($rooms) ? (reset($rooms) ?: '') : (string) $rooms,
            'target_label' => $bookingCode,
        ], $request);
    }

    /**
     * Ghi log Check-out
     */
    public static function logCheckOut($bookingCode, $rooms, ?Request $request = null): ActivityLog
    {
        $roomsStr = is_array($rooms) ? implode(', ', $rooms) : (string) $rooms;
        $desc = "Check out cho đăng ký {$bookingCode} - phòng: {$roomsStr}";

        return self::logBusiness([
            'action' => 'CheckOut',
            'module' => 'frontdesk',
            'component' => 'CheckoutPage',
            'description' => $desc,
            'target_type' => 'BookingRoom',
            'target_id' => is_array($rooms) ? (reset($rooms) ?: '') : (string) $rooms,
            'target_label' => $bookingCode,
        ], $request);
    }

    /**
     * Ghi log Chuyển phòng (Move Room)
     */
    public static function logRoomMove($bookingCode, $oldRoom, $newRoom, ?string $reason = null, ?string $guestInfo = null, ?Request $request = null): ActivityLog
    {
        $reasonStr = $reason ? " Lý do: {$reason}" : "";
        $guestStr = $guestInfo ? "({$guestInfo})" : "";
        $desc = "Chuyển phòng: {$oldRoom}{$guestStr} -> {$newRoom}{$guestStr}{$reasonStr}";

        return self::logBusiness([
            'action' => 'Modify',
            'module' => 'reservation',
            'component' => 'RoomMapPage',
            'description' => $desc,
            'target_type' => 'BookingRoom',
            'target_id' => (string) $oldRoom,
            'target_label' => $bookingCode,
        ], $request);
    }

    /**
     * Ghi log Nâng hạng phòng (Upgrade Room)
     */
    public static function logRoomUpgrade($bookingCode, $roomNumber, $oldClass, $newClass, $oldPrice = 0, $newPrice = 0, ?Request $request = null): ActivityLog
    {
        $priceDiff = $newPrice - $oldPrice;
        $diffStr = $priceDiff != 0 ? " (Chênh lệch: " . number_format($priceDiff, 0, '.', ',') . " đ)" : "";
        $desc = "Nâng hạng phòng: {$roomNumber} - Hạng cũ: {$oldClass} -> Hạng mới: {$newClass}{$diffStr}";

        return self::logBusiness([
            'action' => 'Modify',
            'module' => 'reservation',
            'component' => 'UpgradeModal',
            'description' => $desc,
            'target_type' => 'BookingRoom',
            'target_id' => (string) $roomNumber,
            'target_label' => $bookingCode,
            'new_values' => [
                'booking_code' => $bookingCode,
                'room_number' => (string) $roomNumber,
                'old_class' => $oldClass,
                'new_class' => $newClass,
                'price_diff' => $priceDiff,
            ],
        ], $request);
    }

    /**
     * Ghi log Cập nhật thông tin khách lưu trú
     */
    public static function logGuestUpdated($guestCode, $oldName, $newName, $roomNumber = null, $bookingCode = null, ?Request $request = null): ActivityLog
    {
        $roomStr = $roomNumber ? " (Phòng {$roomNumber})" : "";
        $desc = "Cập nhật thông tin khách: Mã khách: {$guestCode} - Họ tên: {$oldName} -> {$newName}{$roomStr}";

        return self::logBusiness([
            'action' => 'Modify',
            'module' => 'reservation',
            'component' => 'GuestInfoModal',
            'description' => $desc,
            'target_type' => 'Guest',
            'target_id' => (string) ($roomNumber ?: $guestCode),
            'target_label' => $bookingCode ?: $guestCode,
        ], $request);
    }

    /**
     * Ghi log Đổi trạng thái phòng (Housekeeping)
     */
    public static function logRoomStatusChanged($roomNumber, $oldStatusName, $newStatusName, ?Request $request = null): ActivityLog
    {
        $desc = "Phòng {$roomNumber}. Đổi trạng thái: {$oldStatusName} -> {$newStatusName}";

        return self::logBusiness([
            'action' => 'Modify',
            'module' => 'housekeeping',
            'component' => 'RoomMapPage',
            'description' => $desc,
            'target_type' => 'Room',
            'target_id' => (string) $roomNumber,
            'target_label' => (string) $roomNumber,
        ], $request);
    }

    /**
     * Ghi log Khóa / Mở khóa phòng
     */
    public static function logRoomLock($roomNumber, $lockType, $fromDate, $toDate, ?string $reason = null, bool $isLocked = true, ?Request $request = null): ActivityLog
    {
        if ($isLocked) {
            $fromStr = $fromDate ? \Carbon\Carbon::parse($fromDate)->format('d/m/Y') : '-';
            $toStr = $toDate ? \Carbon\Carbon::parse($toDate)->format('d/m/Y') : '-';
            $reasonStr = $reason ? ". Lý do: {$reason}" : "";
            $desc = "Khóa phòng {$roomNumber}: Loại khóa: {$lockType}, Từ ngày {$fromStr} đến {$toStr}{$reasonStr}";
        } else {
            $desc = "Mở khóa phòng {$roomNumber}";
        }

        return self::logBusiness([
            'action' => $isLocked ? 'Lock' : 'Unlock',
            'module' => 'reservation',
            'component' => 'LockRoomPage',
            'description' => $desc,
            'target_type' => 'RoomLock',
            'target_id' => (string) $roomNumber,
            'target_label' => (string) $roomNumber,
        ], $request);
    }

    /**
     * Ghi log Dịch vụ (Minibar, Giặt là, Bồi thường, Extra Bed,...)
     */
    public static function logServiceAction(string $actionType, $roomNumber, $bookingCode, $serviceName, $qty = 1, $price = 0, ?Request $request = null): ActivityLog
    {
        $priceStr = number_format($price, 0, '.', ',') . ' đ';
        $totalStr = number_format($price * $qty, 0, '.', ',') . ' đ';

        if ($actionType === 'create' || $actionType === 'add') {
            $desc = "* Thêm dịch vụ phòng {$roomNumber} (ĐK {$bookingCode}): {$serviceName} (SL: {$qty}, Đơn giá: {$priceStr}, Thành tiền: {$totalStr})";
            $act = 'AddService';
        } elseif ($actionType === 'update') {
            $desc = "* Sửa dịch vụ phòng {$roomNumber} (ĐK {$bookingCode}): {$serviceName} (SL: {$qty}, Thành tiền: {$totalStr})";
            $act = 'Modify';
        } else {
            $desc = "* Xóa dịch vụ phòng {$roomNumber} (ĐK {$bookingCode}): {$serviceName}";
            $act = 'DeleteService';
        }

        return self::logBusiness([
            'action' => $act,
            'module' => 'housekeeping',
            'component' => 'ServicesModal',
            'description' => $desc,
            'target_type' => 'BookingRoomService',
            'target_id' => (string) $roomNumber,
            'target_label' => (string) $bookingCode,
            'new_values' => [
                'room_number' => (string) $roomNumber,
                'booking_code' => (string) $bookingCode,
                'service_name' => $serviceName,
                'quantity' => $qty,
                'price' => $price,
                'total_amount' => $price * $qty,
            ],
        ], $request);
    }

    /**
     * Ghi log Thanh toán / Đặt cọc / Chuyển hóa đơn
     */
    public static function logPaymentAction(string $type, $bookingCode, $roomNumber, $amount, $method = 'Tiền mặt', ?string $note = null, ?Request $request = null): ActivityLog
    {
        $amountStr = number_format($amount, 0, '.', ',') . ' đ';
        $noteStr = $note ? ", Diễn giải: {$note}" : "";
        $roomStr = $roomNumber ? " (Phòng {$roomNumber})" : "";

        if ($type === 'deposit') {
            $desc = "* Đặt cọc đăng ký {$bookingCode}{$roomStr}: Số tiền: {$amountStr}, Phương thức: {$method}{$noteStr}";
            $act = 'Payment';
        } elseif ($type === 'transfer') {
            $desc = "* Chuyển hóa đơn đăng ký {$bookingCode}{$roomStr}: Số tiền: {$amountStr}{$noteStr}";
            $act = 'Modify';
        } elseif ($type === 'refund') {
            $desc = "* Hoàn tiền đăng ký {$bookingCode}{$roomStr}: Số tiền: {$amountStr}, Phương thức: {$method}{$noteStr}";
            $act = 'Refund';
        } else {
            $desc = "* Thanh toán hóa đơn đăng ký {$bookingCode}{$roomStr}: Số tiền: {$amountStr}, Phương thức: {$method}{$noteStr}";
            $act = 'Payment';
        }

        return self::logBusiness([
            'action' => $act,
            'module' => 'frontdesk',
            'component' => 'CheckoutPage',
            'description' => $desc,
            'target_type' => 'Payment',
            'target_id' => (string) ($roomNumber ?: $bookingCode),
            'target_label' => (string) $bookingCode,
            'new_values' => [
                'booking_code' => (string) $bookingCode,
                'room_number' => (string) $roomNumber,
                'amount' => $amount,
                'payment_method' => $method,
                'note' => $note,
            ],
        ], $request);
    }

    /**
     * Ghi log Khóa sổ / Sang ngày (Day Close / Night Audit)
     */
    public static function logDayClose($oldDate, $newDate, ?Request $request = null): ActivityLog
    {
        $desc = "* Chạy sang ngày nghiệp vụ: {$oldDate} -> {$newDate}";

        return self::logBusiness([
            'action' => 'DayClose',
            'module' => 'frontdesk',
            'component' => 'DayClosePage',
            'description' => $desc,
            'target_type' => 'SystemDateRoll',
            'target_id' => (string) $newDate,
            'target_label' => 'NightAudit',
        ], $request);
    }

    /**
     * Ghi log Quản lý kho (Nhập, Xuất, Chuyển kho, Kiểm kê)
     */
    public static function logInventoryAction(string $type, $warehouseName, $code, $detail = '', ?Request $request = null): ActivityLog
    {
        $desc = "* {$type} kho {$warehouseName}: Mã phiếu: {$code}. {$detail}";

        return self::logBusiness([
            'action' => 'Inventory',
            'module' => 'housekeeping',
            'component' => 'InventoryTab',
            'description' => $desc,
            'target_type' => 'Inventory',
            'target_id' => (string) $code,
            'target_label' => (string) $warehouseName,
        ], $request);
    }

    /**
     * Tính diff giữa old và new values - chỉ giữ các field thực sự thay đổi.
     */
    public static function diffValues(array $old, array $new): array
    {
        $changedOld = [];
        $changedNew = [];

        // Bỏ qua các field hệ thống không cần so sánh
        $skipFields = ['updated_at', 'created_at', 'remember_token', 'password'];

        foreach ($new as $key => $newVal) {
            if (in_array($key, $skipFields)) continue;

            $oldVal = $old[$key] ?? null;

            // So sánh giá trị (ép kiểu string để tránh lỗi type mismatch)
            if ((string) $oldVal !== (string) $newVal) {
                $changedOld[$key] = $oldVal;
                $changedNew[$key] = $newVal;
            }
        }

        return ['old' => $changedOld, 'new' => $changedNew];
    }
}
