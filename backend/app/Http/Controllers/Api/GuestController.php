<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\BookingCancelLog;
use App\Models\BookingRoom;
use App\Models\CancelReason;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * GuestController — Epic 7: Thông tin khách lưu trú
 * Quản lý guests (người lớn), booking_room_guests (gán vào phòng),
 * booking_children (trẻ em + gán phòng), booking_child_breakfast_details
 */
class GuestController extends Controller
{
    // =========================================
    // GUESTS (người lớn)
    // =========================================

    // GET /booking-rooms/{roomId}/guests
    public function roomGuests($roomId)
    {
        $room   = BookingRoom::findOrFail($roomId);
        $guests = $room->guests()->with('guest')->get();

        return response()->json(['success' => true, 'data' => $guests]);
    }

    // POST /booking-rooms/{roomId}/guests — Thêm khách vào phòng
    public function addGuest(Request $request, $roomId)
    {
        $room = BookingRoom::findOrFail($roomId);

        if (!in_array($room->status, [\App\Models\BookingRoom::STATUS_BOOKED, \App\Models\BookingRoom::STATUS_CHECKED_IN])) {
            return response()->json(['success' => false, 'message' => 'Phòng không ở trạng thái hợp lệ.'], 422);
        }

        $request->validate([
            'full_name'       => 'required|string|max:200',
            'id_number'       => 'nullable|string|max:50',
            'passport_number' => 'nullable|string|max:50',
            'dob'             => 'nullable|date',
            'gender'          => 'nullable|integer|in:0,1,2',
            'nationality_code'=> 'nullable|string|max:5',
            'phone'           => 'nullable|string|max:20',
            'email'           => 'nullable|email|max:150',
            'address'         => 'nullable|string|max:500',
            'is_primary'      => 'nullable|boolean',
            'inherit_guest_id'=> 'nullable|exists:guests,id', // Kế thừa từ khách cũ
        ]);

        DB::beginTransaction();
        try {
            // Kế thừa thông tin khách cũ
            if ($request->filled('inherit_guest_id')) {
                $guest = \App\Models\Guest::findOrFail($request->inherit_guest_id);
            } else {
                // Tìm khách theo id_number hoặc passport_number để gợi ý kế thừa
                $existingGuest = null;
                if ($request->id_number) {
                    $existingGuest = \App\Models\Guest::where('id_number', $request->id_number)->first();
                } elseif ($request->passport_number) {
                    $existingGuest = \App\Models\Guest::where('passport_number', $request->passport_number)->first();
                }

                if ($existingGuest) {
                    // Update thông tin mới vào guest hiện có
                    $existingGuest->update($request->only([
                        'full_name', 'dob', 'gender', 'nationality_code', 'phone', 'email', 'address',
                    ]));
                    $guest = $existingGuest;
                } else {
                    $guest = \App\Models\Guest::create($request->only([
                        'full_name', 'id_number', 'passport_number', 'dob', 'gender',
                        'nationality_code', 'phone', 'email', 'address',
                    ]));
                }
            }

            // Gán vào phòng (tránh duplicate)
            $pivot = \App\Models\BookingRoomGuest::firstOrCreate(
                ['booking_room_id' => $roomId, 'guest_id' => $guest->id],
                ['is_primary' => $request->is_primary ?? false, 'status' => 0]
            );

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['success' => false, 'message' => 'Lỗi: ' . $e->getMessage()], 500);
        }

        return response()->json([
            'success' => true,
            'data'    => $pivot->load('guest'),
            'message' => 'Đã thêm khách vào phòng.',
        ], 201);
    }

    // DELETE /booking-rooms/{roomId}/guests/{guestId}
    public function removeGuest($roomId, $guestId)
    {
        \App\Models\BookingRoomGuest::where('booking_room_id', $roomId)
            ->where('guest_id', $guestId)
            ->delete();

        return response()->json(['success' => true, 'message' => 'Đã xóa khách khỏi phòng.']);
    }

    // GET /guests/search?q=keyword — Tìm khách để kế thừa thông tin
    public function searchGuests(Request $request)
    {
        $q = $request->q;
        $guests = \App\Models\Guest::where('full_name', 'like', "%$q%")
            ->orWhere('id_number', 'like', "%$q%")
            ->orWhere('passport_number', 'like', "%$q%")
            ->limit(20)
            ->get();

        return response()->json(['success' => true, 'data' => $guests]);
    }

    // =========================================
    // CHILDREN (trẻ em)
    // =========================================

    // GET /bookings/{bookingId}/children
    public function bookingChildren($bookingId)
    {
        $booking  = Booking::findOrFail($bookingId);
        $children = $booking->children()->with('bookingRoom', 'breakfastDetails')->get();

        return response()->json(['success' => true, 'data' => $children]);
    }

    // POST /bookings/{bookingId}/children — Thêm trẻ em vào booking
    public function addChild(Request $request, $bookingId)
    {
        Booking::findOrFail($bookingId);

        $request->validate([
            'booking_room_id' => 'nullable|exists:booking_rooms,id',
            'full_name'       => 'nullable|string|max:200',
            'age_group'       => 'nullable|in:baby,child',
        ]);

        $child = \App\Models\BookingChild::create([
            'booking_id'      => $bookingId,
            'booking_room_id' => $request->booking_room_id,
            'full_name'       => $request->full_name,
            'age_group'       => $request->age_group ?? 'child',
            'child_status'    => 0,
        ]);

        // Auto-generate breakfast detail rows cho mỗi ngày nếu có room
        if ($request->booking_room_id) {
            $this->generateBreakfastDetails($child);
        }

        return response()->json([
            'success' => true,
            'data'    => $child->load('breakfastDetails'),
            'message' => 'Đã thêm trẻ em.',
        ], 201);
    }

    // DELETE /bookings/{bookingId}/children/{childId}
    public function removeChild($bookingId, $childId)
    {
        $child = \App\Models\BookingChild::where('booking_id', $bookingId)->findOrFail($childId);
        $child->breakfastDetails()->delete();
        $child->delete();

        return response()->json(['success' => true, 'message' => 'Đã xóa trẻ em.']);
    }

    // =========================================
    // BREAKFAST DETAILS (Epic 13)
    // =========================================

    // GET /booking-children/{childId}/breakfast-details
    public function breakfastDetails($childId)
    {
        $child   = \App\Models\BookingChild::findOrFail($childId);
        $details = $child->breakfastDetails()->orderBy('service_date')->get();

        return response()->json(['success' => true, 'data' => $details]);
    }

    // PATCH /booking-children/{childId}/breakfast-details/{detailId}
    public function updateBreakfastDetail(Request $request, $childId, $detailId)
    {
        $detail = \App\Models\BookingChildBreakfastDetail::where('booking_child_id', $childId)
            ->findOrFail($detailId);

        $request->validate([
            'breakfast'       => 'nullable|boolean',
            'is_free'         => 'nullable|boolean',
            'is_extra_charge' => 'nullable|boolean',
            'is_room'         => 'nullable|boolean',
            'amount'          => 'nullable|numeric|min:0',
        ]);

        $detail->update($request->only(['breakfast', 'is_free', 'is_extra_charge', 'is_room', 'amount']));

        return response()->json(['success' => true, 'data' => $detail, 'message' => 'Cập nhật ăn sáng trẻ em thành công.']);
    }

    // =========================================
    // CANCEL REASONS catalog
    // =========================================

    // GET /cancel-reasons
    public function cancelReasons()
    {
        $reasons = CancelReason::where('is_active', true)->get();
        return response()->json(['success' => true, 'data' => $reasons]);
    }

    // =========================================
    // PRIVATE
    // =========================================

    /**
     * Tự động tạo booking_child_breakfast_details cho mỗi ngày trong giai đoạn ở.
     */
    private function generateBreakfastDetails(\App\Models\BookingChild $child): void
    {
        if (!$child->booking_room_id) return;

        $room = BookingRoom::find($child->booking_room_id);
        if (!$room) return;

        $isBaby     = $child->age_group === 'baby';
        $current    = Carbon::parse($room->arrival_date);
        $end        = Carbon::parse($room->departure_date);

        // Lấy giá ăn sáng từ hotel_settings
        $setting = \App\Models\HotelSetting::first();
        $amount  = $setting?->child_breakfast_rate ?? 0;

        while ($current->lt($end)) {
            \App\Models\BookingChildBreakfastDetail::firstOrCreate(
                [
                    'booking_child_id' => $child->id,
                    'service_date'     => $current->toDateString(),
                ],
                [
                    'breakfast'       => true,
                    'is_free'         => $isBaby,   // Em bé: miễn phí
                    'is_extra_charge' => false,
                    'is_room'         => true,
                    'amount'          => $isBaby ? 0 : $amount,
                ]
            );
            $current = $current->addDay();
        }
    }
}
