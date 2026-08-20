<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Permission;
use App\Models\Role;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class RoleController extends Controller
{
    /** Danh sách tất cả roles */
    public function index(Request $request)
    {
        $roles = Role::with('permissions')
            ->when($request->filled('search'), fn($q) => $q->where('name', 'like', '%' . $request->search . '%'))
            ->orderBy('level')->orderBy('name')
            ->get();

        return response()->json(['success' => true, 'data' => $roles]);
    }

    /** Tạo role mới */
    public function store(Request $request)
    {
        $v = Validator::make($request->all(), [
            'code'             => 'required|string|max:30|unique:roles,code',
            'name'             => 'required|string|max:100',
            'description'      => 'nullable|string|max:255',
            'level'            => 'integer|in:1,2,3',
            'department_scope' => 'nullable|string|in:FO,HK,FB,MGMT',
        ]);
        if ($v->fails()) return response()->json(['success' => false, 'errors' => $v->errors()], 422);

        $role = Role::create($v->validated() + ['is_active' => true]);
        return response()->json(['success' => true, 'data' => $role], 201);
    }

    /** Sửa role */
    public function update(Request $request, int $id)
    {
        $role = Role::findOrFail($id);
        $v = Validator::make($request->all(), [
            'name'             => 'string|max:100',
            'description'      => 'nullable|string|max:255',
            'is_active'        => 'boolean',
            'department_scope' => 'nullable|string|in:FO,HK,FB,MGMT',
        ]);
        if ($v->fails()) return response()->json(['success' => false, 'errors' => $v->errors()], 422);

        $role->update($v->validated());
        return response()->json(['success' => true, 'data' => $role]);
    }

    /** Xóa role (chỉ custom role, không xóa built-in) */
    public function destroy(int $id)
    {
        $role = Role::findOrFail($id);
        $builtIn = ['super_admin','branch_admin','fo_manager','fo_staff','hk_manager','hk_staff','fb_manager','fb_staff','mgmt'];
        if (in_array($role->code, $builtIn)) {
            return response()->json(['success' => false, 'message' => 'Không thể xóa vai trò hệ thống mặc định'], 403);
        }
        $role->delete();
        return response()->json(['success' => true, 'message' => 'Đã xóa vai trò']);
    }

    /** Lấy danh sách permissions của một role */
    public function getPermissions(int $id)
    {
        $role = Role::with('permissions')->findOrFail($id);
        return response()->json(['success' => true, 'data' => $role->permissions]);
    }

    /** Gán/cập nhật permissions cho role */
    public function syncPermissions(Request $request, int $id)
    {
        $role = Role::findOrFail($id);
        $v = Validator::make($request->all(), [
            'permission_ids'   => 'required|array',
            'permission_ids.*' => 'integer|exists:permissions,id',
        ]);
        if ($v->fails()) return response()->json(['success' => false, 'errors' => $v->errors()], 422);

        $role->permissions()->sync($request->permission_ids);
        return response()->json([
            'success' => true,
            'message' => 'Đã cập nhật quyền cho vai trò',
            'data'    => $role->load('permissions'),
        ]);
    }

    /** Danh sách tất cả permissions (theo module) */
    public function allPermissions()
    {
        $permissions = Permission::orderBy('module')->orderBy('name')->get()
            ->groupBy('module');
        return response()->json(['success' => true, 'data' => $permissions]);
    }
}
