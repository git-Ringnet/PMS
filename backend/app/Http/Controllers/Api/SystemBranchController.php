<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\SystemBranchResource;
use App\Models\SystemBranch;
use Illuminate\Http\Request;

use Illuminate\Validation\Rule;

class SystemBranchController extends Controller
{
    /**
     * Display a listing of system branches.
     */
    public function index(Request $request)
    {
        $query = SystemBranch::query();

        if ($request->has('search') && !empty($request->search)) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('code', 'like', "%{$search}%");
            });
        }

        $branches = $query->orderBy('id', 'desc')->get();

        return response()->json([
            'success' => true,
            'data' => SystemBranchResource::collection($branches),
        ]);
    }

    /**
     * Store a newly created system branch.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'code' => [
                'required',
                'string',
                'max:100',
                Rule::unique(SystemBranch::class, 'code'),
            ],
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique(SystemBranch::class, 'name'),
            ],
            'tax_code' => 'nullable|string|max:100',
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:50',
            'address' => 'nullable|string|max:1000',
            'accounting_month' => 'nullable|integer|min:1|max:12',
            'accounting_year' => 'nullable|integer|min:1900|max:2100',
            'is_active' => 'nullable|boolean',
        ]);

        $validated['organization_type'] = $validated['organization_type'] ?? 'PMS';
        $branch = SystemBranch::create($validated);

        // Tự động khởi tạo Database, Migrate bảng và Seed dữ liệu mẫu cho chi nhánh mới
        $provisionResult = \App\Services\TenantDatabaseService::provisionBranch($branch, withSeed: true);

        return response()->json([
            'success' => true,
            'message' => 'Tạo chi nhánh và khởi tạo Database thành công!',
            'data' => new SystemBranchResource($branch->fresh()),
            'provision' => $provisionResult,
        ], 201);
    }

    /**
     * Display the specified system branch.
     */
    public function show($id)
    {
        $branch = SystemBranch::find($id);
        if (!$branch) {
            return response()->json(['message' => 'System branch not found'], 404);
        }
        return response()->json([
            'success' => true,
            'data' => new SystemBranchResource($branch),
        ]);
    }

    /**
     * Update the specified system branch.
     */
    public function update(Request $request, $id)
    {
        $branch = SystemBranch::find($id);
        if (!$branch) {
            return response()->json(['message' => 'System branch not found'], 404);
        }

        $validated = $request->validate([
            'code' => [
                'required',
                'string',
                'max:100',
                Rule::unique(SystemBranch::class, 'code')->ignore($branch->id),
            ],
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique(SystemBranch::class, 'name')->ignore($branch->id),
            ],
            'tax_code' => 'nullable|string|max:100',
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:50',
            'address' => 'nullable|string|max:1000',
            'accounting_month' => 'nullable|integer|min:1|max:12',
            'accounting_year' => 'nullable|integer|min:1900|max:2100',
            'is_active' => 'nullable|boolean',
        ]);

        $branch->update($validated);

        return response()->json([
            'success' => true,
            'data' => new SystemBranchResource($branch),
        ]);
    }

    /**
     * Remove the specified system branch.
     */
    public function destroy(Request $request, $id)
    {
        $branch = SystemBranch::find($id);
        if (!$branch) {
            return response()->json(['message' => 'System branch not found'], 404);
        }

        $dropDatabase = $request->boolean('drop_database', false);
        $dbName = \App\Services\TenantDatabaseService::getDatabaseName($branch->code);

        // Xóa liên kết user - chi nhánh
        \App\Models\UserBranch::where('system_branch_id', $branch->id)->delete();

        // Nếu người dùng chọn xóa kèm Database vật lý
        $dbDropped = false;
        if ($dropDatabase) {
            $dbDropped = \App\Services\TenantDatabaseService::dropBranchDatabase($branch->code);
        }

        $branch->delete();

        return response()->json([
            'success' => true,
            'message' => $dropDatabase && $dbDropped 
                ? "Đã xóa chi nhánh và cơ sở dữ liệu ({$dbName}) thành công!"
                : "Đã xóa chi nhánh thành công!",
            'dropped_database' => $dbDropped,
        ]);
    }

    /**
     * Khởi tạo hoặc cập nhật lại Database cho chi nhánh.
     */
    public function provision(Request $request, $id)
    {
        $branch = SystemBranch::find($id);
        if (!$branch) {
            return response()->json(['message' => 'System branch not found'], 404);
        }

        $withSeed = $request->boolean('seed', true);
        $result = \App\Services\TenantDatabaseService::provisionBranch($branch, $withSeed);

        return response()->json([
            'success' => $result['success'],
            'message' => $result['success'] ? 'Khởi tạo Database chi nhánh thành công!' : 'Có lỗi khi khởi tạo Database chi nhánh.',
            'data' => new SystemBranchResource($branch->fresh()),
            'provision' => $result,
        ]);
    }
}
