<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ShiftResource;
use App\Models\Shift;
use Illuminate\Http\Request;

class ShiftController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $shifts = Shift::all();
        return response()->json([
            'success' => true,
            'data' => ShiftResource::collection($shifts)
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'start_time' => 'required|string|max:50',
            'end_time' => 'required|string|max:50',
        ]);

        $shift = Shift::create($validated);

        return response()->json([
            'success' => true,
            'data' => new ShiftResource($shift)
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show($id)
    {
        $shift = Shift::find($id);
        if (!$shift) {
            return response()->json(['message' => 'Shift not found'], 404);
        }
        return response()->json([
            'success' => true,
            'data' => new ShiftResource($shift)
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, $id)
    {
        $shift = Shift::find($id);
        if (!$shift) {
            return response()->json(['message' => 'Shift not found'], 404);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'start_time' => 'required|string|max:50',
            'end_time' => 'required|string|max:50',
        ]);

        $shift->update($validated);

        return response()->json([
            'success' => true,
            'data' => new ShiftResource($shift)
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id)
    {
        $shift = Shift::find($id);
        if (!$shift) {
            return response()->json(['message' => 'Shift not found'], 404);
        }

        $shift->delete();

        return response()->json([
            'success' => true,
            'message' => 'Shift deleted successfully'
        ]);
    }
}
