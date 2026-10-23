<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ReportDefinition;
use App\Services\Reports\ReportMenuService;
use App\Services\Reports\ReportPermissionService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Dòng 327: màn Cấu hình menu báo cáo (kéo thả nhóm / báo cáo theo module) và menu Báo cáo trên thanh điều hướng.
 * Dòng 328: quyền xem từng báo cáo theo vai trò tại chi nhánh.
 */
class ReportMenuController extends Controller
{
    public function __construct(
        private readonly ReportMenuService $menu,
        private readonly ReportPermissionService $permissions
    ) {}

    /** GET /report-menu: dữ liệu cho màn cấu hình (toàn bộ báo cáo + cây của từng module). */
    public function index()
    {
        $reports = ReportDefinition::orderBy('name')->get();

        return response()->json([
            'success' => true,
            'data' => [
                'locations' => collect(ReportMenuService::LOCATIONS)->map(fn ($label, $code) => ['code' => $code, 'label' => $label])->values(),
                'reports' => $reports->map(fn (ReportDefinition $report) => [
                    'id' => $report->id,
                    'code' => $report->code,
                    'name' => $report->name,
                    'group' => $report->group,
                    'is_active' => (bool) $report->is_active,
                    'locations' => collect(ReportMenuService::LOCATIONS)->keys()
                        ->filter(fn ($location) => ReportMenuService::belongsTo($report, $location))->values(),
                ])->values(),
                'trees' => collect(ReportMenuService::LOCATIONS)->map(fn ($label, $location) => $this->menu->tree($location, $reports)),
            ],
        ]);
    }

    /** GET /report-menu/navigation: menu Báo cáo của từng module, chỉ gồm báo cáo đang dùng và người dùng được xem. */
    public function navigation(Request $request)
    {
        $reports = ReportDefinition::where('is_active', true)->get();
        $this->permissions->ensurePermissions($reports);
        $visible = $this->permissions->visibleReports($request->user(), $reports, $request->attributes->get('_branch_id'));

        $menus = collect(ReportMenuService::LOCATIONS)->map(function ($label, $location) use ($visible) {
            $members = $visible->filter(fn ($report) => ReportMenuService::belongsTo($report, $location));
            $items = collect($this->menu->tree($location, $visible))
                ->reject(fn ($item) => $item['type'] === 'group' && empty($item['children']))
                ->values();

            return [
                'top_order' => $members->isEmpty() ? 20 : (int) $members->min('menu_top_order'),
                'items' => $items,
            ];
        });

        return response()->json(['success' => true, 'data' => $menus]);
    }

    /** PUT /report-menu/{location}: lưu cây menu của module. */
    public function update(Request $request, string $location)
    {
        abort_unless(array_key_exists($location, ReportMenuService::LOCATIONS), 404);

        $validated = $request->validate([
            'tree' => 'present|array',
            'tree.*.type' => ['required', Rule::in(['group', 'report'])],
            'tree.*.name' => 'nullable|string|max:255',
            'tree.*.report_id' => 'required_if:tree.*.type,report|nullable|integer',
            'tree.*.children' => 'nullable|array',
            'tree.*.children.*.report_id' => 'required|integer',
        ]);

        $this->menu->save($location, $validated['tree']);

        return response()->json([
            'success' => true,
            'message' => 'Đã lưu cấu hình menu báo cáo '.ReportMenuService::LOCATIONS[$location].'.',
            'data' => $this->menu->tree($location, ReportDefinition::all()),
        ]);
    }

    /** GET /report-definitions/{id}/role-access?system_branch_id= */
    public function roleAccess(Request $request, ReportDefinition $reportDefinition)
    {
        $branchId = $this->branchId($request);

        return response()->json([
            'success' => true,
            'data' => [
                'permission_code' => ReportPermissionService::permissionCode($reportDefinition),
                'system_branch_id' => $branchId,
                'roles' => $this->permissions->roleAccess($reportDefinition, $branchId),
            ],
        ]);
    }

    /** PUT /report-definitions/{id}/role-access */
    public function updateRoleAccess(Request $request, ReportDefinition $reportDefinition)
    {
        $system = config('database_domains.system_connection', 'mysql_system');
        $validated = $request->validate([
            'role_ids' => 'present|array',
            'role_ids.*' => ['integer', Rule::exists($system.'.roles', 'id')],
        ]);
        $branchId = $this->branchId($request);

        $this->permissions->syncRoleAccess($reportDefinition, $branchId, array_map('intval', $validated['role_ids']));

        return response()->json([
            'success' => true,
            'message' => 'Đã lưu quyền xem báo cáo.',
            'data' => ['roles' => $this->permissions->roleAccess($reportDefinition, $branchId)],
        ]);
    }

    private function branchId(Request $request): int
    {
        $system = config('database_domains.system_connection', 'mysql_system');
        $validated = $request->validate([
            'system_branch_id' => ['nullable', 'integer', Rule::exists($system.'.system_branches', 'id')],
        ]);
        $branchId = $validated['system_branch_id'] ?? $request->attributes->get('_branch_id');
        abort_unless($branchId, 422, 'Vui lòng chọn chi nhánh.');

        return (int) $branchId;
    }
}
