<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\UnitOfMeasureResource;
use App\Models\UnitOfMeasure;
use Illuminate\Http\Request;

class UnitOfMeasureController extends Controller
{
    public function index()
    {
        $units = UnitOfMeasure::all();
        return response()->json([
            'success' => true,
            'data' => UnitOfMeasureResource::collection($units)
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'code' => 'required|string|max:50|unique:units_of_measure,code',
            'name' => 'required|string|max:255',
            'symbol' => 'nullable|string|max:255',
            'is_inactive' => 'nullable|boolean',
        ]);

        $unit = UnitOfMeasure::create($validated);

        return response()->json([
            'success' => true,
            'data' => new UnitOfMeasureResource($unit)
        ], 201);
    }

    public function show($id)
    {
        $unit = UnitOfMeasure::find($id);
        if (!$unit) {
            return response()->json(['message' => 'Unit of measure not found'], 404);
        }
        return response()->json([
            'success' => true,
            'data' => new UnitOfMeasureResource($unit)
        ]);
    }

    public function update(Request $request, $id)
    {
        $unit = UnitOfMeasure::find($id);
        if (!$unit) {
            return response()->json(['message' => 'Unit of measure not found'], 404);
        }

        $validated = $request->validate([
            'code' => 'required|string|max:50|unique:units_of_measure,code,' . $unit->id,
            'name' => 'required|string|max:255',
            'symbol' => 'nullable|string|max:255',
            'is_inactive' => 'nullable|boolean',
        ]);

        $unit->update($validated);

        return response()->json([
            'success' => true,
            'data' => new UnitOfMeasureResource($unit)
        ]);
    }

    public function destroy($id)
    {
        $unit = UnitOfMeasure::find($id);
        if (!$unit) {
            return response()->json(['message' => 'Unit of measure not found'], 404);
        }

        $unit->delete();

        return response()->json([
            'success' => true,
            'message' => 'Unit of measure deleted successfully'
        ]);
    }
}
