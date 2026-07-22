<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\BookingCancelLog;
use App\Models\BookingChild;
use App\Models\BookingRoom;
use App\Models\BookingRoomGuest;
use App\Models\CancelReason;
use App\Models\Guest;
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

    // GET /bookings/{bookingId}/guests
    public function bookingGuests($bookingId)
    {
        $booking = Booking::with([
            'bookingRooms' => function ($q) {
                $q->whereNull('deleted_at')
                  ->where('status', '!=', \App\Models\BookingRoom::STATUS_CANCELLED)
                  ->with(['roomClass', 'guests.guest', 'children']);
            }
        ])->findOrFail($bookingId);

        $grouped = $booking->bookingRooms->map(function ($room) {
            return [
                'booking_room_id'  => $room->id,
                'room_number'      => $room->room_number,
                'room_class_name'  => $room->roomClass?->name ?? '',
                'arrival_date'     => $room->arrival_date,
                'departure_date'   => $room->departure_date,
                'rate'             => $room->rate,
                'adults_count'     => $room->adults ?? 1,
                'babies_count'     => $room->babies ?? 0,
                'children_count'   => $room->children_qty ?? 0,
                'guests'           => $room->guests->map(fn($rg) => array_merge(
                    $rg->guest->toArray(),
                    ['pivot_id' => $rg->id, 'is_primary' => $rg->is_primary]
                )),
                'children'         => $room->children->values(),
            ];
        });

        return response()->json(['success' => true, 'data' => $grouped]);
    }

    // POST /bookings/{bookingId}/init-guests
    public function initGuests($bookingId)
    {
        $booking = Booking::with([
            'bookingRooms' => function ($q) {
                $q->whereNull('deleted_at')
                  ->where('status', '!=', \App\Models\BookingRoom::STATUS_CANCELLED)
                  ->with(['guests', 'children']);
            }
        ])->findOrFail($bookingId);

        DB::beginTransaction();
        try {
            foreach ($booking->bookingRooms as $room) {
                $existingGuestsCount = $room->guests->count();
                $existingBabies   = $room->children->where('age_group', 'baby')->count();
                $existingChildren = $room->children->where('age_group', 'child')->count();

                $numAdults   = max(1, intval($room->adults ?? 1));
                $targetBabies   = intval($room->babies ?? 0);
                $targetChildren = intval($room->children_qty ?? 0);

                // Tạo guests (người lớn) còn thiếu
                for ($i = $existingGuestsCount + 1; $i <= $numAdults; $i++) {
                    $guest = Guest::create([
                        'full_name'        => "Guest {$i}",
                        'title'            => 'Mr.',
                        'nationality_code' => 'VN',
                        'guest_status'     => 0,
                    ]);
                    BookingRoomGuest::firstOrCreate(
                        ['booking_room_id' => $room->id, 'guest_id' => $guest->id],
                        [
                            'is_primary'          => $i === 1,
                            'status'              => $room->status,
                            'actual_arrival_date' => $room->arrival_date,
                            'checkin_by'          => Auth::user()?->username ?? 'system',
                            'breakfast'           => $room->breakfast,
                        ]
                    );
                }

                // Tạo em bé còn thiếu
                for ($i = $existingBabies + 1; $i <= $targetBabies; $i++) {
                    BookingChild::create([
                        'booking_id'       => $bookingId,
                        'booking_room_id'  => $room->id,
                        'full_name'        => "Baby {$i}",
                        'title'            => 'Mr.',
                        'nationality_code' => 'VN',
                        'age_group'        => 'baby',
                        'child_status'     => 0,
                    ]);
                }

                // Tạo trẻ em còn thiếu
                for ($i = $existingChildren + 1; $i <= $targetChildren; $i++) {
                    BookingChild::create([
                        'booking_id'       => $bookingId,
                        'booking_room_id'  => $room->id,
                        'full_name'        => "Child {$i}",
                        'title'            => 'Mr.',
                        'nationality_code' => 'VN',
                        'age_group'        => 'child',
                        'child_status'     => 0,
                    ]);
                }


            }
            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }

        return response()->json(['success' => true, 'message' => 'Đã khởi tạo thông tin khách mẫu.']);
    }

    // GET /booking-rooms/{roomId}/guests
    public function roomGuests($roomId)
    {
        $room   = BookingRoom::findOrFail($roomId);
        $guests = $room->guests()->with(['guest' => function ($query) {
            $query->withCount('bookingRoomGuests');
        }])->get();

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
            // actual_arrival_date: Nếu phòng đang inhouse (status=1) thì dùng ngày hệ thống hiện tại,
            // Nếu phòng chưa check-in (status=0) thì dùng arrival_date của phòng.
            $actualArrival = ($room->status === \App\Models\BookingRoom::STATUS_CHECKED_IN)
                ? now()->toDateString()
                : $room->arrival_date->toDateString();

            $pivot = \App\Models\BookingRoomGuest::firstOrCreate(
                ['booking_room_id' => $roomId, 'guest_id' => $guest->id],
                [
                    'is_primary'          => $request->is_primary ?? false,
                    'status'              => $room->status,
                    'actual_arrival_date' => $actualArrival,
                    'checkin_by'          => Auth::user()?->username ?? 'system',
                    'breakfast'           => $room->breakfast,
                ]
            );

            // Cập nhật lại số lượng adults trên booking_rooms
            $room->update(['adults' => max(1, $room->guests()->count())]);

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

    // POST /booking-rooms/{roomId}/guests/{guestId}/checkout — Checkout lẻ từng khách
    // TODO: Tích hợp thêm nút "Checkout riêng" trên giao diện UI (tab Danh sách khách của phòng) sau.
    public function checkoutGuest(Request $request, $roomId, $guestId)
    {
        $pivot = BookingRoomGuest::where('booking_room_id', $roomId)
            ->where('guest_id', $guestId)
            ->firstOrFail();

        if ($pivot->status === BookingRoomGuest::STATUS_CHECKED_OUT) {
            return response()->json(['success' => false, 'message' => 'Khách đã checkout rồi.'], 422);
        }
        if ($pivot->status === BookingRoomGuest::STATUS_CANCELLED) {
            return response()->json(['success' => false, 'message' => 'Khách đã bị hủy.'], 422);
        }

        $avService = app(\App\Services\AvailabilityService::class);
        $systemDate = $avService->getSystemDate();

        $pivot->update([
            'status'               => BookingRoomGuest::STATUS_CHECKED_OUT,
            'actual_checkout_date' => $systemDate->toDateString(),
            'actual_checkout_time' => now()->format('H:i:s'),
            'checkout_by'          => Auth::user()?->username ?? 'system',
        ]);

        return response()->json([
            'success' => true,
            'data'    => $pivot->fresh()->load('guest'),
            'message' => 'Checkout khách thành công.',
        ]);
    }

    // GET /booking-rooms/{roomId}/guests/on-date?date=YYYY-MM-DD — Khách đang ở trong phòng ngày X
    public function getGuestsOnDate(Request $request, $roomId)
    {
        $request->validate([
            'date' => 'required|date_format:Y-m-d',
        ]);

        $date = $request->date;

        $guests = BookingRoomGuest::where('booking_room_id', $roomId)
            ->where('actual_arrival_date', '<=', $date)
            ->where(function ($q) use ($date) {
                $q->whereNull('actual_checkout_date')
                  ->orWhere('actual_checkout_date', '>=', $date);
            })
            ->whereNotIn('status', [
                BookingRoomGuest::STATUS_CHECKED_OUT,
                BookingRoomGuest::STATUS_CANCELLED,
            ])
            ->with('guest')
            ->get();

        return response()->json(['success' => true, 'data' => $guests]);
    }

    // PUT /booking-rooms/{roomId}/guests/{guestId} — Cập nhật thông tin guest
    public function updateGuest(Request $request, $roomId, $guestId)
    {
        // Verify pivot exists
        $pivot = BookingRoomGuest::where('booking_room_id', $roomId)
            ->where('guest_id', $guestId)
            ->firstOrFail();

        $guest = Guest::findOrFail($guestId);

        $request->validate([
            'full_name'         => 'nullable|string|max:200',
            'title'             => 'nullable|string|max:20',
            'id_type'           => 'nullable|string|max:50',
            'id_number'         => 'nullable|string|max:50',
            'id_issue_date'     => 'nullable|date',
            'passport_number'   => 'nullable|string|max:50',
            'passport_expiry'   => 'nullable|date',
            'dob'               => 'nullable|date',
            'gender'            => 'nullable|integer|in:0,1,2',
            'nationality_code'  => 'nullable|string|max:5',
            'phone'             => 'nullable|string|max:20',
            'email'             => 'nullable|email|max:150',
            'address'           => 'nullable|string|max:500',
            'guest_type'        => 'nullable|string|max:50',
            'province'          => 'nullable|string|max:100',
            'district'          => 'nullable|string|max:100',
            'ward'              => 'nullable|string|max:100',
            'residence_type'    => 'nullable|string|max:20',
            'temp_residence_to' => 'nullable|date',
            'visa_no'           => 'nullable|string|max:50',
            'entry_date'        => 'nullable|date',
            'visa_expiry_date'  => 'nullable|date',
            'entry_purpose'     => 'nullable|string|max:200',
            'border_gate'       => 'nullable|string|max:100',
            'occupation'        => 'nullable|string|max:200',
            'note'              => 'nullable|string',
        ]);

        $guest->update($request->only([
            'full_name', 'title', 'id_type', 'id_number', 'id_issue_date',
            'passport_number', 'passport_expiry', 'dob', 'gender', 'nationality_code',
            'phone', 'email', 'address', 'guest_type',
            'province', 'district', 'ward',
            'residence_type', 'temp_residence_to',
            'visa_no', 'entry_date', 'visa_expiry_date',
            'entry_purpose', 'border_gate', 'occupation', 'note',
        ]));

        // Cập nhật thông tin vào bảng pivot booking_room_guests cho từng khách cụ thể
        $pivotData = [];
        if ($request->has('arrival_date'))   $pivotData['actual_arrival_date']  = $request->arrival_date;
        if ($request->has('arrival_time'))   $pivotData['actual_arrival_time']  = $request->arrival_time;
        if ($request->has('departure_date')) $pivotData['actual_checkout_date'] = $request->departure_date;
        if ($request->has('departure_time')) $pivotData['actual_checkout_time'] = $request->departure_time;
        if (!empty($pivotData)) {
            $pivot->update($pivotData);
        }

        // Cập nhật các trường thông tin lưu trú của BookingRoom lên MySQL CSDL
        $room = BookingRoom::find($roomId);
        if ($room) {
            $roomData = [];
            if ($request->has('arrival_date'))   $roomData['arrival_date']   = $request->arrival_date;
            if ($request->has('departure_date')) $roomData['departure_date'] = $request->departure_date;
            if ($request->has('arrival_time'))   $roomData['arrival_time']   = $request->arrival_time;
            if ($request->has('departure_time')) $roomData['departure_time'] = $request->departure_time;
            if ($request->has('rate'))           $roomData['rate']           = $request->rate;
            if ($request->has('extra_bed_qty'))  $roomData['extra_bed_qty']  = $request->extra_bed_qty;
            if ($request->has('extra_bed_rate')) $roomData['extra_bed_rate'] = $request->extra_bed_rate;
            if (!empty($roomData)) {
                $room->update($roomData);
            }
        }

        return response()->json(['success' => true, 'data' => $guest, 'message' => 'Cập nhật thông tin khách thành công.']);
    }

    // DELETE /booking-rooms/{roomId}/guests/{guestId}
    public function removeGuest($roomId, $guestId)
    {
        BookingRoomGuest::where('booking_room_id', $roomId)
            ->where('guest_id', $guestId)
            ->delete();

        // Xóa hẳn bản ghi trong bảng guests nếu khách không còn gán ở phòng nào khác
        $otherCount = BookingRoomGuest::where('guest_id', $guestId)->count();
        if ($otherCount === 0) {
            $guest = Guest::find($guestId);
            if ($guest) {
                $guest->delete();
            }
        }

        $room = BookingRoom::find($roomId);
        if ($room) {
            $room->update(['adults' => max(1, $room->guests()->count())]);
        }

        return response()->json(['success' => true, 'message' => 'Đã xóa khách khỏi phòng và cơ sở dữ liệu.']);
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
    public function bookingChildren(Request $request, $bookingId)
    {
        $booking  = Booking::findOrFail($bookingId);
        $query    = $booking->children()->with('bookingRoom', 'breakfastDetails');

        if ($request->filled('booking_room_id')) {
            $roomId = $request->booking_room_id;
            $query->where(function ($q) use ($roomId) {
                $q->where('booking_room_id', $roomId)
                  ->orWhereNull('booking_room_id');
            });
        }

        $children = $query->get();

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
            $r = BookingRoom::find($request->booking_room_id);
            if ($r) {
                $r->update([
                    'children_qty' => $r->children()->where('age_group', 'child')->count(),
                    'babies'       => $r->children()->where('age_group', 'baby')->count(),
                ]);
            }
        }

        return response()->json([
            'success' => true,
            'data'    => $child->load('breakfastDetails'),
            'message' => 'Đã thêm trẻ em.',
        ], 201);
    }

    // PUT /booking-children/{childId} — Cập nhật thông tin trẻ em
    public function updateChild(Request $request, $childId)
    {
        $child = BookingChild::findOrFail($childId);

        $request->validate([
            'full_name'        => 'nullable|string|max:200',
            'title'            => 'nullable|string|max:20',
            'dob'              => 'nullable|date',
            'nationality_code' => 'nullable|string|max:5',
            'age_group'        => 'nullable|in:baby,child',
        ]);

        $child->update($request->only(['full_name', 'title', 'dob', 'nationality_code', 'age_group']));

        return response()->json(['success' => true, 'data' => $child, 'message' => 'Cập nhật thông tin trẻ em thành công.']);
    }

    // DELETE /bookings/{bookingId}/children/{childId}
    public function removeChild($bookingId, $childId)
    {
        $child = BookingChild::where('booking_id', $bookingId)->findOrFail($childId);
        $roomId = $child->booking_room_id;
        $child->breakfastDetails()->delete();
        $child->delete();

        if ($roomId) {
            $r = BookingRoom::find($roomId);
            if ($r) {
                $r->update([
                    'children_qty' => $r->children()->where('age_group', 'child')->count(),
                    'babies'       => $r->children()->where('age_group', 'baby')->count(),
                ]);
            }
        }

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

    // POST /bookings/{bookingId}/bulk-update-guests
    public function bulkUpdate(Request $request)
    {
        $guestsData = $request->input('guests', []);
        $childrenData = $request->input('children', []);

        DB::beginTransaction();
        try {
            // Update người lớn (guests)
            foreach ($guestsData as $gData) {
                if (empty($gData['id'])) continue;
                $guest = Guest::find($gData['id']);
                if ($guest) {
                    $guest->update([
                        'full_name'         => $gData['full_name'] ?? '',
                        'title'             => $gData['title'] ?? null,
                        'dob'               => $gData['dob'] ?? null,
                        'nationality_code'  => $gData['nationality_code'] ?? null,
                        'id_type'           => $gData['id_type'] ?? null,
                        'id_number'         => $gData['id_number'] ?? null,
                        'id_issue_date'     => $gData['id_issue_date'] ?? null,
                        'passport_expiry'   => $gData['passport_expiry'] ?? null,
                        'address'           => $gData['address'] ?? null,
                        'province'          => $gData['province'] ?? null,
                        'district'          => $gData['district'] ?? null,
                        'ward'              => $gData['ward'] ?? null,
                        'residence_type'    => $gData['residence_type'] ?? null,
                        'temp_residence_to' => $gData['temp_residence_to'] ?? null,
                        'phone'             => $gData['phone'] ?? null,
                        'email'             => $gData['email'] ?? null,
                        'guest_type'        => $gData['guest_type'] ?? null,
                        'visa_no'           => $gData['visa_no'] ?? null,
                        'entry_date'        => $gData['entry_date'] ?? null,
                        'visa_expiry_date'  => $gData['visa_expiry_date'] ?? null,
                        'entry_purpose'     => $gData['entry_purpose'] ?? null,
                        'border_gate'       => $gData['border_gate'] ?? null,
                        'occupation'        => $gData['occupation'] ?? null,
                        'note'              => $gData['note'] ?? null,
                    ]);
                }
            }

            // Update trẻ em / em bé
            foreach ($childrenData as $cData) {
                if (empty($cData['id'])) continue;
                $child = BookingChild::find($cData['id']);
                if ($child) {
                    $child->update([
                        'full_name'         => $cData['full_name'] ?? '',
                        'title'             => $cData['title'] ?? null,
                        'dob'               => $cData['dob'] ?? null,
                        'nationality_code'  => $cData['nationality_code'] ?? null,
                        'id_type'           => $cData['id_type'] ?? null,
                        'id_number'         => $cData['id_number'] ?? null,
                        'id_issue_date'     => $cData['id_issue_date'] ?? null,
                        'passport_expiry'   => $cData['passport_expiry'] ?? null,
                        'address'           => $cData['address'] ?? null,
                        'province'          => $cData['province'] ?? null,
                        'district'          => $cData['district'] ?? null,
                        'ward'              => $cData['ward'] ?? null,
                        'residence_type'    => $cData['residence_type'] ?? null,
                        'temp_residence_to' => $cData['temp_residence_to'] ?? null,
                        'phone'             => $cData['phone'] ?? null,
                        'email'             => $cData['email'] ?? null,
                        'guest_type'        => $cData['guest_type'] ?? null,
                        'visa_no'           => $cData['visa_no'] ?? null,
                        'entry_date'        => $cData['entry_date'] ?? null,
                        'visa_expiry_date'  => $cData['visa_expiry_date'] ?? null,
                        'entry_purpose'     => $cData['entry_purpose'] ?? null,
                        'border_gate'       => $cData['border_gate'] ?? null,
                        'occupation'        => $cData['occupation'] ?? null,
                        'note'              => $cData['note'] ?? null,
                    ]);
                    $this->generateBreakfastDetails($child);
                }
            }

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }

        return response()->json(['success' => true, 'message' => 'Cập nhật thông tin khách hàng loạt thành công!']);
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
