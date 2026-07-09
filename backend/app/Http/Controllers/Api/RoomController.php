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
        $query = Room::with(['roomForm', 'roomClass', 'activeLock', 'allActiveLocks']);

        // Filter out rooms that belong to inactive room classes
        $query->whereHas('roomClass', function($q) {
            $q->where('is_active', true);
        });

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

        $avService = app(\App\Services\RoomAvailabilityService::class);
        $systemDate = $avService->getSystemDate();
        $sysDateStr = $systemDate->toDateString();

        // Tải các phòng đang được đặt/đang ở hôm nay
        $bookingRoomsToday = \App\Models\BookingRoom::whereNotNull('room_number')
            ->whereIn('status', [
                \App\Models\BookingRoom::STATUS_BOOKED,
                \App\Models\BookingRoom::STATUS_CHECKED_IN
            ])
            ->where(function($q) use ($sysDateStr) {
                $q->where(function($sub) use ($sysDateStr) {
                    $sub->where('arrival_date', '<=', $sysDateStr)
                        ->where('departure_date', '>', $sysDateStr);
                })->orWhere('arrival_date', $sysDateStr)
                  ->orWhere('departure_date', $sysDateStr);
            })
            ->with(['booking.company', 'guests.guest'])
            ->get();

        foreach ($rooms as $room) {
            // Ưu tiên trạng thái OOO/OOS (Active Lock)
            if ($room->activeLock) {
                $room->status = 'maintenance';
                continue;
            }

            $br = $bookingRoomsToday->where('room_number', $room->room_number)->first();
            if ($br) {
                if ($br->status === \App\Models\BookingRoom::STATUS_CHECKED_IN) {
                    if ($br->departure_date->toDateString() === $sysDateStr) {
                        $room->status = 'checkout';
                    } else {
                        $room->status = 'occupied';
                    }
                } else if ($br->status === \App\Models\BookingRoom::STATUS_BOOKED) {
                    if ($br->arrival_date->toDateString() === $sysDateStr) {
                        $room->status = 'reserved';
                    }
                }

                $primaryGuest = $br->guests->where('pivot.is_primary', 1)->first() ?? $br->guests->first();
                $room->guest_name = $primaryGuest?->full_name ?? '';
                $room->booking_code = $br->booking?->booking_code ?? '';
                $room->booking_name = $br->booking?->booking_name ?? '';
                $room->company_name = $br->booking?->company?->name ?? '';
            } else {
                if (!in_array($room->status, ['dirty', 'maintenance'])) {
                    $room->status = 'available';
                }
            }
        }

        return response()->json([
            'success' => true,
            'data' => RoomResource::collection($rooms),
            'meta' => [
                'total' => $rooms->count(),
                'floors' => Room::select('floor')
                    ->whereHas('roomClass', function($q) {
                        $q->where('is_active', true);
                    })
                    ->distinct()
                    ->orderBy('floor')
                    ->pluck('floor')
                    ->map(fn($f) => (int)$f),
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
        $activeRoomQuery = Room::whereHas('roomClass', function($q) {
            $q->where('is_active', true);
        });

        $stats = [
            'total' => (clone $activeRoomQuery)->count(),
            'available' => (clone $activeRoomQuery)->where('status', 'available')->count(),
            'occupied' => (clone $activeRoomQuery)->where('status', 'occupied')->count(),
            'dirty' => (clone $activeRoomQuery)->where('status', 'dirty')->count(),
            'maintenance' => (clone $activeRoomQuery)->where('status', 'maintenance')->count(),
            'reserved' => (clone $activeRoomQuery)->where('status', 'reserved')->count(),
            'checkout' => (clone $activeRoomQuery)->where('status', 'checkout')->count(),
        ];

        return response()->json([
            'success' => true,
            'data' => $stats
        ]);
    }

    /**
     * Lấy danh sách số phòng trống của một loại phòng trong khoảng ngày
     * GET /rooms/vacant?room_class_id=1&arrival_date=2026-07-08&departure_date=2026-07-09&exclude_booking_room_id=...
     */
    public function vacant(Request $request)
    {
        $request->validate([
            'room_class_id' => 'required|exists:room_classes,id',
            'arrival_date'  => 'required|date',
            'departure_date'=> 'required|date|after:arrival_date',
        ]);

        $roomClassId   = $request->room_class_id;
        $arrivalDate   = $request->arrival_date;
        $departureDate = $request->departure_date;
        $excludeId     = $request->exclude_booking_room_id;

        $avService = app(\App\Services\RoomAvailabilityService::class);

        // Lấy tất cả phòng của loại phòng này
        $rooms = Room::where('room_class_id', $roomClassId)->get();

        $vacantRooms = [];
        foreach ($rooms as $room) {
            // 1. Kiểm tra OOO/OOS lock
            $isLocked = \App\Models\RoomLock::where('room_number', $room->room_number)
                ->where('is_active', 1)
                ->where('start_date', '<', $departureDate)
                ->where('end_date', '>', $arrivalDate)
                ->exists();

            if ($isLocked) continue;

            // 2. Kiểm tra có bị booking khác chiếm dụng không
            $isOccupied = $avService->isRoomNumberOccupied(
                $room->room_number, $arrivalDate, $departureDate, $excludeId
            );

            if (!$isOccupied) {
                $vacantRooms[] = [
                    'id' => $room->id,
                    'room_number' => $room->room_number,
                    'status' => $room->status,
                ];
            }
        }

        return response()->json([
            'success' => true,
            'data'    => $vacantRooms,
        ]);
    }
}

