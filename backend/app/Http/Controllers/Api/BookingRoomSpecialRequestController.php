<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BookingRoom;
use App\Models\BookingRoomSpecialRequest;
use App\Models\SpecialRequest;
use Illuminate\Http\Request;

/**
 * BookingRoomSpecialRequestController (Epic 15)
 * Quản lý yêu cầu đặc biệt cho từng phòng trong booking.
 */
class BookingRoomSpecialRequestController extends Controller
{
    // GET /booking-rooms/{roomId}/special-requests — Lấy danh sách yêu cầu đặc biệt của phòng
    public function index($roomId)
    {
        $room     = BookingRoom::findOrFail($roomId);
        $requests = $room->specialRequests()->with('specialRequest')->get();

        return response()->json(['success' => true, 'data' => $requests]);
    }

    // GET /special-requests — Danh mục master
    public function catalog()
    {
        $catalog = SpecialRequest::where('is_active', true)
            ->orderBy('sort_order')
            ->get();

        return response()->json(['success' => true, 'data' => $catalog]);
    }

    // POST /booking-rooms/{roomId}/special-requests — Gán yêu cầu đặc biệt
    public function store(Request $request, $roomId)
    {
        $room = BookingRoom::findOrFail($roomId);

        if ($room->status === BookingRoom::STATUS_CHECKED_OUT) {
            return response()->json(['success' => false, 'message' => 'Không điều chỉnh yêu cầu với phòng đã checkout.'], 422);
        }

        $request->validate([
            'special_request_id' => 'required|exists:special_requests,id',
            'note'               => 'nullable|string',
        ]);

        $item = BookingRoomSpecialRequest::firstOrCreate(
            [
                'booking_room_id'    => $roomId,
                'special_request_id' => $request->special_request_id,
            ],
            ['note' => $request->note]
        );

        if (!$item->wasRecentlyCreated) {
            $item->update(['note' => $request->note]);
        }

        return response()->json([
            'success' => true,
            'data'    => $item->load('specialRequest'),
            'message' => 'Đã gán yêu cầu đặc biệt thành công.',
        ], 201);
    }

    // DELETE /booking-rooms/{roomId}/special-requests/{id}
    public function destroy($roomId, $id)
    {
        $room = BookingRoom::findOrFail($roomId);

        if ($room->status === BookingRoom::STATUS_CHECKED_OUT) {
            return response()->json(['success' => false, 'message' => 'Không thể xóa yêu cầu với phòng đã checkout.'], 422);
        }

        BookingRoomSpecialRequest::where('booking_room_id', $roomId)
            ->where('id', $id)
            ->delete();

        return response()->json(['success' => true, 'message' => 'Đã xóa yêu cầu đặc biệt.']);
    }
}
