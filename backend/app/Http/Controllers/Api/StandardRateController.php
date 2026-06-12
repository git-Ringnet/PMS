<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\StandardRateResource;
use App\Models\StandardRate;
use Illuminate\Http\Request;

class StandardRateController extends Controller
{
    /**
     * Display a listing of the standard rates.
     */
    public function index()
    {
        $rates = StandardRate::whereHas('roomClass', function($q) {
            $q->where('is_active', true);
        })->with(['roomClass', 'roomForm'])->get();
        return StandardRateResource::collection($rates);
    }

    /**
     * Store a newly created standard rate in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'room_class_id' => 'required|exists:room_classes,id',
            'room_form_id' => 'required|exists:room_forms,id',
            'room_price' => 'required|numeric|min:0',
            'extra_bed_price' => 'required|numeric|min:0',
        ]);

        $exists = StandardRate::where('room_class_id', $validated['room_class_id'])
            ->where('room_form_id', $validated['room_form_id'])
            ->exists();

        if ($exists) {
            return response()->json([
                'message' => 'Giá phòng chuẩn cho loại phòng và dạng phòng này đã tồn tại!'
            ], 422);
        }

        $rate = StandardRate::create($validated);
        $rate->load(['roomClass', 'roomForm']);

        return new StandardRateResource($rate);
    }

    /**
     * Update the specified standard rate in storage.
     */
    public function update(Request $request, $id)
    {
        $rate = StandardRate::findOrFail($id);

        $validated = $request->validate([
            'room_class_id' => 'required|exists:room_classes,id',
            'room_form_id' => 'required|exists:room_forms,id',
            'room_price' => 'required|numeric|min:0',
            'extra_bed_price' => 'required|numeric|min:0',
        ]);

        $exists = StandardRate::where('room_class_id', $validated['room_class_id'])
            ->where('room_form_id', $validated['room_form_id'])
            ->where('id', '!=', $rate->id)
            ->exists();

        if ($exists) {
            return response()->json([
                'message' => 'Giá phòng chuẩn cho loại phòng và dạng phòng này đã tồn tại!'
            ], 422);
        }

        $rate->update($validated);
        $rate->load(['roomClass', 'roomForm']);

        return new StandardRateResource($rate);
    }

    /**
     * Remove the specified standard rate from storage.
     */
    public function destroy($id)
    {
        $rate = StandardRate::findOrFail($id);
        $rate->delete();

        return response()->json(['message' => 'Xóa giá phòng chuẩn thành công!']);
    }
}
