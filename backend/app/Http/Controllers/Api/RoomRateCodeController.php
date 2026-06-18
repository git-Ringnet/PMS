<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\RoomRateCodeResource;
use App\Models\RoomRateCode;
use Illuminate\Http\Request;

class RoomRateCodeController extends Controller
{
    public function index()
    {
        $rates = RoomRateCode::with(['roomClass', 'roomForm'])->get();
        return response()->json([
            'success' => true,
            'data' => RoomRateCodeResource::collection($rates)
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'code' => 'required|string|max:50|unique:room_rate_codes,code',
            'description' => 'nullable|string|max:255',
            'room_class_id' => 'nullable|exists:room_classes,id',
            'room_form_id' => 'nullable|exists:room_forms,id',
            'adults' => 'nullable|integer|min:0',
            'children' => 'nullable|integer|min:0',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date',
            'price' => 'nullable|numeric|min:0',
            'breakfast_price' => 'nullable|numeric|min:0',
            'extra_bed_price' => 'nullable|numeric|min:0',
            'has_breakfast' => 'nullable|boolean',
            'is_allowed' => 'nullable|boolean',
            'rate_type' => 'nullable|string|max:20',
        ]);

        $rate = RoomRateCode::create($validated);
        $rate->load(['roomClass', 'roomForm']);

        return response()->json([
            'success' => true,
            'data' => new RoomRateCodeResource($rate)
        ], 201);
    }

    public function show($id)
    {
        $rate = RoomRateCode::with(['roomClass', 'roomForm'])->find($id);
        if (!$rate) {
            return response()->json(['message' => 'Room rate code not found'], 404);
        }
        return response()->json([
            'success' => true,
            'data' => new RoomRateCodeResource($rate)
        ]);
    }

    public function update(Request $request, $id)
    {
        $rate = RoomRateCode::find($id);
        if (!$rate) {
            return response()->json(['message' => 'Room rate code not found'], 404);
        }

        $validated = $request->validate([
            'code' => 'required|string|max:50|unique:room_rate_codes,code,' . $rate->id,
            'description' => 'nullable|string|max:255',
            'room_class_id' => 'nullable|exists:room_classes,id',
            'room_form_id' => 'nullable|exists:room_forms,id',
            'adults' => 'nullable|integer|min:0',
            'children' => 'nullable|integer|min:0',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date',
            'price' => 'nullable|numeric|min:0',
            'breakfast_price' => 'nullable|numeric|min:0',
            'extra_bed_price' => 'nullable|numeric|min:0',
            'has_breakfast' => 'nullable|boolean',
            'is_allowed' => 'nullable|boolean',
            'rate_type' => 'nullable|string|max:20',
        ]);

        $rate->update($validated);
        $rate->load(['roomClass', 'roomForm']);

        return response()->json([
            'success' => true,
            'data' => new RoomRateCodeResource($rate)
        ]);
    }

    public function destroy($id)
    {
        $rate = RoomRateCode::find($id);
        if (!$rate) {
            return response()->json(['message' => 'Room rate code not found'], 404);
        }

        $rate->delete();

        return response()->json([
            'success' => true,
            'message' => 'Room rate code deleted successfully'
        ]);
    }
}
