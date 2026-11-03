<?php

namespace App\Support;

use App\Models\Booking;
use App\Models\BookingRoom;
use App\Services\ActivityLogService as Log;
use Illuminate\Support\Facades\DB;

/**
 * Nội dung lịch sử thao tác cho Đăng ký / Phòng thuê theo mẫu sheet GHI LOG.
 */
class BookingLogText
{
    /** Dòng 3, 14: thông tin đăng ký khi tạo mới (không có VAT; Liên hệ = SĐT người đặt phòng). */
    public static function bookingInfo(Booking $booking): string
    {
        $booking->loadMissing(['company', 'market', 'customerSource', 'booker', 'registrationStatus', 'paymentMethod', 'creator']);
        $code = Log::bookingCode($booking->id);

        $parts = [
            'Tên nhóm' => $booking->booking_name,
            'FIT' => $booking->is_git ? 'GIT' : 'FIT',
            'Ngày đến' => Log::d($booking->arrival_date),
            'Ngày đi' => Log::d($booking->departure_date),
            'Số ngày' => $booking->num_of_days,
            'Trạng thái' => $booking->registrationStatus?->name,
            'Công ty' => $booking->company?->name,
            'Tour Code' => $booking->event_code,
            'Liên hệ' => $booking->booker?->phone ?: $booking->contact_phone,
            'Payment' => $booking->paymentMethod?->name,
            'Booker' => $booking->booker?->name,
            'SalesPerson' => $booking->sales_person ?: $booking->creator?->name,
            'Market Segment' => $booking->market?->name,
            'Source Code' => $booking->customerSource?->name,
            'Email' => $booking->contact_email ?: $booking->booker?->email,
            'Ghi chú đăng ký' => $booking->note,
        ];

        return "* Thông Tin Đăng Ký {$code} : " . self::dashParts($parts);
    }

    /** Dòng 4, 15, 16: thêm mới phòng thuê (giá thật, Loại = tên loại phòng, Dạng = Double/Twin...). */
    public static function roomCreated(BookingRoom $room): string
    {
        $room->loadMissing(['roomClass']);

        return '* Thêm mới Phòng thuê : ' . implode(' - ', [
            'Mã: ' . $room->id,
            'Giá: ' . Log::money(self::roomRate($room)),
            'Loại: ' . ($room->roomClass?->name ?? ''),
            'Dạng: ' . self::formName($room),
            'Phòng: ' . ($room->room_number ?: 'Chưa gán'),
            'Ngày đến: ' . Log::d($room->arrival_date),
            'Ngày đi: ' . Log::d($room->departure_date),
            'Người lớn: ' . (int) $room->adults,
            'Trẻ em: ' . (int) $room->children_qty,
            'Trẻ em ăn sáng miễn phí: ' . self::freeBreakfastChildren($room),
            'Ăn sáng: ' . ($room->breakfast ? 'Có' : 'Không'),
            'Thêm giường: ' . (int) $room->extra_bed_qty,
            'BirthDay: ' . (self::hasBirthday($room) ? 'Có' : 'Không'),
        ]);
    }

    public static function roomRate(BookingRoom $room): float
    {
        $rate = (float) ($room->rate ?? 0);
        if ($rate <= 0 && (float) ($room->base_price ?? 0) > 0) {
            $rate = (float) $room->base_price;
        }

        return $rate;
    }

    /** Tên dạng phòng (Double / Twin / Triple...) theo RoomKind của phòng thuê. */
    public static function formName(BookingRoom $room): string
    {
        $kind = $room->RoomKind ?? null;
        if ($kind !== null && $kind !== '') {
            $name = self::roomFormName($kind);
            if ($name !== null) {
                return $name;
            }
        }

        return self::formNameFromClass($room->room_class_id) ?? '';
    }

    public static function roomFormName(mixed $kind): ?string
    {
        static $cache = [];
        $key = (string) $kind;
        if (!array_key_exists($key, $cache)) {
            $cache[$key] = DB::table('room_forms')->where('id', $kind)->value('name');
        }

        return $cache[$key];
    }

    /** Dạng phòng theo giá phòng chuẩn của loại phòng. */
    public static function formNameFromClass(mixed $roomClassId): ?string
    {
        if (!$roomClassId) {
            return null;
        }
        try {
            $formId = DB::table('standard_rates')->where('room_class_id', $roomClassId)->value('room_form_id');
        } catch (\Throwable $e) {
            $formId = null;
        }

        return $formId ? self::roomFormName($formId) : null;
    }

    public static function className(mixed $roomClassId): string
    {
        static $cache = [];
        if (!$roomClassId) {
            return '';
        }
        $key = (string) $roomClassId;
        if (!array_key_exists($key, $cache)) {
            $cache[$key] = (string) DB::table('room_classes')->where('id', $roomClassId)->value('name');
        }

        return $cache[$key];
    }

    protected static function freeBreakfastChildren(BookingRoom $room): int
    {
        try {
            return (int) DB::table('booking_children')
                ->where('booking_room_id', $room->id)
                ->whereExists(function ($q) {
                    $q->select(DB::raw(1))
                        ->from('booking_child_breakfast_details')
                        ->whereColumn('booking_child_breakfast_details.booking_child_id', 'booking_children.id')
                        ->where('booking_child_breakfast_details.breakfast', 1)
                        ->where('booking_child_breakfast_details.is_free', 1);
                })
                ->count();
        } catch (\Throwable $e) {
            return 0;
        }
    }

    protected static function hasBirthday(BookingRoom $room): bool
    {
        try {
            return DB::table('booking_room_special_requests as brsr')
                ->join('special_requests as sr', 'sr.id', '=', 'brsr.special_request_id')
                ->where('brsr.booking_room_id', $room->id)
                ->where(function ($q) {
                    $q->where('sr.code', 'BD')->orWhere('sr.name', 'like', '%sinh nhật%')->orWhere('sr.name', 'like', '%birthday%');
                })
                ->exists();
        } catch (\Throwable $e) {
            return false;
        }
    }

    /** "-Nhãn : giá trị" giữ đủ các nhãn như mẫu (giá trị trống vẫn in nhãn). */
    public static function dashParts(array $parts): string
    {
        $text = [];
        foreach ($parts as $label => $val) {
            $text[] = "-{$label} : " . (is_scalar($val) ? trim((string) $val) : '');
        }

        return implode(' ', $text);
    }

    /** Giá trị hiển thị cho trường của Đăng ký (tên danh mục thay cho id). */
    public static function bookingFieldValue(string $field, mixed $value): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        return match ($field) {
            'arrival_date', 'departure_date' => Log::d($value),
            'is_git' => $value ? 'GIT' : 'FIT',
            'company_id' => (string) DB::table('companies')->where('id', $value)->value('name'),
            'market_id' => (string) DB::table('markets')->where('id', $value)->value('name'),
            'customer_source_id' => (string) DB::table('customer_sources')->where('id', $value)->value('name'),
            'booker_id' => (string) DB::table('bookers')->where('id', $value)->value('name'),
            'payment_method_id' => (string) DB::table('payment_methods')->where('id', $value)->value('name'),
            'registration_status_id' => (string) DB::table('registration_statuses')->where('booking_status_id', $value)->value('name'),
            'payment_value' => Log::money($value),
            default => is_scalar($value) ? (string) $value : '',
        };
    }

    /** Trường đăng ký được ghi log khi cập nhật (dòng 17, 18, 33). */
    public const BOOKING_FIELDS = [
        'booking_name' => 'Tên nhóm',
        'is_git' => 'FIT',
        'arrival_date' => 'Ngày Đến',
        'departure_date' => 'Ngày Đi',
        'num_of_days' => 'Số Ngày',
        'registration_status_id' => 'Trạng thái',
        'company_id' => 'Công Ty',
        'event_code' => 'Tour Code',
        'contact_phone' => 'Liên hệ',
        'payment_method_id' => 'Payment',
        'booker_id' => 'Booker',
        'sales_person' => 'SalesPerson',
        'market_id' => 'Market Segment',
        'customer_source_id' => 'Source Code',
        'contact_email' => 'Email',
        'note' => 'Ghi chú đăng ký',
    ];

    /** Dòng 44: "Superior Triple: 2 (G0000062,G0000063) - Deluxe Double City view: 1 (G0000061)". */
    public static function roomsByClass(iterable $rooms): string
    {
        $groups = [];
        foreach ($rooms as $room) {
            $name = self::className($room->room_class_id) ?: 'Chưa rõ loại';
            $groups[$name][] = (string) $room->id;
        }

        $parts = [];
        foreach ($groups as $name => $ids) {
            $parts[] = "{$name}: " . count($ids) . ' (' . implode(',', $ids) . ')';
        }

        return implode(' - ', $parts);
    }

    /** Trường phòng thuê được ghi log khi cập nhật (dòng 9, 17, 27, 33, 35, 100). */
    public const ROOM_FIELDS = [
        'room_number' => 'Phòng',
        'room_class_id' => 'Loại',
        'RoomKind' => 'Dạng',
        'arrival_date' => 'Ngày Checkin',
        'departure_date' => 'Ngày Checkout',
        'rate' => 'Giá',
        'rate_code' => 'Mã giá',
        'adults' => 'Người lớn',
        'children_qty' => 'Trẻ em',
        'babies' => 'Em bé',
        'extra_bed_qty' => 'Thêm giường',
        'extra_bed_rate' => 'Giá thêm giường',
        'breakfast' => 'Ăn sáng',
        'is_day_use' => 'Ở theo giờ',
        'note' => 'Ghi chú',
    ];

    public static function roomFieldValue(string $field, mixed $value): string
    {
        if ($value === null || $value === '') {
            return in_array($field, ['breakfast', 'is_day_use'], true) ? 'Không' : '';
        }

        return match ($field) {
            'arrival_date', 'departure_date' => Log::d($value),
            'rate', 'extra_bed_rate' => Log::money($value),
            'room_class_id' => self::className($value),
            'RoomKind' => (string) self::roomFormName($value),
            'breakfast', 'is_day_use' => $value ? 'Có' : 'Không',
            'adults', 'children_qty', 'babies', 'extra_bed_qty' => (string) (int) $value,
            default => is_scalar($value) ? (string) $value : '',
        };
    }

    /** Các trường phòng thay đổi: [field => [old, new]] (giá trị hiển thị). */
    public static function roomChanges(array $old, array $new): array
    {
        $changes = [];
        foreach (self::ROOM_FIELDS as $field => $label) {
            if (!array_key_exists($field, $new) && !array_key_exists($field, $old)) {
                continue;
            }
            $o = self::roomFieldValue($field, $old[$field] ?? null);
            $n = self::roomFieldValue($field, array_key_exists($field, $new) ? $new[$field] : ($old[$field] ?? null));
            if ($o !== $n) {
                $changes[$field] = [$o, $n];
            }
        }

        return $changes;
    }

    /**
     * Mô tả cập nhật một phòng thuê; null nếu không có thay đổi cần ghi.
     *  - Đổi loại/dạng phòng (dòng 9):  "* Cập nhật phòng thuê: điều chỉnh loại dạng phòng cho G.." + từng dòng
     *  - Chỉ đổi ngày (dòng 17):        "* Cập nhật phòng (G..) ngày đến từ A => A, ngày đi từ B => C"
     *  - Còn lại (dòng 27):             "* Cập Nhật Phòng Thuê (G..): - Ngày Checkout A -> B - Giá X -> Y"
     */
    public static function roomDiff(string $roomId, array $old, array $new): ?string
    {
        $changes = self::roomChanges($old, $new);
        if (!$changes) {
            return null;
        }

        if (isset($changes['room_class_id']) || isset($changes['RoomKind'])) {
            $lines = ["* Cập nhật phòng thuê: điều chỉnh loại dạng phòng cho {$roomId}"];
            foreach ($changes as $field => [$o, $n]) {
                $lines[] = self::ROOM_FIELDS[$field] . ": {$o} -> {$n}";
            }

            return implode("\n", $lines);
        }

        if (array_diff(array_keys($changes), ['extra_bed_qty', 'extra_bed_rate']) === []) {
            // Dòng 30: "Extra Bed phòng (G0000048) Thêm giường: 0 -> 1 Giá thêm giường: 0 -> 300,000"
            $qtyOld = self::roomFieldValue('extra_bed_qty', $old['extra_bed_qty'] ?? null);
            $qtyNew = self::roomFieldValue('extra_bed_qty', $new['extra_bed_qty'] ?? ($old['extra_bed_qty'] ?? null));
            $rateOld = self::roomFieldValue('extra_bed_rate', $old['extra_bed_rate'] ?? null);
            $rateNew = self::roomFieldValue('extra_bed_rate', $new['extra_bed_rate'] ?? ($old['extra_bed_rate'] ?? null));

            return "Extra Bed phòng ({$roomId}) Thêm giường: {$qtyOld} -> {$qtyNew} Giá thêm giường: {$rateOld} -> {$rateNew}";
        }

        if (array_diff(array_keys($changes), ['arrival_date', 'departure_date']) === []) {
            $arrOld = self::roomFieldValue('arrival_date', $old['arrival_date'] ?? null);
            $arrNew = self::roomFieldValue('arrival_date', $new['arrival_date'] ?? ($old['arrival_date'] ?? null));
            $depOld = self::roomFieldValue('departure_date', $old['departure_date'] ?? null);
            $depNew = self::roomFieldValue('departure_date', $new['departure_date'] ?? ($old['departure_date'] ?? null));

            return "* Cập nhật phòng ({$roomId}) ngày đến từ {$arrOld} => {$arrNew}, ngày đi từ {$depOld} => {$depNew}";
        }

        $parts = [];
        foreach ($changes as $field => [$o, $n]) {
            $parts[] = '- ' . self::ROOM_FIELDS[$field] . " {$o} -> {$n}";
        }

        return "* Cập Nhật Phòng Thuê ({$roomId}): " . implode(' ', $parts);
    }

    /** Ảnh chụp thuộc tính các phòng thuê để so sánh trước/sau khi lưu. */
    public static function roomSnapshot(iterable $rooms): array
    {
        $snap = [];
        foreach ($rooms as $room) {
            $snap[(string) $room->id] = array_intersect_key($room->getAttributes(), self::ROOM_FIELDS + ['status' => 1]);
        }

        return $snap;
    }

    /** Nhãn/khóa thông tin đưa đón (shuttle_info). */
    public const SHUTTLE_FIELDS = [
        'type' => 'Loại hình',
        'vehicle' => 'Phương tiện',
        'code' => 'Mã',
        'date' => 'Ngày',
        'time' => 'Giờ',
        'price' => 'Hiện giá',
        'location' => 'Địa điểm',
        'note' => 'Ghi chú',
    ];

    protected static function shuttleValue(string $field, mixed $value): string
    {
        if ($value === null || $value === '' || is_array($value)) {
            return '';
        }
        if ($field === 'date') {
            try {
                return \Carbon\Carbon::parse($value)->format('d/m/Y');
            } catch (\Throwable $e) {
                return (string) $value;
            }
        }
        if ($field === 'price') {
            return Log::money($value);
        }

        return trim((string) $value);
    }

    /**
     * Dòng 24, 25, 26: thêm / sửa / xóa thông tin đưa đón, so khớp theo id dòng.
     *
     * @return string[]
     */
    public static function shuttleDiff(string $bookingCode, mixed $old, mixed $new): array
    {
        $index = function ($rows): array {
            $out = [];
            foreach (is_array($rows) ? $rows : [] as $i => $row) {
                if (is_array($row)) {
                    $out[(string) ($row['id'] ?? "idx{$i}")] = $row;
                }
            }

            return $out;
        };
        $oldRows = $index(is_string($old) ? json_decode($old, true) : $old);
        $newRows = $index(is_string($new) ? json_decode($new, true) : $new);
        $lines = [];

        foreach ($newRows as $id => $row) {
            if (!isset($oldRows[$id])) {
                $parts = [];
                foreach (self::SHUTTLE_FIELDS as $f => $label) {
                    $parts[] = "- {$label}: -> " . self::shuttleValue($f, $row[$f] ?? null);
                }
                $lines[] = "* Cập nhật đăng ký {$bookingCode}: Thêm thông tin đưa đón khách:" . implode(', ', $parts) . '.';
                continue;
            }
            $parts = [];
            foreach (self::SHUTTLE_FIELDS as $f => $label) {
                $o = self::shuttleValue($f, $oldRows[$id][$f] ?? null);
                $n = self::shuttleValue($f, $row[$f] ?? null);
                if ($o !== $n) {
                    $parts[] = "- {$label}: {$o} -> {$n}";
                }
            }
            if ($parts) {
                $type = self::shuttleValue('type', $row['type'] ?? null);
                array_unshift($parts, "- Loại hình: {$type} ");
                $lines[] = "* Cập nhật đăng ký {$bookingCode}: Cập nhật thông tin đưa đón khách:" . implode(', ', array_unique($parts));
            }
        }

        foreach ($oldRows as $id => $row) {
            if (!isset($newRows[$id])) {
                $parts = [];
                foreach (self::SHUTTLE_FIELDS as $f => $label) {
                    $parts[] = "{$label}: " . self::shuttleValue($f, $row[$f] ?? null);
                }
                $lines[] = "* Xóa thông tin đưa đón khách cho đăng ký {$bookingCode}: " . implode(', - ', $parts) . '.';
            }
        }

        return $lines;
    }

    /**
     * " -Ngày Đi: 14-09-2026 -> 17-09-2026 -Số Ngày: 6 -> 9" chỉ các trường thay đổi.
     *
     * @param  array  $old  thuộc tính trước khi lưu
     * @param  array  $new  thuộc tính sau khi lưu
     */
    public static function bookingDiff(array $old, array $new): array
    {
        $lines = [];
        foreach (self::BOOKING_FIELDS as $field => $label) {
            if (!array_key_exists($field, $new)) {
                continue;
            }
            $o = self::bookingFieldValue($field, $old[$field] ?? null);
            $n = self::bookingFieldValue($field, $new[$field] ?? null);
            if ($o !== $n) {
                $lines[] = "-{$label}: {$o} -> {$n}";
            }
        }

        return $lines;
    }
}
