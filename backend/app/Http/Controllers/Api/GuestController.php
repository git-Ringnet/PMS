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
use App\Models\BookingRoomService;
use App\Models\RoomRateCode;
use App\Models\StandardRate;
use App\Models\RoomLock;
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
                    $baby = BookingChild::create([
                        'booking_id'       => $bookingId,
                        'booking_room_id'  => $room->id,
                        'full_name'        => "Baby {$i}",
                        'title'            => 'Mr.',
                        'nationality_code' => 'VN',
                        'age_group'        => 'baby',
                        'child_status'     => 0,
                    ]);
                    $this->generateBreakfastDetails($baby);
                }

                // Tạo trẻ em còn thiếu
                for ($i = $existingChildren + 1; $i <= $targetChildren; $i++) {
                    $childRecord = BookingChild::create([
                        'booking_id'       => $bookingId,
                        'booking_room_id'  => $room->id,
                        'full_name'        => "Child {$i}",
                        'title'            => 'Mr.',
                        'nationality_code' => 'VN',
                        'age_group'        => 'child',
                        'child_status'     => 0,
                    ]);
                    $this->generateBreakfastDetails($childRecord);
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
            'avatar'          => 'nullable|string|max:255',
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
                        'full_name', 'dob', 'gender', 'nationality_code', 'phone', 'email', 'address', 'avatar',
                    ]));
                    $guest = $existingGuest;
                } else {
                    $guest = \App\Models\Guest::create($request->only([
                        'full_name', 'id_number', 'passport_number', 'dob', 'gender',
                        'nationality_code', 'phone', 'email', 'address', 'avatar',
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

        $avService = app(\App\Services\RoomAvailabilityService::class);
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

    // Checkout tạm cho tab Hóa đơn: chọn khách trong phòng hoặc toàn bộ Master.
    public function checkoutRoom(Request $request, $roomId)
    {
        $data = $request->validate(['guest_ids' => 'required|array|min:1', 'guest_ids.*' => 'string|max:50', 'skip_remaining_room_charge' => 'nullable|boolean']);
        $room = BookingRoom::with('booking')->findOrFail($roomId);
        $guestIds = collect($data['guest_ids']);
        $activeGuestIds = $room->guests()->whereNotIn('status', [BookingRoomGuest::STATUS_CHECKED_OUT, BookingRoomGuest::STATUS_CANCELLED])->pluck('guest_id');
        $isFullRoomCheckout = $activeGuestIds->diff($guestIds)->isEmpty();
        if ($isFullRoomCheckout) {
            $eligibility = $this->validateFullCheckout($room, false, (bool) ($data['skip_remaining_room_charge'] ?? false));
            if ($eligibility) return response()->json(['success' => false, 'code' => $eligibility['code'], 'message' => $eligibility['message'], 'data' => $eligibility['data'] ?? null], 422);
        }
        $response = $this->checkoutScope($room, $guestIds, true, $isFullRoomCheckout);
        if ($isFullRoomCheckout && $response->getData()->success) {
            $booking = $room->booking()->first();
            $hasActiveRooms = $booking->bookingRooms()
                ->whereNotIn('status', [BookingRoom::STATUS_CANCELLED, BookingRoom::STATUS_CHECKED_OUT])
                ->exists();
            if (!$hasActiveRooms) {
                if ($this->hasUnpaidMasterBills($booking)) {
                    $booking->update(['status' => \App\Models\Booking::STATUS_CHECKIN]);
                } else {
                    $booking->update(['status' => \App\Models\Booking::STATUS_CHECKOUT]);
                }
            }
        }
        return $response;
    }

    /** Xem trước điều kiện checkout nhiều phòng trước khi thực hiện. */
    public function previewCheckoutRooms(Request $request, $bookingId)
    {
        $data = $request->validate(['room_ids' => 'required|array|min:1', 'room_ids.*' => 'string|max:50']);
        $booking = Booking::with('bookingRooms')->findOrFail($bookingId);
        $rooms = $booking->bookingRooms->whereIn('id', $data['room_ids']);
        $roomIds = $rooms->pluck('id')->map(fn ($id) => (string) $id)->all();
        $unpaidMasterBills = \App\Models\ServiceBill::query()
            ->where('Edit', 0)
            ->where(function ($q) { $q->whereNull('PaymentId')->orWhere('PaymentId', ''); })
            ->where('Status', '!=', 2)
            ->where(function ($q) use ($booking, $roomIds) {
                $q->whereRaw('CAST(RegisterID2 AS CHAR) = ?', [(string) $booking->id])
                    ->orWhereRaw('CAST(RegisterId1 AS CHAR) = ?', [(string) $booking->id]);
                if ($roomIds) {
                    $q->orWhereIn(DB::raw('CAST(RentalRoomId2 AS CHAR)'), $roomIds)
                        ->orWhereIn(DB::raw('CAST(RentalRoomId1 AS CHAR)'), $roomIds);
                }
            })
            ->get(['Ma', 'Date', 'CreatedDate', 'OpenTime', 'ServiceId', 'DescriptionServive', 'Amount', 'RentalRoomId2', 'CustomerId2', 'Folio', 'Status']);

        return response()->json([
            'success' => true,
            'data' => [
                'master_unpaid' => $this->hasUnpaidMasterBills($booking),
                'master_unpaid_bills' => $unpaidMasterBills->map(fn ($bill) => [
                    'id' => $bill->Ma,
                    'date' => $bill->Date ?: $bill->CreatedDate,
                    'time' => $bill->OpenTime,
                    'service' => $bill->DescriptionServive ?: $bill->ServiceId,
                    'amount' => (float) $bill->Amount,
                    'room_id' => $bill->RentalRoomId2,
                    'guest_id' => $bill->CustomerId2,
                    'folio' => $bill->Folio,
                ])->values(),
                'rooms' => $rooms->map(function (BookingRoom $room) {
                    $eligibility = $this->validateFullCheckout($room);
                    return [
                        'room_id' => $room->id,
                        'room_number' => $room->room_number,
                        'eligible' => !$eligibility,
                        'code' => $eligibility['code'] ?? null,
                        'message' => $eligibility['message'] ?? null,
                    ];
                })->values(),
            ],
        ]);
    }

    /** Checkout riêng một trẻ em; vẫn phải còn ít nhất một người lớn trong phòng. */
    public function checkoutChild($roomId, $childId)
    {
        $room = BookingRoom::findOrFail($roomId);
        $child = BookingChild::where('booking_room_id', $room->id)->findOrFail($childId);
        if ((int) $child->child_status === BookingRoomGuest::STATUS_CHECKED_OUT) {
            return response()->json(['success' => false, 'message' => 'Trẻ em đã checkout.'], 422);
        }
        $activeAdults = $room->guests()->whereNotIn('status', [BookingRoomGuest::STATUS_CHECKED_OUT, BookingRoomGuest::STATUS_CANCELLED])->count();
        if ($activeAdults === 0) {
            return response()->json(['success' => false, 'message' => 'Phải còn ít nhất một người lớn trong phòng để checkout trẻ em.'], 422);
        }
        $child->update(['child_status' => BookingRoomGuest::STATUS_CHECKED_OUT]);
        return response()->json(['success' => true, 'message' => 'Checkout trẻ em thành công.']);
    }

    public function checkoutBooking(Request $request, $bookingId)
    {
        $booking = \App\Models\Booking::with('bookingRooms.guests')->findOrFail($bookingId);
        $rooms = $booking->bookingRooms->where('status', BookingRoom::STATUS_CHECKED_IN);

        // Checkout Master chỉ xét công nợ/cọc của toàn Booking, không xét ngày đi từng phòng.
        $eligibility = $this->validateMasterCheckout($booking, $rooms);
        if ($eligibility) return response()->json(['success' => false, 'code' => $eligibility['code'], 'message' => $eligibility['message']], 422);

        return DB::transaction(function () use ($booking, $rooms) {
            foreach ($rooms->pluck('id') as $roomId) {
                $room = BookingRoom::with('booking')->findOrFail($roomId);
                $guestIds = $room->guests->whereNotIn('status', [BookingRoomGuest::STATUS_CHECKED_OUT, BookingRoomGuest::STATUS_CANCELLED])->pluck('guest_id');
                if ($guestIds->isNotEmpty()) {
                    $result = $this->checkoutScope($room, $guestIds, false, true);
                    if ($result instanceof \Illuminate\Http\JsonResponse && !$result->getData()->success) {
                        throw new \RuntimeException($result->getData()->message ?? 'Không thể checkout phòng.');
                    }
                }
            }
            $booking->refresh();
            $booking->update(['status' => \App\Models\Booking::STATUS_CHECKOUT]);

            try {
                $bCode = $booking->booking_code ?? ('GAL' . $booking->id);
                $roomsList = $rooms->pluck('room_number')->filter()->implode(', ');
                \App\Services\ActivityLogService::logCheckOut($bCode, $roomsList, $request);
            } catch (\Throwable $e) {}

            return response()->json(['success' => true, 'message' => 'Đã checkout toàn bộ Master.']);
        });
    }

    /**
     * Hoàn tác checkout của một phòng trong đúng ngày hệ thống.
     * Chỉ khôi phục nhóm khách được checkout sau cùng; khách checkout sớm giữ nguyên lịch sử.
     */
    public function restoreRoomCheckout($roomId)
    {
        return DB::transaction(function () use ($roomId) {
            $room = BookingRoom::with(['booking', 'guests', 'children', 'room'])->lockForUpdate()->findOrFail($roomId);
            $systemDate = app(\App\Services\RoomAvailabilityService::class)->getSystemDate()->startOfDay();

            if ($room->status !== BookingRoom::STATUS_CHECKED_OUT) {
                return response()->json(['success' => false, 'message' => 'Chỉ có thể khôi phục phòng đã checkout.'], 422);
            }

            if (!$room->CheckoutDate || Carbon::parse($room->CheckoutDate)->startOfDay()->ne($systemDate)) {
                return response()->json(['success' => false, 'message' => 'Chỉ được khôi phục checkout của phòng trong ngày hệ thống.'], 422);
            }

            if ($room->room_number) {
                $hasInhouseBooking = BookingRoom::where('room_number', $room->room_number)
                    ->where('id', '!=', $room->id)
                    ->where('status', BookingRoom::STATUS_CHECKED_IN)
                    ->exists();
                if ($hasInhouseBooking) {
                    return response()->json(['success' => false, 'message' => 'Phòng đã được booking khác check-in, không thể khôi phục checkout.'], 422);
                }

                $hasActiveLock = RoomLock::where('room_number', $room->room_number)
                    ->where('is_active', RoomLock::STATUS_ACTIVE)
                    ->where('start_date', '<=', $systemDate->copy()->endOfDay())
                    ->where('end_date', '>=', $systemDate->copy()->startOfDay())
                    ->exists();
                if ($hasActiveLock) {
                    return response()->json(['success' => false, 'message' => 'Phòng đang bị khóa, không thể khôi phục checkout.'], 422);
                }
            }

            $checkedOutGuests = $room->guests->where('status', BookingRoomGuest::STATUS_CHECKED_OUT);
            if ($checkedOutGuests->isEmpty()) {
                return response()->json(['success' => false, 'message' => 'Không tìm thấy khách checkout để khôi phục.'], 422);
            }

            $lastCheckout = $checkedOutGuests->sortByDesc(fn ($guest) => sprintf('%s %s', $guest->actual_checkout_date?->toDateString() ?? '', $guest->actual_checkout_time ?? ''))->first();
            $lastCheckoutDate = $lastCheckout->actual_checkout_date?->toDateString();
            $lastCheckoutTime = $lastCheckout->actual_checkout_time;
            $restoredGuests = $checkedOutGuests->filter(fn ($guest) =>
                $guest->actual_checkout_date?->toDateString() === $lastCheckoutDate
                && $guest->actual_checkout_time === $lastCheckoutTime
            );

            BookingRoomGuest::whereIn('id', $restoredGuests->pluck('id'))->update([
                'status' => BookingRoomGuest::STATUS_CHECKED_IN,
                'actual_checkout_date' => null,
                'actual_checkout_time' => null,
                'checkout_by' => null,
            ]);
            app(\App\Services\GuestStatusSyncService::class)->syncForGuestIds($restoredGuests->pluck('guest_id'));
            $room->children()->where('child_status', BookingRoomGuest::STATUS_CHECKED_OUT)->update(['child_status' => BookingRoomGuest::STATUS_CHECKED_IN]);
            $room->update([
                'status' => BookingRoom::STATUS_CHECKED_IN,
                'CheckoutDate' => null,
                'CheckoutTime' => null,
                'check_out_user' => null,
            ]);
            if ($room->room) $room->room->update(['status' => 'occupied']);
            if ($room->booking->status === Booking::STATUS_CHECKOUT) $room->booking->update(['status' => Booking::STATUS_CHECKIN]);

            return response()->json([
                'success' => true,
                'data' => ['room_id' => $room->id, 'restored_guest_ids' => $restoredGuests->pluck('guest_id')->values()],
                'message' => 'Khôi phục checkout phòng thành công.',
            ]);
        });
    }

    /** Khôi phục checkout Master: chỉ mở lại trạng thái booking, không khôi phục phòng con. */
    public function restoreBookingCheckout($bookingId)
    {
        return DB::transaction(function () use ($bookingId) {
            $booking = Booking::findOrFail($bookingId);
            if ($booking->status !== Booking::STATUS_CHECKOUT) {
                return response()->json(['success' => false, 'message' => 'Chỉ có thể khôi phục Master đã checkout.'], 422);
            }

            $systemDate = app(\App\Services\RoomAvailabilityService::class)->getSystemDate()->startOfDay();

            $bookingRooms = $booking->bookingRooms()->where('status', BookingRoom::STATUS_CHECKED_OUT)->get();
            foreach ($bookingRooms as $room) {
                if ($room->room_number) {
                    $hasInhouseBooking = BookingRoom::where('room_number', $room->room_number)
                        ->where('id', '!=', $room->id)
                        ->where('status', BookingRoom::STATUS_CHECKED_IN)
                        ->exists();
                    if ($hasInhouseBooking) {
                        return response()->json(['success' => false, 'message' => "Phòng {$room->room_number} đã được booking khác check-in, không thể khôi phục checkout."], 422);
                    }

                    $hasActiveLock = RoomLock::where('room_number', $room->room_number)
                        ->where('is_active', RoomLock::STATUS_ACTIVE)
                        ->where('start_date', '<=', $systemDate->copy()->endOfDay())
                        ->where('end_date', '>=', $systemDate->copy()->startOfDay())
                        ->exists();
                    if ($hasActiveLock) {
                        return response()->json(['success' => false, 'message' => "Phòng {$room->room_number} đang bị khóa, không thể khôi phục checkout."], 422);
                    }
                }

                $room->guests()->where('status', BookingRoomGuest::STATUS_CHECKED_OUT)->update([
                    'status' => BookingRoomGuest::STATUS_CHECKED_IN,
                    'actual_checkout_date' => null,
                    'actual_checkout_time' => null,
                    'checkout_by' => null,
                ]);

                $room->children()->where('child_status', BookingRoomGuest::STATUS_CHECKED_OUT)->update([
                    'child_status' => BookingRoomGuest::STATUS_CHECKED_IN
                ]);

                $room->update([
                    'status' => BookingRoom::STATUS_CHECKED_IN,
                    'CheckoutDate' => null,
                    'CheckoutTime' => null,
                    'check_out_user' => null,
                ]);

                if ($room->room) {
                    $room->room->update(['status' => 'occupied']);
                }
            }

            $booking->update(['status' => Booking::STATUS_CHECKIN]);
            return response()->json(['success' => true, 'message' => 'Khôi phục checkout Master thành công.']);
        });
    }

    private function checkoutScope(BookingRoom $room, $guestIds, bool $wrap = true, bool $fullRoom = false)
    {
        $work = function () use ($room, $guestIds, $fullRoom) {
            if ($room->status === BookingRoom::STATUS_CANCELLED) throw new \RuntimeException('Phòng đã hủy.');
            $allRequestedPivots = $room->guests()->whereIn('guest_id', $guestIds)->get();
            if ($allRequestedPivots->count() !== $guestIds->unique()->count()) throw new \RuntimeException('Có khách không thuộc phòng được chọn.');
            $pivots = $allRequestedPivots->whereNotIn('status', [BookingRoomGuest::STATUS_CHECKED_OUT, BookingRoomGuest::STATUS_CANCELLED]);
            if ($pivots->isEmpty() && $allRequestedPivots->isNotEmpty() && $allRequestedPivots->every(fn ($pivot) => $pivot->status === BookingRoomGuest::STATUS_CHECKED_OUT)) {
                return ['room_id' => $room->id, 'checked_out_guest_ids' => $allRequestedPivots->pluck('guest_id')->values(), 'room_checked_out' => true, 'already_checked_out' => true];
            }
            if ($pivots->isEmpty()) throw new \RuntimeException('Phải chọn ít nhất một khách đang lưu trú trong phòng.');
            $systemDate = app(\App\Services\RoomAvailabilityService::class)->getSystemDate();
            foreach ($pivots as $pivot) $pivot->update(['status' => BookingRoomGuest::STATUS_CHECKED_OUT, 'actual_checkout_date' => $systemDate->toDateString(), 'actual_checkout_time' => now()->format('H:i:s'), 'checkout_by' => Auth::user()?->username ?? 'system']);
            $remaining = $room->guests()->whereNotIn('status', [BookingRoomGuest::STATUS_CHECKED_OUT, BookingRoomGuest::STATUS_CANCELLED])->count();
            if ($remaining === 0) {
                if ($room->children()->exists() && !$fullRoom) throw new \RuntimeException('Không thể checkout hết người lớn để chỉ còn trẻ em trong phòng.');
                $room->children()->where('child_status', 0)->update(['child_status' => 2]);
                $room->update([
                    'status' => BookingRoom::STATUS_CHECKED_OUT,
                    'CheckoutDate' => $systemDate->toDateString(),
                    'CheckoutTime' => now()->format('H:i:s'),
                    'departure_date' => $systemDate->toDateString(),
                    'check_out_user' => Auth::user()?->username ?? 'system',
                ]);
                if ($room->room) $room->room->update(['status' => 'checkout']);
            } else {
                $targetGuestId = $room->guests()->whereNotIn('status', [BookingRoomGuest::STATUS_CHECKED_OUT, BookingRoomGuest::STATUS_CANCELLED])->orderByDesc('is_primary')->orderBy('id')->value('guest_id');
                if ($targetGuestId) {
                    \App\Models\BookingRoomService::where('booking_room_id', $room->id)->whereIn('guest_id', $pivots->pluck('guest_id'))->update(['guest_id' => $targetGuestId]);
                    \App\Models\Payment::where('booking_room_id', $room->id)->whereIn('guest_id', $pivots->pluck('guest_id'))->update(['guest_id' => $targetGuestId]);
                    \App\Models\ServiceBill::where(function ($q) use ($room) {
                        $q->whereRaw('CAST(RentalRoomId2 AS CHAR) = ?', [(string) $room->id])
                          ->orWhereRaw('CAST(RentalRoomId1 AS CHAR) = ?', [(string) $room->id]);
                    })->where(function ($q) use ($pivots) { $q->whereIn('CustomerId2', $pivots->pluck('guest_id'))->orWhere(function ($inner) use ($pivots) { $inner->whereNull('CustomerId2')->whereIn('CustomerId1', $pivots->pluck('guest_id')); }); })->update(['CustomerId2' => $targetGuestId]);
                }
                $hasPrimary = $room->guests()->where('is_primary', true)->whereNotIn('status', [BookingRoomGuest::STATUS_CHECKED_OUT, BookingRoomGuest::STATUS_CANCELLED])->exists();
                if (!$hasPrimary) {
                    $room->guests()->whereNotIn('status', [BookingRoomGuest::STATUS_CHECKED_OUT, BookingRoomGuest::STATUS_CANCELLED])->update(['is_primary' => false]);
                    $room->guests()->whereNotIn('status', [BookingRoomGuest::STATUS_CHECKED_OUT, BookingRoomGuest::STATUS_CANCELLED])->orderBy('id')->limit(1)->update(['is_primary' => true]);
                }
            }
            return ['room_id' => $room->id, 'checked_out_guest_ids' => $pivots->pluck('guest_id')->values(), 'room_checked_out' => $remaining === 0];
        };
        try { $result = $wrap ? DB::transaction($work) : $work(); return response()->json(['success' => true, 'data' => $result, 'message' => 'Checkout thành công.']); }
        catch (\Throwable $e) { return response()->json(['success' => false, 'message' => $e->getMessage()], 422); }
    }

    private function validateFullCheckout(BookingRoom $room, bool $masterScope = false, bool $skipRemainingRoomCharge = false): ?array
    {
        if ($room->status === BookingRoom::STATUS_CANCELLED) return ['code' => 'room_cancelled', 'message' => 'Phòng đã hủy.'];
        $activeAdults = $room->guests()->whereNotIn('status', [BookingRoomGuest::STATUS_CHECKED_OUT, BookingRoomGuest::STATUS_CANCELLED])->count();
        if ($activeAdults === 0 && !$room->children()->exists()) return ['code' => 'no_active_guest', 'message' => 'Phòng không còn khách đang lưu trú.'];

        $systemDate = app(\App\Services\RoomAvailabilityService::class)->getSystemDate()->startOfDay();
        $departure = Carbon::parse($room->departure_date)->startOfDay();
        $allowEarlyCheckout = in_array(
            strtolower((string) \App\Models\HotelConfig::where('name', 'AllowEarlyCheckout')->value('value')),
            ['1', 'true', 'yes'],
            true
        );
        if (!$masterScope && $departure->gt($systemDate)) {
            if (!$allowEarlyCheckout) {
                return ['code' => 'early_checkout_disabled', 'message' => 'Hệ thống không cho phép trả phòng sớm (AllowEarlyCheckout=0).'];
            }
            $unchargedDates = $this->remainingRoomChargeDates($room, $systemDate, $departure->copy()->subDay());
            if (!$skipRemainingRoomCharge && $unchargedDates->isNotEmpty()) {
                return ['code' => 'early_checkout', 'message' => 'Phòng ' . ($room->room_number ?: $room->id) . ' checkout sớm; cần charge tiền phòng các đêm còn lại trước.', 'data' => ['room_id' => $room->id, 'remaining_from' => $systemDate->toDateString(), 'remaining_to' => $departure->copy()->subDay()->toDateString(), 'remaining_dates' => $unchargedDates->values()]];
            }
        }

        $unpaidQuery = \App\Models\ServiceBill::query()
            ->where('Edit', 0)
            ->where(function ($q) { $q->whereNull('PaymentId')->orWhere('PaymentId', ''); })
            ->where('Status', '!=', 2)
            ->whereRaw('CAST(RentalRoomId2 AS CHAR) = ?', [(string) $room->id]);
        $unpaid = $unpaidQuery->exists();
        if ($unpaid) return ['code' => 'unpaid_bill', 'message' => 'Phòng còn hóa đơn chưa thanh toán.'];
        if ($this->hasUnpaidDebt($room->booking_id, $masterScope ? null : $room->id)) {
            return ['code' => 'unpaid_debt', 'message' => 'Phòng vẫn còn công nợ chưa thanh toán.'];
        }
        $unusedDepositQuery = \App\Models\Payment::where('booking_id', $room->booking_id)
            ->where('edit_flag', 0)
            ->whereNull('payment_id')
            ->where(function ($q) { $q->where('pack2', 'DPR')->orWhere('pack4', 'AP'); });
        if (!$masterScope) $unusedDepositQuery->where('booking_room_id', $room->id);
        $unusedDeposit = $unusedDepositQuery->exists();
        if ($unusedDeposit) return ['code' => 'unused_deposit', 'message' => 'Phòng/booking còn tiền cọc chưa dùng để thanh toán hóa đơn.'];
        if ($masterScope) {
            $bookingUnpaid = \App\Models\ServiceBill::query()->where('Edit', 0)->where(function ($q) use ($room) { $q->whereRaw('CAST(RegisterID2 AS CHAR) = ?', [(string) $room->booking_id])->orWhereRaw('CAST(RegisterId1 AS CHAR) = ?', [(string) $room->booking_id]); })->whereNull('PaymentId')->where('Status', '!=', 2)->exists();
            if ($bookingUnpaid) return ['code' => 'unpaid_master', 'message' => 'Master còn hóa đơn chưa thanh toán.'];
        }
        return null;
    }

    /** Master checkout ignores departure dates but requires every current bill/deposit to be settled. */
    private function validateMasterCheckout(Booking $booking, $rooms): ?array
    {
        $roomIds = $rooms->pluck('id')->map(fn ($id) => (string) $id)->all();
        $unpaid = \App\Models\ServiceBill::query()
            ->where('Edit', 0)
            ->where(function ($q) { $q->whereNull('PaymentId')->orWhere('PaymentId', ''); })
            ->where('Status', '!=', 2)
            ->where(function ($q) use ($booking, $roomIds) {
                $q->whereRaw('CAST(RegisterID2 AS CHAR) = ?', [(string) $booking->id])
                    ->orWhereRaw('CAST(RegisterId1 AS CHAR) = ?', [(string) $booking->id]);
                if ($roomIds) {
                    $q->orWhereIn(DB::raw('CAST(RentalRoomId2 AS CHAR)'), $roomIds)
                        ->orWhereIn(DB::raw('CAST(RentalRoomId1 AS CHAR)'), $roomIds);
                }
            })
            ->exists();
        if ($unpaid) return ['code' => 'unpaid_master', 'message' => 'Master còn hóa đơn chưa thanh toán.'];

        if ($this->hasUnpaidDebt($booking->id)) {
            return ['code' => 'unpaid_debt', 'message' => 'Phòng vẫn còn công nợ chưa thanh toán.'];
        }

        $unusedDeposit = \App\Models\Payment::where('booking_id', $booking->id)
            ->where('edit_flag', 0)
            ->whereNull('payment_id')
            ->where(function ($q) { $q->where('pack2', 'DPR')->orWhere('pack4', 'AP'); })
            ->exists();
        if ($unusedDeposit) return ['code' => 'unused_deposit', 'message' => 'Master còn tiền cọc chưa dùng để thanh toán hóa đơn.'];

        return null;
    }

    /** Công nợ AC chỉ được xem là đã thanh toán khi tổng giải trừ đạt đủ số tiền gốc. */
    private function hasUnpaidDebt(int|string $bookingId, int|string|null $roomId = null): bool
    {
        $query = \App\Models\Payment::query()
            ->where('booking_id', $bookingId)
            ->where('payment_method_id', 'AC')
            ->where('edit_flag', 0)
            ->where('status', '!=', \App\Models\Payment::STATUS_DELETED)
            ->whereNull('deleted_at');

        if ($roomId !== null) {
            $query->whereRaw('CAST(booking_room_id AS CHAR) = ?', [(string) $roomId]);
        }

        return $query->get(['id', 'amount'])->contains(function ($payment) {
            $settled = \App\Models\PaymentDebtSettlement::query()
                ->where('payment_id', $payment->id)
                ->where('edit_flag', 0)
                ->sum('amount');

            return round((float) $payment->amount - (float) $settled, 2) > 0;
        });
    }

    private function hasUnpaidMasterBills(Booking $booking): bool
    {
        $checkedOutRoomIds = $booking->bookingRooms()
            ->where('status', BookingRoom::STATUS_CHECKED_OUT)
            ->pluck('id')
            ->map(fn ($id) => (string) $id)
            ->all();

        return \App\Models\ServiceBill::query()
            ->where('Edit', 0)
            ->where(function ($q) { $q->whereNull('PaymentId')->orWhere('PaymentId', ''); })
            ->where('Status', '!=', 2)
            ->where(function ($q) use ($booking) {
                $q->whereRaw('CAST(RegisterID2 AS CHAR) = ?', [(string) $booking->id])
                    ->orWhereRaw('CAST(RegisterId1 AS CHAR) = ?', [(string) $booking->id]);
            })
            ->where(function ($q) use ($booking, $checkedOutRoomIds) {
                $q->whereNull('RentalRoomId2')->orWhere('RentalRoomId2', '')->orWhere('RentalRoomId2', '0');
                if ($checkedOutRoomIds) $q->orWhereIn(DB::raw('CAST(RentalRoomId2 AS CHAR)'), $checkedOutRoomIds);
            })
            ->exists();
    }

    private function hasRemainingRoomCharges(BookingRoom $room, Carbon $from, Carbon $to): bool
    {
        return $this->remainingRoomChargeDates($room, $from, $to)->isEmpty();
    }

    private function remainingRoomChargeDates(BookingRoom $room, Carbon $from, Carbon $to)
    {
        $requiredDates = collect();
        for ($date = $from->copy(); $date->lte($to); $date->addDay()) $requiredDates->push($date->toDateString());

        $chargedDates = \App\Models\ServiceBill::where('RegisterId1', $room->booking_id)
            ->where('RentalRoomId1', $room->id)
            ->where('ServiceId', 'RM')
            ->where('Edit', 0)
            ->whereIn(DB::raw('DATE(Date)'), $requiredDates->all())
            ->pluck('Date')
            ->map(fn ($date) => Carbon::parse($date)->toDateString())
            ->unique();

        return $requiredDates->diff($chargedDates)->values();
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
            'avatar'            => 'nullable|string|max:255',
            'arrival_date'      => 'nullable|date',
            'arrival_time'      => 'nullable|date_format:H:i',
            'departure_date'    => 'nullable|date',
            'departure_time'    => 'nullable|date_format:H:i',
            'rate'              => 'nullable|numeric|min:0',
            'rate_code'         => 'nullable|string|max:100|exists:room_rate_codes,Ma',
            'extra_bed_qty'     => 'nullable|integer|min:0',
            'extra_bed_rate'    => 'nullable|numeric|min:0',
        ]);

        $guest->update($request->only([
            'full_name', 'title', 'id_type', 'id_number', 'id_issue_date',
            'passport_number', 'passport_expiry', 'dob', 'gender', 'nationality_code',
            'phone', 'email', 'address', 'guest_type',
            'province', 'district', 'ward',
            'residence_type', 'temp_residence_to',
            'visa_no', 'entry_date', 'visa_expiry_date',
            'entry_purpose', 'border_gate', 'occupation', 'note', 'avatar',
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
            $this->updateBookingRoomFromGuestRequest($request, $room);
        }

        return response()->json(['success' => true, 'data' => $guest, 'message' => 'Cập nhật thông tin khách thành công.']);
    }

    private function updateBookingRoomFromGuestRequest(Request $request, BookingRoom $room): void
    {
        $roomData = [];
        if ($request->has('arrival_date'))   $roomData['arrival_date']   = $request->arrival_date;
        if ($request->has('departure_date')) $roomData['departure_date'] = $request->departure_date;
        if ($request->has('arrival_time'))   $roomData['arrival_time']   = $request->arrival_time;
        if ($request->has('departure_time')) $roomData['departure_time'] = $request->departure_time;
        if ($request->has('rate'))           $roomData['rate']           = $request->rate;
        if ($request->has('rate_code'))      $roomData['rate_code']      = filled($request->rate_code) ? trim($request->rate_code) : null;
        if ($request->has('extra_bed_qty'))  $roomData['extra_bed_qty']  = $request->extra_bed_qty;
        if ($request->has('extra_bed_rate')) $roomData['extra_bed_rate'] = $request->extra_bed_rate;

        DB::transaction(function () use ($request, $room, $roomData) {
            if ($roomData) $room->update($roomData);
            if ($request->has('rate_code')) {
                $this->syncRateCodeRoomCharges($room->fresh(), $request->has('rate') ? (float) $request->rate : null);
            }
        });
    }

    private function syncRateCodeRoomCharges(BookingRoom $room, ?float $fallbackRate = null): void
    {
        $systemDate = app(\App\Services\RoomAvailabilityService::class)->getSystemDate()->startOfDay();
        $arrival = Carbon::parse($room->arrival_date)->startOfDay();
        $departure = Carbon::parse($room->departure_date)->startOfDay();
        $start = $arrival->greaterThan($systemDate) ? $arrival : $systemDate;
        if ($start->greaterThanOrEqualTo($departure)) return;

        $rateCode = $room->rate_code ? RoomRateCode::with(['ratePlans', 'dailyMappings'])->find($room->rate_code) : null;
        $standardRate = !$rateCode && $room->room_class_id
            ? (float) (StandardRate::where('room_class_id', $room->room_class_id)
                ->when($room->RoomKind, fn ($query) => $query->where('room_form_id', $room->RoomKind))
                ->value('room_price') ?? 0)
            : 0;
        $firstRate = null;
        $room->loadMissing('roomClass');
        $roomForm = $room->RoomKind ? \App\Models\RoomForm::find($room->RoomKind)?->name : null;

        for ($date = $start->copy(); $date->lt($departure); $date->addDay()) {
            $resolvedRate = $this->resolveRateCodePrice($rateCode, $room->room_class_id, $room->roomClass?->code, $roomForm, $date);
            $rate = $rateCode ? $resolvedRate : ($standardRate ?: (float) ($fallbackRate ?? 0));
            $firstRate ??= $rate;

            $service = BookingRoomService::withTrashed()
                ->where('booking_room_id', $room->id)
                ->where('service_code', BookingRoomService::CODE_ROOM)
                ->whereDate('service_date', $date->toDateString())
                ->first();
            if ($service && (int) $service->is_posted === 1) continue;

            BookingRoomService::withTrashed()->updateOrCreate(
                ['booking_room_id' => $room->id, 'service_code' => BookingRoomService::CODE_ROOM, 'service_date' => $date->toDateString()],
                [
                    'service_name' => BookingRoomService::catalogName(BookingRoomService::CODE_ROOM, 'Dịch vụ phòng nghỉ'),
                    'quantity' => 1,
                    'rate' => $rate,
                    'department' => 'FO',
                    'is_room' => 1,
                    'is_posted' => 0,
                    'deleted_at' => null,
                    'created_by' => Auth::user()?->username ?? 'system',
                ]
            );
        }

        if ($firstRate !== null) $room->update(['rate' => $firstRate]);
    }

    private function resolveRateCodePrice(?RoomRateCode $rateCode, $roomClassId, ?string $roomClassCode, ?string $roomForm, Carbon $date): float
    {
        if (!$rateCode) return 0;
        $mapping = $rateCode->IsDaily
            ? $rateCode->dailyMappings->first(fn ($item) => Carbon::parse($item->Date)->toDateString() === $date->toDateString())
            : null;
        if ($rateCode->IsDaily && !$mapping) return 0;
        $plan = $rateCode->IsDaily
            ? $rateCode->ratePlans->firstWhere('Code', $mapping->Code)
            : ($rateCode->ratePlans->firstWhere('Code', 'DEFAULT') ?? $rateCode->ratePlans->first());
        if (!$plan) return 0;
        $period = is_string($plan->Period) ? json_decode($plan->Period, true) : $plan->Period;
        if (!is_array($period)) return 0;
        if (!$roomClassCode || !$roomForm) return 0;
        $planCode = (string) ($plan->Code ?: 'DEFAULT');
        foreach ([
            $planCode . '_' . $roomClassCode . '_' . $roomForm,
            $rateCode->Ma . '_' . $roomClassCode . '_' . $roomForm,
        ] as $key) {
            if (array_key_exists($key, $period) && is_numeric($period[$key])) return (float) $period[$key];
        }
        return 0;
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
            'arrival_date'     => 'nullable|date',
            'arrival_time'     => 'nullable|date_format:H:i',
            'departure_date'   => 'nullable|date',
            'departure_time'   => 'nullable|date_format:H:i',
            'rate'             => 'nullable|numeric|min:0',
            'rate_code'        => 'nullable|string|max:100|exists:room_rate_codes,Ma',
            'extra_bed_qty'    => 'nullable|integer|min:0',
            'extra_bed_rate'   => 'nullable|numeric|min:0',
        ]);

        $child->update($request->only(['full_name', 'title', 'dob', 'nationality_code', 'age_group']));

        if ($child->booking_room_id) {
            $room = BookingRoom::find($child->booking_room_id);
            if ($room) $this->updateBookingRoomFromGuestRequest($request, $room);
        }

        return response()->json(['success' => true, 'data' => $child, 'message' => 'Cập nhật thông tin trẻ em thành công.']);
    }

    // DELETE /bookings/{bookingId}/children/{childId}
    public function removeChild($bookingId, $childId)
    {
        $child = BookingChild::where('booking_id', $bookingId)->findOrFail($childId);
        $roomId = $child->booking_room_id;
        $child->breakfastDetails()->delete();
        $child->delete();

        // Xóa các dịch vụ ăn sáng trẻ em tương ứng trong booking_room_services
        if ($roomId) {
            $serviceCode = \App\Models\HotelConfig::where('name', 'Booking_BFChildSetServiceId')->value('value') ?: 'BD';
            \App\Models\BookingRoomService::where('booking_room_id', $roomId)
                ->where('service_code', $serviceCode)
                ->where('note', "Phụ thu ăn sáng trẻ em: {$child->full_name}")
                ->delete();

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

        $updateData = $request->only(['breakfast', 'is_free', 'is_extra_charge', 'is_room', 'amount']);

        // Tự động tính toán lại amount nếu client không gửi kèm
        if (!$request->has('amount')) {
            $breakfast = $request->has('breakfast')
                ? filter_var($request->breakfast, FILTER_VALIDATE_BOOLEAN)
                : (bool) $detail->breakfast;

            $isExtraCharge = $request->has('is_extra_charge')
                ? filter_var($request->is_extra_charge, FILTER_VALIDATE_BOOLEAN)
                : (bool) $detail->is_extra_charge;

            $isFree = $request->has('is_free')
                ? filter_var($request->is_free, FILTER_VALIDATE_BOOLEAN)
                : (bool) $detail->is_free;

            // Loại trừ tương hỗ giữa Miễn phí (is_free) và Phụ phí (is_extra_charge)
            if ($request->has('is_extra_charge') && filter_var($request->is_extra_charge, FILTER_VALIDATE_BOOLEAN)) {
                $isFree = false;
                $updateData['is_free'] = false;
            }
            if ($request->has('is_free') && filter_var($request->is_free, FILTER_VALIDATE_BOOLEAN)) {
                $isExtraCharge = false;
                $updateData['is_extra_charge'] = false;
            }

            if (!$breakfast || $isFree) {
                $updateData['amount'] = 0.0;
            } else {
                $setting = \App\Models\HotelSetting::first();
                if ($isExtraCharge) {
                    // Bật phụ phí → lấy giá ăn sáng trẻ em từ cấu hình công ty
                    $updateData['amount'] = (float) ($setting?->breakfast_child_rate ?? 0);
                } else {
                    // Tắt phụ phí → lấy giá từ tham số BreakfastRateChild
                    $bfRateChild = \App\Models\HotelConfig::where('name', 'BreakfastRateChild')->value('value');
                    $updateData['amount'] = (float) ($bfRateChild ?? $setting?->breakfast_child_rate ?? 0);
                }
            }
        }

        $detail->update($updateData);

        // Đồng bộ chi tiết ăn sáng trẻ em vào booking_room_services
        $this->syncChildBreakfastToService($detail);

        return response()->json(['success' => true, 'data' => $detail->fresh(), 'message' => 'Cập nhật ăn sáng trẻ em thành công.']);
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

    public function uploadAvatar(Request $request, $id)
    {
        $request->validate([
            'avatar' => 'required|image|max:10240',
        ], [
            'avatar.required' => 'Vui lòng chọn ảnh đại diện.',
            'avatar.image' => 'File tải lên phải là hình ảnh.',
            'avatar.max' => 'Dung lượng ảnh không được vượt quá 10MB.',
            'avatar.uploaded' => 'Tải ảnh lên thất bại. Vui lòng kiểm tra lại dung lượng file (tối đa 10MB) hoặc cấu hình máy chủ PHP.',
        ]);

        $guest = Guest::find($id);
        if (!$guest) {
            return response()->json(['success' => false, 'message' => 'Không tìm thấy khách hàng'], 404);
        }

        if ($request->hasFile('avatar')) {
            // Remove old file
            if ($guest->avatar && file_exists(public_path($guest->avatar))) {
                @unlink(public_path($guest->avatar));
            }

            $file = $request->file('avatar');
            $filename = 'avatar_' . $guest->id . '_' . time() . '_' . $file->getClientOriginalName();
            
            // Ensure directory exists
            $dirPath = public_path('uploads/avatars');
            if (!file_exists($dirPath)) {
                mkdir($dirPath, 0755, true);
            }
            
            $file->move($dirPath, $filename);
            
            $guest->avatar = 'uploads/avatars/' . $filename;
            $guest->save();
        }

        return response()->json([
            'success' => true,
            'avatar' => $guest->avatar,
            'message' => 'Tải ảnh đại diện lên thành công.',
        ]);
    }

    // =========================================
    // PRIVATE
    // =========================================

    /**
     * Tự động tạo booking_child_breakfast_details cho mỗi ngày trong giai đoạn ở.
     *
     * Logic tham số:
     *  - Booking_AutoExtraChargeBFChild = 1 → is_extra_charge = true (mặc định thu phụ phí)
     *                                         amount = hotel_settings.breakfast_child_rate (giá màn hình công ty)
     *  - Booking_AutoExtraChargeBFChild = 0 → is_extra_charge = false (không thu phụ phí mặc định)
     *                                         amount = HotelConfig['BreakfastRateChild'] (giá từ tham số hệ thống)
     *  - Em bé (baby): luôn miễn phí, is_extra_charge = false, amount = 0
     */
    private function generateBreakfastDetails(\App\Models\BookingChild $child): void
    {
        if (!$child->booking_room_id) return;

        $room = BookingRoom::find($child->booking_room_id);
        if (!$room) return;

        $isBaby  = $child->age_group === 'baby';
        $current = Carbon::parse($room->arrival_date);
        $end     = Carbon::parse($room->departure_date);

        // Đọc tham số hệ thống Booking_AutoExtraChargeBFChild từ hotel_configs
        $autoExtraChargeVal = \App\Models\HotelConfig::where('name', 'Booking_AutoExtraChargeBFChild')
            ->value('value');
        $autoExtraCharge = (int) $autoExtraChargeVal === 1;

        // Xác định amount:
        //  - autoExtraCharge = true  → giá từ hotel_settings.breakfast_child_rate (màn hình công ty)
        //  - autoExtraCharge = false → giá từ HotelConfig['BreakfastRateChild']
        $setting = \App\Models\HotelSetting::first();
        if ($autoExtraCharge) {
            $amount = (float) ($setting?->breakfast_child_rate ?? 0);
        } else {
            $bfRateChild = \App\Models\HotelConfig::where('name', 'BreakfastRateChild')->value('value');
            $amount = (float) ($bfRateChild ?? $setting?->breakfast_child_rate ?? 0);
        }

        while ($current->lt($end)) {
            $detail = \App\Models\BookingChildBreakfastDetail::firstOrCreate(
                [
                    'booking_child_id' => $child->id,
                    'service_date'     => $current->toDateString(),
                ],
                [
                    'breakfast'       => true,
                    'is_free'         => $isBaby,           // Em bé: miễn phí
                    'is_extra_charge' => !$isBaby && $autoExtraCharge,
                    'is_room'         => !$autoExtraCharge, // Nếu extra charge thì không phân bổ vào phòng
                    'amount'          => $isBaby ? 0 : $amount,
                ]
            );
            
            // Đồng bộ sang booking_room_services
            $this->syncChildBreakfastToService($detail);

            $current = $current->addDay();
        }
    }

    /**
     * Đồng bộ chi tiết ăn sáng trẻ em vào booking_room_services để hiển thị lên Folio/Checkout.
     */
    private function syncChildBreakfastToService(\App\Models\BookingChildBreakfastDetail $detail): void
    {
        $child = $detail->bookingChild;
        if (!$child || !$child->booking_room_id) return;

        // Lấy mã dịch vụ phụ thu ăn sáng trẻ em từ HotelConfig
        $serviceCode = \App\Models\HotelConfig::where('name', 'Booking_BFChildSetServiceId')->value('value') ?: 'BD';

        // Điều kiện để tạo dịch vụ: Có ăn sáng và có extra charge và không miễn phí
        $shouldHaveService = $detail->breakfast && $detail->is_extra_charge && !$detail->is_free;

        if ($shouldHaveService) {
            \App\Models\BookingRoomService::updateOrCreateForDate(
                [
                    'booking_room_id' => $child->booking_room_id,
                    'service_code'    => $serviceCode,
                    'note'            => "Phụ thu ăn sáng trẻ em: {$child->full_name}",
                ],
                $detail->service_date,
                [
                    'service_name'    => "Phụ thu ăn sáng trẻ em: {$child->full_name}",
                    'quantity'        => 1,
                    'rate'            => $detail->amount,
                    'total_amount'    => $detail->amount,
                    'department'      => 'FO',
                    'folio'           => 1,
                    'is_room'         => $detail->is_room ? 1 : 0,
                    'is_posted'       => 0,
                ]
            );
        } else {
            // Xóa dịch vụ nếu có
            \App\Models\BookingRoomService::where('booking_room_id', $child->booking_room_id)
                ->where('service_code', $serviceCode)
                ->whereDate('service_date', $detail->service_date->toDateString())
                ->where('note', "Phụ thu ăn sáng trẻ em: {$child->full_name}")
                ->delete();
        }
    }
}
