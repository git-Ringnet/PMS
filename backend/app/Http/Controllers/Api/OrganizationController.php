<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\OrganizationDepartment;
use App\Models\Position;
use App\Models\PositionBranchRole;
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
        foreach ($departments as $index => $department) {
            OrganizationDepartment::updateOrCreate(
                ['code' => strtoupper(trim($department->code))],
                [
                    'name' => $department->name,
                    'phone' => $department->phone,
                    'legacy_show' => $department->show,
                    'is_active' => (bool) $department->show,
                    'sort_order' => $index + 1,
                ]
            );
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
            'code' => ['sometimes', 'string', 'max:20', Rule::unique($system . '.organization_departments', 'code')->ignore($department->id)],
            'name' => 'sometimes|required|string|max:200',
            'phone' => 'nullable|string|max:100',
            'is_active' => 'sometimes|boolean',
        ]);
        $department->update($validated);

        return response()->json(['success' => true, 'data' => $department->fresh()]);
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
        $departmentId = $request->input('organization_department_id', $position->organization_department_id);
        $validated = $request->validate([
            'organization_department_id' => ['sometimes', 'integer', Rule::exists($system . '.organization_departments', 'id')],
            'code' => [
                'sometimes', 'string', 'max:100',
                Rule::unique($system . '.positions', 'code')->where('organization_department_id', $departmentId)->ignore($position->id)
            ],
            'name' => 'sometimes|required|string|max:120',
            'is_active' => 'sometimes|boolean',
        ]);
        $position->update($validated);

        return response()->json(['success' => true, 'data' => $position->fresh()->load('department')]);
    }

    public function destroyPosition(Position $position)
    {
        if ($position->userAssignments()->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'Vị trí đã được gán cho nhân viên, chỉ có thể ngừng sử dụng.',
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
        });

        return response()->json([
            'success' => true,
            'message' => 'Đã cập nhật ứng dụng, chi nhánh và Role cho vị trí.',
            'data' => $position->fresh()->load(['branchRoles.branch', 'branchRoles.role']),
        ]);
    }
}

