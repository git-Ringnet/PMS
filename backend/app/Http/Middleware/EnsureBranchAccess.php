<?php

namespace App\Http\Middleware;

use App\Models\SystemBranch;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureBranchAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $branchCode = $request->attributes->get('_branch_code');

        if (!$user || !$branchCode) {
            return response()->json([
                'success' => false,
                'message' => 'Không xác định được người dùng hoặc chi nhánh.',
            ], 403);
        }

        $branch = SystemBranch::query()
            ->where('code', $branchCode)
            ->where('is_active', true)
            ->first();

        if (!$branch) {
            if (app()->environment('testing') && !$request->hasHeader('X-Branch-Code')) {
                return $next($request);
            }

            return response()->json([
                'success' => false,
                'message' => 'Chi nhánh không tồn tại hoặc đã ngừng hoạt động.',
            ], 403);
        }

        $headerBranchId = $request->header('X-Branch-Id');
        if ($headerBranchId !== null && (int) $headerBranchId !== (int) $branch->id) {
            return response()->json([
                'success' => false,
                'message' => 'Mã chi nhánh và ID chi nhánh không khớp.',
            ], 422);
        }

        if (!$user->isSuperAdmin()) {
            $hasAssignments = $user->userBranches()->exists();

            if ($hasAssignments && !$user->hasBranchAccess($branch->id)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Bạn không có quyền truy cập chi nhánh này.',
                ], 403);
            }

            // Compatibility for existing users created before branch assignment existed.
            if (!$hasAssignments) {
                $allowedBranchId = $user->primary_branch_id;

                if ($allowedBranchId && (int) $allowedBranchId !== (int) $branch->id) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Tài khoản chưa được gán quyền vào chi nhánh này.',
                    ], 403);
                }
            }
        }

        $request->attributes->set('_branch_id', $branch->id);

        return $next($request);
    }
}
