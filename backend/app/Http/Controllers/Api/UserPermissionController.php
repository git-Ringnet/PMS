<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SystemBranch;
use App\Models\User;
use App\Models\UserBranch;
use App\Models\UserRole;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class UserPermissionController extends Controller
{
    /** Lấy branches + roles của một user */
    public function getUserPermissions(int $userId)
    {
        $user = User::with([
            'branches',
            'roles.permissions',
            'userRoles.role',
            'userRoles.branch',
        ])->findOrFail($userId);

        return response()->json([
            'success' => true,
            'data'    => [
                'user_id'         => $user->id,
                'branches'        => $user->branches->map(fn($b) => [
                    'id'         => $b->id,
                    'code'       => $b->code,
                    'name'       => $b->name,
                    'is_primary' => (bool) $b->pivot->is_primary,
                ]),
                'roles'           => $user->userRoles->map(fn($ur) => [
                    'user_role_id'     => $ur->id,
                    'role_id'          => $ur->role_id,
                    'role_code'        => $ur->role?->code,
                    'role_name'        => $ur->role?->name,
                    'system_branch_id' => $ur->system_branch_id,
                    'branch_code'      => $ur->branch?->code,
                    'branch_name'      => $ur->branch?->name,
                ]),
                'all_permissions' => $user->allPermissions()->values(),
            ],
        ]);
    }

    /** Gán/cập nhật branches cho user */
    public function syncBranches(Request $request, int $userId)
    {
        $user = User::findOrFail($userId);
        $v = Validator::make($request->all(), [
            'branches'              => 'required|array',
            'branches.*.branch_id'  => 'required|integer|exists:system_branches,id',
            'branches.*.is_primary' => 'boolean',
        ]);
        if ($v->fails()) return response()->json(['success' => false, 'errors' => $v->errors()], 422);

        // Reset & sync branches
        UserBranch::where('user_id', $userId)->delete();
        $primaryId = null;
        foreach ($request->branches as $b) {
            UserBranch::create([
                'user_id'          => $userId,
                'system_branch_id' => $b['branch_id'],
                'is_primary'       => $b['is_primary'] ?? false,
            ]);
            if ($b['is_primary'] ?? false) $primaryId = $b['branch_id'];
        }

        // Cập nhật primary_branch_id trên user
        if ($primaryId) {
            $user->update(['primary_branch_id' => $primaryId]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Đã cập nhật chi nhánh cho nhân viên',
            'data'    => $user->fresh()->branches,
        ]);
    }

    /** Gán/cập nhật roles cho user (per branch) */
    public function syncRoles(Request $request, int $userId)
    {
        $user = User::findOrFail($userId);
        $v = Validator::make($request->all(), [
            'roles'                      => 'required|array',
            'roles.*.role_id'            => 'required|integer|exists:roles,id',
            'roles.*.system_branch_id'   => 'nullable|integer|exists:system_branches,id',
        ]);
        if ($v->fails()) return response()->json(['success' => false, 'errors' => $v->errors()], 422);

        // Reset & sync roles
        UserRole::where('user_id', $userId)->delete();
        foreach ($request->roles as $r) {
            UserRole::updateOrCreate(
                [
                    'user_id'          => $userId,
                    'role_id'          => $r['role_id'],
                    'system_branch_id' => $r['system_branch_id'] ?? null,
                ],
                []
            );
        }

        return response()->json([
            'success' => true,
            'message' => 'Đã cập nhật vai trò cho nhân viên',
        ]);
    }

    /** Danh sách chi nhánh (cho select trong UI) */
    public function listBranches()
    {
        $branches = SystemBranch::where('is_active', true)
            ->orderBy('code')
            ->get(['id', 'code', 'name', 'address', 'organization_type', 'db_connection']);

        return response()->json(['success' => true, 'data' => $branches]);
    }
}
