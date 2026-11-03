<?php

namespace App\Support;

use App\Models\BookingRoom;
use App\Services\ActivityLogService as Log;
use Illuminate\Support\Facades\DB;

/**
 * Nội dung lịch sử thao tác cho thông tin khách lưu trú (sheet GHI LOG dòng 36, 49, 100, 101, 102).
 */
class GuestLogText
{
    public const FIELDS = [
        'title' => 'Danh xưng',
        'full_name' => 'Họ tên',
        'gender' => 'Giới tính',
        'dob' => 'Ngày sinh',
        'nationality_code' => 'Quốc tịch',
        'id_type' => 'Loại giấy tờ',
        'id_number' => 'Số giấy tờ',
        'id_issue_date' => 'Ngày cấp',
        'passport_number' => 'Số passport',
        'passport_expiry' => 'Ngày hết hạn',
        'province' => 'Tỉnh Thành',
        'district' => 'Quận/huyện',
        'ward' => 'Phường/xã',
        'phone' => 'Điện thoại',
        'email' => 'Email',
        'address' => 'Địa chỉ',
        'guest_type' => 'Loại Khách',
        'visa_no' => 'Số Visa',
        'residence_type' => 'Thường trú/Tạm trú',
        'temp_residence_to' => 'Tạm trú đến',
        'entry_date' => 'Ngày nhập cảnh',
        'visa_expiry_date' => 'Ngày hết hạn visa',
        'entry_purpose' => 'Mục đích nhập cảnh',
        'border_gate' => 'Cửa khẩu',
        'occupation' => 'Nghề nghiệp',
        'note' => 'Ghi chú',
    ];

    protected const DATE_FIELDS = ['dob', 'id_issue_date', 'passport_expiry', 'temp_residence_to', 'entry_date', 'visa_expiry_date'];

    public static function value(string $field, mixed $value): string
    {
        if ($value === null || $value === '' || is_array($value)) {
            return '';
        }
        if (in_array($field, self::DATE_FIELDS, true)) {
            return Log::d($value);
        }

        return match ($field) {
            'gender' => match ((int) $value) {
                1 => 'Nam',
                2 => 'Nữ',
                default => 'Khác',
            },
            'nationality_code' => self::nationality((string) $value),
            default => trim((string) $value),
        };
    }

    protected static function nationality(string $code): string
    {
        static $cache = [];
        if (!array_key_exists($code, $cache)) {
            try {
                $name = DB::table('nationalities')->where('nationality_id', $code)->value('nationality_name');
            } catch (\Throwable $e) {
                $name = null;
            }
            $name = $name ? trim(preg_replace('/\s*\(.*\)\s*$/u', '', $name)) : '';
            $cache[$code] = $name !== '' ? "{$code} - {$name}" : $code;
        }

        return $cache[$code];
    }

    /** "- Danh xưng: Mr. -> Ms. - Họ tên: Guest 1 -> Nguyễn Thị Hoa ..." chỉ các trường thay đổi. */
    public static function diff(array $old, array $new): string
    {
        $parts = [];
        foreach (self::FIELDS as $field => $label) {
            if (!array_key_exists($field, $new)) {
                continue;
            }
            $o = self::value($field, $old[$field] ?? null);
            $n = self::value($field, $new[$field]);
            if ($o !== $n) {
                $parts[] = "- {$label}: {$o} -> {$n}";
            }
        }

        return implode(' ', $parts);
    }

    /** "- Danh xưng: Mr. - Họ tên: Guest 3 - Giới tính: Nam ..." toàn bộ thông tin khi thêm khách. */
    public static function full(array $attrs): string
    {
        $parts = [];
        foreach (self::FIELDS as $field => $label) {
            $parts[] = "- {$label}: " . self::value($field, $attrs[$field] ?? null);
        }

        return implode(' ', $parts);
    }

    /** Phòng thuê (mã G) hiện tại của khách trong đăng ký, ưu tiên phòng đang ở / đăng ký. */
    public static function roomOf(string $guestId, ?int $bookingId = null): ?BookingRoom
    {
        $query = BookingRoom::query()
            ->whereIn('id', DB::table('booking_room_guests')->where('guest_id', $guestId)->pluck('booking_room_id'))
            ->when($bookingId, fn ($q) => $q->where('booking_id', $bookingId))
            ->orderByRaw('CASE WHEN status IN (0, 1) THEN 0 ELSE 1 END')
            ->orderByDesc('id');

        return $query->first();
    }
}
