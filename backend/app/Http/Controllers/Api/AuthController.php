<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    /**
     * Standard email/password login.
     */
    /**
     * Standard email/password login.
     */
    public function login(Request $request)
    {
        \Illuminate\Support\Facades\Log::info('Login attempt', [
            'username' => $request->input('username'),
            'ip' => $request->ip(),
        ]);
        $credentials = $request->validate([
            'username' => 'required|string',
            'password' => 'required|string',
        ]);

        $loginInput = trim((string) $request->input('username'));
        $password = (string) $request->input('password');

        $user = User::where('username', $loginInput)
            ->orWhere('email', $loginInput)
            ->first();

        if (!$user || !Hash::check($password, $user->password)) {
            \App\Services\ActivityLogService::logLogin($request, $user, false);
            return response()->json([
                'message' => 'Tên đăng nhập hoặc mật khẩu không chính xác.'
            ], 422);
        }

        Auth::login($user);

        $user = Auth::user();
        if (!$user->is_active_user) {
            Auth::logout();
            \App\Services\ActivityLogService::logLogin($request, $user, false);
            return response()->json([
                'message' => 'Tài khoản của bạn đã bị khóa hoặc ngừng sử dụng.'
            ], 403);
        }

        $user->load('setting');
        $token = $user->createToken('auth_token')->plainTextToken;
        \App\Services\ActivityLogService::logLogin($request, $user, true);

        // Load branches & permissions cho frontend
        $user->load(['branches', 'userRoles.role', 'userRoles.branch', 'userBranchPositions.branch']);

        // Tổng hợp danh sách chi nhánh (kết hợp cả user_branches và user_branch_positions)
        $accessibleBranches = $user->isSuperAdmin()
            ? \App\Models\SystemBranch::where('is_active', true)->get()
            : $user->branches;

        if (!$user->isSuperAdmin() && $accessibleBranches->isEmpty()) {
            $branchIdsFromPositions = $user->userBranchPositions->pluck('system_branch_id')->unique();
            $accessibleBranches = \App\Models\SystemBranch::whereIn('id', $branchIdsFromPositions)->where('is_active', true)->get();
        }

        $branches = $accessibleBranches->map(fn($b) => [
            'id'            => $b->id,
            'code'          => $b->code,
            'name'          => $b->name,
            'address'       => $b->address,
            'is_primary'    => (int) $b->id === (int) ($user->primary_branch_id ?? $accessibleBranches->first()?->id),
            'db_connection' => $b->db_connection,
        ]);
        $activeBranch = $branches->firstWhere('is_primary', true) ?? $branches->first();
        $branchId     = $activeBranch ? $activeBranch['id'] : null;
        $permissions  = $user->allPermissionsForBranch($branchId);

        // Ưu tiên nạp roles từ positions theo schema mới, fallback sang userRoles
        $ubpRoles = $user->userBranchPositions
            ->when($branchId, fn($c) => $c->where('system_branch_id', $branchId))
            ->flatMap(function ($ubp) {
                $role = \App\Models\PositionBranchRole::where('position_id', $ubp->position_id)
                    ->where('system_branch_id', $ubp->system_branch_id)
                    ->where('application_code', $ubp->application_code)
                    ->where('is_active', true)
                    ->with('role')
                    ->first()?->role;

                return $role ? [[
                    'role_code'        => $role->code,
                    'role_name'        => $role->name,
                    'system_branch_id' => $ubp->system_branch_id,
                    'application_code' => $ubp->application_code,
                ]] : [];
            });

        $roles = $ubpRoles->isNotEmpty()
            ? $ubpRoles->values()
            : $user->userRoles->map(fn($ur) => [
                'role_code'        => $ur->role?->code,
                'role_name'        => $ur->role?->name,
                'system_branch_id' => $ur->system_branch_id,
            ]);

        return response()->json([
            'token'         => $token,
            'user'          => $user,
            'permissions'   => $permissions,
            'branches'      => $branches,
            'active_branch' => $activeBranch,
            'roles'         => $roles,
        ]);
    }

    /**
     * Logout and revoke tokens.
     */
    public function logout(Request $request)
    {
        $user = $request->user();
        \App\Services\ActivityLogService::logLogout($request, $user);
        $user->currentAccessToken()->delete();

        return response()->json([
            'message' => 'Đăng xuất thành công.'
        ]);
    }

    /**
     * Get authenticated user — trả kèm permissions/branches/roles để frontend refresh.
     */
    public function me(Request $request)
    {
        $user = $request->user();
        $user->load(['setting', 'branches', 'userRoles.role', 'userRoles.branch', 'userBranchPositions.branch']);

        $accessibleBranches = $user->isSuperAdmin()
            ? \App\Models\SystemBranch::where('is_active', true)->get()
            : $user->branches;

        if (!$user->isSuperAdmin() && $accessibleBranches->isEmpty()) {
            $branchIdsFromPositions = $user->userBranchPositions->pluck('system_branch_id')->unique();
            $accessibleBranches = \App\Models\SystemBranch::whereIn('id', $branchIdsFromPositions)->where('is_active', true)->get();
        }

        $branches = $accessibleBranches->map(fn($b) => [
            'id'            => $b->id,
            'code'          => $b->code,
            'name'          => $b->name,
            'address'       => $b->address,
            'is_primary'    => (int) $b->id === (int) ($user->primary_branch_id ?? $accessibleBranches->first()?->id),
            'db_connection' => $b->db_connection,
        ]);

        // Lấy branchId từ header (khi frontend đã chọn branch cụ thể)
        $branchId = null;
        $headerBranchId = $request->header('X-Branch-Id');
        if ($headerBranchId) {
            $branchId = (int) $headerBranchId;
        } else {
            $activeBranch = $branches->firstWhere('is_primary', true) ?? $branches->first();
            $branchId = $activeBranch ? $activeBranch['id'] : null;
        }

        $permissions = $user->allPermissionsForBranch($branchId);

        $ubpRoles = $user->userBranchPositions
            ->when($branchId, fn($c) => $c->where('system_branch_id', $branchId))
            ->flatMap(function ($ubp) {
                $role = \App\Models\PositionBranchRole::where('position_id', $ubp->position_id)
                    ->where('system_branch_id', $ubp->system_branch_id)
                    ->where('application_code', $ubp->application_code)
                    ->where('is_active', true)
                    ->with('role')
                    ->first()?->role;

                return $role ? [[
                    'role_code'        => $role->code,
                    'role_name'        => $role->name,
                    'system_branch_id' => $ubp->system_branch_id,
                    'application_code' => $ubp->application_code,
                ]] : [];
            });

        $roles = $ubpRoles->isNotEmpty()
            ? $ubpRoles->values()
            : $user->userRoles->map(fn($ur) => [
                'role_code'        => $ur->role?->code,
                'role_name'        => $ur->role?->name,
                'system_branch_id' => $ur->system_branch_id,
            ]);

        $activeBranch = $branches->firstWhere('id', $branchId) ?? $branches->firstWhere('is_primary', true) ?? $branches->first();

        return response()->json([
            'user'          => $user,
            'permissions'   => $permissions,
            'branches'      => $branches,
            'active_branch' => $activeBranch,
            'roles'         => $roles,
        ]);
    }

    /**
     * Tự đổi mật khẩu cho user đã đăng nhập.
     */
    public function changePassword(Request $request)
    {
        $request->validate([
            'current_password' => 'required|string',
            'new_password'     => 'required|string|min:6|confirmed',
        ], [
            'current_password.required' => 'Vui lòng nhập mật khẩu hiện tại.',
            'new_password.required'     => 'Vui lòng nhập mật khẩu mới.',
            'new_password.min'          => 'Mật khẩu mới phải có ít nhất 6 ký tự.',
            'new_password.confirmed'    => 'Xác nhận mật khẩu mới không khớp.',
        ]);

        $user = $request->user();
        if (!Hash::check($request->current_password, $user->password)) {
            return response()->json([
                'message' => 'Mật khẩu hiện tại không chính xác.',
                'errors'  => ['current_password' => ['Mật khẩu hiện tại không chính xác.']]
            ], 422);
        }

        $user->update([
            'password'             => Hash::make($request->new_password),
            'must_change_password' => false,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Đổi mật khẩu thành công.',
        ]);
    }
}
