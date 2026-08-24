<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware bảo vệ route theo permission.
 *
 * Dùng: ->middleware('permission:fo.booking.create')
 * Hoặc nhiều quyền: ->middleware('permission:fo.booking.create,fo.booking.edit')
 */
class RequirePermission
{
    public function handle(Request $request, Closure $next, string ...$permissions): Response
    {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated.',
            ], 401);
        }

        // Super admin bypass
        if ($user->isSuperAdmin()) {
            return $next($request);
        }

        // Lấy branch_id từ request (đã được EnsureBranchAccess set)
        $branchId = $request->attributes->get('_branch_id');

        // Kiểm tra từng permission — thỏa bất kỳ 1 là pass
        foreach ($permissions as $permission) {
            if ($user->hasPermission(trim($permission), $branchId)) {
                return $next($request);
            }
        }

        return response()->json([
            'success' => false,
            'message' => 'Bạn không có quyền thực hiện thao tác này.',
            'required_permissions' => $permissions,
        ], 403);
    }
}
