<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\BranchResource;
use App\Models\Branch;
use Illuminate\Http\Request;

class BranchController extends Controller
{
    /**
     * Display a listing of branches.
     */
    public function index()
    {
        $branches = Branch::orderBy('id')->get();
        return response()->json([
            'success' => true,
            'data' => BranchResource::collection($branches),
        ]);
    }

    /**
     * Store a newly created branch.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
        ]);

        $branch = Branch::create($validated);

        return response()->json([
            'success' => true,
            'data' => new BranchResource($branch),
        ], 201);
    }

    /**
     * Display the specified branch.
     */
    public function show($id)
    {
        $branch = Branch::find($id);
        if (!$branch) {
            return response()->json(['message' => 'Branch not found'], 404);
        }
        return response()->json([
            'success' => true,
            'data' => new BranchResource($branch),
        ]);
    }

    /**
     * Update the specified branch.
     */
    public function update(Request $request, $id)
    {
        $branch = Branch::find($id);
        if (!$branch) {
            return response()->json(['message' => 'Branch not found'], 404);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
        ]);

        $branch->update($validated);

        return response()->json([
            'success' => true,
            'data' => new BranchResource($branch),
        ]);
    }

    /**
     * Remove the specified branch.
     */
    public function destroy($id)
    {
        $branch = Branch::find($id);
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
