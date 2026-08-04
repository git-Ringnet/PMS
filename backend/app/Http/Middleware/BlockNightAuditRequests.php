<?php

namespace App\Http\Middleware;

use Closure;
use App\Models\HotelSetting;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class BlockNightAuditRequests
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Kiểm tra xem hệ thống có đang trong quá trình sang ngày không
        $settings = HotelSetting::first();
        if ($settings && $settings->is_night_audit_running) {
            // Chỉ chặn các request thay đổi dữ liệu (non-GET) 
            // và KHÔNG thuộc endpoint night-audit
            if (!$request->isMethod('GET') && !$request->is('api/night-audit/*')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Hệ thống đang thực hiện sang ngày mới. Vui lòng không thực hiện thao tác này lúc này!'
                ], 503);
            }
        }

        return $next($request);
    }
}
