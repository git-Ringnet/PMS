<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\RoomResource;
use App\Models\Room;
use Illuminate\Http\Request;

class RoomController extends Controller
{
    /**
     * Display a listing of the rooms.
     */
    public function index(Request $request)
    {
        $query = Room::with(['roomForm', 'roomClass', 'activeLock']);

        // Optional filtering
        if ($request->has('floor') && !empty($request->floor)) {
            $query->where('floor', $request->floor);
        }
        if ($request->has('status') && !empty($request->status)) {
            $query->where('status', $request->status);
        }
        if ($request->has('room_type_id') && !empty($request->room_type_id)) {
            $query->where('room_class_id', $request->room_type_id);
        }

        $rooms = $query->get();

        return response()->json([
            'success' => true,
            'data' => RoomResource::collection($rooms),
            'meta' => [
                'total' => $rooms->count(),
                'floors' => Room::select('floor')->distinct()->orderBy('floor')->pluck('floor')->map(fn($f) => (int)$f),
            ]
        ]);
    }

    /**
     * Store a newly created room in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'room_number' => 'required|string|max:50|unique:rooms,room_number',
            'room_form_id' => 'required|exists:room_forms,id',
            'room_class_id' => 'required|exists:room_classes,id',
            'max_guests' => 'required|integer|min:1',
            'floor' => 'required|string|max:50',
            'area' => 'nullable|string|max:100',
            'extra_beds_limit' => 'nullable|integer|min:0',
            'grid_row' => 'nullable|integer|min:0',
            'grid_column' => 'nullable|integer|min:0',
            'owner_room' => 'nullable|string|max:100',
            'linked_room' => 'nullable|string|max:100',
            'is_internal' => 'nullable|boolean',
            'status' => 'nullable|string|max:50',
            'notes' => 'nullable|string',
        ]);

        $room = Room::create($validated);
        $room->load(['roomForm', 'roomClass']);

        return response()->json([
            'success' => true,
            'data' => new RoomResource($room)
        ], 201);
    }

    /**
     * Display the specified room.
     */
    public function show($id)
    {
        $room = Room::with(['roomForm', 'roomClass'])->find($id);
        if (!$room) {
            return response()->json(['message' => 'Room not found'], 404);
        }
        return response()->json([
            'success' => true,
            'data' => new RoomResource($room)
        ]);
    }

    /**
     * Update the specified room in storage.
     */
    public function update(Request $request, $id)
    {
        $room = Room::find($id);
        if (!$room) {
            return response()->json(['message' => 'Room not found'], 404);
        }

        $validated = $request->validate([
            'room_number' => 'required|string|max:50|unique:rooms,room_number,' . $room->id,
            'room_form_id' => 'required|exists:room_forms,id',
            'room_class_id' => 'required|exists:room_classes,id',
            'max_guests' => 'required|integer|min:1',
            'floor' => 'required|string|max:50',
            'area' => 'nullable|string|max:100',
            'extra_beds_limit' => 'nullable|integer|min:0',
            'grid_row' => 'nullable|integer|min:0',
            'grid_column' => 'nullable|integer|min:0',
            'owner_room' => 'nullable|string|max:100',
            'linked_room' => 'nullable|string|max:100',
            'is_internal' => 'nullable|boolean',
            'status' => 'nullable|string|max:50',
            'notes' => 'nullable|string',
        ]);

        $room->update($validated);
        $room->load(['roomForm', 'roomClass']);

        return response()->json([
            'success' => true,
            'data' => new RoomResource($room)
        ]);
    }

    /**
     * Remove the specified room from storage.
     */
    public function destroy($id)
    {
        $room = Room::find($id);
        if (!$room) {
            return response()->json(['message' => 'Room not found'], 404);
        }

        $room->delete();

        return response()->json([
            'success' => true,
            'message' => 'Room deleted successfully'
        ]);
    }

    /**
     * Update room status.
     */
    public function updateStatus(Request $request, $id)
    {
        $room = Room::find($id);
        if (!$room) {
            return response()->json(['message' => 'Room not found'], 404);
        }

        $validated = $request->validate([
            'status' => 'required|string|in:available,occupied,dirty,maintenance,reserved,checkout'
        ]);

        $room->update(['status' => $validated['status']]);
        $room->load(['roomForm', 'roomClass']);

        return response()->json([
            'success' => true,
            'data' => new RoomResource($room)
        ]);
    }

    /**
     * Get room occupancy statistics.
     */
    public function stats()
    {
        $total = Room::count();
        $stats = [
            'total' => $total,
            'available' => Room::where('status', 'available')->count(),
            'occupied' => Room::where('status', 'occupied')->count(),
            'dirty' => Room::where('status', 'dirty')->count(),
            'maintenance' => Room::where('status', 'maintenance')->count(),
            'reserved' => Room::where('status', 'reserved')->count(),
            'checkout' => Room::where('status', 'checkout')->count(),
        ];

        return response()->json([
            'success' => true,
            'data' => $stats
        ]);
    }
}
