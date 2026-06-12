<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\BranchTotalResource;
use App\Models\BranchTotal;
use Illuminate\Http\Request;

class BranchTotalController extends Controller
{
    /**
     * Display a listing of branches.
     */
    public function index()
    {
        $branches = BranchTotal::orderBy('id')->get();
        return response()->json([
            'success' => true,
            'data' => BranchTotalResource::collection($branches),
        ]);
    }

    /**
     * Store a newly created branch.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'code' => 'nullable|string|max:255',
            'name' => 'required|string|max:255',
            'api_url' => 'nullable|string|max:255',
            'api_report_url' => 'nullable|string|max:255',
            'is_master' => 'nullable|boolean',
        ]);

        if (!empty($validated['is_master'])) {
            BranchTotal::where('is_master', true)->update(['is_master' => false]);
        }

        $branch = BranchTotal::create($validated);

        return response()->json([
            'success' => true,
            'data' => new BranchTotalResource($branch),
        ], 201);
    }

    /**
     * Display the specified branch.
     */
    public function show($id)
    {
        $branch = BranchTotal::find($id);
        if (!$branch) {
            return response()->json(['message' => 'Branch not found'], 404);
        }
        return response()->json([
            'success' => true,
            'data' => new BranchTotalResource($branch),
        ]);
    }

    /**
     * Update the specified branch.
     */
    public function update(Request $request, $id)
    {
        $branch = BranchTotal::find($id);
        if (!$branch) {
            return response()->json(['message' => 'Branch not found'], 404);
        }

        $validated = $request->validate([
            'code' => 'nullable|string|max:255',
            'name' => 'required|string|max:255',
            'api_url' => 'nullable|string|max:255',
            'api_report_url' => 'nullable|string|max:255',
            'is_master' => 'nullable|boolean',
        ]);

        if (!empty($validated['is_master'])) {
            BranchTotal::where('id', '!=', $id)->where('is_master', true)->update(['is_master' => false]);
        }

        $branch->update($validated);

        return response()->json([
            'success' => true,
            'data' => new BranchTotalResource($branch),
        ]);
    }

    /**
     * Remove the specified branch.
     */
    public function destroy($id)
    {
        $branch = BranchTotal::find($id);
        if (!$branch) {
            return response()->json(['message' => 'Branch not found'], 404);
        }

        $branch->delete();

        return response()->json([
            'success' => true,
            'message' => 'Branch deleted successfully',
        ]);
    }
}
