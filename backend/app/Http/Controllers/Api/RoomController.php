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
        $query = Room::with(['roomForm', 'roomClass', 'activeLock', 'allActiveLocks'])
            ->orderBy('orders', 'asc')
            ->orderBy('room_number', 'asc');

        // Filter out rooms that belong to inactive room classes (unless include_inactive=1)
        $includeInactive = $request->has('include_inactive') && $request->boolean('include_inactive');
        if (!$includeInactive) {
            $query->whereHas('roomClass', function($q) {
                $q->where('is_active', true);
            });
        }

        // Filter internal/virtual rooms (exclude by default unless include_internal=1 or is_internal parameter is passed)
        if ($request->has('include_internal') && $request->boolean('include_internal')) {
            // include all rooms (both physical and internal/virtual)
        } elseif ($request->has('is_internal')) {
            $query->where('is_internal', $request->boolean('is_internal'));
        } else {
            $query->physical();
        }

        // Optional filtering
        if ($request->has('floor') && !empty($request->floor)) {
            $query->where('floor', $request->floor);
        }
        if ($request->has('status') && !empty($request->status)) {
            $query->where('room_status_code', $request->status);
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
            ->with(['booking.company', 'booking.registrationStatus', 'booking.paymentMethod', 'guests.guest', 'children'])
            ->get();

        /** @var Room $room */
        foreach ($rooms as $room) {
            $room->booking_status = null;

            // Ưu tiên trạng thái OOO/OOS (Active Lock hôm nay)
            $currentLock = $room->allActiveLocks ? $room->allActiveLocks->first(function($l) use ($sysDateStr) {
                $startStr = \Carbon\Carbon::parse($l->start_date)->toDateString();
                $endStr = \Carbon\Carbon::parse($l->end_date)->toDateString();
                return $sysDateStr >= $startStr && $sysDateStr <= $endStr;
            }) : null;

            if ($currentLock && in_array($room->room_status_code, ['ooo', 'oos', 'occupied_ooo'])) {
                // Phòng có lock OOO/OOS -> ghi đè room_status_code tương ứng
                $lockCode = $currentLock->lock_type === 'OOS' ? 'oos' : 'ooo';
                $room->lock_type = $currentLock->lock_type;
                $room->setRelation('activeLock', $currentLock);
                $room->room_status_code = $lockCode;
            } else {
                $room->lock_type = null;
                $room->setRelation('activeLock', null);
            }

            // Tìm booking tương ứng
            $br = $bookingRoomsToday->where('room_number', $room->room_number)->first();
            if ($br) {
                if ($br->status === \App\Models\BookingRoom::STATUS_CHECKED_IN) {
                    if ($br->departure_date->toDateString() === $sysDateStr) {
                        $room->booking_status = 'checkout';
                    } else {
                        $room->booking_status = 'occupied';
                    }
                } else if ($br->status === \App\Models\BookingRoom::STATUS_BOOKED) {
                    if ($br->arrival_date->toDateString() === $sysDateStr) {
                        $room->booking_status = 'reserved';
                    }
                }

                $primaryGuest = $br->guests->where('pivot.is_primary', 1)->first() ?? $br->guests->first();
                $room->guest_name = $primaryGuest?->full_name ?? '';
                $room->booking_code = $br->booking?->booking_code ?? '';
                $room->booking_name = $br->booking?->booking_name ?? '';
                $room->company_name = $br->booking?->company?->name ?? '';
                $room->booking_color = $br->booking?->color ?? '';
                $room->arrival_date = $br->arrival_date ? $br->arrival_date->toDateString() : '';
                $room->departure_date = $br->departure_date ? $br->departure_date->toDateString() : '';
                $room->nights = $br->arrival_date && $br->departure_date ? $br->arrival_date->diffInDays($br->departure_date) : 1;
                $room->adults = $br->adults ?? 2;
                $room->children = $br->children ? $br->children->where('age_group', 'child')->count() : 0;
                $room->babies = $br->children ? $br->children->where('age_group', 'baby')->count() : 0;
                $room->arrival_time = $br->arrival_time ?? '14:00';
                $room->rate = $br->rate ?? 0;
                $room->booking_note = $br->booking?->note ?? '';
                $room->special_requests = $br->booking?->special_requests ?? '';
                $room->guest_details = $br->guests->map(fn($g) => $g->full_name)->toArray();
                
                $room->external_booking_code = $br->booking?->external_booking_code ?? '';
                $room->registration_status = $br->booking?->registrationStatus?->name ?? '';
                $room->confirm_date = $br->booking?->confirm_date ? \Carbon\Carbon::parse($br->booking->confirm_date)->toDateString() : '';
                $room->sales_person = $br->booking?->sales_person ?? '';
                $room->is_git = (bool)($br->booking?->is_git ?? false);
                $room->has_vat = (bool)($br->booking?->has_vat ?? false);
                $room->payment_method = $br->booking?->paymentMethod?->name ?? '';
                $room->payment_value = $br->booking?->payment_value ?? 0;
                $room->is_do_not_move = $br->is_do_not_move ?? 0;
                $room->booking_room_id = $br->id ?? null;
                $room->booking_id = $br->booking_id ?? null;
            }


            // Legacy $room->status được tự động tính qua getStatusAttribute() trên Room model
        }

        return response()->json([
            'success' => true,
            'data' => RoomResource::collection($rooms),
            'meta' => [
                'total' => $rooms->count(),
                'floors' => Room::select('floor')
                    ->when(!$includeInactive, function($q) {
                        $q->whereHas('roomClass', function($subQ) {
                            $subQ->where('is_active', true);
                        });
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
            'orders' => 'nullable|integer|unique:rooms,orders',
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
            'orders' => 'nullable|integer|unique:rooms,orders,' . $room->id,
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

        $validCodes = [
            'vacant_ready', 'vacant_dirty', 'vacant_clean',
            'ooo', 'oos', 'turndown', 'housekeeping', 'dnd', 'vacant_priority',
            'occupied_ready', 'occupied_dirty', 'occupied_clean', 'occupied_ooo',
        ];

        $validated = $request->validate([
            'room_status_code' => 'required|string|in:' . implode(',', $validCodes),
        ]);

        $newCode = $validated['room_status_code'];
        $room->update(['room_status_code' => $newCode]);

        // Nếu chuyển sang trạng thái thường (không phải ooo/oos/occupied_ooo) -> Tự động giải phóng các active lock của phòng này
        if (!in_array($newCode, ['ooo', 'oos', 'occupied_ooo'])) {
            $currentUser = auth()->user()?->username ?? auth()->user()?->name ?? 'system';
            \App\Models\RoomLock::where('room_number', $room->room_number)
                ->where('is_active', 1)
                ->update([
                    'is_active' => 2,
                    'unlock_username' => $currentUser,
                    'unlocked_at' => now(),
                ]);
        }

        $room->load(['roomForm', 'roomClass']);

        return response()->json([
            'success' => true,
            'data' => new RoomResource($room)
        ]);
    }

    /**
     * Get room occupancy statistics.
     */
    public function stats(Request $request)
    {
        $roomsResponse = $this->index(new Request(['include_internal' => 0]))->getData(true);
        $rooms = $roomsResponse['data'] ?? [];

        $stats = [
            'total' => count($rooms),
            'available' => 0,
            'occupied' => 0,
            'dirty' => 0,
            'maintenance' => 0,
            'reserved' => 0,
            'checkout' => 0,
        ];

        foreach ($rooms as $room) {
            $st = $room['status'] ?? 'available';
            if (array_key_exists($st, $stats)) {
                $stats[$st]++;
            }
        }

        // Bổ sung thống kê phòng đến (bao gồm cả phòng đã gán và chưa gán số phòng)
        $avService = app(\App\Services\RoomAvailabilityService::class);
        $sysDateStr = $request->date ? \Carbon\Carbon::parse($request->date)->toDateString() : $avService->getSystemDate()->toDateString();

        $occupiedCurrent = \App\Models\BookingRoom::where('status', \App\Models\BookingRoom::STATUS_CHECKED_IN)
            ->where(function($q) use ($sysDateStr) {
                $q->whereDate('arrival_date', '<=', $sysDateStr)
                  ->whereDate('departure_date', '>=', $sysDateStr);
            })
            ->count();

        $pendingArrivals = \App\Models\BookingRoom::where('status', \App\Models\BookingRoom::STATUS_BOOKED)
            ->whereDate('arrival_date', '<=', $sysDateStr)
            ->count();

        $pendingDepartures = \App\Models\BookingRoom::where('status', \App\Models\BookingRoom::STATUS_CHECKED_IN)
            ->whereDate('departure_date', '<=', $sysDateStr)
            ->count();

        $occupiedProjected = max(0, $occupiedCurrent + $pendingArrivals - $pendingDepartures);

        $stats['arrivals_checked_in'] = $occupiedCurrent;
        $stats['arrivals_pending']    = $pendingArrivals;
        $stats['arrivals_total']      = $occupiedCurrent + $pendingArrivals;
        $stats['occupied_current']    = $occupiedCurrent;
        $stats['occupied_projected']  = $occupiedProjected;

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
            'departure_date'=> 'required|date|after_or_equal:arrival_date',
        ]);

        $roomClassId   = $request->room_class_id;
        $arrivalDate   = $request->arrival_date;
        $departureDate = $request->departure_date;
        $excludeId     = $request->exclude_booking_room_id;

        $avService = app(\App\Services\RoomAvailabilityService::class);

        // Lấy tất cả phòng vật lý của loại phòng này (loại trừ phòng ảo/nội bộ)
        $rooms = Room::where('room_class_id', $roomClassId)->where('is_internal', false)->get();

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

