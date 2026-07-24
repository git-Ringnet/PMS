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
            'departure_date'  => 'required|date|after_or_equal:arrival_date',
            'rate'            => 'required|numeric|min:0',
            'adults'          => 'nullable|integer|min:1',
            'children'        => 'nullable|integer|min:0',
            'babies'          => 'nullable|integer|min:0',
            'guest_name'      => 'nullable|string|max:100',
            'extra_bed_qty'   => 'nullable|integer|min:0',
            'extra_bed_rate'  => 'nullable|numeric|min:0',
            'arrival_time'    => 'nullable|date_format:H:i',
            'departure_time'  => 'nullable|date_format:H:i',
            'note'            => 'nullable|string',
            'room_number'     => 'nullable|string|exists:rooms,room_number',
            'breakfast'       => 'nullable|boolean',
            'is_day_use'      => 'nullable|boolean',
        ]);

        $isDayUse = filter_var($request->input('is_day_use'), FILTER_VALIDATE_BOOLEAN);
        if (!$isDayUse && $validated['departure_date'] === $validated['arrival_date']) {
            return response()->json([
                'success' => false,
                'message' => 'Ngày đi phải sau ngày đến đối với phòng ở qua đêm thông thường.',
            ], 422);
        }

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

        // Thêm khách chính (guest_name)
        $roomGuestName = trim($validated['guest_name'] ?? '');
        if (empty($roomGuestName)) {
            $roomGuestName = 'Guest 1';
        }
        $guest = \App\Models\Guest::create([
            'full_name' => $roomGuestName,
            'title' => 'Mr.',
            'nationality_code' => 'VN',
            'guest_status' => \App\Models\Guest::STATUS_ACTIVE,
        ]);
        \App\Models\BookingRoomGuest::create([
            'booking_room_id' => $bookingRoom->id,
            'guest_id' => $guest->id,
            'is_primary' => 1,
        ]);

        // Thêm trẻ em / em bé
        $numChildren = (int)($validated['children'] ?? 0);
        for ($c = 0; $c < $numChildren; $c++) {
            $child = \App\Models\BookingChild::create([
                'booking_id' => $booking->id,
                'booking_room_id' => $bookingRoom->id,
                'full_name' => 'Child ' . ($c + 1),
                'age_group' => 'child',
            ]);
            $this->createChildBreakfastDetails($child, $bookingRoom);
        }

        $numBabies = (int)($validated['babies'] ?? 0);
        for ($b = 0; $b < $numBabies; $b++) {
            $baby = \App\Models\BookingChild::create([
                'booking_id' => $booking->id,
                'booking_room_id' => $bookingRoom->id,
                'full_name' => 'Baby ' . ($b + 1),
                'age_group' => 'baby',
            ]);
            $this->createChildBreakfastDetails($baby, $bookingRoom);
        }

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

        // Khóa chuyển phòng Do Not Move
        if ($bookingRoom->is_do_not_move) {
            if (
                ($request->has('room_number') && $request->room_number !== $bookingRoom->room_number) ||
                ($request->has('room_class_id') && (int)$request->room_class_id !== (int)$bookingRoom->room_class_id) ||
                ($request->has('arrival_date') && $request->arrival_date !== $bookingRoom->arrival_date->toDateString()) ||
                ($request->has('departure_date') && $request->departure_date !== $bookingRoom->departure_date->toDateString())
            ) {
                return response()->json([
                    'success' => false,
                    'message' => 'Phòng này đang bị khóa chuyển phòng (Do Not Move). Vui lòng mở khóa trước.'
                ], 422);
            }
        }

        $isInhouse = $bookingRoom->status === BookingRoom::STATUS_CHECKED_IN;

        // Rule Epic 2: phòng inhouse chỉ cho sửa ngày đi, giờ đi, giá và số phòng (chuyển phòng)
        if ($isInhouse) {
            $validated = $request->validate([
                'arrival_date'    => 'nullable|date',
                'departure_date'  => 'nullable|date',
                'departure_time'  => 'nullable|date_format:H:i',
                'rate'            => 'nullable|numeric|min:0',
                'extra_bed_qty'   => 'nullable|integer|min:0',
                'extra_bed_rate'  => 'nullable|numeric|min:0',
                'note'            => 'nullable|string',
                'guest_name'      => 'nullable|string|max:100',
                'room_number'     => 'nullable|string',
                'breakfast'       => 'nullable|boolean',
                'is_day_use'      => 'nullable|boolean',
            ]);
        } else {
            $validated = $request->validate([
                'room_class_id'   => 'sometimes|exists:room_classes,id',
                'arrival_date'    => 'sometimes|date',
                'departure_date'  => 'sometimes|date',
                'arrival_time'    => 'nullable|date_format:H:i',
                'departure_time'  => 'nullable|date_format:H:i',
                'rate'            => 'nullable|numeric|min:0',
                'adults'          => 'nullable|integer|min:1',
                'children'        => 'nullable|integer|min:0',
                'babies'          => 'nullable|integer|min:0',
                'guest_name'      => 'nullable|string|max:100',
                'extra_bed_qty'   => 'nullable|integer|min:0',
                'extra_bed_rate'  => 'nullable|numeric|min:0',
                'note'            => 'nullable|string',
                'room_number'     => 'nullable|string',
                'breakfast'       => 'nullable|boolean',
                'is_day_use'      => 'nullable|boolean',
            ]);
        }

        $systemDate   = $this->avService->getSystemDate();
        $arrivalDate  = $validated['arrival_date'] ?? $bookingRoom->arrival_date->toDateString();
        $departureDate = $validated['departure_date'] ?? $bookingRoom->departure_date->toDateString();
        $roomClassId  = $validated['room_class_id'] ?? $bookingRoom->room_class_id;

        $isDayUse = filter_var($request->has('is_day_use') ? $request->input('is_day_use') : $bookingRoom->is_day_use, FILTER_VALIDATE_BOOLEAN);
        if (!$isDayUse && $departureDate === $arrivalDate) {
            return response()->json([
                'success' => false,
                'message' => 'Ngày đi phải sau ngày đến đối với phòng ở qua đêm thông thường.',
            ], 422);
        }

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
        $targetRoomNumber = $validated['room_number'] ?? $bookingRoom->room_number;
        if (!empty($targetRoomNumber)) {
            $datesChanged = (isset($validated['arrival_date']) && $validated['arrival_date'] !== $bookingRoom->arrival_date->toDateString())
                || (isset($validated['departure_date']) && $validated['departure_date'] !== $bookingRoom->departure_date->toDateString());
            $roomNumberChanged = (isset($validated['room_number']) && $validated['room_number'] !== $bookingRoom->room_number);

            if ($datesChanged || $roomNumberChanged) {
                if ($this->avService->isRoomNumberOccupied($targetRoomNumber, $arrivalDate, $departureDate, $roomId, $bookingId)) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Số phòng ' . $targetRoomNumber . ' đã được gán cho booking khác trong cùng khoảng thời gian.',
                    ], 422);
                }
            }
        }

        $currentUser = Auth::user()?->username ?? 'system';
        $validated['updated_by'] = $currentUser;

        if ($isInhouse && isset($validated['room_number']) && $validated['room_number'] !== $bookingRoom->room_number) {
            $newRoomNumber = $validated['room_number'];
            unset($validated['room_number']);
            
            DB::beginTransaction();
            try {
                $newRoom = $bookingRoom->moveToRoom($newRoomNumber, $systemDate->toDateString(), $currentUser);
                $newRoom->update($validated);
                DB::commit();
                
                $bookingRoom = $newRoom;
            } catch (\Exception $e) {
                DB::rollBack();
                return response()->json(['success' => false, 'message' => 'Lỗi khi chuyển phòng: ' . $e->getMessage()], 500);
            }
        } else {
            $bookingRoom->update($validated);
        }

        // Cập nhật khách chính (guest_name)
        if ($request->has('guest_name')) {
            $roomGuestName = trim($request->guest_name);
            if (!empty($roomGuestName)) {
                $primaryGuestRelation = $bookingRoom->guests()->where('is_primary', 1)->first();
                if ($primaryGuestRelation && $primaryGuestRelation->guest) {
                    $primaryGuestRelation->guest->update(['full_name' => $roomGuestName]);
                } else {
                    $guest = \App\Models\Guest::create([
                        'full_name' => $roomGuestName,
                        'guest_status' => \App\Models\Guest::STATUS_ACTIVE,
                    ]);
                    \App\Models\BookingRoomGuest::create([
                        'booking_room_id' => $bookingRoom->id,
                        'guest_id' => $guest->id,
                        'is_primary' => 1,
                    ]);
                }
            }
        }

        // Cập nhật số lượng children
        if ($request->has('children')) {
            $bookingRoom->children()->where('age_group', 'child')->delete();
            $numChildren = (int)$request->children;
            for ($c = 0; $c < $numChildren; $c++) {
                $child = \App\Models\BookingChild::create([
                    'booking_id' => $bookingRoom->booking_id,
                    'booking_room_id' => $bookingRoom->id,
                    'full_name' => 'Child ' . ($c + 1),
                    'age_group' => 'child',
                ]);
                $this->createChildBreakfastDetails($child, $bookingRoom);
            }
        }

        // Cập nhật số lượng babies
        if ($request->has('babies')) {
            $bookingRoom->children()->where('age_group', 'baby')->delete();
            $numBabies = (int)$request->babies;
            for ($b = 0; $b < $numBabies; $b++) {
                $baby = \App\Models\BookingChild::create([
                    'booking_id' => $bookingRoom->booking_id,
                    'booking_room_id' => $bookingRoom->id,
                    'full_name' => 'Baby ' . ($b + 1),
                    'age_group' => 'baby',
                ]);
                $this->createChildBreakfastDetails($baby, $bookingRoom);
            }
        }

        // Sync extra bed services nếu có thay đổi EB
        if (isset($validated['extra_bed_qty']) || isset($validated['extra_bed_rate'])) {
            $this->upsertExtraBedServices($bookingRoom->fresh());
        }

        // Sync header booking nếu ngày phòng vượt ra ngoài header
        $this->syncBookingHeaderDates($bookingRoom->booking);

        $bookingRoom->load(['roomClass', 'room']);

        $warning = (isset($av) && $av < 0 && $this->allowOverAV())
            ? 'Cảnh báo: Số phòng trống của loại phòng đã bị âm (AV = ' . $av . '). Vẫn ghi nhận lưu theo cấu hình hệ thống.'
            : null;

        return response()->json([
            'success' => true,
            'data'    => $bookingRoom,
            'message' => 'Cập nhật phòng thành công!' . ($warning ? ' ' . $warning : ''),
            'warning' => $warning,
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
            'breakfast'      => 'nullable|boolean',
            'is_day_use'     => 'nullable|boolean',
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
                    'breakfast'      => $request->has('breakfast') ? $request->breakfast : null,
                    'is_day_use'     => $request->has('is_day_use') ? $request->is_day_use : null,
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

        // Điều kiện 1.5: Phải được gán số phòng vật lý trước khi check-in
        if (empty($bookingRoom->room_number)) {
            return response()->json(['success' => false, 'message' => 'Phòng chưa được gán số phòng. Vui lòng gán số phòng trước khi giao phòng.'], 422);
        }

        // Điều kiện 2: arrival_date = system_date
        if ($bookingRoom->arrival_date->toDateString() !== $systemDate->toDateString()) {
            return response()->json([
                'success' => false,
                'message' => 'Ngày đến của phòng (' . $bookingRoom->arrival_date->toDateString() . ') phải bằng ngày hệ thống (' . $systemDate->toDateString() . ').',
            ], 422);
        }

        // Điều kiện 3: Trạng thái vật lý phòng phải = 'Phòng sẵn sàng' (status = 'available')
        // Nếu AllowCheckinVacantClean = 1 → cho phép check-in cả khi phòng dirty (chờ kiểm tra)
        $allowVacantClean = HotelConfig::where('name', 'AllowCheckinVacantClean')->value('value');
        if (!empty($bookingRoom->room_number)) {
            $physicalRoom = \App\Models\Room::where('room_number', $bookingRoom->room_number)->first();
            if ($physicalRoom && $physicalRoom->status !== 'available') {
                $allowedWhenVacantClean = ['dirty', 'checkout'];
                if ($allowVacantClean == '1' && in_array($physicalRoom->status, $allowedWhenVacantClean)) {
                    // Cho phép check-in khi AllowCheckinVacantClean = 1 và phòng đang dirty/checkout
                } else {
                    $statusLabels = [
                        'available' => 'Phòng sẵn sàng',
                        'dirty'     => 'Phòng chưa dọn (dirty)',
                        'occupied'  => 'Phòng đang có khách (occupied)',
                        'maintenance' => 'Phòng sửa chữa (OOO)',
                        'reserved'  => 'Phòng đã đặt trước',
                        'checkout'  => 'Phòng chờ dọn (checkout)',
                    ];
                    $currentStatusLabel = $statusLabels[$physicalRoom->status] ?? $physicalRoom->status;
                    return response()->json([
                        'success'     => false,
                        'message'     => 'Phòng ' . $bookingRoom->room_number . ' hiện đang ở trạng thái "' . $currentStatusLabel . '". Vui lòng kiểm tra lại thông tin.',
                        'room_status' => $physicalRoom->status,
                    ], 422);
                }
            }
        }

        // Điều kiện 4: Check IsCheckBookingStatusWhenCheckin
        // 0 = không kiểm tra, 1 = chỉ cho check-in khi booking_status_id = 1 (Guaranteed) hoặc 27 (Allotment)
        $checkStatusCfg = HotelConfig::where('name', 'IsCheckBookingStatusWhenCheckin')->first();
        if ($checkStatusCfg && $checkStatusCfg->value == '1') {
            $allowedStatusIds = [1, 27]; // booking_status_id: 1 = Guaranteed, 27 = Allotment
            $regStatus = $booking->registrationStatus;
            if (!$regStatus || !in_array((int)$regStatus->booking_status_id, $allowedStatusIds)) {
                $statusName = $regStatus ? ($regStatus->name ?? 'Không xác định') : 'Không có';
                return response()->json([
                    'success' => false,
                    'message' => 'Trạng thái booking "' . $statusName . '" không hợp lệ để check-in. Yêu cầu trạng thái Guaranteed hoặc Allotment.',
                ], 422);
            }
        }

        DB::beginTransaction();
        try {
            $bookingRoom->update([
                'status'              => BookingRoom::STATUS_CHECKED_IN,
                'check_in_user'       => Auth::user()?->username ?? 'system',
                'actual_arrival_date' => $bookingRoom->actual_arrival_date ?? $bookingRoom->arrival_date,
                'updated_by'          => Auth::user()?->username ?? 'system',
            ]);

            if ($bookingRoom->room_number) {
                \App\Models\Room::where('room_number', $bookingRoom->room_number)->update([
                    'status' => 'dirty'
                ]);
            }

            // Đồng bộ status cho khách sang CHECKED_IN
            $bookingRoom->guests()->update([
                'status' => BookingRoom::STATUS_CHECKED_IN
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
    // POST: Hủy nhận phòng / Undo check-in
    // POST /bookings/{bookingId}/rooms/{roomId}/undo-checkin
    // =========================================
    public function undoCheckIn(Request $request, $bookingId, $roomId)
    {
        $bookingRoom = BookingRoom::where('booking_id', $bookingId)->with('booking')->findOrFail($roomId);
        $booking     = $bookingRoom->booking;

        // Chỉ cho phép hủy check-in nếu phòng đang Checked In (status = 1)
        if ($bookingRoom->status !== BookingRoom::STATUS_CHECKED_IN) {
            return response()->json(['success' => false, 'message' => 'Phòng không ở trạng thái đã check-in.'], 422);
        }

        DB::beginTransaction();
        try {
            $bookingRoom->update([
                'status'     => BookingRoom::STATUS_BOOKED,
                'updated_by' => Auth::user()?->username ?? 'system',
            ]);

            if ($bookingRoom->room_number) {
                \App\Models\Room::where('room_number', $bookingRoom->room_number)->update([
                    'room_status_code' => 'vacant_ready'
                ]);
            }

            // Đồng bộ status cho khách về BOOKED
            $bookingRoom->guests()->update([
                'status' => BookingRoom::STATUS_BOOKED
            ]);

            // Cập nhật booking status về STATUS_RESERVATION (0) nếu trước đó là STATUS_CHECKIN (1)
            if ($booking->status === Booking::STATUS_CHECKIN) {
                $booking->update(['status' => Booking::STATUS_RESERVATION]);
            }

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['success' => false, 'message' => 'Lỗi: ' . $e->getMessage()], 500);
        }

        return response()->json([
            'success' => true,
            'data'    => $bookingRoom->fresh()->load(['roomClass', 'room']),
            'message' => 'Hủy nhận phòng thành công!',
        ]);
    }

    // =========================================
    // PATCH: Gỡ số phòng (Epic 8)
    // PATCH /bookings/{bookingId}/rooms/{roomId}/unassign
    // =========================================
    public function unassign($bookingId, $roomId)
    {
        $bookingRoom = BookingRoom::where('booking_id', $bookingId)->findOrFail($roomId);

        if ($bookingRoom->is_do_not_move) {
            return response()->json(['success' => false, 'message' => 'Phòng này đang bị khóa chuyển phòng (Do Not Move). Vui lòng mở khóa trước.'], 422);
        }

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

        // Kiểm tra cấu hình CheckModuleBeforeDelete
        $booking = Booking::find($bookingId);
        if ($booking) {
            $checkModuleConfig = \Illuminate\Support\Facades\DB::table('hotel_configs')
                ->where('name', 'CheckModuleBeforeDelete')
                ->value('value');

            if ($checkModuleConfig === '1' || $checkModuleConfig === 1) {
                $currentModule = strtolower($request->input('current_module', 'reservation'));
                $bookingModule = strtolower($booking->module ?? 'reservation');

                if ($bookingModule === 'reservation' && $currentModule === 'reception') {
                    return response()->json([
                        'success' => false,
                        'message' => 'Đăng ký được tạo bởi bộ phận đặt phòng. Bạn không có quyền được hủy.'
                    ], 403);
                }
                if ($bookingModule === 'reception' && $currentModule === 'reservation') {
                    return response()->json([
                        'success' => false,
                        'message' => 'Đăng ký được tạo bởi bộ phận lễ tân. Bạn không có quyền được hủy.'
                    ], 403);
                }
            }
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
            $reasonText = $request->note;
            if ($request->cancel_reason_id && empty($reasonText)) {
                $cReason = \App\Models\CancelReason::find($request->cancel_reason_id);
                $reasonText = $cReason?->name;
            }

            // Hủy phòng
            $bookingRoom->update([
                'status'     => BookingRoom::STATUS_CANCELLED,
                'reason'     => $reasonText,
                'updated_by' => Auth::user()?->username ?? 'system',
            ]);

            // Ghi log hủy phòng (SP8052)
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

        if ($bookingRoom->is_do_not_move) {
            return response()->json(['success' => false, 'message' => 'Phòng này đang bị khóa chuyển phòng (Do Not Move). Vui lòng mở khóa trước.'], 422);
        }

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

        if (empty($bookingRoom->room_number)) {
            return response()->json(['success' => false, 'message' => 'Phòng chưa được gán số phòng. Vui lòng gán số phòng trước khi khóa chuyển phòng.'], 422);
        }

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

        $arrivalDate   = request('arrival_date') ?? $bookingRoom->arrival_date->toDateString();
        $departureDate = request('departure_date') ?? $bookingRoom->departure_date->toDateString();

        // Lấy danh sách phòng vật lý cùng loại (không lấy phòng ảo: is_internal = 0), sắp xếp theo tầng thấp→cao
        $candidates = \App\Models\Room::where('room_class_id', $bookingRoom->room_class_id)
            ->where('is_internal', false)
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
        $arrivalDate   = $room->arrival_date->toDateString();
        $departureDate = $room->departure_date->toDateString();

        if ($room->extra_bed_qty <= 0) {
            // Chỉ xóa các dịch vụ EB chưa được post
            $room->services()
                ->where('service_code', BookingRoomService::CODE_EXTRA_BED)
                ->where('is_posted', 0)
                ->forceDelete();
            return;
        }

        // 1. Lấy danh sách dịch vụ EB hiện tại
        $existingServices = $room->services()
            ->where('service_code', BookingRoomService::CODE_EXTRA_BED)
            ->get()
            ->keyBy(function ($item) {
                return $item->service_date->toDateString();
            });

        // 2. Xóa các ngày nằm ngoài giai đoạn ở mới (nếu thu hẹp ngày ở) mà chưa post
        $current = Carbon::parse($arrivalDate);
        $end     = Carbon::parse($departureDate);
        $stayDates = [];
        while ($current->lt($end)) {
            $stayDates[] = $current->toDateString();
            $current = $current->addDay();
        }

        $room->services()
            ->where('service_code', BookingRoomService::CODE_EXTRA_BED)
            ->whereNotIn('service_date', $stayDates)
            ->where('is_posted', 0)
            ->forceDelete();

        // 3. Upsert cho từng ngày
        foreach ($stayDates as $dateStr) {
            $existing = $existingServices->get($dateStr);
            if ($existing && $existing->is_posted == 1) {
                // Đã post rồi thì không được ghi đè hay thay đổi giá/số lượng
                continue;
            }

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

    /**
     * Tách phòng (split booking room segment)
     * POST /bookings/{bookingId}/rooms/{roomId}/split
     */
    public function split(Request $request, $bookingId, $roomId)
    {
        $request->validate([
            'split_date' => 'required|date'
        ]);

        $splitDateStr = $request->split_date;
        $splitDate    = \Carbon\Carbon::parse($splitDateStr)->startOfDay();

        $bookingRoom = BookingRoom::where('booking_id', $bookingId)->findOrFail($roomId);

        if ($bookingRoom->status === BookingRoom::STATUS_CANCELLED) {
            return response()->json(['success' => false, 'message' => 'Phòng đã hủy, không thể tách.'], 422);
        }
        if ($bookingRoom->status === BookingRoom::STATUS_CHECKED_OUT) {
            return response()->json(['success' => false, 'message' => 'Phòng đã checkout, không thể tách.'], 422);
        }

        $arrDate = \Carbon\Carbon::parse($bookingRoom->arrival_date)->startOfDay();
        $depDate = \Carbon\Carbon::parse($bookingRoom->departure_date)->startOfDay();

        if ($splitDate->lte($arrDate) || $splitDate->gte($depDate)) {
            return response()->json([
                'success' => false,
                'message' => 'Ngày tách phải nằm giữa ngày đến (' . $arrDate->toDateString() . ') và ngày đi (' . $depDate->toDateString() . ') của phòng.'
            ], 422);
        }

        try {
            \Illuminate\Support\Facades\DB::beginTransaction();

            $originalDepartureDate = $bookingRoom->departure_date->toDateString();

            // 1. Cập nhật ngày đi của đoạn đầu tiên
            $bookingRoom->departure_date = $splitDateStr;
            $bookingRoom->save();

            // 2. Tạo đoạn thứ hai (copy từ đoạn thứ nhất)
            $newBookingRoom = $bookingRoom->replicate();
            $newBookingRoom->id = null; // Tự động sinh ID mới dạng Gx
            $newBookingRoom->arrival_date = $splitDateStr;
            $newBookingRoom->departure_date = $originalDepartureDate;
            $newBookingRoom->status = BookingRoom::STATUS_BOOKED; // Reset về BOOKED cho đoạn sau
            $newBookingRoom->save();

            \Illuminate\Support\Facades\DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Tách phòng thành công.',
                'data' => [
                    'original' => $bookingRoom,
                    'new'      => $newBookingRoom
                ]
            ]);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Lỗi hệ thống khi tách phòng: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Tạo chi tiết ăn sáng cho trẻ em
     */
    private function createChildBreakfastDetails($child, $bRoom)
    {
        $setting = \App\Models\HotelSetting::first();
        $autoExtra = $setting?->booking_auto_extra_charge_bf_child == 1;
        $childRate = $setting?->breakfast_child_rate ?? 90000;

        $current = Carbon::parse($bRoom->arrival_date);
        $departure = Carbon::parse($bRoom->departure_date);

        while ($current->lt($departure)) {
            $isFree = true;
            $isExtra = false;
            $amount = 0;

            if ($child->age_group === 'child') {
                if ($autoExtra) {
                    $isFree = false;
                    $isExtra = true;
                    $amount = $childRate;
                }
            }

            \App\Models\BookingChildBreakfastDetail::create([
                'booking_child_id' => $child->id,
                'service_date'     => $current->toDateString(),
                'breakfast'        => true,
                'is_free'          => $isFree,
                'is_extra_charge'  => $isExtra,
                'is_room'          => true, // FIT
                'amount'           => $amount,
            ]);

            $current = $current->addDay();
        }
    }

    /**
     * GET /bookings/{bookingId}/rooms/{roomId}/move-target-rooms
     * Lấy danh sách phòng trống khả dụng & phòng inhouse cho phép gộp
     */
    public function getMoveTargetRooms($bookingId, $roomId)
    {
        $bookingRoom = BookingRoom::where('booking_id', $bookingId)
            ->with(['guests.guest', 'roomClass', 'children', 'services'])
            ->findOrFail($roomId);

        $systemDate = $this->avService->getSystemDate();
        $moveDateStr = $systemDate->toDateString();
        $departureDateStr = $bookingRoom->departure_date->toDateString();

        $effectiveStartDate = ($bookingRoom->status === BookingRoom::STATUS_CHECKED_IN)
            ? max($moveDateStr, $bookingRoom->arrival_date->toDateString())
            : $bookingRoom->arrival_date->toDateString();

        // 1. Fetch available physical rooms (excluding internal/virtual rooms)
        $allRooms = \App\Models\Room::with(['roomClass', 'roomForm'])
            ->physical()
            ->where('room_number', '!=', $bookingRoom->room_number ?? '')
            ->get();

        $ratesMap = \App\Models\StandardRate::all()->keyBy('room_class_id');

        $availableRooms = [];
        $statusLabels = [
            'available'   => 'Sẵn sàng (Vacant Clean)',
            'clean'       => 'Vacant Clean',
            'dirty'       => 'Vacant Dirty',
            'checkout'    => 'Vacant Dirty',
            'maintenance' => 'Out of Service (OOO)',
            'occupied'    => 'Occupied',
        ];

        foreach ($allRooms as $room) {
            $isOccupied = $this->avService->isRoomNumberOccupied(
                $room->room_number,
                $effectiveStartDate,
                $departureDateStr,
                $bookingRoom->id
            );

            if (!$isOccupied) {
                $stdRate = $ratesMap->get($room->room_class_id);

                $availableRooms[] = [
                    'id'              => $room->id,
                    'room_number'     => $room->room_number,
                    'room_class_id'   => $room->room_class_id,
                    'room_class_code' => $room->roomClass?->code ?? $room->roomClass?->Ma ?? '',
                    'room_class_name' => $room->roomClass?->name ?? '',
                    'room_form_name'  => $room->roomForm?->name ?? 'Double',
                    'floor'           => $room->floor,
                    'grid_row'        => (int)$room->grid_row,
                    'grid_column'     => (int)$room->grid_column,
                    'is_internal'     => (bool)$room->is_internal,
                    'is_virtual'      => (bool)$room->is_virtual,
                    'rate'            => (float)($stdRate?->rate ?? 0),
                    'extra_bed_rate'  => (float)($stdRate?->extra_bed_rate ?? 0),
                    'status'          => $room->status,
                    'status_label'    => $statusLabels[$room->status] ?? $room->status,
                    'is_ready'        => $room->status === 'available',
                ];
            }
        }

        usort($availableRooms, fn($a, $b) => strcmp($a['room_number'], $b['room_number']));

        // 2. Fetch occupied (In-House) rooms for merging (departure_date >= current room's departure_date)
        $occupiedBookingRooms = BookingRoom::where('id', '!=', $bookingRoom->id)
            ->where('status', BookingRoom::STATUS_CHECKED_IN)
            ->where('departure_date', '>=', $departureDateStr)
            ->whereHas('room', fn($q) => $q->physical())
            ->with(['guests.guest', 'roomClass', 'room.roomForm'])
            ->get();

        $occupiedRooms = [];
        foreach ($occupiedBookingRooms as $obrItem) {
            $primaryGuest = $obrItem->guests->firstWhere('is_primary', 1)?->guest;
            $guestNames = $obrItem->guests->map(fn($g) => $g->guest?->full_name)->filter()->values();
            $mainGuestName = $primaryGuest?->full_name ?? ($guestNames[0] ?? 'Khách');

            $statusText = "Inhouse guest: ({$obrItem->booking_id}) " . mb_strtoupper($mainGuestName);

            $occupiedRooms[] = [
                'booking_room_id' => $obrItem->id,
                'booking_id'      => $obrItem->booking_id,
                'booking_code'    => $obrItem->booking?->booking_code ?? $obrItem->id,
                'room_number'     => $obrItem->room_number,
                'room_class_id'   => $obrItem->room_class_id,
                'room_class_code' => $obrItem->roomClass?->code ?? $obrItem->roomClass?->Ma ?? '',
                'room_class_name' => $obrItem->roomClass?->name ?? '',
                'room_form_name'  => $obrItem->room?->roomForm?->name ?? 'Double',
                'floor'           => $obrItem->room?->floor,
                'grid_row'        => (int)($obrItem->room?->grid_row ?? 0),
                'grid_column'     => (int)($obrItem->room?->grid_column ?? 0),
                'is_internal'     => (bool)($obrItem->room?->is_internal ?? false),
                'is_virtual'      => (bool)($obrItem->room?->is_virtual ?? false),
                'guest_name'      => $mainGuestName,
                'all_guests'      => $guestNames,
                'arrival_date'    => $obrItem->arrival_date->toDateString(),
                'departure_date'  => $obrItem->departure_date->toDateString(),
                'rate'            => (float)$obrItem->rate,
                'extra_bed_rate'  => (float)($obrItem->extra_bed_rate ?? 0),
                'status_label'    => $statusText,
                'adults'          => $obrItem->adults,
                'children'        => $obrItem->children_count ?? 0,
            ];
        }

        usort($occupiedRooms, fn($a, $b) => strcmp($a['room_number'], $b['room_number']));

        $guests = $bookingRoom->guests->map(function ($gPivot) {
            return [
                'guest_id'   => $gPivot->guest_id,
                'full_name'  => $gPivot->guest?->full_name ?? '',
                'is_primary' => (bool)$gPivot->is_primary,
                'is_child'   => false,
            ];
        });

        $children = $bookingRoom->children
            ? $bookingRoom->children->where('child_status', 0)->values()->map(function ($c) {
                return [
                    'guest_id'   => $c->id,
                    'full_name'  => $c->full_name ?: ($c->age_group === 'baby' ? 'Em bé' : 'Trẻ em'),
                    'is_primary' => false,
                    'is_child'   => true,
                    'age_group'  => $c->age_group,
                ];
            })
            : collect();

        if ($children->isEmpty()) {
            $fallbackChildren = collect();
            $childQty = (int)($bookingRoom->children_qty ?? 0);
            $babyQty = (int)($bookingRoom->babies ?? 0);

            for ($i = 1; $i <= $childQty; $i++) {
                $fallbackChildren->push([
                    'guest_id'   => 'CHILD_' . $i,
                    'full_name'  => "Trẻ em {$i}",
                    'is_primary' => false,
                    'is_child'   => true,
                    'age_group'  => 'child',
                ]);
            }
            for ($b = 1; $b <= $babyQty; $b++) {
                $fallbackChildren->push([
                    'guest_id'   => 'BABY_' . $b,
                    'full_name'  => "Em bé {$b}",
                    'is_primary' => false,
                    'is_child'   => true,
                    'age_group'  => 'baby',
                ]);
            }
            $children = $fallbackChildren;
        }

        return response()->json([
            'success' => true,
            'data' => [
                'current_room' => [
                    'id'               => $bookingRoom->id,
                    'booking_id'       => $bookingRoom->booking_id,
                    'booking_code'     => $bookingRoom->booking?->booking_code,
                    'room_number'      => $bookingRoom->room_number,
                    'room_class_id'    => $bookingRoom->room_class_id,
                    'room_class_code'  => $bookingRoom->roomClass?->code ?? $bookingRoom->roomClass?->Ma ?? '',
                    'room_class_name'  => $bookingRoom->roomClass?->name ?? '',
                    'room_form_name'   => $bookingRoom->room?->roomForm?->name ?? 'Double',
                    'arrival_date'     => $bookingRoom->arrival_date->toDateString(),
                    'departure_date'   => $bookingRoom->departure_date->toDateString(),
                    'move_date'        => $moveDateStr,
                    'status'           => $bookingRoom->status,
                    'is_do_not_move'   => (bool)$bookingRoom->is_do_not_move,
                    'rate'             => (float)$bookingRoom->rate,
                    'extra_bed_qty'    => (int)($bookingRoom->extra_bed_qty ?? 0),
                    'extra_bed_rate'   => (float)($bookingRoom->extra_bed_rate ?? 0),
                    'guests'           => $guests,
                    'children'         => $children,
                ],
                'available_rooms' => $availableRooms,
                'occupied_rooms'  => $occupiedRooms,
            ]
        ]);
    }

    /**
     * POST /bookings/{bookingId}/rooms/{roomId}/move
     * Thực hiện Chuyển phòng (Form A) hoặc Gộp phòng (Form B)
     */
    public function moveRoom(Request $request, $bookingId, $roomId)
    {
        $bookingRoom = BookingRoom::where('booking_id', $bookingId)
            ->with(['guests', 'children', 'services'])
            ->findOrFail($roomId);

        // 1. Check lock status (is_do_not_move)
        if ($bookingRoom->is_do_not_move) {
            return response()->json([
                'success' => false,
                'message' => "Phòng {$bookingRoom->room_number} đang bị khóa chuyển phòng (Do Not Move). Vui lòng mở khóa trước.",
            ], 422);
        }

        $request->validate([
            'move_type'          => 'required|in:available,merge',
            'reason'             => 'required|string|max:500',
            'target_room_number' => 'required|string',
            'selected_guest_ids' => 'nullable|array',
            'is_change_rate'     => 'nullable|boolean',
            'rate'               => 'nullable|numeric|min:0',
            'extra_bed_qty'      => 'nullable|integer|min:0',
            'extra_bed_rate'     => 'nullable|numeric|min:0',
        ], [
            'reason.required' => 'Vui lòng nhập lý do chuyển phòng.',
            'target_room_number.required' => 'Vui lòng chọn phòng đích.',
        ]);

        $moveType = $request->input('move_type');
        $reason = trim($request->input('reason'));
        $targetRoomNumber = trim($request->input('target_room_number'));
        $selectedGuestIds = $request->input('selected_guest_ids', []);
        $isChangeRate = filter_var($request->input('is_change_rate', false), FILTER_VALIDATE_BOOLEAN);

        $currentUser = Auth::user()?->username ?? 'system';
        $systemDate = $this->avService->getSystemDate();

        if ($moveType === 'available') {
            // Form A: Move to Available Room
            $physicalRoom = \App\Models\Room::where('room_number', $targetRoomNumber)->first();
            if (!$physicalRoom) {
                return response()->json(['success' => false, 'message' => "Không tìm thấy số phòng {$targetRoomNumber} trong hệ thống."], 422);
            }

            // Rule 2.1: Target room MUST be in "Sẵn sàng" (status === 'available').
            if ($physicalRoom->status !== 'available') {
                return response()->json([
                    'success' => false,
                    'message' => 'Vui lòng kiểm tra tình trạng phòng',
                    'detail'  => "Phòng {$targetRoomNumber} hiện chưa ở trạng thái Sẵn sàng (Trạng thái: {$physicalRoom->status})."
                ], 422);
            }

            $moveDateStr = ($bookingRoom->status === BookingRoom::STATUS_CHECKED_IN)
                ? max($systemDate->toDateString(), $bookingRoom->arrival_date->toDateString())
                : $bookingRoom->arrival_date->toDateString();
            $departureDateStr = $bookingRoom->departure_date->toDateString();

            if ($this->avService->isRoomNumberOccupied($targetRoomNumber, $moveDateStr, $departureDateStr, $bookingRoom->id)) {
                return response()->json([
                    'success' => false,
                    'message' => "Số phòng {$targetRoomNumber} đã có khách ở hoặc đã được phân phòng trong khoảng thời gian {$moveDateStr} đến {$departureDateStr}.",
                ], 422);
            }

            DB::beginTransaction();
            try {
                $now = \Carbon\Carbon::now();
                $timeStr = $now->format('H:i:s');
                $sysDateStr = $systemDate->toDateString();

                $allGuests = $bookingRoom->guests()->get();
                $allGuestsCount = $allGuests->count();
                $isAllGuestsMoved = empty($selectedGuestIds) || (count($selectedGuestIds) >= $allGuestsCount);

                $movedGuestPivots = empty($selectedGuestIds)
                    ? $allGuests
                    : $allGuests->whereIn('guest_id', $selectedGuestIds);
                $movedGuestIds = $movedGuestPivots->pluck('guest_id')->toArray();

                $movedAdultsCount = $movedGuestPivots->count(); // Count of moved adults
                $movedChildrenCount = 0; // Count of moved children

                if ($bookingRoom->status === BookingRoom::STATUS_CHECKED_IN) {
                    $originalArrivalStr = $bookingRoom->actual_arrival_date
                        ? $bookingRoom->actual_arrival_date->toDateString()
                        : $bookingRoom->arrival_date->toDateString();
                    $originalDepartureStr = $bookingRoom->departure_date->toDateString();

                    // --- 1. PREPARE NEW ROOM (Sp2100) ---
                    $attributes = $bookingRoom->getAttributes();
                    unset($attributes['id'], $attributes['created_at'], $attributes['updated_at'], $attributes['deleted_at']);

                    $attributes['room_class_id']       = $physicalRoom->room_class_id;
                    $attributes['RoomKind']            = $physicalRoom->roomForm?->name ?? $physicalRoom->roomClass?->name ?? $bookingRoom->RoomKind;
                    $attributes['room_number']         = $targetRoomNumber;
                    $attributes['status']              = BookingRoom::STATUS_CHECKED_IN;
                    $attributes['arrival_date']        = $sysDateStr;
                    $attributes['departure_date']      = \Carbon\Carbon::parse($originalDepartureStr)->subDay()->toDateString();
                    $attributes['actual_arrival_date'] = $originalArrivalStr;
                    $attributes['arrival_time']        = $timeStr;
                    $attributes['ActutalNumOfDays']    = max(1, \Carbon\Carbon::parse($sysDateStr)->diffInDays(\Carbon\Carbon::parse($originalDepartureStr)));
                    $attributes['adults']              = $movedAdultsCount;
                    $attributes['children_qty']        = $movedChildrenCount;
                    $attributes['booking_date']        = $sysDateStr;
                    $attributes['check_in_user']       = $currentUser;
                    $attributes['check_out_user']      = null;
                    $attributes['CheckoutDate']        = null;
                    $attributes['CheckoutTime']        = null;
                    $attributes['move_room']           = null;
                    $attributes['created_by']          = $currentUser;
                    $attributes['updated_by']          = $currentUser;

                    $origArrivalFormatted = \Carbon\Carbon::parse($originalArrivalStr)->format('d-m-Y');
                    $attributes['note'] = trim("From Room {$bookingRoom->room_number}, old arrival date: {$origArrivalFormatted}" . ($reason ? " | Lý do: {$reason}" : ""));

                    if ($isChangeRate) {
                        if ($request->has('rate')) $attributes['rate'] = $request->rate;
                        if ($request->has('extra_bed_qty')) $attributes['extra_bed_qty'] = $request->extra_bed_qty;
                        if ($request->has('extra_bed_rate')) $attributes['extra_bed_rate'] = $request->extra_bed_rate;
                        if ($request->filled('rate_code')) $attributes['rate_code'] = $request->rate_code;
                    }

                    // Insert new booking room record (Sp2100)
                    $newRoom = BookingRoom::create($attributes);

                    // --- 2. HANDLE OLD ROOM (Sp2100 & Sp2200 & Sp2500) ---
                    if ($isAllGuestsMoved) {
                        // All guests moved -> Old room status = 100, departure_date = system_date - 1
                        $prevDepartureDateStr = \Carbon\Carbon::parse($sysDateStr)->subDay()->toDateString();
                        $actualDaysStayed = max(1, \Carbon\Carbon::parse($originalArrivalStr)->diffInDays(\Carbon\Carbon::parse($sysDateStr)));

                        $bookingRoom->update([
                            'departure_date'   => $prevDepartureDateStr,
                            'ActutalNumOfDays' => $actualDaysStayed,
                            'status'           => 100, // Status 100 = Chuyển phòng
                            'move_room'        => $newRoom->id,
                            'departure_time'   => $timeStr,
                            'CheckoutDate'     => $sysDateStr,
                            'CheckoutTime'     => $timeStr,
                            'check_out_user'   => $currentUser,
                            'note'             => trim(($bookingRoom->note ? $bookingRoom->note . ' | ' : '') . "Đã chuyển toàn bộ sang phòng {$targetRoomNumber}: {$reason}"),
                            'updated_by'       => $currentUser,
                        ]);

                        // Update physical room status to dirty
                        \App\Models\Room::where('room_number', $bookingRoom->room_number)->update(['room_status_code' => 'vacant_dirty']);

                        // Sp2200: Update all guests in old room -> Status = 100
                        \App\Models\BookingRoomGuest::where('booking_room_id', $bookingRoom->id)->update([
                            'status'               => 100,
                            'actual_checkout_date' => $sysDateStr,
                            'actual_checkout_time' => $timeStr,
                            'checkout_by'          => $currentUser,
                        ]);

                        // Sp2500 (booking_children): Update children in old room -> Status = 100 & Clone to new room
                        $oldChildren = \App\Models\BookingChild::where('booking_room_id', $bookingRoom->id)->get();
                        \App\Models\BookingChild::where('booking_room_id', $bookingRoom->id)->update([
                            'child_status' => 100,
                        ]);

                        foreach ($oldChildren as $childItem) {
                            $oldChildId = $childItem->id;
                            $childData = $childItem->toArray();
                            unset($childData['id'], $childData['created_at'], $childData['updated_at']);
                            $childData['id'] = 'BC' . uniqid();
                            $childData['booking_room_id'] = $newRoom->id;
                            $childData['child_status'] = 0;
                            $newChild = \App\Models\BookingChild::create($childData);

                            // Sp2401 (booking_child_breakfast_details): Transfer unbilled breakfast details >= system_date
                            \App\Models\BookingChildBreakfastDetail::where('booking_child_id', $oldChildId)
                                ->where('service_date', '>=', $sysDateStr)
                                ->update(['booking_child_id' => $newChild->id]);
                        }
                    } else {
                        // Partial move -> Old room remains Checked-In (Status = 1), update remaining guest count
                        $remainingAdults = max(1, ($bookingRoom->adults ?? 1) - $movedAdultsCount);
                        $remainingChildren = max(0, ($bookingRoom->children_qty ?? 0) - $movedChildrenCount);

                        $bookingRoom->update([
                            'adults'       => $remainingAdults,
                            'children_qty' => $remainingChildren,
                            'note'           => trim(($bookingRoom->note ? $bookingRoom->note . ' | ' : '') . "Đã chuyển {$movedAdultsCount} khách sang phòng {$targetRoomNumber}: {$reason}"),
                            'updated_by'     => $currentUser,
                        ]);

                        // Sp2200: Only update moved guests -> Status = 100
                        \App\Models\BookingRoomGuest::where('booking_room_id', $bookingRoom->id)
                            ->whereIn('guest_id', $movedGuestIds)
                            ->update([
                                'status'               => 100,
                                'actual_checkout_date' => $sysDateStr,
                                'actual_checkout_time' => $timeStr,
                                'checkout_by'          => $currentUser,
                            ]);
                    }

                    // --- 3. INSERT GUESTS INTO NEW ROOM (Sp2200) ---
                    foreach ($movedGuestPivots as $gPivot) {
                        \App\Models\BookingRoomGuest::create([
                            'booking_room_id'      => $newRoom->id,
                            'guest_id'             => $gPivot->guest_id,
                            'is_primary'           => $gPivot->is_primary,
                            'status'               => BookingRoom::STATUS_CHECKED_IN,
                            'actual_arrival_date'  => $sysDateStr,
                            'actual_arrival_time'  => $timeStr,
                            'checkin_by'           => $currentUser,
                            'actual_checkout_date' => $originalDepartureStr,
                        ]);
                    }

                    // --- 4. TRANSFER LINKED RECORDS (Sp2401, Sp2102, Sp2107, Sp3000, Sp3002) ---
                    // Sp2401 & Sp2102 & Sp3000: Transfer future/unbilled services from system_date onwards
                    \App\Models\BookingRoomService::where('booking_room_id', $bookingRoom->id)
                        ->where('service_date', '>=', $sysDateStr)
                        ->update(['booking_room_id' => $newRoom->id]);

                    // Sp2107: Transfer special requests
                    \App\Models\BookingRoomSpecialRequest::where('booking_room_id', $bookingRoom->id)
                        ->update(['booking_room_id' => $newRoom->id]);

                    // Sp3002: Transfer room-level payments/deposits (Register-level payments with booking_room_id = null remain unchanged)
                    \App\Models\Payment::where('booking_room_id', $bookingRoom->id)
                        ->update(['booking_room_id' => $newRoom->id]);

                    DB::commit();

                    return response()->json([
                        'success' => true,
                        'message' => "Chuyển phòng {$bookingRoom->room_number} sang phòng {$targetRoomNumber} thành công!",
                        'data'    => $newRoom->fresh(),
                    ]);
                } else {
                    // Reserved state room change
                    $oldNumber = $bookingRoom->room_number;
                    $updateData = [
                        'room_number' => $targetRoomNumber,
                        'note'        => trim(($bookingRoom->note ? $bookingRoom->note . ' | ' : '') . "Đổi phòng từ {$oldNumber} sang {$targetRoomNumber}: {$reason}"),
                        'updated_by'  => $currentUser,
                    ];

                    if ($isChangeRate) {
                        if ($request->has('rate')) $updateData['rate'] = $request->rate;
                        if ($request->has('extra_bed_qty')) $updateData['extra_bed_qty'] = $request->extra_bed_qty;
                        if ($request->has('extra_bed_rate')) $updateData['extra_bed_rate'] = $request->extra_bed_rate;
                        if ($request->filled('rate_code')) $updateData['rate_code'] = $request->rate_code;
                    }
                    $bookingRoom->update($updateData);

                    DB::commit();

                    return response()->json([
                        'success' => true,
                        'message' => "Đổi số phòng sang {$targetRoomNumber} thành công!",
                        'data'    => $bookingRoom->fresh(),
                    ]);
                }
            } catch (\Exception $e) {
                DB::rollBack();
                return response()->json(['success' => false, 'message' => 'Lỗi khi xử lý chuyển phòng: ' . $e->getMessage()], 500);
            }

        } else if ($moveType === 'merge') {
            $targetBookingRoom = BookingRoom::where('room_number', $targetRoomNumber)
                ->where('status', BookingRoom::STATUS_CHECKED_IN)
                ->where('id', '!=', $bookingRoom->id)
                ->first();

            if (!$targetBookingRoom) {
                return response()->json(['success' => false, 'message' => "Không tìm thấy phòng đang ở (In-house) {$targetRoomNumber} để gộp."], 422);
            }

            if ($targetBookingRoom->departure_date->lt($bookingRoom->departure_date)) {
                return response()->json([
                    'success' => false,
                    'message' => "Phòng gộp {$targetRoomNumber} có ngày trả phòng (" . $targetBookingRoom->departure_date->toDateString() . ") trước ngày trả phòng hiện tại (" . $bookingRoom->departure_date->toDateString() . "). Không thể gộp.",
                ], 422);
            }

            $activeGuests = $bookingRoom->guests()->where('status', '!=', 100)->get();
            $totalAdultsCount = $activeGuests->count();

            $guestsToMove = empty($selectedGuestIds)
                ? $activeGuests
                : $activeGuests->whereIn('guest_id', $selectedGuestIds);
            $movedGuestIds = $guestsToMove->pluck('guest_id')->toArray();
            $movedAdultsCount = count($movedGuestIds);

            if ($movedAdultsCount === 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'Vui lòng chọn ít nhất 1 khách để chuyển sang phòng gộp.',
                ], 422);
            }

            // Check if adding moved guests will exceed target room capacity
            $targetPhysicalRoom = \App\Models\Room::where('room_number', $targetRoomNumber)->first();
            $maxCapacity = $targetPhysicalRoom?->max_guests
                        ?? $targetPhysicalRoom?->roomForm?->max_adults
                        ?? 2;

            $currentAdults = $targetBookingRoom->adults ?? 1;
            $newTotalAdults = $currentAdults + $movedAdultsCount;

            if ($newTotalAdults > $maxCapacity && !$request->boolean('confirm_exceed_capacity')) {
                return response()->json([
                    'success'                  => false,
                    'require_capacity_confirm' => true,
                    'message'                  => "Số người sau khi gộp ({$newTotalAdults} người) vượt quá sức chứa tối đa của phòng {$targetRoomNumber} ({$maxCapacity} người). Bạn có muốn tiếp tục gộp không?",
                ], 422);
            }

            DB::beginTransaction();
            try {
                $timeStr = \Carbon\Carbon::now()->format('H:i:s');
                $sysDateStr = $systemDate->toDateString();
                $isAllGuestsMoved = empty($selectedGuestIds) || ($movedAdultsCount >= $totalAdultsCount);

                $oldChildren = \App\Models\BookingChild::where('booking_room_id', $bookingRoom->id)->get();
                $movedChildrenCount = $isAllGuestsMoved ? $oldChildren->count() : 0;

                if ($isAllGuestsMoved && $oldChildren->count() > 0) {
                    \App\Models\BookingChild::where('booking_room_id', $bookingRoom->id)->update([
                        'child_status' => 100,
                    ]);

                    foreach ($oldChildren as $childItem) {
                        $childData = $childItem->toArray();
                        unset($childData['id'], $childData['created_at'], $childData['updated_at']);
                        $childData['id'] = 'BC' . uniqid();
                        $childData['booking_room_id'] = $targetBookingRoom->id;
                        $childData['child_status'] = 0;
                        \App\Models\BookingChild::create($childData);
                    }
                }

                // --- 1. UPDATE OLD ROOM GUESTS (Sp2200) -> Status = 100 ---
                \App\Models\BookingRoomGuest::where('booking_room_id', $bookingRoom->id)
                    ->whereIn('guest_id', $movedGuestIds)
                    ->update([
                        'status'               => 100,
                        'actual_checkout_date' => $sysDateStr,
                        'actual_checkout_time' => $timeStr,
                        'checkout_by'          => $currentUser,
                    ]);

                // --- 2. INSERT GUESTS INTO TARGET INHOUSE ROOM (Sp2200) -> Status = 1 ---
                foreach ($guestsToMove as $gPivot) {
                    \App\Models\BookingRoomGuest::firstOrCreate([
                        'booking_room_id' => $targetBookingRoom->id,
                        'guest_id'        => $gPivot->guest_id,
                    ], [
                        'is_primary'           => 0,
                        'status'               => BookingRoom::STATUS_CHECKED_IN,
                        'actual_arrival_date'  => $sysDateStr,
                        'actual_arrival_time'  => $timeStr,
                        'checkin_by'           => $currentUser,
                        'actual_checkout_date' => $targetBookingRoom->departure_date->toDateString(),
                    ]);
                }

                // --- 3. UPDATE TARGET INHOUSE ROOM (Sp2100) ---
                $newTotalChildren = ($targetBookingRoom->children_qty ?? 0) + $movedChildrenCount;
                $noteMsg = "Gộp khách từ phòng {$bookingRoom->room_number}: {$reason}";
                $targetUpdateData = [
                    'adults'       => $newTotalAdults,
                    'children_qty' => $newTotalChildren,
                    'note'         => trim(($targetBookingRoom->note ? $targetBookingRoom->note . ' | ' : '') . $noteMsg),
                    'updated_by'   => $currentUser,
                ];
                if ($isChangeRate) {
                    if ($request->has('rate')) $targetUpdateData['rate'] = $request->rate;
                    if ($request->has('extra_bed_qty')) $targetUpdateData['extra_bed_qty'] = $request->extra_bed_qty;
                    if ($request->has('extra_bed_rate')) $targetUpdateData['extra_bed_rate'] = $request->extra_bed_rate;
                    if ($request->filled('rate_code')) $targetUpdateData['rate_code'] = $request->rate_code;
                }
                $targetBookingRoom->update($targetUpdateData);

                // --- 4. UPDATE OLD ROOM (Sp2100) ---
                if ($isAllGuestsMoved) {
                    $originalArrivalStr = $bookingRoom->actual_arrival_date
                        ? $bookingRoom->actual_arrival_date->toDateString()
                        : $bookingRoom->arrival_date->toDateString();
                    $prevDepartureDateStr = \Carbon\Carbon::parse($sysDateStr)->subDay()->toDateString();
                    $actualDaysStayed = max(1, \Carbon\Carbon::parse($originalArrivalStr)->diffInDays(\Carbon\Carbon::parse($sysDateStr)));

                    $bookingRoom->update([
                        'departure_date'   => $prevDepartureDateStr,
                        'ActutalNumOfDays' => $actualDaysStayed,
                        'status'           => 100, // Status 100 = Chuyển phòng / Gộp phòng
                        'move_room'        => $targetBookingRoom->id,
                        'departure_time'   => $timeStr,
                        'CheckoutDate'     => $sysDateStr,
                        'CheckoutTime'     => $timeStr,
                        'check_out_user'   => $currentUser,
                        'note'             => trim(($bookingRoom->note ? $bookingRoom->note . ' | ' : '') . "Đã gộp toàn bộ khách sang phòng {$targetRoomNumber}: {$reason}"),
                        'updated_by'       => $currentUser,
                    ]);

                    \App\Models\Room::where('room_number', $bookingRoom->room_number)->update(['room_status_code' => 'vacant_dirty']);
                } else {
                    $remainingAdults = max(1, $totalAdultsCount - $movedAdultsCount);
                    $bookingRoom->update([
                        'adults'     => $remainingAdults,
                        'note'       => trim(($bookingRoom->note ? $bookingRoom->note . ' | ' : '') . "Đã gộp {$movedAdultsCount} khách sang phòng {$targetRoomNumber}: {$reason}"),
                        'updated_by' => $currentUser,
                    ]);
                }

                // --- 5. TRANSFER LINKED RECORDS (Sp2102, Sp2107, Sp3000, Sp3002) TO TARGET INHOUSE ROOM IF ALL MOVED ---
                if ($isAllGuestsMoved) {
                    \App\Models\BookingRoomService::where('booking_room_id', $bookingRoom->id)
                        ->where('service_date', '>=', $sysDateStr)
                        ->update(['booking_room_id' => $targetBookingRoom->id]);

                    \App\Models\BookingRoomSpecialRequest::where('booking_room_id', $bookingRoom->id)
                        ->update(['booking_room_id' => $targetBookingRoom->id]);

                }

                DB::commit();

                return response()->json([
                    'success' => true,
                    'message' => "Gộp khách từ phòng {$bookingRoom->room_number} sang phòng {$targetRoomNumber} thành công!",
                    'data'    => $targetBookingRoom->fresh(),
                ]);
            } catch (\Exception $e) {
                DB::rollBack();
                return response()->json(['success' => false, 'message' => 'Lỗi khi gộp phòng: ' . $e->getMessage()], 500);
            }
        }
    }
}

