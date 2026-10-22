<?php

namespace App\Services;

use App\Models\SecurityAllowedIp;
use App\Models\SecurityExemptUser;
use App\Models\SecuritySetting;
use App\Models\SecurityTimeWindow;
use App\Models\User;
use Carbon\Carbon;

/**
 * Giới hạn truy cập theo địa chỉ IP và theo khoảng thời gian (Bảo mật nâng cao).
 * Super admin luôn được miễn để không tự khóa hệ thống.
 */
class AccessRestrictionService
{
    public const TIMEZONE = 'Asia/Ho_Chi_Minh';

    public function isExempt(?User $user): bool
    {
        if (!$user) {
            return false;
        }

        return $user->isSuperAdmin()
            || SecurityExemptUser::where('user_id', $user->id)->exists();
    }

    /**
     * Trả về thông báo lỗi nếu người dùng bị chặn, null nếu được phép.
     */
    public function violationMessage(?User $user, string $ip, ?Carbon $now = null): ?string
    {
        $settings = SecuritySetting::current();
        if (!$settings->ip_restriction_enabled && !$settings->time_restriction_enabled) {
            return null;
        }
        if ($this->isExempt($user)) {
            return null;
        }

        if ($settings->ip_restriction_enabled && !$this->isIpAllowed($ip)) {
            return 'Địa chỉ IP của bạn không được phép truy cập hệ thống. Vui lòng sử dụng mạng nội bộ của khách sạn hoặc liên hệ quản trị viên.';
        }
        if ($settings->time_restriction_enabled && !$this->isWithinTimeWindows($now)) {
            return 'Hiện tại không nằm trong khoảng thời gian được phép truy cập hệ thống. Vui lòng liên hệ quản trị viên.';
        }

        return null;
    }

    public function isIpAllowed(string $ip): bool
    {
        foreach (SecurityAllowedIp::pluck('ip') as $allowed) {
            if (self::ipMatches($ip, (string) $allowed)) {
                return true;
            }
        }

        return false;
    }

    public function isWithinTimeWindows(?Carbon $now = null): bool
    {
        $now = ($now ?? Carbon::now())->copy()->timezone(self::TIMEZONE);
        $day = $now->dayOfWeekIso;
        $time = $now->format('H:i');

        foreach (SecurityTimeWindow::all() as $window) {
            if (in_array($day, $window->dayNumbers(), true)
                && $time >= $window->start_time && $time <= $window->end_time) {
                return true;
            }
        }

        return false;
    }

    /** Khớp một IP với địa chỉ đơn hoặc dải CIDR (IPv4 / IPv6). */
    public static function ipMatches(string $ip, string $rule): bool
    {
        $ip = trim($ip);
        $rule = trim($rule);
        if ($ip === '' || $rule === '') {
            return false;
        }
        if (!str_contains($rule, '/')) {
            return self::normalizeIp($ip) === self::normalizeIp($rule);
        }

        [$network, $bits] = explode('/', $rule, 2);
        $ipBin = @inet_pton($ip);
        $netBin = @inet_pton(trim($network));
        if ($ipBin === false || $netBin === false || strlen($ipBin) !== strlen($netBin) || !ctype_digit($bits)) {
            return false;
        }

        $bits = (int) $bits;
        if ($bits > strlen($netBin) * 8) {
            return false;
        }

        $fullBytes = intdiv($bits, 8);
        if ($fullBytes > 0 && substr($ipBin, 0, $fullBytes) !== substr($netBin, 0, $fullBytes)) {
            return false;
        }
        $remainder = $bits % 8;
        if ($remainder === 0) {
            return true;
        }
        $mask = (0xFF << (8 - $remainder)) & 0xFF;

        return (ord($ipBin[$fullBytes]) & $mask) === (ord($netBin[$fullBytes]) & $mask);
    }

    /** Địa chỉ hợp lệ: IP đơn hoặc CIDR (IPv4 /0-32, IPv6 /0-128). */
    public static function isValidRule(string $rule): bool
    {
        $rule = trim($rule);
        if (!str_contains($rule, '/')) {
            return filter_var($rule, FILTER_VALIDATE_IP) !== false;
        }

        [$network, $bits] = explode('/', $rule, 2);
        if (!ctype_digit($bits)) {
            return false;
        }
        $isV4 = filter_var($network, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false;
        $isV6 = filter_var($network, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) !== false;

        return ($isV4 && (int) $bits <= 32) || ($isV6 && (int) $bits <= 128);
    }

    private static function normalizeIp(string $ip): string
    {
        $bin = @inet_pton($ip);

        return $bin === false ? strtolower($ip) : bin2hex($bin);
    }
}
