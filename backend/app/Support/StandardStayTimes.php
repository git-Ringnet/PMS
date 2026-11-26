<?php

namespace App\Support;

use App\Models\HotelConfig;

/**
 * Giờ nhận phòng / trả phòng tiêu chuẩn của khách sạn
 * (Cấu hình hệ thống › Định nghĩa khách sạn › Thông tin khách sạn).
 * Chưa cấu hình hoặc giá trị sai thì dùng 14:00 / 12:00 như trước.
 */
final class StandardStayTimes
{
    public const CHECK_IN_KEY = 'StandardCheckInTime';
    public const CHECK_OUT_KEY = 'StandardCheckOutTime';
    public const DEFAULT_CHECK_IN = '14:00:00';
    public const DEFAULT_CHECK_OUT = '12:00:00';

    /** Giờ nhận phòng tiêu chuẩn dạng HH:MM:SS. */
    public static function checkIn(): string
    {
        return self::read(self::CHECK_IN_KEY, self::DEFAULT_CHECK_IN);
    }

    /** Giờ trả phòng tiêu chuẩn dạng HH:MM:SS. */
    public static function checkOut(): string
    {
        return self::read(self::CHECK_OUT_KEY, self::DEFAULT_CHECK_OUT);
    }

    /** Chuẩn hóa "H:MM", "HH:MM" hoặc "HH:MM:SS" về HH:MM:SS; sai định dạng trả null. */
    public static function normalize(?string $value): ?string
    {
        $value = trim((string) $value);
        if (!preg_match('/^(\d{1,2}):(\d{2})(?::(\d{2}))?$/', $value, $m)) {
            return null;
        }
        $hour = (int) $m[1];
        $minute = (int) $m[2];
        $second = isset($m[3]) ? (int) $m[3] : 0;
        if ($hour > 23 || $minute > 59 || $second > 59) {
            return null;
        }

        return sprintf('%02d:%02d:%02d', $hour, $minute, $second);
    }

    private static function read(string $key, string $default): string
    {
        try {
            $value = HotelConfig::where('name', $key)->value('value');
        } catch (\Throwable) {
            return $default;
        }

        return self::normalize($value) ?? $default;
    }
}
