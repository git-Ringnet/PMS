<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Permission;
use App\Models\Role;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class RoleController extends Controller
{
    public function index(Request $request)
    {
        $roles = Role::with('permissions')
            ->when($request->filled('search'), fn ($query) => $query->where('name', 'like', '%'.$request->search.'%'))
            ->orderBy('level')
            ->orderBy('name')
            ->get();

        return response()->json(['success' => true, 'data' => $roles]);
    }

    public function store(Request $request)
    {
        $system = config('database_domains.system_connection', 'mysql_system');
        $validator = Validator::make($request->all(), [
            'code' => ['required', 'string', 'max:30', \Illuminate\Validation\Rule::unique($system . '.roles', 'code')],
            'name' => 'required|string|max:100',
            'description' => 'nullable|string|max:255',
            'level' => 'integer|in:1,2,3',
            'department_scope' => 'nullable|string|max:20',
            'allow_historical_date_actions' => 'nullable|boolean',
        ]);
        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $role = Role::create($validator->validated() + ['is_active' => true]);
        return response()->json(['success' => true, 'data' => $role], 201);
    }

    public function update(Request $request, int $id)
    {
        $role = Role::findOrFail($id);
        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|string|max:100',
            'description' => 'nullable|string|max:255',
            'is_active' => 'sometimes|boolean',
            'department_scope' => 'nullable|string|max:20',
            'allow_historical_date_actions' => 'sometimes|boolean',
        ]);
        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $role->update($validator->validated());
        return response()->json(['success' => true, 'data' => $role->fresh()]);
    }

    public function destroy(int $id)
    {
        $role = Role::findOrFail($id);
        $builtIn = ['super_admin', 'branch_admin', 'fo_manager', 'fo_staff', 'hk_manager', 'hk_staff', 'fb_manager', 'fb_staff', 'mgmt'];
        if (in_array($role->code, $builtIn, true)) {
            return response()->json(['success' => false, 'message' => 'Không thể xóa vai trò hệ thống mặc định'], 403);
        }
        if ($role->positionAssignments()->exists()) {
            return response()->json(['success' => false, 'message' => 'Role đang được gán cho vị trí công việc.'], 422);
        }

        $role->delete();
        return response()->json(['success' => true, 'message' => 'Đã xóa vai trò']);
    }

    public function getPermissions(int $id)
    {
        $role = Role::with('permissions')->findOrFail($id);
        return response()->json(['success' => true, 'data' => $role->permissions]);
    }

    /**
     * API cũ: cập nhật bộ quyền mặc định. Quyền vận hành theo chi nhánh dùng branch-permissions.
     */
    public function syncPermissions(Request $request, int $id)
    {
        $system = config('database_domains.system_connection', 'mysql_system');
        $role = Role::findOrFail($id);
        $validator = Validator::make($request->all(), [
            'permission_ids' => 'required|array',
            'permission_ids.*' => ['integer', \Illuminate\Validation\Rule::exists($system . '.permissions', 'id')],
        ]);
        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        DB::connection($system)->transaction(function () use ($role, $request) {
            $role->permissions()->sync($request->permission_ids);
        });

        return response()->json([
            'success' => true,
            'message' => 'Đã cập nhật bộ quyền mặc định cho vai trò',
            'data' => $role->load('permissions'),
        ]);
    }

    public function allPermissions(Request $request)
    {
        $permissions = Permission::query()
            ->when($request->filled('application_code'), fn ($query) =>
                $query->where('application_code', strtoupper($request->application_code))
            )
            ->orderBy('module')
            ->orderBy('sort_order')
            ->get()
            ->groupBy('module');

        return response()->json(['success' => true, 'data' => $permissions]);
    }
}
