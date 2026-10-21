<?php

namespace App\Http\Middleware;

use App\Models\SecuritySetting;
use App\Services\AccessRestrictionService;
use Closure;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;
use Symfony\Component\HttpFoundation\Response;

/**
 * Bảo mật nâng cao: tự đăng xuất sau N giờ kể từ lúc đăng nhập (áp dụng cho mọi tài khoản), và giới hạn truy cập
 * theo IP / khoảng thời gian. Trả 401 (kèm code) để frontend đưa người dùng về trang đăng nhập.
 */
class EnforceAccessRestrictions
{
    public function __construct(private readonly AccessRestrictionService $restrictions)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if (!$user || $request->is('api/logout')) {
            return $next($request);
        }

        $settings = SecuritySetting::current();
        $token = $user->currentAccessToken();

        if ($settings->auto_logout_enabled
            && $token instanceof PersonalAccessToken
            && $token->created_at
            && $token->created_at->copy()->addHours($settings->auto_logout_hours)->isPast()) {
            $token->delete();

            return response()->json([
                'success' => false,
                'code' => 'session_expired',
                'message' => "Phiên đăng nhập đã hết hạn sau {$settings->auto_logout_hours} giờ. Vui lòng đăng nhập lại.",
            ], 401);
        }

        $violation = $this->restrictions->violationMessage($user, (string) $request->ip());
        if ($violation) {
            return response()->json([
                'success' => false,
                'code' => 'access_restricted',
                'message' => $violation,
            ], 401);
        }

        return $next($request);
    }
}
