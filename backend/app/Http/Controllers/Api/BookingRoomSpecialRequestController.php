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
        $catalog = SpecialRequest::orderBy('sort_order', 'asc')
            ->get();

        return response()->json(['success' => true, 'data' => $catalog]);
    }

    // POST /special-requests — Tạo mới danh mục master
    public function storeMaster(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100|unique:special_requests,name',
            'code' => 'nullable|string|max:30|unique:special_requests,code',
        ]);

        if (empty($validated['code'])) {
            $validated['code'] = strtoupper(substr(md5($validated['name']), 0, 8));
        }

        $item = SpecialRequest::create([
            'name' => $validated['name'],
            'code' => $validated['code'],
            'is_active' => true,
            'sort_order' => (int)SpecialRequest::max('sort_order') + 1,
        ]);

        return response()->json([
            'success' => true,
            'data' => $item,
            'message' => 'Tạo yêu cầu đặc biệt mới thành công.'
        ], 201);
    }

    // DELETE /special-requests/{id} — Xóa danh mục master
    public function destroyMaster($id)
    {
        $item = SpecialRequest::findOrFail($id);
        $item->delete();

        return response()->json([
            'success' => true,
            'message' => 'Đã xóa yêu cầu đặc biệt khỏi hệ thống.'
        ]);
    }

    // POST /booking-rooms/{roomId}/special-requests/sync — Đồng bộ danh sách yêu cầu đặc biệt cho phòng
    public function sync(Request $request, $roomId)
    {
        $room = BookingRoom::findOrFail($roomId);

        if ($room->status === BookingRoom::STATUS_CHECKED_OUT) {
            return response()->json(['success' => false, 'message' => 'Không thể sửa đổi yêu cầu với phòng đã checkout.'], 422);
        }

        $request->validate([
            'special_request_ids' => 'present|array',
            'special_request_ids.*' => 'exists:special_requests,id',
        ]);

        $ids = $request->special_request_ids;

        // Xóa những yêu cầu không nằm trong danh mục mới chọn
        BookingRoomSpecialRequest::where('booking_room_id', $roomId)
            ->whereNotIn('special_request_id', $ids)
            ->delete();

        // Thêm mới các yêu cầu được chọn
        foreach ($ids as $id) {
            BookingRoomSpecialRequest::firstOrCreate([
                'booking_room_id' => $roomId,
                'special_request_id' => $id,
            ]);
        }

        return response()->json([
            'success' => true,
            'data'    => $room->specialRequests()->with('specialRequest')->get(),
            'message' => 'Đã đồng bộ yêu cầu đặc biệt thành công.'
        ]);
    }

    // POST /booking-rooms/{roomId}/special-requests — Gán yêu cầu đặc biệt đơn lẻ
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

    // DELETE /booking-rooms/{roomId}/special-requests/{id} — Xóa yêu cầu đặc biệt đã gán cho phòng
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
