<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\RoomLock;
use App\Models\Room;
use Illuminate\Http\Request;

class RoomLockController extends Controller
{
    /**
     * Display a listing of room locks.
     */
    public function index(Request $request)
    {
        $query = RoomLock::with(['room.roomForm', 'room.roomClass']);

        if ($request->has('is_active')) {
            $query->where('is_active', filter_var($request->is_active, FILTER_VALIDATE_BOOLEAN));
        }

        $locks = $query->orderBy('created_at', 'desc')->get();

        return response()->json([
            'success' => true,
            'data' => $locks,
        ]);
    }

    /**
     * Store a newly created room lock.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'room_id' => 'required|exists:rooms,id',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'reason' => 'nullable|string|max:255',
            'maintenance_percent' => 'nullable|integer|min:0|max:100',
            'status' => 'nullable|string|max:50',
            'username' => 'nullable|string|max:50',
            'lock_type' => 'required|string|in:OOO,OOS',
        ]);

        // Deactivate previous active locks for this room
        RoomLock::where('room_id', $validated['room_id'])->where('is_active', true)->update(['is_active' => false]);

        $validated['is_active'] = true;
        if (!isset($validated['username'])) {
            $validated['username'] = $request->user()?->name ?? $request->user()?->username ?? 'NB0016';
        }
        if (!isset($validated['status'])) {
            $validated['status'] = 'New';
        }

        $lock = RoomLock::create($validated);

        // Update room status to maintenance
        Room::where('id', $validated['room_id'])->update(['status' => 'maintenance']);

        $lock->load(['room.roomForm', 'room.roomClass']);

        return response()->json([
            'success' => true,
            'data' => $lock,
        ], 201);
    }

    /**
     * Bulk lock multiple rooms.
     */
    public function bulkLock(Request $request)
    {
        $validated = $request->validate([
            'room_ids' => 'required|array',
            'room_ids.*' => 'exists:rooms,id',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'reason' => 'nullable|string|max:255',
            'maintenance_percent' => 'nullable|integer|min:0|max:100',
            'status' => 'nullable|string|max:50',
            'username' => 'nullable|string|max:50',
            'lock_type' => 'required|string|in:OOO,OOS',
        ]);

        $locksCreated = [];
        $username = $validated['username'] ?? $request->user()?->name ?? $request->user()?->username ?? 'NB0016';
        $status = $validated['status'] ?? 'New';
        $mPercent = $validated['maintenance_percent'] ?? 0;

        foreach ($validated['room_ids'] as $roomId) {
            // Deactivate previous active locks for this room
            RoomLock::where('room_id', $roomId)->where('is_active', true)->update(['is_active' => false]);

            $lock = RoomLock::create([
                'room_id' => $roomId,
                'start_date' => $validated['start_date'],
                'end_date' => $validated['end_date'],
                'reason' => $validated['reason'],
                'maintenance_percent' => $mPercent,
                'status' => $status,
                'username' => $username,
                'lock_type' => $validated['lock_type'],
                'is_active' => true,
            ]);

            // Update room status to maintenance
            Room::where('id', $roomId)->update(['status' => 'maintenance']);

            $locksCreated[] = $lock;
        }

        return response()->json([
            'success' => true,
            'message' => count($locksCreated) . ' rooms locked successfully.',
            'data' => $locksCreated,
        ]);
    }

    /**
     * Bulk unlock multiple rooms.
     */
    public function bulkUnlock(Request $request)
    {
        $validated = $request->validate([
            'room_ids' => 'required|array',
            'room_ids.*' => 'exists:rooms,id',
        ]);

        foreach ($validated['room_ids'] as $roomId) {
            // Deactivate active locks
            RoomLock::where('room_id', $roomId)->where('is_active', true)->update(['is_active' => false]);

            // Update room status to available
            Room::where('id', $roomId)->update(['status' => 'available']);
        }

        return response()->json([
            'success' => true,
            'message' => count($validated['room_ids']) . ' rooms unlocked successfully.',
        ]);
    }

    /**
     * Get lock history of a specific room.
     */
    public function history($roomId)
    {
        $history = RoomLock::where('room_id', $roomId)
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $history,
        ]);
    }

    /**
     * Display the specified room lock.
     */
    public function show($id)
    {
        $lock = RoomLock::with(['room.roomForm', 'room.roomClass'])->find($id);
        if (!$lock) {
            return response()->json(['message' => 'Room lock not found'], 404);
        }
        return response()->json([
            'success' => true,
            'data' => $lock,
        ]);
    }

    /**
     * Update the specified room lock.
     */
    public function update(Request $request, $id)
    {
        $lock = RoomLock::find($id);
        if (!$lock) {
            return response()->json(['message' => 'Room lock not found'], 404);
        }

        $validated = $request->validate([
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'reason' => 'nullable|string|max:255',
            'maintenance_percent' => 'nullable|integer|min:0|max:100',
            'status' => 'nullable|string|max:50',
            'username' => 'nullable|string|max:50',
            'lock_type' => 'required|string|in:OOO,OOS',
            'is_active' => 'nullable|boolean',
        ]);

        $lock->update($validated);

        if ($lock->is_active) {
            Room::where('id', $lock->room_id)->update(['status' => 'maintenance']);
        } else {
            // Check if there are other active locks, otherwise restore status to available
            $hasActive = RoomLock::where('room_id', $lock->room_id)->where('is_active', true)->exists();
            if (!$hasActive) {
                Room::where('id', $lock->room_id)->update(['status' => 'available']);
            }
        }

        $lock->load(['room.roomForm', 'room.roomClass']);

        return response()->json([
            'success' => true,
            'data' => $lock,
        ]);
    }

    /**
     * Remove the specified room lock.
     */
    public function destroy($id)
    {
        $lock = RoomLock::find($id);
        if (!$lock) {
            return response()->json(['message' => 'Room lock not found'], 404);
        }

        $roomId = $lock->room_id;
        $lock->delete();

        // Check if there are other active locks
        $hasActive = RoomLock::where('room_id', $roomId)->where('is_active', true)->exists();
        if (!$hasActive) {
            Room::where('id', $roomId)->update(['status' => 'available']);
        }

        return response()->json([
            'success' => true,
            'message' => 'Room lock deleted successfully',
        ]);
    }
}
