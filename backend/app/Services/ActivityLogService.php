<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

class ActivityLogService
{
    private static ?string $bookingPrefix = null;

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
     * Ghi log nghiệp vụ theo chuẩn sheet GHI LOG (log_version = 2).
     *
     * Mô tả, mã đăng ký và mã phòng (mã G phòng thuê) được ghi tường minh tại thao tác, màn hình
     * hiển thị giữ nguyên, không dựng lại. Màn hình lấy theo tab frontend (X-Screen), $opts['screen']
     * là màn hình mặc định khi không xác định được tab; $opts['force_screen'] luôn dùng giá trị truyền vào.
     *
     * @param  array{screen?:string,force_screen?:string,module?:string,booking_code?:string|int|null,
     *               room_code?:string|array|null,target_type?:string,target_id?:string|int|null,
     *               target_label?:string|null,old_values?:array|null,new_values?:array|null}  $opts
     */
    public static function record(string $action, string $description, array $opts = [], ?Request $request = null): ActivityLog
    {
        $request ??= request();
        $user = $request?->user() ?? auth()->user();

        $screen = $opts['force_screen']
            ?? \App\Support\ActivityScreen::label($request, $opts['screen'] ?? null)
            ?? 'Hệ thống';

        return self::log([
            'user_id' => $user?->id,
            'user_name' => $user?->name ?? '',
            'employee_code' => $user?->employee_code,
            'action' => $action,
            'module' => $opts['module'] ?? self::moduleForScreen($screen),
            'component' => $screen,
            'description' => $description,
            'target_type' => $opts['target_type'] ?? null,
            'target_id' => isset($opts['target_id']) ? (string) $opts['target_id'] : null,
            'target_label' => $opts['target_label'] ?? null,
            'booking_code' => self::bookingCode($opts['booking_code'] ?? null),
            'room_code' => self::roomCodes($opts['room_code'] ?? null),
            'log_version' => 2,
            'system_date' => $opts['system_date'] ?? null,
            'old_values' => $opts['old_values'] ?? null,
            'new_values' => $opts['new_values'] ?? null,
            'ip_address' => $request?->ip(),
            'user_agent' => $request?->userAgent(),
            'request_method' => $request?->method() ?? 'POST',
            'request_url' => $request?->fullUrl(),
            'response_status' => 200,
        ]);
    }

    /**
     * Gom nhiều request của cùng một lần thao tác (frontend gửi chung $batch) vào MỘT dòng log:
     * mô tả = $header + các $item nối tiếp, cột mã phòng cộng dồn. Không có $batch thì ghi một dòng mới.
     */
    /** Dòng log (v2) gần nhất của cùng lần thao tác $batch. */
    public static function findBatch(string $batch, string $action, ?string $bookingCode): ?ActivityLog
    {
        try {
            return ActivityLog::query()
                ->where('log_version', 2)
                ->where('action', $action)
                ->when($bookingCode, fn ($q) => $q->where('booking_code', $bookingCode))
                ->where('created_at', '>=', now()->subMinutes(30))
                ->orderByDesc('id')
                ->limit(50)
                ->get()
                ->first(fn (ActivityLog $log) => ($log->new_values['batch'] ?? null) === $batch);
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Dòng 28, 38, 39: dịch vụ tự động (booking_room_services) của phòng thuê.
     *  - thêm nhiều ngày một lần (cùng log_batch): một dòng, các ngày liên tục gom "Từ ngày - Đến ngày"
     *  - sửa một đêm: "Cập nhật dịch vụ tự động cho mã đăng ký GAL22 - Phòng: G.. - Dịch vụ: RM - Ngày: .. - Số lượng: 1 - Số tiền: 0"
     */
    public static function logRoomService(\App\Models\BookingRoom $room, \App\Models\BookingRoomService $service, bool $created, ?Request $request = null): ActivityLog
    {
        $request ??= request();
        $code = self::bookingCode($room->booking_id);
        $opts = [
            'screen' => \App\Support\ActivityScreen::REGISTRATION,
            'booking_code' => $code,
            'room_code' => $room->id,
            'target_type' => 'BookingRoomService',
            'target_id' => $room->id,
            'target_label' => $code,
        ];
        $date = \Carbon\Carbon::parse($service->service_date)->toDateString();

        if (!$created) {
            return self::record('Modify', "Cập nhật dịch vụ tự động cho mã đăng ký {$code} - Phòng: {$room->id} - Dịch vụ: {$service->service_code}"
                . ' - Ngày: ' . self::d($date) . ' - Số lượng: ' . (float) $service->quantity . ' - Số tiền: ' . self::money($service->rate), $opts, $request);
        }

        $batch = trim((string) $request?->input('log_batch', ''));
        $state = [
            'batch' => $batch !== '' ? "{$batch}:{$room->id}:{$service->service_code}" : null,
            'service' => $service->service_name ?: $service->service_code,
            'qty' => (float) $service->quantity,
            'rate' => (float) $service->rate,
            'fit' => $service->is_room === false || (string) $service->is_room === '0' ? 'GIT' : 'FIT',
            'dates' => [$date],
        ];

        $existing = $state['batch'] ? self::findBatch($state['batch'], 'Add', $code) : null;
        if ($existing) {
            $state['dates'] = array_values(array_unique(array_merge($existing->new_values['dates'] ?? [], [$date])));
            $existing->update(['description' => self::roomServiceText($code, $room->id, $state), 'new_values' => $state]);
            $request?->attributes->set('_activity_logged', true);

            return $existing;
        }

        return self::record('Add', self::roomServiceText($code, $room->id, $state), $opts + ['new_values' => $state], $request);
    }

    /**
     * Dòng 29: chi tiết ăn sáng trẻ em. Các ngày lưu cùng một lần (log_batch) gom một dòng log,
     * mỗi nhóm cấu hình giống nhau (giá, phụ phí, FIT, ăn sáng) một đoạn "từ ngày - đến ngày".
     */
    public static function logChildBreakfast(?\App\Models\BookingChild $child, \App\Models\BookingChildBreakfastDetail $detail, ?Request $request = null): ActivityLog
    {
        $request ??= request();
        $roomId = (string) ($child?->booking_room_id ?? '');
        $code = self::bookingCode($child?->booking_id);
        $entry = [
            'date' => \Carbon\Carbon::parse($detail->service_date)->toDateString(),
            'amount' => (float) $detail->amount,
            'extra' => (bool) $detail->is_extra_charge,
            'room' => (bool) $detail->is_room,
            'breakfast' => (bool) $detail->breakfast,
        ];
        $opts = [
            'screen' => \App\Support\ActivityScreen::REGISTRATION,
            'booking_code' => $code,
            'room_code' => $roomId ?: null,
            'target_type' => 'BookingChild',
            'target_id' => $child?->id,
            'target_label' => $code,
        ];

        $batch = trim((string) $request?->input('log_batch', ''));
        $state = ['batch' => $batch !== '' ? "{$batch}:{$roomId}" : null, 'entries' => [$entry]];
        $existing = $state['batch'] ? self::findBatch($state['batch'], 'Modify', $code) : null;
        if ($existing) {
            $state['entries'] = array_merge($existing->new_values['entries'] ?? [], [$entry]);
            $existing->update(['description' => self::childBreakfastText($roomId, $state['entries']), 'new_values' => $state]);
            $request?->attributes->set('_activity_logged', true);

            return $existing;
        }

        return self::record('Modify', self::childBreakfastText($roomId, $state['entries']), $opts + ['new_values' => $state], $request);
    }

    protected static function childBreakfastText(string $roomId, array $entries): string
    {
        $groups = [];
        foreach ($entries as $e) {
            $key = json_encode([$e['amount'], $e['extra'], $e['room'], $e['breakfast']]);
            $groups[$key]['meta'] = $e;
            $groups[$key]['dates'][] = $e['date'];
        }

        $yn = fn ($v) => $v ? 'Có' : 'Không';
        $lines = [];
        foreach ($groups as $group) {
            $m = $group['meta'];
            foreach (self::dateRanges($group['dates']) as [$from, $to]) {
                $lines[] = "* Cập nhật chi tiết ăn sáng - phòng: {$roomId} - từ ngày: " . self::d($from) . ' - đến ngày: ' . self::d($to)
                    . ' - giá: ' . self::money($m['amount']) . ' - extra change: ' . $yn($m['extra'])
                    . ' - FIT: ' . $yn($m['room']) . ' - Ăn sáng: ' . $yn($m['breakfast']);
            }
        }

        return implode("\n", $lines);
    }

    /**
     * Dòng 53, 67: post tiền phòng.
     *  - Màn hình Sang ngày: "* Thêm tiền phòng cho các phòng:102(G..), 109(G..) - Từ ngày - Đến ngày - Giờ - Ca - Bộ phận - Mã dịch vụ RM - Mô tả"
     *  - Trả phòng: "* Thêm tiền phòng cho đăng ký GAL27 -số phòng:102 - ..." ; mode update/surcharge là tiền phòng nhập tay
     *    (có / không tính công suất phòng).
     *
     * @param  array<string,string>  $rooms  [mã G => số phòng] đã post thành công
     */
    public static function logRoomCharge(?int $bookingId, array $rooms, string $mode, string $from, string $to, ?float $rate, ?Request $request = null): ?ActivityLog
    {
        if (!$rooms) {
            return null;
        }
        $request ??= request();
        $screen = \App\Support\ActivityScreen::label($request, \App\Support\ActivityScreen::CHECKOUT);
        $code = self::bookingCode($bookingId);
        $time = now('Asia/Ho_Chi_Minh')->format('H:i');
        $batch = trim((string) $request?->input('log_batch', ''));
        $state = [
            'batch' => $batch !== '' ? "{$batch}:roomcharge" : null,
            'rooms' => $rooms,
            'mode' => $mode,
            'from' => $from,
            'to' => $to,
            'time' => $time,
            'rate' => $rate,
            'booking' => $code,
            'screen' => $screen,
            'dept' => \App\Support\ActivityScreen::department($request),
        ];

        $existing = $state['batch'] ? self::findBatch($state['batch'], 'Add', null) : null;
        if ($existing) {
            $state = array_merge($existing->new_values ?? [], ['rooms' => ($existing->new_values['rooms'] ?? []) + $rooms]);
            $existing->update([
                'description' => self::roomChargeText($state),
                'new_values' => $state,
                'room_code' => self::roomCodes(array_keys($state['rooms'])),
                'booking_code' => ($existing->booking_code === $code) ? $code : null,
            ]);
            $request?->attributes->set('_activity_logged', true);

            return $existing;
        }

        return self::record('Add', self::roomChargeText($state), [
            'force_screen' => $screen,
            'booking_code' => $code,
            'room_code' => array_keys($rooms),
            'target_type' => 'ServiceBill',
            'target_label' => $code,
            'new_values' => $state,
        ], $request);
    }

    protected static function roomChargeText(array $s): string
    {
        $roomList = implode(', ', array_map(fn ($id, $no) => "{$no}({$id})", array_keys($s['rooms']), $s['rooms']));
        $manual = in_array($s['mode'], ['update', 'surcharge'], true);
        $head = $s['screen'] === \App\Support\ActivityScreen::DAY_CLOSE || empty($s['booking'])
            ? "* Thêm tiền phòng cho các phòng:{$roomList}"
            : '* Thêm tiền phòng' . ($manual ? ' (nhập tay số tiền)' : '') . " cho đăng ký {$s['booking']} -số phòng:" . implode(', ', $s['rooms']);

        $text = $head . ' - Từ ngày: ' . self::d($s['from']) . ' - Đến ngày: ' . self::d($s['to'])
            . " - Giờ: {$s['time']} -Ca:" . self::shiftAt($s['time']) . " -Bộ phận: {$s['dept']} - Mã dịch vụ RM -Mô tả dịch vụ: Dịch vụ phòng nghỉ";

        if (!$manual) {
            return $text . ' (Room rate night)';
        }

        return $text . ' -Số tiền: ' . self::money($s['rate']) . ' (enter room charge - '
            . ($s['mode'] === 'surcharge' ? 'không tính công suất phòng' : 'có tính công suất phòng') . ')';
    }

    /** "* Thêm Dịch Vụ Tự Động Cho Mã Đăng Ký GAL22 - Phòng: G.. - Dịch Vụ: X - Từ ngày: A - Đến ngày:B - ..." */
    protected static function roomServiceText(string $code, string $roomId, array $state): string
    {
        $tail = fn ($from, $to) => "Dịch Vụ: {$state['service']} - Từ ngày: " . self::d($from) . ' - Đến ngày:' . self::d($to)
            . ' - Số lượng: ' . $state['qty'] . ' - Số tiền: ' . self::money($state['rate']) . ' - ' . $state['fit'];

        $lines = [];
        foreach (self::dateRanges($state['dates']) as $i => [$from, $to]) {
            $lines[] = $i === 0 ? "* Thêm Dịch Vụ Tự Động Cho Mã Đăng Ký {$code} - Phòng: {$roomId} - " . $tail($from, $to) : $tail($from, $to);
        }

        return implode("\n", $lines);
    }

    /** Gom các ngày (Y-m-d) thành các khoảng liên tục [[from, to], ...]. */
    public static function dateRanges(array $dates): array
    {
        $dates = array_values(array_unique(array_filter($dates)));
        sort($dates);
        $ranges = [];
        foreach ($dates as $date) {
            $last = count($ranges) - 1;
            if ($last >= 0 && \Carbon\Carbon::parse($ranges[$last][1])->addDay()->toDateString() === $date) {
                $ranges[$last][1] = $date;
            } else {
                $ranges[] = [$date, $date];
            }
        }

        return $ranges;
    }

    public static function recordBatched(?string $batch, string $action, string $header, string $item, array $opts = [], ?Request $request = null): ActivityLog
    {
        $code = self::bookingCode($opts['booking_code'] ?? null);
        $batch = $batch !== null ? trim($batch) : '';

        if ($batch !== '') {
            try {
                $existing = self::findBatch($batch, $action, $code);

                if ($existing) {
                    $rooms = array_merge(
                        $existing->room_code ? preg_split('/\s*,\s*/', $existing->room_code) : [],
                        (array) ($opts['room_code'] ?? [])
                    );
                    $existing->update([
                        'description' => $existing->description . ' ' . $item,
                        'room_code' => self::roomCodes($rooms),
                        'target_id' => self::roomCodes($rooms),
                    ]);
                    request()?->attributes->set('_activity_logged', true);

                    return $existing;
                }
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning('ActivityLog batch failed: ' . $e->getMessage());
            }
        }

        $opts['new_values'] = array_merge($opts['new_values'] ?? [], $batch !== '' ? ['batch' => $batch] : []);

        return self::record($action, $header . ' ' . $item, $opts, $request);
    }

    /** "GAL20" từ id/mã; null nếu trống. */
    public static function bookingCode(mixed $value): ?string
    {
        if ($value === null || $value === '' || $value === 0 || $value === '0') {
            return null;
        }
        $value = trim((string) $value);

        return preg_match('/^[A-Za-z]+\d+$/', $value) ? strtoupper($value) : self::bookingPrefix() . $value;
    }

    /** Tiền tố mã đăng ký theo cấu hình khách sạn (vd NV, GAL); mặc định GAL. */
    public static function bookingPrefix(): string
    {
        return self::$bookingPrefix ??= strtoupper((string) (\App\Models\HotelSetting::query()->value('prefix_booking_id') ?: 'GAL'));
    }

    public static function forgetBookingPrefix(): void
    {
        self::$bookingPrefix = null;
    }

    /** Log cũ ghi cứng "GAL12": hiển thị theo tiền tố hiện hành. */
    public static function localizePrefix(?string $text): ?string
    {
        $prefix = self::bookingPrefix();

        return ($text === null || $prefix === 'GAL') ? $text : preg_replace('/(?<![A-Za-z])GAL(?=\d)/', $prefix, $text);
    }

    /** Danh sách mã phòng thuê "G0000048, G0000049" (bỏ trùng, bỏ rỗng). */
    public static function roomCodes(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        $codes = is_array($value) ? $value : preg_split('/\s*,\s*/', (string) $value);
        $codes = array_values(array_unique(array_filter(array_map(fn ($c) => trim((string) $c), $codes), fn ($c) => $c !== '')));

        return $codes ? mb_substr(implode(', ', $codes), 0, 1000) : null;
    }

    /** Ngày dạng 03-09-2026 như sheet GHI LOG. */
    public static function d(mixed $value): string
    {
        if (empty($value) || !is_scalar($value) && !$value instanceof \DateTimeInterface) {
            return '';
        }
        try {
            return \Carbon\Carbon::parse($value)->format('d-m-Y');
        } catch (\Throwable $e) {
            return (string) $value;
        }
    }

    /** Tiền dạng 540,000. */
    public static function money(mixed $value): string
    {
        return number_format(is_numeric($value) ? (float) $value : 0, 0, '.', ',');
    }

    /** Ca làm việc chứa giờ $time (H:i) theo cấu hình bảng shifts; rỗng nếu không xác định được. */
    public static function shiftAt(?string $time = null): string
    {
        $time = substr($time ?: now('Asia/Ho_Chi_Minh')->format('H:i'), 0, 5);
        try {
            $toMinute = static fn (string $v): int => ((int) substr($v, 0, 2) * 60) + (int) substr($v, 3, 2);
            $minute = $toMinute($time);
            foreach (\App\Models\Shift::query()->orderBy('id')->get() as $shift) {
                if (!$shift->start_time || !$shift->end_time) {
                    continue;
                }
                $start = $toMinute((string) $shift->start_time);
                $end = $toMinute((string) $shift->end_time);
                $inside = $start === $end || ($start < $end ? $minute >= $start && $minute < $end : $minute >= $start || $minute < $end);
                if ($inside) {
                    return (string) ($shift->name ?: $shift->id);
                }
            }
        } catch (\Throwable $e) {
            // Không có cấu hình ca: bỏ trống
        }

        return '';
    }

    /** Tên tình trạng buồng phòng theo bảng room_statuses (vd vacant_dirty → Phòng chưa dọn). */
    public static function roomStatusName(?string $code): string
    {
        if ($code === null || $code === '') {
            return '';
        }
        static $cache = null;
        if ($cache === null) {
            try {
                $cache = \Illuminate\Support\Facades\DB::table('room_statuses')->pluck('name_vi', 'code')->all();
            } catch (\Throwable $e) {
                $cache = [];
            }
        }

        return $cache[$code] ?? $code;
    }

    protected static function moduleForScreen(string $screen): string
    {
        return match ($screen) {
            \App\Support\ActivityScreen::ROOM_SCHEDULE, \App\Support\ActivityScreen::REGISTRATION, \App\Support\ActivityScreen::ROOM_LOCK => 'reservation',
            \App\Support\ActivityScreen::ROOM_MAP, \App\Support\ActivityScreen::CHECKOUT, \App\Support\ActivityScreen::DAY_CLOSE => 'frontdesk',
            \App\Support\ActivityScreen::HK_ASSIGN, \App\Support\ActivityScreen::LOST_FOUND, \App\Support\ActivityScreen::INVENTORY,
            \App\Support\ActivityScreen::MENU, \App\Support\ActivityScreen::HK_SERVICE => 'housekeeping',
            \App\Support\ActivityScreen::COMPANY => 'config',
            \App\Support\ActivityScreen::REPORTS => 'reports',
            default => 'other',
        };
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
        $request ??= request();

        // Nhận phòng nhanh: log "Giao phòng nhanh" ghi lúc check-in (dòng 52)
        if ($request?->boolean('quick_checkin')) {
            return new ActivityLog();
        }

        $code = self::bookingCode($booking->id);
        $rooms = $booking->bookingRooms()->with('roomClass')->orderBy('id')->get();
        $roomIds = $rooms->pluck('id')->all();

        // Dòng 3, 14: thông tin đăng ký
        $log = self::record('Add', \App\Support\BookingLogText::bookingInfo($booking), [
            'screen' => \App\Support\ActivityScreen::REGISTRATION,
            'booking_code' => $code,
            'room_code' => $roomIds,
            'target_type' => 'Booking',
            'target_id' => $booking->id,
            'target_label' => $code,
        ], $request);

        // Dòng 4, 15: mỗi phòng thuê một dòng, cột mã phòng là các mã G
        if ($rooms->isNotEmpty()) {
            self::record('Add', $rooms->map(fn ($r) => \App\Support\BookingLogText::roomCreated($r))->implode("\n"), [
                'screen' => \App\Support\ActivityScreen::REGISTRATION,
                'booking_code' => $code,
                'room_code' => $roomIds,
                'target_type' => 'BookingRoom',
                'target_id' => implode(',', $roomIds),
                'target_label' => $code,
            ], $request);
        }

        return $log;
    }

    /**
     * Ghi log xem báo cáo (Chuẩn format HKT Row 1 & Row 71)
     */
    public static function logReportView($report, string $from, string $to, ?Request $request = null): ActivityLog
    {
        // Dòng 1, 71: "Xem báo cáo: "Báo cáo hủy đăng ký"  - Từ ngày 04-09-2026 , - Đến ngày 04-09-2026"
        $reportName = is_object($report) ? ($report->name ?? 'Báo cáo') : (string) $report;

        return self::record('Report', "Xem báo cáo: \"{$reportName}\"  - Từ ngày {$from} , - Đến ngày {$to}", [
            'force_screen' => \App\Support\ActivityScreen::REPORTS,
            'module' => 'reports',
            'target_type' => 'ReportDefinition',
            'target_id' => is_object($report) ? $report->id : null,
            'target_label' => $reportName,
            'new_values' => ['parameters' => ['from_date' => $from, 'to_date' => $to]],
        ], $request);
    }

    /**
     * Ảnh chụp đăng ký + phòng thuê trước khi lưu, dùng cho logBookingChanges().
     */
    public static function snapshotBooking($booking): array
    {
        $booking = $booking instanceof \App\Models\Booking ? $booking : \App\Models\Booking::find($booking);
        if (!$booking) {
            return ['booking' => [], 'rooms' => []];
        }

        return [
            'booking' => $booking->getRawOriginal(),
            'rooms' => \App\Support\BookingLogText::roomSnapshot($booking->bookingRooms()->get()),
        ];
    }

    /**
     * Dòng 16, 17, 18, 24-26, 33: ghi các thay đổi của đăng ký so với ảnh chụp trước khi lưu.
     *  - một dòng "* Cập Nhật Thông Tin Đăng Ký GAL.." chỉ gồm trường thay đổi
     *  - một dòng cho các phòng được cập nhật (cột mã phòng = các mã G đó)
     *  - một dòng cho các phòng thêm mới, một dòng cho thông tin đưa đón
     */
    public static function logBookingChanges($booking, array $snapshot, ?Request $request = null): void
    {
        $booking = $booking instanceof \App\Models\Booking ? $booking : \App\Models\Booking::find($booking);
        if (!$booking) {
            return;
        }
        $code = self::bookingCode($booking->id);
        $fresh = \App\Models\Booking::find($booking->id);
        $oldAttrs = $snapshot['booking'] ?? [];
        $newAttrs = $fresh ? $fresh->getRawOriginal() : [];
        $opts = ['screen' => \App\Support\ActivityScreen::REGISTRATION, 'booking_code' => $code, 'target_type' => 'Booking', 'target_id' => $booking->id, 'target_label' => $code];

        $diff = \App\Support\BookingLogText::bookingDiff($oldAttrs, $newAttrs);
        if ($diff) {
            self::record('Modify', "* Cập Nhật Thông Tin Đăng Ký {$code} : " . implode(' ', $diff), $opts, $request);
        }

        if (array_key_exists('shuttle_info', $newAttrs)) {
            $shuttle = \App\Support\BookingLogText::shuttleDiff($code, $oldAttrs['shuttle_info'] ?? null, $newAttrs['shuttle_info'] ?? null);
            if ($shuttle) {
                self::record('Modify', implode("\n", $shuttle), $opts, $request);
            }
        }

        $oldRooms = $snapshot['rooms'] ?? [];
        $rooms = \App\Models\BookingRoom::where('booking_id', $booking->id)->with('roomClass')->orderBy('id')->get();
        $updated = [];
        $updatedIds = [];
        $created = [];
        $createdIds = [];
        foreach ($rooms as $room) {
            $id = (string) $room->id;
            if (!array_key_exists($id, $oldRooms)) {
                $created[] = \App\Support\BookingLogText::roomCreated($room);
                $createdIds[] = $id;
                continue;
            }
            $line = \App\Support\BookingLogText::roomDiff($id, $oldRooms[$id], $room->getAttributes());
            if ($line !== null) {
                $updated[] = $line;
                $updatedIds[] = $id;
            }
        }
        if ($updated) {
            self::record('Modify', implode("\n", $updated), array_merge($opts, ['room_code' => $updatedIds, 'target_type' => 'BookingRoom', 'target_id' => implode(',', $updatedIds)]), $request);
        }
        if ($created) {
            self::record('Add', implode("\n", $created), array_merge($opts, ['room_code' => $createdIds, 'target_type' => 'BookingRoom', 'target_id' => implode(',', $createdIds)]), $request);
        }
    }

    /**
     * Ghi log khi Cập nhật thông tin Phòng thuê (Chuẩn format HKT)
     */
    public static function logBookingRoomUpdated($bookingRoom, array $oldValues, array $newValues, ?Request $request = null, ?array $bookingBefore = null): ActivityLog
    {
        $request ??= request();
        $code = self::bookingCode($bookingRoom->booking_id);
        $id = (string) $bookingRoom->id;
        $screen = \App\Support\ActivityScreen::label($request, \App\Support\ActivityScreen::REGISTRATION);
        $opts = ['force_screen' => $screen, 'booking_code' => $code, 'target_type' => 'BookingRoom', 'target_id' => $id, 'target_label' => $code];

        // Dòng 7: dòng cập nhật đăng ký chỉ ghi khi ngày đi/số ngày của đăng ký thực sự thay đổi theo phòng
        if ($bookingBefore !== null) {
            $after = \App\Models\Booking::find($bookingRoom->booking_id)?->getRawOriginal() ?? [];
            $diff = \App\Support\BookingLogText::bookingDiff($bookingBefore, $after);
            if ($diff) {
                self::record('Modify', "* Cập Nhật Thông Tin Đăng Ký {$code} : " . implode(' ', $diff), array_merge($opts, ['target_type' => 'Booking', 'target_id' => $bookingRoom->booking_id]), $request);
            }
        }

        $changes = \App\Support\BookingLogText::roomChanges($oldValues, $newValues);
        if (!$changes) {
            return new ActivityLog();
        }

        if ($screen === \App\Support\ActivityScreen::ROOM_MAP && !isset($changes['room_class_id'])) {
            // Dòng 49, 100: "Thay đổi Mã Đăng Ký GAL20 - Phòng: 605 (G0000043)" + từng thay đổi
            $roomNo = $newValues['room_number'] ?? $oldValues['room_number'] ?? $bookingRoom->room_number;
            $lines = ["Thay đổi Mã Đăng Ký {$code} - Phòng: " . ($roomNo ?: 'Chưa gán') . " ({$id})"];
            foreach ($changes as $field => [$o, $n]) {
                $label = $field === 'rate' ? 'Room Rate' : \App\Support\BookingLogText::ROOM_FIELDS[$field];
                $lines[] = "- {$label}: {$o} -> {$n}";
            }
            $desc = implode("\n", $lines);
        } else {
            $desc = \App\Support\BookingLogText::roomDiff($id, $oldValues, $newValues);
        }

        return self::record('Modify', $desc, array_merge($opts, ['room_code' => $id]), $request);
    }

    /**
     * Ghi log Check-in
     */
    public static function logCheckIn($bookingId, \App\Models\BookingRoom $room, ?Request $request = null): ActivityLog
    {
        $request ??= request();
        $code = self::bookingCode($bookingId);
        $opts = [
            'screen' => \App\Support\ActivityScreen::ROOM_MAP,
            'booking_code' => $code,
            'room_code' => $room->id,
            'target_type' => 'BookingRoom',
            'target_id' => $room->id,
            'target_label' => $code,
        ];

        // Dòng 52: nhận phòng nhanh từ Room map, một dòng tóm tắt thay cho log tạo đăng ký + check in
        if ($request?->boolean('quick_checkin')) {
            $desc = "* Giao phòng nhanh cho phòng {$room->room_number} ({$room->id}) từ Room map có:\n" . implode(', ', [
                '- Ngày đến: ' . self::d($room->arrival_date),
                '- Ngày đi: ' . self::d($room->departure_date),
                '- Ngày: ' . (int) ($room->NumOfDays ?? \Carbon\Carbon::parse($room->arrival_date)->diffInDays(\Carbon\Carbon::parse($room->departure_date))),
                '- Giá: ' . self::money(\App\Support\BookingLogText::roomRate($room)),
                '- Người lớn: ' . (int) $room->adults,
                '- Trẻ em: ' . (int) $room->children_qty,
                '- Thêm giường: ' . (int) $room->extra_bed_qty,
                '- Giá thêm giường: ' . self::money($room->extra_bed_rate),
                '- Ăn sáng: ' . ($room->breakfast ? 'Có' : 'Không'),
            ]);

            return self::record('CheckIn', $desc, array_merge($opts, ['force_screen' => \App\Support\ActivityScreen::ROOM_MAP]), $request);
        }

        // Dòng 37: "Check in cho đăng ký GAL21 - các phòng: 906", cột Mã phòng = mã G
        return self::record('CheckIn', "Check in cho đăng ký {$code} - các phòng: " . ($room->room_number ?: $room->id), $opts, $request);
    }

    /**
     * Dòng 62: "* Check Out Cho Đăng Ký GAL16." (check out đăng ký) hoặc
     * "* Check Out Cho Đăng Ký GAL16 - Phòng: 405 (G0000028)" (check out phòng).
     *
     * @param  iterable<\App\Models\BookingRoom>|null  $rooms  null khi check out cấp đăng ký
     */
    public static function logCheckOut($bookingId, ?iterable $rooms, ?Request $request = null): ActivityLog
    {
        $code = self::bookingCode($bookingId);
        $opts = ['screen' => \App\Support\ActivityScreen::CHECKOUT, 'booking_code' => $code, 'target_type' => 'Booking', 'target_id' => $bookingId, 'target_label' => $code];

        if ($rooms === null) {
            return self::record('CheckOut', "* Check Out Cho Đăng Ký {$code}.", $opts, $request);
        }

        $labels = [];
        $ids = [];
        foreach ($rooms as $room) {
            $ids[] = (string) $room->id;
            $labels[] = ($room->room_number ?: 'Chưa gán') . " ({$room->id})";
        }
        if (!$ids) {
            return new ActivityLog();
        }

        return self::record('CheckOut', "* Check Out Cho Đăng Ký {$code} - Phòng: " . implode(', ', $labels), array_merge($opts, [
            'room_code' => $ids,
            'target_type' => 'BookingRoom',
            'target_id' => implode(',', $ids),
        ]), $request);
    }

    /**
     * Ghi log Chuyển phòng (Move Room)
     */
    public static function logRoomMove(\App\Models\BookingRoom $from, \App\Models\BookingRoom $to, array $guestIds, ?string $reason, mixed $oldRate, ?string $oldRoomStatus, ?Request $request = null): ActivityLog
    {
        $code = self::bookingCode($from->booking_id);
        if (!$guestIds) {
            $guestIds = \App\Models\BookingRoomGuest::where('booking_room_id', $to->id)->pluck('guest_id')->all();
        }
        $guests = implode(', ', array_filter(array_map('strval', $guestIds)));
        $side = fn ($room) => ($room->room_number ?: 'Chưa gán') . "({$room->id}" . ($guests !== '' ? " - {$guests}" : '') . ')';

        // Dòng 50, 99: "*Chuyển phòng: 605(G.. - K.., K..) -> 101(G.. - K.., K..) Giá: 540,000 => 600,000 Lý do: abc"
        $desc = "*Chuyển phòng: {$side($from)} -> {$side($to)}";
        if ((float) $oldRate !== (float) $to->rate) {
            $desc .= ' Giá: ' . self::money($oldRate) . ' => ' . self::money($to->rate);
        }
        if ($reason) {
            $desc .= " Lý do: {$reason}";
        }

        // Dòng 99: kèm thay đổi tình trạng buồng phòng của phòng cũ
        if ($oldRoomStatus !== null && $from->room_number) {
            $newStatus = \App\Models\Room::where('room_number', $from->room_number)->value('room_status_code');
            $desc .= "\nCập nhật trạng thái phòng {$from->room_number}. Đổi trạng thái từ: " . self::roomStatusName($oldRoomStatus) . ' -> ' . self::roomStatusName($newStatus);
        }

        return self::record('Modify', $desc, [
            'screen' => \App\Support\ActivityScreen::ROOM_MAP,
            'booking_code' => $code,
            'room_code' => array_unique([(string) $from->id, (string) $to->id]),
            'target_type' => 'BookingRoom',
            'target_id' => $to->id,
            'target_label' => $code,
        ], $request);
    }

    /**
     * Ghi log Khóa sổ / Sang ngày (Day Close / Night Audit)
     */
    public static function logDayClose($oldDate, $newDate, ?Request $request = null): ActivityLog
    {
        $user = ($request ?? request())?->user();
        $desc = '* Sang ngày thành công: Từ ngày ' . \Carbon\Carbon::parse($oldDate)->format('d/m/Y')
            . ' sang ngày ' . \Carbon\Carbon::parse($newDate)->format('d/m/Y') . ' - User: ' . ($user?->username ?? $user?->name ?? '');

        return self::record('DayClose', $desc, [
            'force_screen' => \App\Support\ActivityScreen::DAY_CLOSE,
            'target_type' => 'SystemDateRoll',
            'target_id' => (string) $newDate,
            'system_date' => \Carbon\Carbon::parse($oldDate)->toDateString(),
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
