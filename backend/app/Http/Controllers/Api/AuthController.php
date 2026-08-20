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
    public function login(Request $request)
    {
        \Illuminate\Support\Facades\Log::info('Login attempt', $request->all());
        $credentials = $request->validate([
            'username' => 'required|string',
            'password' => 'required|string',
        ]);

        if (!Auth::attempt($credentials)) {
            $attemptedUser = User::where('username', $request->input('username'))->first();
            \App\Services\ActivityLogService::logLogin($request, $attemptedUser, false);
            return response()->json([
                'message' => 'Tên đăng nhập hoặc mật khẩu không chính xác.'
            ], 422);
        }

        $user = Auth::user();
        $user->load('setting');
        $token = $user->createToken('auth_token')->plainTextToken;
        \App\Services\ActivityLogService::logLogin($request, $user, true);

        // Load branches & permissions cho frontend
        $user->load(['branches', 'userRoles.role', 'userRoles.branch']);
        $branches = $user->branches->map(fn($b) => [
            'id'         => $b->id,
            'code'       => $b->code,
            'name'       => $b->name,
            'address'    => $b->address,
            'is_primary' => (bool) $b->pivot->is_primary,
            'db_connection' => $b->db_connection,
        ]);
        $activeBranch = $branches->firstWhere('is_primary', true) ?? $branches->first();
        $branchId     = $activeBranch ? $activeBranch['id'] : null;
        $permissions  = $user->allPermissions($branchId)->values();
        $roles        = $user->userRoles->map(fn($ur) => [
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
     * Get authenticated user.
     */
    public function me(Request $request)
    {
        $user = $request->user();
        $user->load('setting');
        return response()->json($user);
    }
}
