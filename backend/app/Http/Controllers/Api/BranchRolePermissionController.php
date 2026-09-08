<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BranchRolePermission;
use App\Models\Permission;
use App\Models\Role;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class BranchRolePermissionController extends Controller
{
    public function matrix(Request $request, Role $role)
    {
        $system = config('database_domains.system_connection', 'mysql_system');
        $validated = $request->validate([
            'system_branch_id' => ['required', 'integer', \Illuminate\Validation\Rule::exists($system . '.system_branches', 'id')],
            'application_code' => 'nullable|string|max:20',
        ]);
        $applicationCode = strtoupper($validated['application_code'] ?? config('database_domains.default_application_code'));
        $selectedIds = BranchRolePermission::query()
            ->where('system_branch_id', $validated['system_branch_id'])
            ->where('role_id', $role->id)
            ->pluck('permission_id');

        $permissions = Permission::query()
            ->where('application_code', $applicationCode)
            ->orderBy('module')
            ->orderBy('sort_order')
            ->get()
            ->map(fn (Permission $permission) => [
                ...$permission->toArray(),
                'granted' => $selectedIds->contains($permission->id),
            ])
            ->groupBy('module');

        return response()->json([
            'success' => true,
            'data' => [
                'role' => $role,
                'system_branch_id' => (int) $validated['system_branch_id'],
                'application_code' => $applicationCode,
                'permissions' => $permissions,
            ],
        ]);
    }

    public function sync(Request $request, Role $role)
    {
        $system = config('database_domains.system_connection', 'mysql_system');
        $validated = $request->validate([
            'system_branch_id' => ['required', 'integer', \Illuminate\Validation\Rule::exists($system . '.system_branches', 'id')],
            'application_code' => 'nullable|string|max:20',
            'permission_ids' => 'present|array',
            'permission_ids.*' => ['integer', \Illuminate\Validation\Rule::exists($system . '.permissions', 'id')],
        ]);
        $applicationCode = strtoupper($validated['application_code'] ?? config('database_domains.default_application_code'));
        $selectedPermissions = Permission::query()
            ->where('application_code', $applicationCode)
            ->whereIn('id', $validated['permission_ids'])
            ->get(['id', 'screen_code', 'action']);

        // Quyền Add/Edit/Delete luôn kéo theo View của cùng màn hình.
        $requiredViewIds = Permission::query()
            ->where('application_code', $applicationCode)
            ->where('action', 'view')
            ->whereIn('screen_code', $selectedPermissions->where('action', '!=', 'view')->pluck('screen_code')->filter())
            ->pluck('id');
        $validIds = $selectedPermissions->pluck('id')->merge($requiredViewIds)->unique();

        DB::connection($system)->transaction(function () use ($system, $role, $validated, $validIds, $applicationCode) {
            $applicationPermissionIds = Permission::where('application_code', $applicationCode)->pluck('id');
            BranchRolePermission::query()
                ->where('system_branch_id', $validated['system_branch_id'])
                ->where('role_id', $role->id)
                ->whereIn('permission_id', $applicationPermissionIds)
                ->delete();

            foreach ($validIds as $permissionId) {
                BranchRolePermission::create([
                    'system_branch_id' => $validated['system_branch_id'],
                    'role_id' => $role->id,
                    'permission_id' => $permissionId,
                ]);
            }
        });

        return response()->json(['success' => true, 'message' => 'Đã lưu phân quyền theo chi nhánh.']);
    }

    public function storeScreen(Request $request)
    {
        $validated = $request->validate([
            'application_code' => 'required|string|max:20',
            'module' => 'required|string|max:20',
            'screen_code' => 'required|string|max:50',
            'screen_name' => 'required|string|max:120',
            'path' => 'nullable|string|max:150',
            'screen_type' => 'required|in:screen,report,feature',
        ]);

        $appCode = strtoupper($validated['application_code']);
        $rawScreen = Str::lower($validated['screen_code']);
        $modulePrefix = Str::lower($validated['module']);
        $baseScreen = str_starts_with($rawScreen, $modulePrefix . '.') ? $rawScreen : $modulePrefix . '.' . $rawScreen;

        $appPrefix = Str::lower($appCode);
        $created = [];
        foreach (['view', 'add', 'edit', 'delete'] as $action) {
            $defaultApplication = strtoupper(config('database_domains.default_application_code'));
            $code = ($appCode === $defaultApplication || str_starts_with($baseScreen, $appPrefix . '.'))
                ? $baseScreen . '.' . $action
                : $appPrefix . '.' . $baseScreen . '.' . $action;

            $created[] = Permission::updateOrCreate(
                ['code' => $code],
                [
                    'name' => $validated['screen_name'] . ' - ' . ucfirst($action),
                    'module' => strtoupper($validated['module']),
                    'description' => null,
                    'application_code' => $appCode,
                    'screen_code' => $baseScreen,
                    'screen_name' => $validated['screen_name'],
                    'path' => $validated['path'] ?? null,
                    'action' => $action,
                    'screen_type' => $validated['screen_type'],
                    'sort_order' => (int) Permission::max('sort_order') + 1,
                ]
            );
        }

        return response()->json(['success' => true, 'data' => $created], 201);
    }

    public function copyRole(Request $request, Role $sourceRole)
    {
        $system = config('database_domains.system_connection', 'mysql_system');
        $validated = $request->validate([
            'code' => ['required', 'string', 'max:30', \Illuminate\Validation\Rule::unique($system . '.roles', 'code')],
            'name' => 'required|string|max:100',
            'description' => 'nullable|string|max:255',
            'system_branch_id' => ['required', 'integer', \Illuminate\Validation\Rule::exists($system . '.system_branches', 'id')],
            'application_code' => 'nullable|string|max:20',
        ]);

        $newRole = DB::connection($system)->transaction(function () use ($sourceRole, $validated) {
            $role = Role::create([
                'code' => $validated['code'],
                'name' => $validated['name'],
                'description' => $validated['description'] ?? $sourceRole->description,
                'level' => $sourceRole->level,
                'department_scope' => $sourceRole->department_scope,
                'is_active' => true,
                'allow_historical_date_actions' => $sourceRole->allow_historical_date_actions,
            ]);

            $applicationCode = strtoupper($validated['application_code'] ?? config('database_domains.default_application_code'));
            $permissionIds = BranchRolePermission::query()
                ->where('system_branch_id', $validated['system_branch_id'])
                ->where('role_id', $sourceRole->id)
                ->whereIn('permission_id', Permission::where('application_code', $applicationCode)->pluck('id'))
                ->pluck('permission_id');

            foreach ($permissionIds as $permissionId) {
                BranchRolePermission::create([
                    'system_branch_id' => $validated['system_branch_id'],
                    'role_id' => $role->id,
                    'permission_id' => $permissionId,
                ]);
            }

            return $role;
        });

        return response()->json(['success' => true, 'data' => $newRole], 201);
    }
}
