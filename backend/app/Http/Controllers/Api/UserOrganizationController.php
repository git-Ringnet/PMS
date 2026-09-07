<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PositionBranchRole;
use App\Models\SystemBranch;
use App\Models\User;
use App\Models\UserBranch;
use App\Models\UserBranchPosition;
use App\Models\UserRole;
use App\Models\UserWarehousePermission;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class UserOrganizationController extends Controller
{
    public function show(User $user)
    {
        return response()->json([
            'success' => true,
            'data' => [
                'assignments' => UserBranchPosition::query()
                    ->where('user_id', $user->id)
                    ->with(['branch', 'position.department', 'position.branchRoles.role'])
                    ->orderBy('system_branch_id')
                    ->get(),
                'primary_branch_id' => $user->primary_branch_id,
                'warehouses' => UserWarehousePermission::where('user_id', $user->id)->get(),
            ],
        ]);
    }

    public function sync(Request $request, User $user)
    {
        $system = config('database_domains.system_connection', 'mysql_system');
        $validated = $request->validate([
            'application_codes' => 'nullable|array',
            'application_codes.*' => 'string|max:20',
            'assignments' => 'present|array',
            'assignments.*.system_branch_id' => ['required', 'integer', Rule::exists($system.'.system_branches', 'id')],
            'assignments.*.application_code' => 'required|string|max:20',
            'assignments.*.position_id' => ['required', 'integer', Rule::exists($system.'.positions', 'id')],
            'primary_branch_id' => ['nullable', 'integer', Rule::exists($system.'.system_branches', 'id')],
        ]);

        $normalized = collect($validated['assignments'])->map(fn ($item) => [
            'system_branch_id' => (int) $item['system_branch_id'],
            'application_code' => strtoupper($item['application_code']),
            'position_id' => (int) $item['position_id'],
        ]);
        $applicationCodes = collect($validated['application_codes'] ?? $normalized->pluck('application_code'))
            ->map(fn ($code) => strtoupper($code))->unique()->values();

        if ($applicationCodes->isEmpty()) {
            throw ValidationException::withMessages(['application_codes' => 'Phải xác định ít nhất một ứng dụng cần đồng bộ.']);
        }
        if ($normalized->contains(fn ($item) => !$applicationCodes->contains($item['application_code']))) {
            throw ValidationException::withMessages(['assignments' => 'Danh sách vị trí chứa ứng dụng ngoài phạm vi đồng bộ.']);
        }
        if ($normalized->duplicates(fn ($item) => $item['system_branch_id'].'|'.$item['application_code'])->isNotEmpty()) {
            throw ValidationException::withMessages(['assignments' => 'Mỗi ứng dụng tại một chi nhánh chỉ được chọn một vị trí công việc.']);
        }

        foreach ($normalized as $item) {
            $configured = PositionBranchRole::query()
                ->where('position_id', $item['position_id'])
                ->where('system_branch_id', $item['system_branch_id'])
                ->where('application_code', $item['application_code'])
                ->where('is_active', true)
                ->exists();
            if (!$configured) {
                throw ValidationException::withMessages([
                    'assignments' => 'Vị trí chưa được cấu hình Role cho ứng dụng và chi nhánh đã chọn.',
                ]);
            }
        }

        $remaining = UserBranchPosition::query()
            ->where('user_id', $user->id)
            ->whereNotIn('application_code', $applicationCodes)
            ->get(['system_branch_id', 'application_code', 'position_id']);
        $allAssignments = $remaining->map(fn ($item) => [
            'system_branch_id' => (int) $item->system_branch_id,
            'application_code' => $item->application_code,
            'position_id' => (int) $item->position_id,
        ])->concat($normalized);
        $accessibleBranchIds = $allAssignments->pluck('system_branch_id')->unique()->values();
        $primaryBranchId = $validated['primary_branch_id'] ?? $user->primary_branch_id ?? $accessibleBranchIds->first();

        if ($primaryBranchId && !$accessibleBranchIds->contains((int) $primaryBranchId)) {
            throw ValidationException::withMessages([
                'primary_branch_id' => 'Chi nhánh chính phải thuộc danh sách chi nhánh được phân quyền.',
            ]);
        }

        DB::connection($system)->transaction(function () use (
            $user, $normalized, $applicationCodes, $accessibleBranchIds, $primaryBranchId
        ) {
            UserBranchPosition::where('user_id', $user->id)
                ->whereIn('application_code', $applicationCodes)
                ->delete();
            foreach ($normalized as $item) {
                UserBranchPosition::create(['user_id' => $user->id] + $item);
            }

            UserBranch::where('user_id', $user->id)->delete();
            foreach ($accessibleBranchIds as $branchId) {
                UserBranch::create([
                    'user_id' => $user->id,
                    'system_branch_id' => $branchId,
                    'is_primary' => (int) $branchId === (int) $primaryBranchId,
                ]);
            }

            // Dựng lại bảng quyền cũ từ toàn bộ vị trí để các chức năng chưa nâng cấp vẫn chạy đúng.
            UserRole::where('user_id', $user->id)->whereNotNull('system_branch_id')->delete();
            $allCurrentAssignments = UserBranchPosition::where('user_id', $user->id)->get();
            foreach ($allCurrentAssignments as $item) {
                $roleId = PositionBranchRole::query()
                    ->where('position_id', $item->position_id)
                    ->where('system_branch_id', $item->system_branch_id)
                    ->where('application_code', $item->application_code)
                    ->where('is_active', true)
                    ->value('role_id');
                if ($roleId) {
                    UserRole::firstOrCreate([
                        'user_id' => $user->id,
                        'role_id' => $roleId,
                        'system_branch_id' => $item->system_branch_id,
                    ]);
                }
            }

            $summaryAssignment = UserBranchPosition::query()
                ->where('user_id', $user->id)
                ->where('system_branch_id', $primaryBranchId)
                ->where('application_code', config('database_domains.default_application_code'))
                ->with('position.department')
                ->first();
            $user->update([
                'primary_branch_id' => $primaryBranchId,
                'department_code' => $summaryAssignment?->position?->department?->code ?? $user->department_code,
                'department' => $summaryAssignment?->position?->department?->name ?? $user->department,
                'job_title_code' => $summaryAssignment?->position?->code ?? $user->job_title_code,
                'job_title' => $summaryAssignment?->position?->name ?? $user->job_title,
            ]);
        });

        return response()->json(['success' => true, 'message' => 'Đã cập nhật vị trí và chi nhánh của nhân viên.']);
    }

    public function syncWarehouses(Request $request, User $user)
    {
        $system = config('database_domains.system_connection', 'mysql_system');
        $validated = $request->validate([
            'warehouses' => 'present|array',
            'warehouses.*.system_branch_id' => ['required', 'integer', Rule::exists($system.'.system_branches', 'id')],
            'warehouses.*.warehouse_id' => 'required|integer|min:1',
        ]);

        $warehouses = collect($validated['warehouses'])->map(fn ($item) => [
            'system_branch_id' => (int) $item['system_branch_id'],
            'warehouse_id' => (int) $item['warehouse_id'],
        ]);
        if ($warehouses->duplicates(fn ($item) => $item['system_branch_id'].'|'.$item['warehouse_id'])->isNotEmpty()) {
            throw ValidationException::withMessages([
                'warehouses' => 'Danh sách quyền kho không được chứa dòng trùng lặp.',
            ]);
        }

        $assignedBranchIds = $user->userBranchPositions()
            ->pluck('system_branch_id')
            ->map(fn ($id) => (int) $id)
            ->unique();
        $requestedBranchIds = $warehouses->pluck('system_branch_id')->unique();
        if ($requestedBranchIds->diff($assignedBranchIds)->isNotEmpty()) {
            throw ValidationException::withMessages([
                'warehouses' => 'Chỉ được phân quyền kho tại chi nhánh mà nhân viên được phép truy cập.',
            ]);
        }

        $branches = SystemBranch::query()->whereIn('id', $requestedBranchIds)->get()->keyBy('id');
        foreach ($warehouses->groupBy('system_branch_id') as $branchId => $items) {
            $connection = $branches->get((int) $branchId)?->db_connection;
            if (!$connection || !config('database.connections.'.$connection)) {
                throw ValidationException::withMessages([
                    'warehouses' => 'Chi nhánh '.$branchId.' chưa cấu hình kết nối dữ liệu kho hợp lệ.',
                ]);
            }

            $validWarehouseIds = DB::connection($connection)
                ->table('warehouses')
                ->whereIn('id', $items->pluck('warehouse_id'))
                ->pluck('id')
                ->map(fn ($id) => (int) $id);
            $invalidIds = $items->pluck('warehouse_id')->diff($validWarehouseIds);
            if ($invalidIds->isNotEmpty()) {
                throw ValidationException::withMessages([
                    'warehouses' => 'Kho không tồn tại tại chi nhánh '.$branchId.': '.$invalidIds->implode(', '),
                ]);
            }
        }

        DB::connection($system)->transaction(function () use ($user, $warehouses) {
            UserWarehousePermission::where('user_id', $user->id)->delete();
            foreach ($warehouses as $warehouse) {
                UserWarehousePermission::firstOrCreate(['user_id' => $user->id] + $warehouse);
            }
        });

        return response()->json(['success' => true, 'message' => 'Đã cập nhật quyền kho của nhân viên.']);
    }
}
