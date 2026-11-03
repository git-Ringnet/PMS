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
            ->orderBy('sort_order', 'asc')
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
        $beforeIds = BookingRoomSpecialRequest::where('booking_room_id', $roomId)->pluck('special_request_id')->map(fn ($id) => (int) $id)->all();

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

        $afterIds = array_map('intval', $ids);
        $this->logSpecial($room, array_diff($afterIds, $beforeIds), array_diff($beforeIds, $afterIds));

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
        } else {
            $this->logSpecial($room, [(int) $request->special_request_id], []);
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

        $item = BookingRoomSpecialRequest::where('booking_room_id', $roomId)->where('id', $id)->first();
        $item?->delete();
        if ($item) {
            $this->logSpecial($room, [], [(int) $item->special_request_id]);
        }

        return response()->json(['success' => true, 'message' => 'Đã xóa yêu cầu đặc biệt.']);
    }

    /**
     * Dòng 31, 32: "* Thêm Special - Phòng thuê: G0000048 - Đăng ký: GAL22 - Special: HNYM"
     * và "* Xoá Special - Phòng thuê: G0000048 - Đăng ký: GAL22 - Special: HNYM".
     */
    private function logSpecial(BookingRoom $room, array $addedIds, array $removedIds): void
    {
        try {
            $code = \App\Services\ActivityLogService::bookingCode($room->booking_id);
            $names = fn (array $ids) => SpecialRequest::whereIn('id', $ids)->get()->map(fn ($sr) => $sr->code ?: $sr->name)->implode(', ');
            $lines = [];
            if ($addedIds) {
                $lines[] = "* Thêm Special - Phòng thuê: {$room->id} - Đăng ký: {$code} - Special: " . $names(array_values($addedIds));
            }
            if ($removedIds) {
                $lines[] = "* Xoá Special - Phòng thuê: {$room->id} - Đăng ký: {$code} - Special: " . $names(array_values($removedIds));
            }
            if ($lines) {
                \App\Services\ActivityLogService::record($addedIds ? 'Add' : 'Cancel', implode("\n", $lines), [
                    'screen' => \App\Support\ActivityScreen::REGISTRATION,
                    'booking_code' => $code,
                    'room_code' => $room->id,
                    'target_type' => 'BookingRoomSpecialRequest',
                    'target_id' => $room->id,
                    'target_label' => $code,
                ]);
            }
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('Log special request error: ' . $e->getMessage());
        }
    }
}
