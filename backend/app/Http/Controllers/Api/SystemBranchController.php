<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\SystemBranchResource;
use App\Models\SystemBranch;
use Illuminate\Http\Request;

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
            'code' => 'required|string|max:100|unique:system_branches,code',
            'name' => 'required|string|max:255|unique:system_branches,name',
            'tax_code' => 'nullable|string|max:100',
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:50',
            'address' => 'nullable|string|max:1000',
            'accounting_month' => 'nullable|integer|min:1|max:12',
            'accounting_year' => 'nullable|integer|min:1900|max:2100',
            'is_active' => 'nullable|boolean',
        ]);

        $branch = SystemBranch::create($validated);

        return response()->json([
            'success' => true,
            'data' => new SystemBranchResource($branch),
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
            'code' => 'required|string|max:100|unique:system_branches,code,' . $id,
            'name' => 'required|string|max:255|unique:system_branches,name,' . $id,
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
    public function destroy($id)
    {
        $branch = SystemBranch::find($id);
        if (!$branch) {
            return response()->json(['message' => 'System branch not found'], 404);
        }

        $branch->delete();

        return response()->json([
            'success' => true,
            'message' => 'System branch deleted successfully',
        ]);
    }
}
