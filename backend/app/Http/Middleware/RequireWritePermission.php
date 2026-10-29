<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Dòng 406: bảo vệ thao tác ghi (thêm / sửa / xóa) của dữ liệu cấu hình.
 *
 * Request đọc (GET / HEAD / OPTIONS) đi qua vì danh mục cấu hình còn được dùng làm dữ liệu tra cứu
 * ở các màn hình nghiệp vụ (đặt phòng, lễ tân...). Request ghi kiểm tra như middleware 'permission'.
 *
 * Dùng: ->middleware('permission.write:config.rooms.edit,system.setting')
 */
class RequireWritePermission
{
    public function handle(Request $request, Closure $next, string ...$permissions): Response
    {
        if ($request->isMethodSafe()) {
            return $next($request);
        }

        return app(RequirePermission::class)->handle($request, $next, ...$permissions);
    }
}
