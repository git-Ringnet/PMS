<?php

namespace App\Support;

use Illuminate\Http\Request;

/**
 * Xác định "Màn hình thao tác" cho lịch sử thao tác (cột B sheet GHI LOG).
 *
 * Frontend gửi header X-Screen = tab đang mở (vd room-plan, create-res); nếu thiếu thì đọc
 * tham số ?tab= trong Referer. Không dựa vào URL API vì cùng một API được gọi từ nhiều màn hình.
 */
class ActivityScreen
{
    public const ROOM_SCHEDULE = 'RoomSchedule';
    public const REGISTRATION = 'Tạo đăng ký';
    public const ROOM_MAP = 'Room map';
    public const CHECKOUT = 'Trả phòng';
    public const COMPANY = 'Công ty';
    public const DAY_CLOSE = 'Sang ngày';
    public const REPORTS = 'Báo cáo';
    public const HK_ASSIGN = 'In Phân công phòng';
    public const LOST_FOUND = 'Quản lý đồ thất lạc';
    public const INVENTORY = 'Tồn kho';
    public const MENU = 'Tạo Menu';
    public const HK_SERVICE = 'Thêm DV Buồng phòng';
    public const ROOM_LOCK = 'Khóa phòng';

    /** tab frontend → nhãn màn hình. */
    protected const TAB_MAP = [
        'room-plan' => self::ROOM_SCHEDULE,
        'create-res' => self::REGISTRATION,
        'room-map' => self::ROOM_MAP,
        'manage-rooms' => self::ROOM_MAP,
        'checkout' => self::CHECKOUT,
        'company' => self::COMPANY,
        'day-close' => self::DAY_CLOSE,
        'reports' => self::REPORTS,
        'print-tasks' => self::HK_ASSIGN,
        'lost-found' => self::LOST_FOUND,
        'inventory' => self::INVENTORY,
        'create-menu' => self::MENU,
        'add-service' => self::HK_SERVICE,
        'lock-room' => self::ROOM_LOCK,
        'search' => 'Tìm kiếm chung',
    ];

    public static function tab(?Request $request): ?string
    {
        if (!$request) {
            return null;
        }

        $header = trim((string) $request->header('X-Screen', ''));
        if ($header !== '') {
            return strtolower($header);
        }

        $referer = (string) $request->header('referer', '');
        if ($referer !== '' && preg_match('/[?&]tab=([a-z0-9_-]+)/i', $referer, $m)) {
            return strtolower($m[1]);
        }

        return null;
    }

    /** Nhãn màn hình theo request; $fallback khi không xác định được tab. */
    public static function label(?Request $request, ?string $fallback = null): ?string
    {
        $tab = self::tab($request);

        return ($tab !== null ? (self::TAB_MAP[$tab] ?? null) : null) ?? $fallback;
    }

    /** Bộ phận thao tác theo phân hệ đang mở: FO / SALE / HK / MANAGE. */
    public static function department(?Request $request): string
    {
        $module = strtolower(trim((string) $request?->header('X-Module', '')));
        if ($module === '' && $request) {
            $path = (string) parse_url((string) $request->header('referer', ''), PHP_URL_PATH);
            $module = strtolower(explode('/', trim($path, '/'))[0] ?? '');
        }

        return match ($module) {
            'reservation' => 'SALE',
            'housekeeping' => 'HK',
            'reports', 'management', 'pms' => 'MANAGE',
            'fnb' => 'FB',
            default => 'FO',
        };
    }

    public static function fromTab(?string $tab): ?string
    {
        return $tab === null ? null : (self::TAB_MAP[strtolower($tab)] ?? null);
    }
}
