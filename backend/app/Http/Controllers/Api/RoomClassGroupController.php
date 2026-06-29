<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\RoomClassGroupResource;
use App\Models\RoomClassGroup;
use Illuminate\Http\Request;

class RoomClassGroupController extends Controller
{
    /**
     * Display a listing of the room class groups.
     */
    public function index()
    {
        $groups = RoomClassGroup::all();
        return RoomClassGroupResource::collection($groups);
    }

    /**
     * Store a newly created room class group in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'code' => 'nullable|string|max:255|unique:room_class_groups,code',
            'is_active' => 'nullable|boolean',
        ]);

        $group = RoomClassGroup::create([
            'name' => $validated['name'],
            'code' => $validated['code'] ?? null,
            'is_active' => $validated['is_active'] ?? true,
        ]);

        return new RoomClassGroupResource($group);
    }

    /**
     * Update the specified room class group in storage.
     */
    public function update(Request $request, $id)
    {
        $group = RoomClassGroup::findOrFail($id);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'code' => 'nullable|string|max:255|unique:room_class_groups,code,' . $group->id,
            'is_active' => 'nullable|boolean',
        ]);

        $group->update([
            'name' => $validated['name'],
            'code' => $validated['code'] ?? $group->code,
            'is_active' => $validated['is_active'] ?? $group->is_active,
        ]);

        return new RoomClassGroupResource($group);
    }

    /**
     * Remove the specified room class group from storage.
     */
    public function destroy($id)
    {
        $group = RoomClassGroup::findOrFail($id);

        // Check if there are any room classes using this group
        if ($group->roomClasses()->exists()) {
            return response()->json([
                'message' => 'Không thể xóa nhóm này vì đang có loại phòng thuộc nhóm này!'
            ], 422);
        }

        $group->delete();

        return response()->json(['message' => 'Xóa nhóm loại phòng thành công!']);
    }
}
