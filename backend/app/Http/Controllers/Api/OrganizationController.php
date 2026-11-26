<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\OrganizationDepartment;
use App\Models\Position;
use App\Models\PositionBranchRole;
use App\Models\UserBranchPosition;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;

class OrganizationController extends Controller
{
    public function index()
    {
        $this->syncLegacyDepartments();

        $departments = OrganizationDepartment::query()
            ->with([
                'positions.branchRoles.branch',
                'positions.branchRoles.role',
                'positions.userAssignments.user',
            ])
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        return response()->json(['success' => true, 'data' => $departments]);
    }

    private function syncLegacyDepartments(): void
    {
        if (!Schema::hasTable('departments')) {
            return;
        }

        $departments = Department::query()->get(['code', 'name', 'phone', 'show']);
        // Dòng 469: chỉ nhập bộ phận còn thiếu. Không ghi đè tên đã sửa và không tạo lại bộ phận đã xóa (soft delete).
        $known = OrganizationDepartment::withTrashed()->pluck('code')->map(fn ($c) => strtoupper($c))->all();
        foreach ($departments as $index => $department) {
            $code = strtoupper(trim($department->code));
            if (in_array($code, $known, true)) {
                continue;
            }
            OrganizationDepartment::create([
                'code' => $code,
                'name' => $department->name,
                'phone' => $department->phone,
                'legacy_show' => $department->show,
                'is_active' => (bool) $department->show,
                'sort_order' => $index + 1,
            ]);
        }
    }

    public function storeDepartment(Request $request)
    {
        $system = config('database_domains.system_connection', 'mysql_system');
        $validated = $request->validate([
            'code' => ['required', 'string', 'max:20', Rule::unique($system . '.organization_departments', 'code')],
            'name' => 'required|string|max:200',
            'phone' => 'nullable|string|max:100',
        ]);

        $department = OrganizationDepartment::create($validated + [
            'is_active' => true,
            'sort_order' => (int) OrganizationDepartment::max('sort_order') + 1,
        ]);

        return response()->json(['success' => true, 'data' => $department], 201);
    }

    public function updateDepartment(Request $request, OrganizationDepartment $department)
    {
        $system = config('database_domains.system_connection', 'mysql_system');
        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:200',
            'phone' => 'nullable|string|max:100',
            'is_active' => 'sometimes|boolean',
        ]);
        // Dòng 469: chỉ cho sửa tên, mã bộ phận không đổi.
        $department->update($validated);

        return response()->json(['success' => true, 'data' => $department->fresh()]);
    }

    public function destroyDepartment(OrganizationDepartment $department)
    {
        // Dòng 469: chỉ xóa bộ phận chưa có vị trí công việc nào.
        if ($department->positions()->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'Bộ phận đã có vị trí công việc, không thể xóa.',
            ], 422);
        }

        $department->delete();

        return response()->json(['success' => true, 'message' => 'Đã xóa bộ phận.']);
    }

    public function storePosition(Request $request)
    {
        $system = config('database_domains.system_connection', 'mysql_system');
        $validated = $request->validate([
            'organization_department_id' => ['required', 'integer', Rule::exists($system . '.organization_departments', 'id')],
            'code' => [
                'required', 'string', 'max:100',
                Rule::unique($system . '.positions', 'code')->where('organization_department_id', $request->input('organization_department_id'))
            ],
            'name' => 'required|string|max:120',
        ]);

        $position = Position::create($validated + [
            'is_active' => true,
            'sort_order' => (int) Position::where('organization_department_id', $validated['organization_department_id'])->max('sort_order') + 1,
        ]);

        return response()->json(['success' => true, 'data' => $position->load('department')], 201);
    }

    public function updatePosition(Request $request, Position $position)
    {
        $system = config('database_domains.system_connection', 'mysql_system');
        $validated = $request->validate([
            'organization_department_id' => ['sometimes', 'integer', Rule::exists($system . '.organization_departments', 'id')],
            'name' => 'sometimes|required|string|max:120',
            'is_active' => 'sometimes|boolean',
        ]);
        // Dòng 469: chỉ cho sửa tên vị trí, mã vị trí không đổi.
        $position->update($validated);

        return response()->json(['success' => true, 'data' => $position->fresh()->load('department')]);
    }

    public function destroyPosition(Position $position)
    {
        if ($position->userAssignments()->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'Vị trí đã được gán cho nhân viên, không thể xóa',
            ], 422);
        }

        $position->delete();
        return response()->json(['success' => true, 'message' => 'Đã xóa vị trí công việc.']);
    }

    public function syncPositionBranches(Request $request, Position $position)
    {
        $system = config('database_domains.system_connection', 'mysql_system');
        $validated = $request->validate([
            'application_code' => 'required|string|max:20',
            'assignments' => 'present|array',
            'assignments.*.system_branch_id' => ['required', 'integer', Rule::exists($system . '.system_branches', 'id')],
            'assignments.*.role_id' => ['required', 'integer', Rule::exists($system . '.roles', 'id')],
        ]);

        $branchIds = collect($validated['assignments'])->pluck('system_branch_id');
        if ($branchIds->duplicates()->isNotEmpty()) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'assignments' => 'Không được cấu hình trùng lặp chi nhánh trong danh sách.',
            ]);
        }

        $applicationCode = strtoupper($validated['application_code']);
        $before = PositionBranchRole::query()->where('position_id', $position->id)->where('application_code', $applicationCode)
            ->pluck('role_id', 'system_branch_id')->map(fn ($id) => (int) $id)->all();
        DB::connection($system)->transaction(function () use ($system, $position, $validated, $applicationCode) {
            PositionBranchRole::query()
                ->where('position_id', $position->id)
                ->where('application_code', $applicationCode)
                ->delete();

            foreach ($validated['assignments'] as $assignment) {
                PositionBranchRole::create([
                    'position_id' => $position->id,
                    'system_branch_id' => $assignment['system_branch_id'],
                    'application_code' => $applicationCode,
                    'role_id' => $assignment['role_id'],
                    'is_active' => true,
                ]);
            }

            $this->applyPositionApplicationToEmployees($position, $applicationCode, $validated['assignments']);
        });

        // Checklist TC_HT_06 dòng 33: ghi rõ chi nhánh nào dùng vai trò nào thay vì id kỹ thuật.
        $after = collect($validated['assignments'])->mapWithKeys(fn ($item) => [(int) $item['system_branch_id'] => (int) $item['role_id']])->all();
        \App\Services\ActivityLogService::record($after === [] ? 'Cancel' : 'Modify',
            \App\Support\SystemLogText::positionApplications($position, $applicationCode, $before, $after),
            ['screen' => \App\Support\SystemLogText::SCREEN_ORG, 'target_type' => 'Position', 'target_id' => $position->id, 'target_label' => $position->name], $request);

        return response()->json([
            'success' => true,
            'message' => 'Đã cập nhật ứng dụng, chi nhánh và Role cho vị trí.',
            'data' => $position->fresh()->load(['branchRoles.branch', 'branchRoles.role']),
        ]);
    }

    /**
     * Checklist TC_HT_03 dòng 18 / task 471: một vị trí có nhiều ứng dụng (vd Lễ tân vào cả PMS và POS).
     * Nhân viên đang giữ vị trí ở chi nhánh nào thì nhận / mất ứng dụng của vị trí ở chi nhánh đó ngay,
     * không phải vào màn Nhân viên lưu lại từng người. Không tự thêm chi nhánh mới cho nhân viên và không
     * đè lên vị trí khác mà nhân viên đang giữ cho cùng ứng dụng tại chi nhánh đó.
     *
     * @param  array<int, array{system_branch_id:int, role_id:int}>  $assignments
     */
    private function applyPositionApplicationToEmployees(Position $position, string $applicationCode, array $assignments): void
    {
        $configuredBranchIds = collect($assignments)->pluck('system_branch_id')->map(fn ($id) => (int) $id)->all();

        UserBranchPosition::query()
            ->where('position_id', $position->id)
            ->where('application_code', $applicationCode)
            ->whereNotIn('system_branch_id', $configuredBranchIds ?: [0])
            ->delete();

        $holders = UserBranchPosition::query()
            ->where('position_id', $position->id)
            ->whereIn('system_branch_id', $configuredBranchIds ?: [0])
            ->get(['user_id', 'system_branch_id'])
            ->unique(fn ($item) => $item->user_id . '|' . $item->system_branch_id);

        foreach ($holders as $holder) {
            $hasApplication = UserBranchPosition::query()
                ->where('user_id', $holder->user_id)
                ->where('system_branch_id', $holder->system_branch_id)
                ->where('application_code', $applicationCode)
                ->exists();
            if (!$hasApplication) {
                UserBranchPosition::create([
                    'user_id' => $holder->user_id,
                    'system_branch_id' => $holder->system_branch_id,
                    'application_code' => $applicationCode,
                    'position_id' => $position->id,
                ]);
            }
        }
    }
}

