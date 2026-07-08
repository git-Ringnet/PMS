<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\BookingRoom;
use App\Models\BookingCancelLog;
use App\Models\BookingRoomService;
use App\Models\HotelConfig;
use App\Models\RoomDoNotMoveLock;
use App\Services\RoomAvailabilityService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class BookingRoomController extends Controller
{
    public function __construct(protected RoomAvailabilityService $avService) {}

    // =========================================
    // HELPER: get AllowOverRoomTypeRoomKind config
    // =========================================
    private function allowOverAV(): bool
    {
        $cfg = HotelConfig::where('name', 'AllowOverRoomTypeRoomKind')->first();
        return $cfg && $cfg->value == '1';
    }

    // =========================================
    // GET: Danh sách phòng trong một booking
    // GET /bookings/{bookingId}/rooms
    // =========================================
    public function index($bookingId)
    {
        $booking = Booking::findOrFail($bookingId);
        $rooms   = $booking->bookingRooms()
            ->with(['roomClass', 'originalRoomClass', 'room', 'specialRequests.specialRequest'])
            ->get();

        return response()->json(['success' => true, 'data' => $rooms]);
    }

    // =========================================
    // POST: Thêm phòng vào booking (Epic 1 sub-flow)
    // POST /bookings/{bookingId}/rooms
    // =========================================
    public function store(Request $request, $bookingId)
    {
        $booking = Booking::findOrFail($bookingId);

        // Không cho thêm phòng vào booking đã checkout/deleted
        if (in_array($booking->status, [Booking::STATUS_CHECKOUT, Booking::STATUS_DELETED])) {
            return response()->json(['success' => false, 'message' => 'Booking đã đóng, không thể thêm phòng.'], 422);
        }

        $validated = $request->validate([
            'room_class_id'   => 'required|exists:room_classes,id',
            'arrival_date'    => 'required|date',
            'departure_date'  => 'required|date|after:arrival_date',
            'rate'            => 'required|numeric|min:0',
            'adults'          => 'nullable|integer|min:1',
            'extra_bed_qty'   => 'nullable|integer|min:0',
            'extra_bed_rate'  => 'nullable|numeric|min:0',
            'arrival_time'    => 'nullable|date_format:H:i',
            'departure_time'  => 'nullable|date_format:H:i',
            'note'            => 'nullable|string',
            'room_number'     => 'nullable|string|exists:rooms,room_number',
        ]);

        $systemDate = $this->avService->getSystemDate();

        // Validate: arrival_date >= system_date
        if (Carbon::parse($validated['arrival_date'])->lt($systemDate)) {
            return response()->json([
                'success' => false,
                'message' => 'Ngày đến không được nhỏ hơn ngày hệ thống (' . $systemDate->toDateString() . ').',
            ], 422);
        }

        // Validate: ngày phòng nằm trong khoảng booking header
        if ($validated['arrival_date'] < $booking->arrival_date->toDateString()
            || $validated['departure_date'] > $booking->departure_date->toDateString()) {
            return response()->json([
                'success' => false,
                'message' => 'Ngày phòng phải nằm trong khoảng ngày của booking (' . $booking->arrival_date->toDateString() . ' – ' . $booking->departure_date->toDateString() . ').',
            ], 422);
        }

        // Validate AV
        $av = $this->avService->getAvailability(
            $validated['room_class_id'],
            $validated['arrival_date'],
            $validated['departure_date']
        );

        if ($av <= 0 && !$this->allowOverAV()) {
            return response()->json([
                'success' => false,
                'message' => 'Không còn phòng trống cho loại phòng này trong khoảng ngày đã chọn. (AV = ' . $av . ')',
                'av'      => $av,
            ], 422);
        }

        // Kiểm tra số phòng vật lý có bị trùng không (nếu gán ngay)
        if (!empty($validated['room_number'])) {
            $occupied = $this->avService->isRoomNumberOccupied(
                $validated['room_number'],
                $validated['arrival_date'],
                $validated['departure_date']
            );
            if ($occupied) {
                return response()->json([
                    'success' => false,
                    'message' => 'Số phòng ' . $validated['room_number'] . ' đã được gán cho booking khác trong cùng khoảng thời gian.',
                ], 422);
            }
        }

        $validated['booking_id']              = $bookingId;
        $validated['original_room_class_id']  = $validated['room_class_id']; // Lưu LP khởi tạo
        $validated['status']                  = BookingRoom::STATUS_BOOKED;
        $validated['created_by']              = Auth::user()?->username ?? 'system';

        $bookingRoom = BookingRoom::create($validated);

        // Nếu có extra bed -> auto-insert dịch vụ EB theo từng ngày
        if (!empty($validated['extra_bed_qty']) && $validated['extra_bed_qty'] > 0) {
            $this->upsertExtraBedServices($bookingRoom);
        }

        $bookingRoom->load(['roomClass', 'originalRoomClass', 'room']);

        $warning = ($av <= 0 && $this->allowOverAV())
            ? 'Cảnh báo: Số phòng trống đã âm (' . $av . '). Vẫn lưu theo cấu hình hệ thống.'
            : null;

        return response()->json([
            'success' => true,
            'data'    => $bookingRoom,
            'message' => 'Thêm phòng thành công!' . ($warning ? ' ' . $warning : ''),
            'warning' => $warning,
        ], 201);
    }

    // =========================================
    // PUT: Cập nhật một phòng trong booking (Epic 2 — single room update)
    // PUT /bookings/{bookingId}/rooms/{roomId}
    // =========================================
    public function update(Request $request, $bookingId, $roomId)
    {
        $bookingRoom = BookingRoom::where('booking_id', $bookingId)->findOrFail($roomId);

        if ($bookingRoom->status === BookingRoom::STATUS_CANCELLED) {
            return response()->json(['success' => false, 'message' => 'Phòng đã hủy, không thể sửa.'], 422);
        }
        if ($bookingRoom->status === BookingRoom::STATUS_CHECKED_OUT) {
            return response()->json(['success' => false, 'message' => 'Phòng đã checkout, không thể sửa.'], 422);
        }

        $isInhouse = $bookingRoom->status === BookingRoom::STATUS_CHECKED_IN;

        // Rule Epic 2: phòng inhouse chỉ cho sửa ngày đi, giờ đi, giá
        if ($isInhouse) {
            $validated = $request->validate([
                'departure_date'  => 'sometimes|required|date|after:arrival_date',
                'departure_time'  => 'nullable|date_format:H:i',
                'rate'            => 'nullable|numeric|min:0',
                'extra_bed_qty'   => 'nullable|integer|min:0',
                'extra_bed_rate'  => 'nullable|numeric|min:0',
                'note'            => 'nullable|string',
            ]);
        } else {
            $validated = $request->validate([
                'room_class_id'   => 'sometimes|exists:room_classes,id',
                'arrival_date'    => 'sometimes|date',
                'departure_date'  => 'sometimes|date|after:arrival_date',
                'arrival_time'    => 'nullable|date_format:H:i',
                'departure_time'  => 'nullable|date_format:H:i',
                'rate'            => 'nullable|numeric|min:0',
                'adults'          => 'nullable|integer|min:1',
                'extra_bed_qty'   => 'nullable|integer|min:0',
                'extra_bed_rate'  => 'nullable|numeric|min:0',
                'note'            => 'nullable|string',
                'room_number'     => 'nullable|string|exists:rooms,room_number',
            ]);
        }

        $systemDate   = $this->avService->getSystemDate();
        $arrivalDate  = $validated['arrival_date'] ?? $bookingRoom->arrival_date->toDateString();
        $departureDate = $validated['departure_date'] ?? $bookingRoom->departure_date->toDateString();
        $roomClassId  = $validated['room_class_id'] ?? $bookingRoom->room_class_id;

        // Validate AV nếu thay đổi ngày hoặc loại phòng
        if (isset($validated['arrival_date']) || isset($validated['departure_date']) || isset($validated['room_class_id'])) {
            $av = $this->avService->getAvailability($roomClassId, $arrivalDate, $departureDate, $roomId);
            if ($av < 0 && !$this->allowOverAV()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Không đủ phòng trống sau khi cập nhật. (AV = ' . $av . ')',
                    'av'      => $av,
                ], 422);
            }
        }

        // Số phòng vật lý trùng lịch
        if (!empty($validated['room_number']) && $validated['room_number'] !== $bookingRoom->room_number) {
            if ($this->avService->isRoomNumberOccupied($validated['room_number'], $arrivalDate, $departureDate, $roomId)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Số phòng ' . $validated['room_number'] . ' đã được gán cho booking khác trong cùng khoảng thời gian.',
                ], 422);
            }
        }

        $validated['updated_by'] = Auth::user()?->username ?? 'system';
        $bookingRoom->update($validated);

        // Sync extra bed services nếu có thay đổi EB
        if (isset($validated['extra_bed_qty']) || isset($validated['extra_bed_rate'])) {
            $this->upsertExtraBedServices($bookingRoom->fresh());
        }

        // Sync header booking nếu ngày phòng vượt ra ngoài header
        $this->syncBookingHeaderDates($bookingRoom->booking);

        $bookingRoom->load(['roomClass', 'room']);

        return response()->json([
            'success' => true,
            'data'    => $bookingRoom,
            'message' => 'Cập nhật phòng thành công!',
        ]);
    }

    // =========================================
    // POST: Bulk update nhiều phòng cùng lúc (Epic 2)
    // POST /bookings/{bookingId}/rooms/bulk-update
    // =========================================
    public function bulkUpdate(Request $request, $bookingId)
    {
        $booking = Booking::findOrFail($bookingId);
        $request->validate([
            'room_ids'       => 'required|array|min:1',
            'room_ids.*'     => 'integer',
            'arrival_date'   => 'nullable|date',
            'departure_date' => 'nullable|date',
            'rate'           => 'nullable|numeric|min:0',
            'adults'         => 'nullable|integer|min:1',
            'extra_bed_qty'  => 'nullable|integer|min:0',
            'extra_bed_rate' => 'nullable|numeric|min:0',
        ]);

        $rooms   = BookingRoom::where('booking_id', $bookingId)
            ->whereIn('id', $request->room_ids)
            ->get();

        $errors  = [];
        $updated = [];

        DB::beginTransaction();
        try {
            foreach ($rooms as $room) {
                if (in_array($room->status, [BookingRoom::STATUS_CANCELLED, BookingRoom::STATUS_CHECKED_OUT])) {
                    $errors[] = 'Phòng #' . $room->id . ' đã hủy/checkout, bỏ qua.';
                    continue;
                }

                $isInhouse    = $room->status === BookingRoom::STATUS_CHECKED_IN;
                $arrivalDate  = ($isInhouse ? $room->arrival_date->toDateString() : ($request->arrival_date ?? $room->arrival_date->toDateString()));
                $departureDate = $request->departure_date ?? $room->departure_date->toDateString();

                // AV check
                if ($request->filled('arrival_date') || $request->filled('departure_date')) {
                    $av = $this->avService->getAvailability($room->room_class_id, $arrivalDate, $departureDate, $room->id);
                    if ($av < 0 && !$this->allowOverAV()) {
                        $errors[] = 'Phòng loại ' . $room->roomClass->name . ': không đủ AV.';
                        continue;
                    }
                }

                $data = array_filter([
                    'departure_date' => $request->departure_date,
                    'rate'           => $request->rate,
                    'adults'         => !$isInhouse ? $request->adults : null,
                    'arrival_date'   => !$isInhouse ? $request->arrival_date : null,
                    'extra_bed_qty'  => $request->extra_bed_qty,
                    'extra_bed_rate' => $request->extra_bed_rate,
                    'updated_by'     => Auth::user()?->username ?? 'system',
                ], fn($v) => !is_null($v));

                $room->update($data);

                if ($request->filled('extra_bed_qty')) {
                    $this->upsertExtraBedServices($room->fresh());
                }

                $updated[] = $room->id;
            }

            $this->syncBookingHeaderDates($booking->fresh());
            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['success' => false, 'message' => 'Lỗi: ' . $e->getMessage()], 500);
        }

        return response()->json([
            'success' => true,
            'updated' => $updated,
            'errors'  => $errors,
            'message' => 'Cập nhật ' . count($updated) . ' phòng thành công.',
        ]);
    }

    // =========================================
    // PATCH: Giao phòng / Check-in (Epic 5)
    // PATCH /bookings/{bookingId}/rooms/{roomId}/check-in
    // =========================================
    public function checkIn(Request $request, $bookingId, $roomId)
    {
        $bookingRoom = BookingRoom::where('booking_id', $bookingId)->with('booking')->findOrFail($roomId);
        $booking     = $bookingRoom->booking;
        $systemDate  = $this->avService->getSystemDate();

        // Check-in chỉ từ Front Office
        // Điều kiện 1: Chưa check-in (status = 0)
        if ($bookingRoom->status !== BookingRoom::STATUS_BOOKED) {
            return response()->json(['success' => false, 'message' => 'Phòng không ở trạng thái có thể check-in.'], 422);
        }

        // Điều kiện 2: arrival_date = system_date
        if ($bookingRoom->arrival_date->toDateString() !== $systemDate->toDateString()) {
            return response()->json([
                'success' => false,
                'message' => 'Ngày đến của phòng (' . $bookingRoom->arrival_date->toDateString() . ') phải bằng ngày hệ thống (' . $systemDate->toDateString() . ').',
            ], 422);
        }

        // Điều kiện 3: Trạng thái vật lý phòng phải = 'Phòng sẵn sàng' (status = 1)
        // Sử dụng room_number và kiểm tra room.status
        if (!empty($bookingRoom->room_number)) {
            $physicalRoom = \App\Models\Room::where('room_number', $bookingRoom->room_number)->first();
            if ($physicalRoom && $physicalRoom->status != 1) {
                $statusLabels = [
                    1 => 'Phòng sẵn sàng',
                    2 => 'Phòng chưa dọn',
                    3 => 'Phòng sạch',
                    4 => 'Phòng sửa chữa (OOO)',
                    5 => 'Phòng dịch vụ',
                    6 => 'Turndown',
                ];
                $currentStatusLabel = $statusLabels[$physicalRoom->status] ?? 'Trạng thái ' . $physicalRoom->status;
                return response()->json([
                    'success' => false,
                    'message' => 'Phòng ' . $bookingRoom->room_number . ' hiện đang ở trạng thái "' . $currentStatusLabel . '". Yêu cầu phòng phải ở trạng thái "Phòng sẵn sàng" trước khi check-in.',
                    'room_status' => $physicalRoom->status,
                ], 422);
            }
        }

        // Điều kiện 4: Check IsCheckBookingStatusWhenCheckin
        $checkStatusCfg = HotelConfig::where('name', 'IsCheckBookingStatusWhenCheckin')->first();
        if ($checkStatusCfg && $checkStatusCfg->value == '1') {
            $allowedStatusValues = ['1', '27']; // Guaranteed statuses
            $regStatus = $booking->registrationStatus;
            if (!$regStatus || !in_array($regStatus->status_value, $allowedStatusValues)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Trạng thái booking không hợp lệ để check-in. Yêu cầu trạng thái Guaranteed hoặc tương đương.',
                ], 422);
            }
        }

        DB::beginTransaction();
        try {
            $bookingRoom->update([
                'status'     => BookingRoom::STATUS_CHECKED_IN,
                'updated_by' => Auth::user()?->username ?? 'system',
            ]);

            // Nếu tất cả phòng trong booking đều đã check-in → cập nhật booking header
            $allCheckedIn = $booking->bookingRooms()
                ->whereIn('status', [BookingRoom::STATUS_BOOKED])
                ->doesntExist();

            if ($allCheckedIn) {
                $booking->update(['status' => Booking::STATUS_CHECKIN]);
            }

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['success' => false, 'message' => 'Lỗi: ' . $e->getMessage()], 500);
        }

        return response()->json([
            'success' => true,
            'data'    => $bookingRoom->fresh()->load(['roomClass', 'room']),
            'message' => 'Giao phòng thành công!',
        ]);
    }

    // =========================================
    // PATCH: Gỡ số phòng (Epic 8)
    // PATCH /bookings/{bookingId}/rooms/{roomId}/unassign
    // =========================================
    public function unassign($bookingId, $roomId)
    {
        $bookingRoom = BookingRoom::where('booking_id', $bookingId)->findOrFail($roomId);

        if ($bookingRoom->status === BookingRoom::STATUS_CHECKED_IN) {
            return response()->json(['success' => false, 'message' => 'Không thể gỡ số phòng khi đang check-in.'], 422);
        }
        if ($bookingRoom->status === BookingRoom::STATUS_CANCELLED) {
            return response()->json(['success' => false, 'message' => 'Phòng đã hủy.'], 422);
        }

        $bookingRoom->update([
            'room_number' => null,
            'updated_by'  => Auth::user()?->username ?? 'system',
        ]);

        return response()->json([
            'success' => true,
            'data'    => $bookingRoom->fresh(),
            'message' => 'Đã gỡ số phòng thành công.',
        ]);
    }

    // =========================================
    // DELETE: Hủy phòng (Epic 9) — cascade + log
    // DELETE /bookings/{bookingId}/rooms/{roomId}/cancel
    // =========================================
    public function cancel(Request $request, $bookingId, $roomId)
    {
        $bookingRoom = BookingRoom::where('booking_id', $bookingId)
            ->with(['guests', 'children'])
            ->findOrFail($roomId);

        if (in_array($bookingRoom->status, [BookingRoom::STATUS_CANCELLED, BookingRoom::STATUS_CHECKED_OUT])) {
            return response()->json(['success' => false, 'message' => 'Phòng đã hủy hoặc đã checkout.'], 422);
        }
        if ($bookingRoom->status === BookingRoom::STATUS_CHECKED_IN) {
            return response()->json(['success' => false, 'message' => 'Không thể hủy phòng đang check-in. Vui lòng checkout trước.'], 422);
        }

        $request->validate([
            'cancel_reason_id' => 'nullable|exists:cancel_reasons,id',
            'note'             => 'nullable|string',
        ]);

        DB::beginTransaction();
        try {
            // Cascade: hủy guests gắn với phòng này
            $bookingRoom->guests()->update(['status' => 3]);
            // Cascade: hủy children gắn với phòng này
            $bookingRoom->children()->update(['child_status' => 3]);
            // Hủy phòng
            $bookingRoom->update([
                'status'     => BookingRoom::STATUS_CANCELLED,
                'updated_by' => Auth::user()?->username ?? 'system',
            ]);

            // Ghi log hủy
            BookingCancelLog::create([
                'cancel_type'            => 'room',
                'booking_id'             => $bookingId,
                'booking_room_id'        => $roomId,
                'cancel_reason_id'       => $request->cancel_reason_id,
                'note'                   => $request->note,
                'cancelled_by_user_id'   => Auth::id() ?? 0,
                'cancelled_by_username'  => Auth::user()?->username ?? 'system',
                'cancelled_at'           => now(),
            ]);

            // Nếu toàn bộ phòng của booking đều đã hủy → hủy cả booking header
            $booking = Booking::findOrFail($bookingId);
            $anyActive = $booking->bookingRooms()
                ->whereIn('status', [BookingRoom::STATUS_BOOKED, BookingRoom::STATUS_CHECKED_IN])
                ->exists();

            if (!$anyActive) {
                // Tự chuyển booking_status về bk_definite = 4
                $cancelledRegStatus = \App\Models\RegistrationStatus::where('bk_definite', 4)->first();

                $booking->update([
                    'status'                 => Booking::STATUS_DELETED,
                    'registration_status_id' => $cancelledRegStatus?->id ?? $booking->registration_status_id,
                ]);
                BookingCancelLog::create([
                    'cancel_type'           => 'booking',
                    'booking_id'            => $bookingId,
                    'cancel_reason_id'      => $request->cancel_reason_id,
                    'note'                  => 'Tự động hủy booking khi tất cả phòng đã bị hủy.',
                    'cancelled_by_user_id'  => Auth::id() ?? 0,
                    'cancelled_by_username' => Auth::user()?->username ?? 'system',
                    'cancelled_at'          => now(),
                ]);
            }

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['success' => false, 'message' => 'Lỗi: ' . $e->getMessage()], 500);
        }

        return response()->json([
            'success' => true,
            'message' => 'Đã hủy phòng thành công.',
        ]);
    }

    // =========================================
    // PATCH: Nâng/hạ hạng phòng (Epic 6)
    // PATCH /bookings/{bookingId}/rooms/{roomId}/upgrade
    // =========================================
    public function upgrade(Request $request, $bookingId, $roomId)
    {
        $bookingRoom = BookingRoom::where('booking_id', $bookingId)->findOrFail($roomId);

        if ($bookingRoom->status !== BookingRoom::STATUS_BOOKED) {
            return response()->json(['success' => false, 'message' => 'Chỉ nâng hạng phòng ở trạng thái đăng ký.'], 422);
        }

        $request->validate([
            'room_class_id' => 'required|exists:room_classes,id',
            'rate'          => 'nullable|numeric|min:0',
        ]);

        $newClassId = $request->room_class_id;
        $av = $this->avService->getAvailability(
            $newClassId,
            $bookingRoom->arrival_date->toDateString(),
            $bookingRoom->departure_date->toDateString(),
            $roomId
        );

        if ($av < 0 && !$this->allowOverAV()) {
            return response()->json([
                'success' => false,
                'message' => 'Không đủ phòng trống cho loại phòng mới. (AV = ' . $av . ')',
                'av'      => $av,
            ], 422);
        }

        $data = [
            'room_class_id' => $newClassId,
            'room_number'   => null, // Reset số phòng vật lý khi đổi loại phòng
            'updated_by'    => Auth::user()?->username ?? 'system',
        ];
        if ($request->filled('rate')) {
            $data['rate'] = $request->rate;
        }

        // original_room_class_id giữ nguyên — không bao giờ ghi đè
        $bookingRoom->update($data);

        $warning = ($av <= 0 && $this->allowOverAV())
            ? 'Cảnh báo: AV âm (' . $av . '). Vẫn lưu theo cấu hình hệ thống.'
            : null;

        return response()->json([
            'success' => true,
            'data'    => $bookingRoom->fresh()->load(['roomClass', 'originalRoomClass']),
            'message' => 'Nâng/hạ hạng phòng thành công.' . ($warning ? ' ' . $warning : ''),
            'warning' => $warning,
        ]);
    }

    // =========================================
    // POST: Khóa Do Not Move (Epic 11)
    // POST /bookings/{bookingId}/rooms/{roomId}/lock-move
    // =========================================
    public function lockMove(Request $request, $bookingId, $roomId)
    {
        $bookingRoom = BookingRoom::where('booking_id', $bookingId)->findOrFail($roomId);

        if ($bookingRoom->is_do_not_move) {
            return response()->json(['success' => false, 'message' => 'Phòng đã đang khóa chuyển phòng.'], 422);
        }

        $request->validate(['note' => 'nullable|string']);

        DB::beginTransaction();
        try {
            $bookingRoom->update(['is_do_not_move' => 1]);

            RoomDoNotMoveLock::create([
                'booking_room_id'    => $roomId,
                'locked_by_user_id'  => Auth::id() ?? 0,
                'locked_by_username' => Auth::user()?->username ?? 'system',
                'locked_at'          => now(),
                'note'               => $request->note,
            ]);
            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['success' => false, 'message' => 'Lỗi: ' . $e->getMessage()], 500);
        }

        return response()->json(['success' => true, 'message' => 'Đã khóa chuyển phòng (Do Not Move).']);
    }

    // =========================================
    // DELETE: Mở khóa Do Not Move (Epic 11)
    // DELETE /bookings/{bookingId}/rooms/{roomId}/lock-move
    // =========================================
    public function unlockMove($bookingId, $roomId)
    {
        $bookingRoom = BookingRoom::where('booking_id', $bookingId)->findOrFail($roomId);
        $activeLock  = $bookingRoom->activeDoNotMoveLock;

        if (!$bookingRoom->is_do_not_move || !$activeLock) {
            return response()->json(['success' => false, 'message' => 'Phòng không đang bị khóa.'], 422);
        }

        $currentUserId = Auth::id();
        $currentUsername = Auth::user()?->username;

        // Rule: chỉ người đã khóa mới unlock, trừ khi có quyền đặc biệt
        if ($activeLock->locked_by_user_id !== $currentUserId) {
            $allowAllUnlock = HotelConfig::where('name', 'Booking_RuleUserUnLockDoNotMove')
                ->where('value', $currentUsername)
                ->orWhere(fn($q) => $q->where('name', 'Booking_RuleUserUnLockDoNotMove')
                    ->whereRaw("FIND_IN_SET(?, value)", [$currentUsername]))
                ->exists();

            if (!$allowAllUnlock) {
                return response()->json([
                    'success' => false,
                    'message' => 'Chỉ người đã khóa (' . $activeLock->locked_by_username . ') mới có thể mở khóa.',
                ], 403);
            }
        }

        DB::beginTransaction();
        try {
            $activeLock->update([
                'unlocked_by_user_id'  => $currentUserId,
                'unlocked_by_username' => $currentUsername,
                'unlocked_at'          => now(),
            ]);
            $bookingRoom->update(['is_do_not_move' => 0]);
            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['success' => false, 'message' => 'Lỗi: ' . $e->getMessage()], 500);
        }

        return response()->json(['success' => true, 'message' => 'Đã mở khóa chuyển phòng.']);
    }

    // =========================================
    // Auto Room Assignment (Epic 3)
    // POST /bookings/{bookingId}/rooms/{roomId}/auto-assign
    // =========================================
    public function autoAssign($bookingId, $roomId)
    {
        $bookingRoom = BookingRoom::where('booking_id', $bookingId)->findOrFail($roomId);

        if ($bookingRoom->status !== BookingRoom::STATUS_BOOKED) {
            return response()->json(['success' => false, 'message' => 'Chỉ tự động gán phòng cho phòng ở trạng thái đăng ký.'], 422);
        }

        $arrivalDate   = $bookingRoom->arrival_date->toDateString();
        $departureDate = $bookingRoom->departure_date->toDateString();

        // Lấy danh sách phòng vật lý cùng loại, sắp xếp theo tầng thấp→cao
        $candidates = \App\Models\Room::where('room_class_id', $bookingRoom->room_class_id)
            ->orderBy('floor', 'asc')
            ->orderBy('room_number', 'asc')
            ->get();

        $assignedRoom = null;
        foreach ($candidates as $room) {
            // Kiểm tra không bị OOO/OOS
            $isLocked = \App\Models\RoomLock::where('room_number', $room->room_number)
                ->where('is_active', 1)
                ->where('start_date', '<', $departureDate)
                ->where('end_date', '>', $arrivalDate)
                ->exists();

            if ($isLocked) continue;

            // Kiểm tra không bị trùng booking khác
            $isOccupied = $this->avService->isRoomNumberOccupied(
                $room->room_number, $arrivalDate, $departureDate, $roomId
            );

            if (!$isOccupied) {
                $assignedRoom = $room;
                break;
            }
        }

        if (!$assignedRoom) {
            return response()->json([
                'success' => false,
                'message' => 'Không tìm thấy phòng vật lý trống phù hợp trong khoảng thời gian này.',
            ], 422);
        }

        $bookingRoom->update([
            'room_number' => $assignedRoom->room_number,
            'updated_by'  => Auth::user()?->username ?? 'system',
        ]);

        return response()->json([
            'success'      => true,
            'room_number'  => $assignedRoom->room_number,
            'data'         => $bookingRoom->fresh()->load('room'),
            'message'      => 'Tự động gán phòng ' . $assignedRoom->room_number . ' thành công.',
        ]);
    }

    // =========================================
    // PRIVATE HELPERS
    // =========================================

    /**
     * Upsert dịch vụ Extra Bed (EB) theo từng ngày trong giai đoạn ở.
     * Xóa ngày quá khứ có EB, thêm/cập nhật cho ngày hiện tại và tương lai.
     */
    private function upsertExtraBedServices(BookingRoom $room): void
    {
        $systemDate    = $this->avService->getSystemDate()->toDateString();
        $arrivalDate   = $room->arrival_date->toDateString();
        $departureDate = $room->departure_date->toDateString();

        if ($room->extra_bed_qty <= 0) {
            // Reset: xóa tất cả EB từ ngày system_date trở đi
            $room->services()
                ->where('service_code', BookingRoomService::CODE_EXTRA_BED)
                ->where('service_date', '>=', $systemDate)
                ->forceDelete();
            return;
        }

        $current = Carbon::parse(max($arrivalDate, $systemDate));
        $end     = Carbon::parse($departureDate);

        while ($current->lt($end)) {
            $dateStr = $current->toDateString();
            BookingRoomService::withTrashed()->updateOrCreate(
                [
                    'booking_room_id' => $room->id,
                    'service_code'    => BookingRoomService::CODE_EXTRA_BED,
                    'service_date'    => $dateStr,
                ],
                [
                    'service_name' => 'Extra Bed',
                    'quantity'     => $room->extra_bed_qty,
                    'rate'         => $room->extra_bed_rate,
                    'is_room'      => 1,
                    'is_posted'    => 0,
                    'deleted_at'   => null,
                    'created_by'   => Auth::user()?->username ?? 'system',
                ]
            );
            $current->addDay();
        }
    }

    /**
     * Đồng bộ ngày header booking nếu ngày phòng vượt ra ngoài.
     */
    private function syncBookingHeaderDates(Booking $booking): void
    {
        $minArrival  = $booking->bookingRooms()->active()->min('arrival_date');
        $maxDepart   = $booking->bookingRooms()->active()->max('departure_date');

        if (!$minArrival || !$maxDepart) return;

        $data = [];
        if ($minArrival < $booking->arrival_date->toDateString()) {
            $data['arrival_date'] = $minArrival;
        }
        if ($maxDepart > $booking->departure_date->toDateString()) {
            $data['departure_date'] = $maxDepart;
        }

        if (!empty($data)) {
            $booking->update($data);
        }
    }
}
