<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\RoomClassResource;
use App\Models\RoomClass;

class RoomClassController extends Controller
{
    /**
     * Display a listing of the room classes.
     */
    public function index()
    {
        $classes = RoomClass::with('roomClassGroup')->get();
        return RoomClassResource::collection($classes);
    }

    /**
     * Store a newly created room class in storage.
     */
    public function store(\Illuminate\Http\Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'code' => 'required|string|max:255|unique:room_classes,code',
            'color' => 'nullable|string|max:7',
            'is_active' => 'nullable',
            'room_class_group_id' => 'nullable|exists:room_class_groups,id',
            'notes' => 'nullable|string',
            'image' => 'nullable|image|max:2048',
        ]);

        $isActive = filter_var($request->input('is_active', true), FILTER_VALIDATE_BOOLEAN);

        $imagePath = null;
        if ($request->hasFile('image')) {
            $imagePath = $request->file('image')->store('room_classes', 'public');
        }

        $groupId = $validated['room_class_group_id'] ?? null;
        if (!$groupId) {
            $groupId = \App\Models\RoomClassGroup::where('code', 'hotel')->value('id')
                ?? \App\Models\RoomClassGroup::first()?->id;
        }

        $roomClass = RoomClass::create([
            'name' => $validated['name'],
            'code' => $validated['code'],
            'color' => $validated['color'] ?? '#ffffff',
            'is_active' => $isActive,
            'room_class_group_id' => $groupId,
            'notes' => $validated['notes'] ?? null,
            'image_path' => $imagePath,
        ]);

        $roomClass->load('roomClassGroup');

        return new RoomClassResource($roomClass);
    }

    /**
     * Update the specified room class in storage.
     */
    public function update(\Illuminate\Http\Request $request, $id)
    {
        $roomClass = RoomClass::findOrFail($id);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'code' => 'required|string|max:255|unique:room_classes,code,' . $roomClass->id,
            'color' => 'nullable|string|max:7',
            'is_active' => 'nullable',
            'room_class_group_id' => 'nullable|exists:room_class_groups,id',
            'notes' => 'nullable|string',
            'image' => 'nullable|image|max:2048',
        ]);

        $isActive = filter_var($request->input('is_active', $roomClass->is_active), FILTER_VALIDATE_BOOLEAN);

        if (!$isActive) {
            $activeCount = RoomClass::where('is_active', true)->where('id', '!=', $roomClass->id)->count();
            if ($activeCount === 0) {
                return response()->json([
                    'message' => 'Không thể tắt trạng thái hoạt động vì phải có ít nhất 1 loại phòng đang hoạt động!'
                ], 422);
            }

            $hasRates = $roomClass->standardRates()->exists();
            $hasRooms = $roomClass->rooms()->exists();
            if ($hasRates || $hasRooms) {
                return response()->json([
                    'message' => 'Không thể tắt trạng thái hoạt động của loại phòng này vì đang có giá phòng chuẩn hoặc phòng đang sử dụng loại phòng này!'
                ], 422);
            }
        }

        if ($request->hasFile('image')) {
            if ($roomClass->image_path) {
                \Illuminate\Support\Facades\Storage::disk('public')->delete($roomClass->image_path);
            }
            $roomClass->image_path = $request->file('image')->store('room_classes', 'public');
        }

        $groupId = $validated['room_class_group_id'] ?? $roomClass->room_class_group_id;
        if (!$groupId) {
            $groupId = \App\Models\RoomClassGroup::where('code', 'hotel')->value('id')
                ?? \App\Models\RoomClassGroup::first()?->id;
        }

        $roomClass->update([
            'name' => $validated['name'],
            'code' => $validated['code'],
            'color' => $validated['color'] ?? $roomClass->color,
            'is_active' => $isActive,
            'room_class_group_id' => $groupId,
            'notes' => $validated['notes'] ?? $roomClass->notes,
        ]);

        $roomClass->load('roomClassGroup');

        return new RoomClassResource($roomClass);
    }

    /**
     * Remove the specified room class from storage.
     */
    public function destroy($id)
    {
        $roomClass = RoomClass::findOrFail($id);
        
        if ($roomClass->is_active) {
            $activeCount = RoomClass::where('is_active', true)->where('id', '!=', $roomClass->id)->count();
            if ($activeCount === 0) {
                return response()->json([
                    'message' => 'Không thể xóa loại phòng này vì phải có ít nhất 1 loại phòng đang hoạt động!'
                ], 422);
            }
        }

        $hasRates = $roomClass->standardRates()->exists();
        $hasRooms = $roomClass->rooms()->exists();
        if ($hasRates || $hasRooms) {
            return response()->json([
                'message' => 'Không thể xóa loại phòng này vì đang có giá phòng chuẩn hoặc phòng đang sử dụng loại phòng này!'
            ], 422);
        }

        if ($roomClass->image_path) {
            \Illuminate\Support\Facades\Storage::disk('public')->delete($roomClass->image_path);
        }

        $roomClass->delete();

        return response()->json(['message' => 'Xóa loại phòng thành công!']);
    }
}
