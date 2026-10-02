<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\BookingRoom;
use App\Models\BookingRoomGuest;
use App\Models\Guest;
use App\Models\BookingChild;
use App\Models\LateCheckin;
use App\Models\NoshowLog;
use App\Models\SystemDateRoll;
use App\Models\HotelSetting;
use App\Models\Room;
use App\Models\RoomLock;
use App\Models\ServiceBill;
use App\Models\ServiceBillDetail;
use App\Models\RoomNightBill;
use App\Models\BookingRoomService;
use App\Models\HotelConfig;
use App\Models\HotelService;
use App\Models\NightAuditRun;
use App\Models\NightAuditRunStep;
use App\Models\NightAuditAgencyProductivitySnapshot;
use App\Models\NightAuditInhouseSnapshot;
use App\Models\NightAuditAgencyProductivityKpiSnapshot;
use App\Models\NightAuditRoomSalesForecastSnapshot;
use App\Models\NightAuditRoomSalesForecastDetailSnapshot;
use App\Models\NightAuditRoomTypeSnapshot;
use App\Services\NightAuditSnapshotService;
use App\Events\NightAuditUpdated;
use App\Events\RoomStatusUpdated;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use App\Services\RegistrationStatusMapper;
use App\Services\TaxBreakdownService;

class NightAuditController extends Controller
{
    /**
     * Helper: Lấy ngày hệ thống hiện tại
     */
    private function getSystemDate()
    {
        $latest = SystemDateRoll::latest('id')->first();
        return $latest
            ? Carbon::parse($latest->system_date)->startOfDay()
            : now()->timezone('Asia/Ho_Chi_Minh')->startOfDay();
    }

    /**
     * Tách lại tiền ăn sáng từ tiền phòng cho các đêm đã post.
     * Chỉ cập nhật SP3001 của SP3000 chưa xuất VAT; tổng tiền bill không thay đổi.
     */
    public function splitOldServices(Request $request)
    {
        $data = $request->validate([
            'from_date' => ['required', 'date'],
            'to_date' => ['required', 'date', 'after_or_equal:from_date'],
        ]);

        $systemDate = $this->getSystemDate();
        $fromDate = Carbon::parse($data['from_date'])->startOfDay();
        $toDate = Carbon::parse($data['to_date'])->startOfDay();
        if ($toDate->gt($systemDate)) {
            return response()->json([
                'success' => false,
                'message' => 'Ngày tách dịch vụ không được lớn hơn ngày hệ thống (' . $systemDate->format('d/m/Y') . ').',
            ], 422);
        }

        $setting = HotelSetting::first();
        $breakfastRate = (float) ($setting?->breakfast_adult_rate ?? 0);
        $roomService = HotelService::where('code', 'RM')->first();
        $breakfastService = HotelService::where('code', 'BF')->first();
        $roomTaxProfile = HotelService::taxProfile($roomService);
        $breakfastTaxProfile = HotelService::taxProfile($breakfastService);
        $result = ['updated' => 0, 'skipped_vat' => 0, 'skipped_invalid' => 0];

        DB::transaction(function () use ($fromDate, $toDate, $breakfastRate, $roomTaxProfile, $breakfastTaxProfile, &$result) {
            // Không phụ thuộc SP3004: các bill dữ liệu cũ có thể chỉ có SP3000/SP3001.
            $bills = ServiceBill::query()
                ->where('DepartmentId', 'FO')
                ->where('ServiceId', 'RM')
                ->whereDate('Date', '>=', $fromDate->toDateString())
                ->whereDate('Date', '<=', $toDate->toDateString())
                ->lockForUpdate()
                ->get();

            foreach ($bills as $bill) {

                $details = ServiceBillDetail::query()
                    ->where('BillServiceId', $bill->Ma)
                    ->lockForUpdate()
                    ->get();
                $isVatIssued = $bill->VatId !== null
                    || $details->contains(fn ($detail) => $detail->VatId !== null || filled($detail->VatNumber));
                if ($isVatIssued) {
                    $result['skipped_vat']++;
                    continue;
                }

                $room = BookingRoom::find($bill->RentalRoomId1);
                $positiveRoomDetail = $details->first(fn ($detail) => $detail->ServiceId === 'RM' && (float) $detail->Amount >= 0);
                if (!$room || !$positiveRoomDetail) {
                    $result['skipped_invalid']++;
                    continue;
                }

                $adults = max(1, (int) $room->adults);
                $breakfastAmount = $room->breakfast ? round($breakfastRate * $adults, 2) : 0;
                $breakfastDetail = $details->first(fn ($detail) => $detail->ServiceId === 'BF');
                $discountRoomDetail = $details->first(fn ($detail) => $detail->ServiceId === 'RM' && (float) $detail->Amount < 0);
                $nextDetailNo = ((int) $details->max('Ma')) + 1;
                $roomNumber = $room->room_number ?: $room->id;

                if ($breakfastAmount > 0) {
                    $bfBreakdown = TaxBreakdownService::breakdown($breakfastAmount, $breakfastTaxProfile['service_charge'], $breakfastTaxProfile['special_tax'], $breakfastTaxProfile['tax'], $adults);
                    $breakfastValues = [
                        'DepartmentId' => 'FO', 'ServiceId' => 'BF',
                        'DescriptionServive' => 'Tiền ăn sáng người lớn - Phòng ' . $roomNumber,
                        // Giá là đơn giá mỗi khách; Amount mới là tổng tiền BF.
                        'OriginalRate' => $breakfastRate, 'Quantity' => $adults,
                        'ServiceCharge' => $breakfastTaxProfile['service_charge'], 'SpecialTax' => $breakfastTaxProfile['special_tax'], 'Tax' => $breakfastTaxProfile['tax'],
                        'ServiceChargeAmount' => $bfBreakdown['service_charge_amount'],
                        'SpecialTaxAmount'    => $bfBreakdown['special_tax_amount'],
                        'TaxAmount'           => $bfBreakdown['tax_amount'],
                        'Amount' => $breakfastAmount, 'Currency' => $bill->Currency, 'Exchange' => 1,
                        'DetailBillOriginalAmount' => $bfBreakdown['net_total'],
                        'OriginalAmount' => $bfBreakdown['net_total'],
                    ];
                    if ($breakfastDetail) {
                        ServiceBillDetail::where('BillServiceId', $bill->Ma)->where('Ma', $breakfastDetail->Ma)->update($breakfastValues);
                    } else {
                        ServiceBillDetail::create($breakfastValues + ['BillServiceId' => $bill->Ma, 'Ma' => $nextDetailNo++]);
                    }

                    $discountBreakdown = TaxBreakdownService::breakdown(-$breakfastAmount, $roomTaxProfile['service_charge'], $roomTaxProfile['special_tax'], $roomTaxProfile['tax']);
                    $discountValues = [
                        'DepartmentId' => 'FO', 'ServiceId' => 'RM',
                        'DescriptionServive' => 'Trừ tiền ăn sáng người lớn - Phòng ' . $roomNumber,
                        'OriginalRate' => $discountBreakdown['original_rate'], 'Quantity' => 1,
                        'ServiceCharge' => $roomTaxProfile['service_charge'], 'SpecialTax' => $roomTaxProfile['special_tax'], 'Tax' => $roomTaxProfile['tax'],
                        'ServiceChargeAmount' => $discountBreakdown['service_charge_amount'],
                        'SpecialTaxAmount'    => $discountBreakdown['special_tax_amount'],
                        'TaxAmount'           => $discountBreakdown['tax_amount'],
                        'Amount' => -$breakfastAmount, 'Currency' => $bill->Currency, 'Exchange' => 1,
                        'DetailBillOriginalAmount' => $discountBreakdown['net_total'],
                        'OriginalAmount' => $discountBreakdown['net_total'],
                    ];
                    if ($discountRoomDetail) {
                        ServiceBillDetail::where('BillServiceId', $bill->Ma)->where('Ma', $discountRoomDetail->Ma)->update($discountValues);
                    } else {
                        ServiceBillDetail::create($discountValues + ['BillServiceId' => $bill->Ma, 'Ma' => $nextDetailNo++]);
                    }
                } else {
                    if ($breakfastDetail) ServiceBillDetail::where('BillServiceId', $bill->Ma)->where('Ma', $breakfastDetail->Ma)->delete();
                    if ($discountRoomDetail) ServiceBillDetail::where('BillServiceId', $bill->Ma)->where('Ma', $discountRoomDetail->Ma)->delete();
                }

                RoomNightBill::where('bill_id', $bill->Ma)->update([
                    'adult' => $adults,
                    'child' => (int) $room->children_qty,
                    'breakfast' => $breakfastAmount > 0 ? $adults : 0,
                    'breakfast_amount' => $breakfastAmount,
                    'room' => $room->room_number,
                    'room_type_id' => $room->room_class_id,
                    'rate' => (float) $positiveRoomDetail->Amount,
                ]);
                $result['updated']++;
            }
        });

        return response()->json([
            'success' => true,
            'message' => "Đã tách lại chi tiết dịch vụ cho {$result['updated']} bill.",
            'data' => $result,
        ]);
    }

    /**
     * Helper: Lấy ca làm việc hiện tại
     */
    private function getSystemShift()
    {
        $latest = SystemDateRoll::latest('id')->first();
        return $latest ? $latest->shift : '1';
    }

    /**
     * GET: Kiểm tra trạng thái phòng đi, phòng đến trước khi sang ngày
     * GET /api/night-audit/check-status
     */
    public function checkStatus()
    {
        $systemDate = $this->getSystemDate()->toDateString();

        $todayStart = Carbon::today();
        $todayEnd = Carbon::today()->endOfDay();
        $alreadyRolledToday = SystemDateRoll::whereBetween('actual_date', [$todayStart, $todayEnd])->exists();

        // 1. Phòng cần check in nhưng chưa check in (arrival_date <= system_date và status = 0, loại bỏ phòng chuyển và phòng chưa gán phòng vật lý)
        $pendingCheckIns = BookingRoom::with(['booking', 'roomClass'])
            ->stayOnly()
            ->whereDate('arrival_date', '<=', $systemDate)
            ->where('status', BookingRoom::STATUS_BOOKED)
            ->where('status', '!=', BookingRoom::STATUS_MOVED)
            ->whereNotNull('room_number')
            ->where('room_number', '!=', '')
            ->whereRaw("LOWER(TRIM(room_number)) NOT IN ('chưa gán', 'chua gan')")
            ->get();

        // 2. Phòng có lịch check out hôm nay/trước đây nhưng vẫn ở trạng thái in-house (departure_date <= system_date và status = 1, loại bỏ phòng chuyển)
        $pendingCheckOuts = BookingRoom::with(['booking', 'roomClass'])
            ->stayOnly()
            ->whereDate('departure_date', '<=', $systemDate)
            ->where('status', BookingRoom::STATUS_CHECKED_IN)
            ->where('status', '!=', BookingRoom::STATUS_MOVED)
            ->whereNotNull('room_number')
            ->where('room_number', '!=', '')
            ->whereRaw("LOWER(TRIM(room_number)) NOT IN ('chưa gán', 'chua gan')")
            ->get();

        $settings = HotelSetting::first();
        $latestRun = NightAuditRun::with('steps')->latest('id')->first();
        $isRunning = (bool) ($settings?->is_night_audit_running) ||
            ($latestRun && $latestRun->status === 'running' && $latestRun->actual_started_at && $latestRun->actual_started_at->gte(now()->subMinutes(15)));

        return response()->json([
            'success' => true,
            'data' => [
                'system_date' => $systemDate,
                'already_rolled_today' => $alreadyRolledToday,
                'is_running' => $isRunning,
                'latest_run' => $latestRun ? [
                    'id'                 => $latestRun->id,
                    'status'             => $latestRun->status,
                    'username'           => $latestRun->username,
                    'source_system_date' => $latestRun->source_system_date?->toDateString(),
                    'target_system_date' => $latestRun->target_system_date?->toDateString(),
                    'started_at'         => $latestRun->actual_started_at?->toIso8601String(),
                    'started_at_ms'      => $latestRun->actual_started_at ? (int) ($latestRun->actual_started_at->getTimestamp() * 1000) : null,
                    'finished_at'        => $latestRun->actual_finished_at?->toIso8601String(),
                    'error_message'      => $latestRun->error_message,
                    'steps'              => $latestRun->steps->map(fn($s) => [
                        'code'          => $s->step_code,
                        'name'          => $s->step_name,
                        'order'         => $s->step_order,
                        'status'        => $s->status,
                        'affected_rows' => $s->affected_rows,
                        'summary'       => $s->summary,
                        'error'         => $s->error_message,
                    ]),
                ] : null,
                'pending_checkins_count' => $pendingCheckIns->count(),
                'pending_checkouts_count' => $pendingCheckOuts->count(),
                'pending_checkins' => $pendingCheckIns->map(fn($r) => [
                    'id' => $r->id,
                    'booking_code' => $r->booking?->code,
                    'booking_name' => $r->booking?->booking_name,
                    'room_number' => $r->room_number ?? 'Chưa gán',
                    'room_type' => $r->roomClass?->name,
                    'arrival_date' => $r->arrival_date->toDateString(),
                    'departure_date' => $r->departure_date->toDateString(),
                ]),
                'pending_checkouts' => $pendingCheckOuts->map(fn($r) => [
                    'id' => $r->id,
                    'booking_code' => $r->booking?->code,
                    'booking_name' => $r->booking?->booking_name,
                    'room_number' => $r->room_number,
                    'room_type' => $r->roomClass?->name,
                    'arrival_date' => $r->arrival_date->toDateString(),
                    'departure_date' => $r->departure_date->toDateString(),
                ]),
            ]
        ]);
    }

    /**
     * POST: Late Check-in (Noshow One Day) dời ngày đến sang hôm sau
     * POST /api/night-audit/late-check-in
     */
    public function lateCheckIn(Request $request)
    {
        $request->validate([
            'booking_room_id' => 'required|string|exists:booking_rooms,id',
            'charge_option'   => 'required|in:all_charged,room_only,no_charge',
            'reason'          => 'nullable|string|max:200',
        ]);

        $bookingRoomId = $request->booking_room_id;
        $chargeOption  = $request->charge_option;
        $userReason    = $request->reason;

        $room = BookingRoom::with('booking')->findOrFail($bookingRoomId);
        if ($room->isVirtual()) {
            return response()->json(['success' => false, 'code' => 'virtual_room', 'message' => 'Folio phòng ảo không hỗ trợ late check-in.'], 422);
        }
        if ($room->status !== BookingRoom::STATUS_BOOKED) {
            return response()->json(['success' => false, 'message' => 'Phòng không ở trạng thái Đặt trước để late check-in.'], 422);
        }

        $systemDate = $this->getSystemDate();
        $nextDate   = $systemDate->copy()->addDay();
        $username   = Auth::user()?->username ?: (Auth::user()?->name ?: 'system');
        $shift      = $this->getSystemShift();

        DB::transaction(function () use ($room, $systemDate, $nextDate, $chargeOption, $userReason, $username, $shift) {
            // 1. Cập nhật ngày đến của phòng thuê
            $room->update([
                'arrival_date'        => $nextDate->toDateString(),
                'actual_arrival_date' => $nextDate->toDateString(),
                'no_show_day'         => $room->no_show_day + 1,
            ]);

            // Cập nhật ngày đến trong booking_room_guests (nếu có)
            BookingRoomGuest::where('booking_room_id', $room->id)->update([
                'actual_arrival_date' => $nextDate->toDateString()
            ]);

            // 2. Ghi nhận lịch sử Late Check-in
            $reasonText = 'NightAudit No Show One Day ' . ($chargeOption === 'no_charge' ? 'No Charge' : 'Charge Room');
            if ($userReason) {
                $reasonText .= ' - ' . $userReason;
            }

            LateCheckin::create([
                'booking_room_id'     => $room->id,
                'late_checkin_date'   => $systemDate->toDateTimeString(),
                'actual_arrival_date' => $nextDate->toDateTimeString(),
                'late_checkin_time'   => now()->format('H:i'),
                'reason'              => $reasonText,
                'status'              => 1,
                'username'            => $username,
                'shift'               => $shift,
            ]);

            // 3. Post tiền phạt đêm noshow nếu có yêu cầu
            if ($chargeOption !== 'no_charge') {
                $this->postSingleNightCharge($room, $systemDate, $chargeOption, $username, $reasonText, true);

                // Post dịch vụ bổ sung từ booking_room_services khi all_charged (chỉ tính ngày đến)
                if ($chargeOption === 'all_charged') {
                    $extraServices = BookingRoomService::where('booking_room_id', $room->id)
                        ->whereDate('service_date', $systemDate->toDateString())
                        ->where('service_code', '!=', 'RM')
                        ->where('is_posted', 0)
                        ->get();

                    foreach ($extraServices as $service) {
                        $this->postSetupServiceBill($room, $service, $username);
                    }
                }
            }
        });

        return response()->json([
            'success' => true,
            'message' => 'Late check-in thành công. Ngày đến mới: ' . $nextDate->toDateString(),
        ]);
    }

    /**
     * POST: Khách không đến (Noshow giải phóng phòng hoàn toàn)
     * POST /api/night-audit/no-show
     */
    public function noShowRoom(Request $request)
    {
        $request->validate([
            'booking_room_id' => 'required|string|exists:booking_rooms,id',
            'charge_option'   => 'required|in:all_charged,room_only,no_charge',
            'reason'          => 'nullable|string|max:200',
        ]);

        $bookingRoomId = $request->booking_room_id;
        $chargeOption  = $request->charge_option;
        $userReason    = $request->reason;

        $room = BookingRoom::with('booking')->findOrFail($bookingRoomId);
        if ($room->isVirtual()) {
            return response()->json(['success' => false, 'code' => 'virtual_room', 'message' => 'Folio phòng ảo không hỗ trợ no-show.'], 422);
        }
        if ($room->status !== BookingRoom::STATUS_BOOKED) {
            return response()->json(['success' => false, 'message' => 'Phòng không ở trạng thái Đặt trước để noshow.'], 422);
        }

        $systemDate = $this->getSystemDate();
        $username   = Auth::user()?->username ?: (Auth::user()?->name ?: 'system');
        $shift      = $this->getSystemShift();

        $warning = null;

        DB::transaction(function () use ($room, $systemDate, $chargeOption, $userReason, $username, $shift, &$warning) {
            // 1. Cập nhật trạng thái phòng thuê = 4 (Noshow) + tăng no_show_day [Fix C]
            $room->update([
                'status'      => 4,
                'no_show_day' => $room->no_show_day + 1,
            ]);

            // Cập nhật khách và trẻ em gán vào phòng
            BookingRoomGuest::where('booking_room_id', $room->id)->update(['status' => 4]);
            $guestIds = BookingRoomGuest::where('booking_room_id', $room->id)->pluck('guest_id');
            app(\App\Services\GuestStatusSyncService::class)->syncForGuestIds($guestIds);
            BookingChild::where('booking_room_id', $room->id)->update(['child_status' => 4]);

            // 2. Giải phóng phòng vật lý
            if ($room->room_number) {
                $physicalRoom = Room::where('room_number', $room->room_number)->first();
                if ($physicalRoom) {
                    $physicalRoom->update(['room_status_code' => 'vacant_ready']);
                    event(new RoomStatusUpdated($physicalRoom->id, 'vacant_ready', 'Phòng trống do Noshow'));
                }
            }

            // 3. Nếu toàn bộ phòng trong booking noshow -> cập nhật trạng thái booking (SP2000)
            $booking = $room->booking;
            $remainingBooked = $booking->bookingRooms()->where('status', BookingRoom::STATUS_BOOKED)->count();

            // [Fix D] Cảnh báo nếu còn phòng khác trong booking chưa xử lý
            if ($remainingBooked > 0) {
                $warning = "Booking {$booking->code} còn {$remainingBooked} phòng chưa xử lý (vẫn ở trạng thái Đặt trước).";
            }

            $allNoShow = $booking->bookingRooms()->where('status', '!=', 4)->count() === 0;
            if ($allNoShow) {
                $noshowRegStatusId = RegistrationStatusMapper::codeFromLegacyCode(25);

                if ($chargeOption === 'no_charge') {
                    $booking->update([
                        'status' => 4, // Noshow
                        'registration_status_id' => $noshowRegStatusId ?? $booking->registration_status_id
                    ]);
                } else {
                    $booking->update([
                        'status' => 0, // Reservation
                        'registration_status_id' => $noshowRegStatusId ?? $booking->registration_status_id
                    ]);
                }
            }

            // 4. Lưu log noshow
            $reasonText = 'NightAudit No Show ' . ($chargeOption === 'no_charge' ? 'No Charge' : 'Charge Room');
            if ($userReason) {
                $reasonText .= ' - ' . $userReason;
            }

            NoshowLog::create([
                'booking_room_id' => $room->id,
                'noshow_date'     => $systemDate->toDateTimeString(),
                'noshow_time'     => now()->format('H:i'),
                'reason'          => $reasonText,
                'status'          => 4,
                'username'        => $username,
                'shift'           => $shift,
            ]);

            // 5. Post phí noshow cho TẤT CẢ các đêm trong khoảng lưu trú
            // (Khác với Late Check-in chỉ tính 1 đêm — Full Noshow giải phóng phòng nên tính hết)
            if ($chargeOption !== 'no_charge') {
                $arrivalDate   = Carbon::parse($room->arrival_date)->startOfDay();
                $departureDate = Carbon::parse($room->departure_date)->startOfDay();
                $nightDate     = $arrivalDate->copy();

                while ($nightDate->lt($departureDate)) {
                    $this->postSingleNightCharge($room, $nightDate->copy(), $chargeOption, $username, $reasonText, true);

                    // Post dịch vụ bổ sung (extra bed, phụ thu...) cho từng đêm khi all_charged
                    if ($chargeOption === 'all_charged') {
                        $extraServices = BookingRoomService::where('booking_room_id', $room->id)
                            ->whereDate('service_date', $nightDate->toDateString())
                            ->where('service_code', '!=', 'RM')
                            ->where('is_posted', 0)
                            ->get();

                        foreach ($extraServices as $service) {
                            $this->postSetupServiceBill($room, $service, $username);
                        }
                    }

                    $nightDate->addDay();
                }
            }
        });

        return response()->json([
            'success' => true,
            'message' => 'Đã ghi nhận khách không đến và giải phóng phòng thành công.',
            'warning' => $warning,  // [Fix D] trả về warning nếu booking còn phòng chưa xử lý
        ]);
    }

    /**
     * POST: Sang ngày hệ thống (Night Audit)
     * POST /api/night-audit/run
     */
    public function runNightAudit(Request $request, NightAuditSnapshotService $snapshotService)
    {
        $request->validate([
            'occupied_to_dirty' => 'required|boolean',
            'empty_to_inspect'   => 'required|boolean',
        ]);

        $occupiedToDirty = (bool) $request->occupied_to_dirty;
        $emptyToInspect   = (bool) $request->empty_to_inspect;

        $systemDate = $this->getSystemDate();
        $nextDate   = $systemDate->copy()->addDay();
        $username   = Auth::user()?->username ?: (Auth::user()?->name ?: 'system');
        $shift      = $this->getSystemShift();

        $settings = HotelSetting::first();

        // 1. Kiểm tra chống chạy đồng thời (Concurrency check & Stale lease recovery)
        $activeRun = NightAuditRun::where('status', 'running')->latest('id')->first();
        if ($activeRun) {
            if ($activeRun->actual_started_at && $activeRun->actual_started_at->lt(now()->subMinutes(15))) {
                $activeRun->update([
                    'status'             => 'recovery_required',
                    'error_code'         => 'STALE_LEASE_TIMEOUT',
                    'error_message'      => 'Tiến trình sang ngày trước đó đã quá 15 phút không phản hồi và được chuyển sang trạng thái cần phục hồi.',
                    'actual_finished_at' => now(),
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'Tiến trình sang ngày đang được thực hiện bởi người dùng khác. Vui lòng chờ trong giây lát.',
                ], 409);
            }
        }

        if ($settings?->is_night_audit_running && (!$activeRun || $activeRun->status !== 'recovery_required')) {
            return response()->json([
                'success' => false,
                'message' => 'Hệ thống đang khóa để tiến hành sang ngày, vui lòng thử lại sau.',
            ], 409);
        }

        // 2. Kiểm tra Idempotency: tránh chạy lại nếu ngày này đã sang ngày thành công
        $existingSuccess = NightAuditRun::whereDate('source_system_date', $systemDate->toDateString())
            ->where('status', 'succeeded')
            ->first();
        if ($existingSuccess && !$request->boolean('force_rerun')) {
            return response()->json([
                'success' => false,
                'message' => "Ngày hệ thống {$systemDate->format('d/m/Y')} đã được sang ngày thành công trước đó (Run #{$existingSuccess->id}).",
                'run_id'  => $existingSuccess->id,
            ], 409);
        }

        // Khóa cờ hệ thống
        if ($settings) {
            $settings->update(['is_night_audit_running' => true]);
        }
        $startedAtMs = (int) (microtime(true) * 1000);
        event(new NightAuditUpdated('started', 'Hệ thống đang tiến hành sang ngày mới...', [
            'username'    => $username,
            'source_date' => $systemDate->toDateString(),
            'target_date' => $nextDate->toDateString(),
            'started_at'  => $startedAtMs,
        ]));

        // 3. Tạo bản ghi Run TRƯỚC transaction (để đảm bảo run record tồn tại kể cả khi transaction nghiệp vụ rollback)
        $idempotencyKey = (string) ($request->header('X-Idempotency-Key')
            ?: ('audit_' . $systemDate->format('Ymd') . '_' . $nextDate->format('Ymd') . '_' . microtime(true)));

        $run = NightAuditRun::create([
            'source_system_date' => $systemDate->toDateString(),
            'target_system_date' => $nextDate->toDateString(),
            'actual_started_at'  => now(),
            'shift'              => $shift,
            'username'           => $username,
            'status'             => 'running',
            'idempotency_key'    => $idempotencyKey,
            'metadata'           => [
                'occupied_to_dirty' => $occupiedToDirty,
                'empty_to_inspect'  => $emptyToInspect,
            ],
        ]);

        // Tạo 13 bước trong Step log để theo dõi tiến độ thật
        $stepDefinitions = [
            ['code' => 'PRE_CHECK',              'name' => 'Kiểm tra điều kiện phòng đến/đi',          'order' => 1],
            ['code' => 'LOCK_AND_VERIFY',        'name' => 'Khóa tiến trình & Xác thực ngày đóng',     'order' => 2],
            ['code' => 'POST_BILLS',             'name' => 'Tự động post tiền phòng & dịch vụ đêm',    'order' => 3],
            ['code' => 'SNAPSHOT_SP7000',        'name' => 'Lưu Snapshot Năng suất đại lý (SP7000)',   'order' => 4],
            ['code' => 'SNAPSHOT_SP7001',        'name' => 'Lưu Snapshot Khách đang ở (SP7001)',      'order' => 5],
            ['code' => 'SNAPSHOT_SP7002',        'name' => 'Lưu Snapshot KPI đại lý (SP7002)',         'order' => 6],
            ['code' => 'SNAPSHOT_SP7003',        'name' => 'Lưu Snapshot Dự báo kinh doanh (SP7003)',  'order' => 7],
            ['code' => 'SNAPSHOT_SP7004',        'name' => 'Lưu Snapshot Chi tiết dự báo (SP7004)',    'order' => 8],
            ['code' => 'SNAPSHOT_SP7005',        'name' => 'Lưu Snapshot Thống kê loại phòng (SP7005)','order' => 9],
            ['code' => 'VERIFY_INVARIANTS',      'name' => 'Kiểm tra đối chiếu toàn vẹn dữ liệu',     'order' => 10],
            ['code' => 'UPDATE_ROOMS_AND_LOCKS', 'name' => 'Cập nhật trạng thái phòng & khóa bảo trì', 'order' => 11],
            ['code' => 'ROLL_SYSTEM_DATE',       'name' => 'Chuyển ngày hệ thống mới',                 'order' => 12],
            ['code' => 'FINALIZE',               'name' => 'Hoàn tất & Giải phóng khóa',               'order' => 13],
        ];

        $steps = [];
        foreach ($stepDefinitions as $def) {
            $steps[$def['code']] = $snapshotService->createStep($run, $def['code'], $def['name'], $def['order']);
        }

        $currentStepCode = 'PRE_CHECK';
        $skippedLocks = [];

        try {
            DB::transaction(function () use (
                $systemDate,
                $nextDate,
                $username,
                $shift,
                $occupiedToDirty,
                $emptyToInspect,
                $run,
                $snapshotService,
                &$steps,
                &$currentStepCode,
                &$skippedLocks
            ) {
                // Bước 1: Pre-checks
                $currentStepCode = 'PRE_CHECK';
                $snapshotService->markStepRunning($steps['PRE_CHECK']);

                $pendingCheckIns = BookingRoom::with(['booking', 'guests.guest'])
                    ->whereDate('arrival_date', '<=', $systemDate->toDateString())
                    ->stayOnly()
                    ->where('status', BookingRoom::STATUS_BOOKED)
                    ->where('status', '!=', BookingRoom::STATUS_MOVED)
                    ->whereNotNull('room_number')
                    ->where('room_number', '!=', '')
                    ->whereRaw("LOWER(TRIM(room_number)) NOT IN ('chưa gán', 'chua gan')")
                    ->get();

                $pendingCheckOuts = BookingRoom::with(['booking', 'guests.guest'])
                    ->whereDate('departure_date', '<=', $systemDate->toDateString())
                    ->stayOnly()
                    ->where('status', BookingRoom::STATUS_CHECKED_IN)
                    ->where('status', '!=', BookingRoom::STATUS_MOVED)
                    ->whereNotNull('room_number')
                    ->where('room_number', '!=', '')
                    ->whereRaw("LOWER(TRIM(room_number)) NOT IN ('chưa gán', 'chua gan')")
                    ->get();

                if ($pendingCheckIns->isNotEmpty() || $pendingCheckOuts->isNotEmpty()) {
                    $inCount = $pendingCheckIns->count();
                    $outCount = $pendingCheckOuts->count();
                    $errorData = [
                        'type' => 'PRE_CHECK_FAILED',
                        'failed_step' => 'PRE_CHECK',
                        'pending_checkins_count' => $inCount,
                        'pending_checkouts_count' => $outCount,
                        'pending_checkins' => $pendingCheckIns->map(fn($r) => [
                            'id' => $r->id,
                            'room_number' => $r->room_number,
                            'booking_code' => $r->booking?->code,
                            'guest_name' => $r->guests->first()?->guest?->full_name ?: ($r->booking?->booking_name ?: 'Khách'),
                            'arrival_date' => Carbon::parse($r->arrival_date)->format('d/m/Y'),
                        ])->values(),
                        'pending_checkouts' => $pendingCheckOuts->map(fn($r) => [
                            'id' => $r->id,
                            'room_number' => $r->room_number,
                            'booking_code' => $r->booking?->code,
                            'guest_name' => $r->guests->first()?->guest?->full_name ?: ($r->booking?->booking_name ?: 'Khách'),
                            'departure_date' => Carbon::parse($r->departure_date)->format('d/m/Y'),
                        ])->values(),
                        'hint' => 'Vui lòng kiểm tra và xử lý các phòng trên tại màn hình Sơ đồ phòng hoặc Đặt phòng (nhận phòng, trả phòng hoặc đánh dấu Noshow) trước khi thực hiện sang ngày.',
                    ];

                    $msgParts = [];
                    if ($inCount > 0) $msgParts[] = "{$inCount} phòng chưa check-in";
                    if ($outCount > 0) $msgParts[] = "{$outCount} phòng chưa check-out";
                    $msg = "Không thể sang ngày vì vẫn còn " . implode(' và ', $msgParts) . ".";

                    $ex = new \RuntimeException($msg);
                    $ex->errorDetails = $errorData;
                    throw $ex;
                }
                $snapshotService->markStepSucceeded($steps['PRE_CHECK'], 0, ['pending_checkins' => 0, 'pending_checkouts' => 0]);

                // Bước 2: Lock & Verify System Date
                $currentStepCode = 'LOCK_AND_VERIFY';
                $snapshotService->markStepRunning($steps['LOCK_AND_VERIFY']);

                $latestRoll = SystemDateRoll::orderBy('id', 'desc')->lockForUpdate()->first();
                $actualCurrentDate = $latestRoll
                    ? Carbon::parse($latestRoll->system_date)->startOfDay()
                    : now()->timezone('Asia/Ho_Chi_Minh')->startOfDay();

                if ($actualCurrentDate->toDateString() !== $systemDate->toDateString()) {
                    throw new \RuntimeException("Ngày hệ thống đã bị thay đổi bởi tiến trình khác (Hiện tại: {$actualCurrentDate->toDateString()}, Yêu cầu: {$systemDate->toDateString()}).");
                }
                $snapshotService->markStepSucceeded($steps['LOCK_AND_VERIFY'], 1, ['system_date' => $systemDate->toDateString()]);

                // Bước 3: Tự động post tiền phòng + các dịch vụ tự động cho phòng đang ở
                $currentStepCode = 'POST_BILLS';
                $snapshotService->markStepRunning($steps['POST_BILLS']);

                $inhouseRooms = BookingRoom::where('status', BookingRoom::STATUS_CHECKED_IN)
                    ->stayOnly()
                    ->where('status', '!=', BookingRoom::STATUS_MOVED)
                    ->get();
                $postedBillsCount = 0;

                foreach ($inhouseRooms as $targetRoom) {
                    $expectedRoomNight = $this->roomNightFlag($targetRoom);
                    $hasStandardRM = false;
                    $existingRMBills = ServiceBill::where('RegisterId1', $targetRoom->booking_id)
                        ->where('RentalRoomId1', $targetRoom->id)
                        ->where('ServiceId', 'RM')
                        ->whereDate('Date', $systemDate->toDateString())
                        ->where('Edit', 0)
                        ->pluck('Ma');

                    if ($existingRMBills->isNotEmpty()) {
                        $hasStandardRM = RoomNightBill::whereIn('bill_id', $existingRMBills)
                            ->where('is_room_night', $expectedRoomNight)
                            ->exists();
                    }

                    if (!$hasStandardRM) {
                        $this->postSingleNightCharge($targetRoom, $systemDate, 'room_only', $username, 'Tự động post tiền phòng - Sang ngày');
                        $postedBillsCount++;
                    }

                    $autoServices = BookingRoomService::where('booking_room_id', $targetRoom->id)
                        ->whereDate('service_date', $systemDate->toDateString())
                        ->where('is_posted', 0)
                        ->where('service_code', '!=', 'RM')
                        ->get();

                    foreach ($autoServices as $service) {
                        $this->postSetupServiceBill($targetRoom, $service, $username);
                        $postedBillsCount++;
                    }
                }
                $snapshotService->markStepSucceeded($steps['POST_BILLS'], $postedBillsCount, [
                    'inhouse_rooms' => $inhouseRooms->count(),
                    'posted_bills'  => $postedBillsCount,
                ]);

                // Bước 4: Snapshot SP7000 (Agency Productivity)
                $currentStepCode = 'SNAPSHOT_SP7000';
                $snapshotService->markStepRunning($steps['SNAPSHOT_SP7000']);
                $sp7000Rows = $snapshotService->captureAgencyProductivity($systemDate, $run);
                $snapshotService->markStepSucceeded($steps['SNAPSHOT_SP7000'], $sp7000Rows);

                // Bước 5: Snapshot SP7001 (Inhouse Guests)
                $currentStepCode = 'SNAPSHOT_SP7001';
                $snapshotService->markStepRunning($steps['SNAPSHOT_SP7001']);
                $sp7001Rows = $snapshotService->captureInhouse($systemDate, $run);
                $snapshotService->markStepSucceeded($steps['SNAPSHOT_SP7001'], $sp7001Rows);

                // Bước 6: Snapshot SP7002 (KPI Agency - Bỏ qua có ghi nhận do thiếu producer nguồn)
                $currentStepCode = 'SNAPSHOT_SP7002';
                $snapshotService->markStepSkipped(
                    $steps['SNAPSHOT_SP7002'],
                    'Chưa cấu hình công thức nguồn (Không có procedure tạo dữ liệu SP7002 trong tài liệu SQL Server của khách)'
                );

                // Bước 7: Snapshot SP7003 (Room Sales Forecast)
                $currentStepCode = 'SNAPSHOT_SP7003';
                $snapshotService->markStepRunning($steps['SNAPSHOT_SP7003']);
                $sp7003Rows = $snapshotService->captureRoomSalesForecast($systemDate, $run);
                $snapshotService->markStepSucceeded($steps['SNAPSHOT_SP7003'], $sp7003Rows);

                // Bước 8: Snapshot SP7004 (Room Sales Forecast Detail - Bỏ qua có ghi nhận do thiếu mapping)
                $currentStepCode = 'SNAPSHOT_SP7004';
                $snapshotService->markStepSkipped(
                    $steps['SNAPSHOT_SP7004'],
                    'Chưa cấu hình công thức nguồn (Chưa có mapping service-code và procedure ghi dữ liệu SP7004)'
                );

                // Bước 9: Snapshot SP7005 (Room Type Statistics)
                $currentStepCode = 'SNAPSHOT_SP7005';
                $snapshotService->markStepRunning($steps['SNAPSHOT_SP7005']);
                $sp7005Rows = $snapshotService->captureRoomType($systemDate, $run);
                $snapshotService->markStepSucceeded($steps['SNAPSHOT_SP7005'], $sp7005Rows);

                // Bước 10: Invariants Verification
                $currentStepCode = 'VERIFY_INVARIANTS';
                $snapshotService->markStepRunning($steps['VERIFY_INVARIANTS']);
                $verification = $snapshotService->verifyInvariants($systemDate, $run);
                $snapshotService->markStepSucceeded($steps['VERIFY_INVARIANTS'], 1, $verification);

                // Bước 11: Cập nhật trạng thái hiển thị sơ đồ phòng & xử lý khóa phòng
                $currentStepCode = 'UPDATE_ROOMS_AND_LOCKS';
                $snapshotService->markStepRunning($steps['UPDATE_ROOMS_AND_LOCKS']);

                if ($occupiedToDirty) {
                    $occupiedNumbers = BookingRoom::where('status', BookingRoom::STATUS_CHECKED_IN)
                        ->stayOnly()
                        ->where('status', '!=', BookingRoom::STATUS_MOVED)
                        ->whereNotNull('room_number')
                        ->pluck('room_number');

                    Room::physical()->whereIn('room_number', $occupiedNumbers)
                        ->whereNotIn('room_status_code', ['ooo', 'oos'])
                        ->update(['room_status_code' => 'occupied_dirty']);
                }

                if ($emptyToInspect) {
                    $occupiedNumbers = BookingRoom::where('status', BookingRoom::STATUS_CHECKED_IN)
                        ->stayOnly()
                        ->where('status', '!=', BookingRoom::STATUS_MOVED)
                        ->whereNotNull('room_number')
                        ->pluck('room_number');

                    Room::physical()->whereNotIn('room_number', $occupiedNumbers)
                        ->whereIn('room_status_code', ['vacant_ready'])
                        ->update(['room_status_code' => 'vacant_clean']);
                }

                // Xử lý mở phòng hết hạn khóa
                $expiredLocks = RoomLock::where('is_active', 1)
                    ->whereHas('room', fn ($room) => $room->physical())
                    ->whereDate('end_date', '<=', $systemDate->toDateString())
                    ->get();

                foreach ($expiredLocks as $lock) {
                    $lock->update([
                        'status'          => 'Done',
                        'is_active'       => 2,
                        'unlocked_at'     => now(),
                        'unlock_username' => 'system',
                    ]);

                    Room::where('room_number', $lock->room_number)->update([
                        'room_status_code' => 'vacant_dirty'
                    ]);
                    event(new RoomStatusUpdated($lock->room->id ?? 0, 'vacant_dirty', 'Phòng tự động mở khóa bảo trì'));
                }

                // Xử lý kích hoạt lịch khóa mới
                $startingLocks = RoomLock::where('is_active', 1)
                    ->whereHas('room', fn ($room) => $room->physical())
                    ->whereDate('start_date', '<=', $nextDate->toDateString())
                    ->where('status', 'New')
                    ->get();

                foreach ($startingLocks as $lock) {
                    $hasInhouse = BookingRoom::where('room_number', $lock->room_number)
                        ->stayOnly()
                        ->where('status', BookingRoom::STATUS_CHECKED_IN)
                        ->exists();

                    if ($hasInhouse) {
                        $skippedLocks[] = [
                            'room_number' => $lock->room_number,
                            'lock_type'   => $lock->lock_type,
                            'reason'      => 'Phòng đang có khách, không thể khóa tự động',
                        ];
                        continue;
                    }

                    $lock->update(['status' => 'Active']);
                    $lockCode = $lock->lock_type === 'OOS' ? 'oos' : 'ooo';
                    Room::where('room_number', $lock->room_number)->update([
                        'room_status_code' => $lockCode
                    ]);
                    event(new RoomStatusUpdated($lock->room->id ?? 0, $lockCode, 'Phòng tự động khóa bảo trì'));
                }
                $snapshotService->markStepSucceeded($steps['UPDATE_ROOMS_AND_LOCKS'], count($expiredLocks) + count($startingLocks));

                // Bước 12: Chuyển ngày hệ thống mới (SystemDateRoll)
                $currentStepCode = 'ROLL_SYSTEM_DATE';
                $snapshotService->markStepRunning($steps['ROLL_SYSTEM_DATE']);

                SystemDateRoll::create([
                    'system_date' => $nextDate->startOfDay()->toDateTimeString(),
                    'actual_date' => now()->timezone('Asia/Ho_Chi_Minh')->toDateTimeString(),
                    'shift'       => $shift,
                    'username'    => $username,
                ]);
                $snapshotService->markStepSucceeded($steps['ROLL_SYSTEM_DATE'], 1, ['new_system_date' => $nextDate->toDateString()]);

                // Bước 13: Hoàn tất
                $currentStepCode = 'FINALIZE';
                $snapshotService->markStepSucceeded($steps['FINALIZE'], 1);
            });

            // Sau commit thành công: đánh dấu Run thành công
            $run->update([
                'status'             => 'succeeded',
                'actual_finished_at' => now(),
            ]);

            event(new NightAuditUpdated('completed', 'Chuyển ngày hệ thống thành công sang: ' . $nextDate->toDateString(), [
                'username'    => $username,
                'source_date' => $systemDate->toDateString(),
                'target_date' => $nextDate->toDateString(),
                'run_id'      => $run->id,
                'started_at'  => $startedAtMs,
            ]));

            return response()->json([
                'success'           => true,
                'run_id'            => $run->id,
                'started_at'        => $startedAtMs,
                'source_date'       => $systemDate->toDateString(),
                'target_date'       => $nextDate->toDateString(),
                'status'            => 'succeeded',
                'message'           => 'Chuyển ngày hệ thống thành công sang ' . $nextDate->toDateString(),
                'steps'             => $run->steps()->get(),
                'skipped_locks'     => $skippedLocks,
                'skipped_snapshots' => [
                    'SP7002' => 'Chưa cấu hình công thức nguồn (Chờ xác nhận logic nghiệp vụ từ khách)',
                    'SP7004' => 'Chưa cấu hình công thức nguồn (Chờ xác nhận mapping service-code từ khách)',
                ],
            ]);

        } catch (\Throwable $e) {
            // Ghi nhận lỗi cho step hiện tại và run bên ngoài transaction
            if (isset($steps[$currentStepCode])) {
                $snapshotService->markStepFailed($steps[$currentStepCode], $e->getMessage());
            }

            $run->update([
                'status'             => 'failed',
                'actual_finished_at' => now(),
                'error_code'         => 'NIGHT_AUDIT_ERROR',
                'error_message'      => $e->getMessage(),
            ]);

            $errorDetails = property_exists($e, 'errorDetails') ? $e->errorDetails : [
                'type'              => 'SYSTEM_ERROR',
                'failed_step'       => $currentStepCode,
                'technical_message' => $e->getMessage(),
                'hint'              => 'Hệ thống đã tự động Rollback 100% dữ liệu về trạng thái an toàn. Vui lòng kiểm tra lại dữ liệu hoặc liên hệ bộ phận kỹ thuật.',
            ];

            event(new NightAuditUpdated('failed', 'Sang ngày thất bại: ' . $e->getMessage(), [
                'username'      => $username,
                'failed_step'   => $currentStepCode,
                'error_message' => $e->getMessage(),
                'error_details' => $errorDetails,
                'rollback_done' => true,
                'started_at'    => $startedAtMs ?? (int) (microtime(true) * 1000),
            ]));

            return response()->json([
                'success'       => false,
                'run_id'        => $run->id,
                'failed_step'   => $currentStepCode,
                'message'       => 'Lỗi khi thực hiện sang ngày tại bước [' . $currentStepCode . ']: ' . $e->getMessage(),
                'error_details' => $errorDetails,
                'rollback_done' => true,
            ], 500);

        } finally {
            if ($settings) {
                $settings->update(['is_night_audit_running' => false]);
            }
        }
    }

    /**
     * POST: Gia hạn đêm ở (Extend Stay) cho phòng đang đi [Bug B]
     * POST /api/night-audit/extend-stay
     */
    public function extendStay(Request $request)
    {
        $request->validate([
            'booking_room_id' => 'required|string|exists:booking_rooms,id',
            'nights'          => 'required|integer|min:1|max:30',
        ]);

        $room   = BookingRoom::with('booking')->findOrFail($request->booking_room_id);
        $nights = (int) $request->nights;

        if ($room->isVirtual()) {
            return response()->json(['success' => false, 'code' => 'virtual_room', 'message' => 'Folio phòng ảo không hỗ trợ gia hạn lưu trú.'], 422);
        }

        if ($room->status !== BookingRoom::STATUS_CHECKED_IN) {
            return response()->json(['success' => false, 'message' => 'Chỉ gia hạn được phòng đang ở (In-house).'], 422);
        }

        $oldDeparture = Carbon::parse($room->departure_date);
        $newDeparture = $oldDeparture->copy()->addDays($nights);

        DB::transaction(function () use ($room, $newDeparture, $nights) {
            // Cập nhật departure_date của phòng thuê
            $room->update([
                'departure_date'        => $newDeparture->toDateString(),
                'actual_departure_date' => $newDeparture->toDateString(),
                'num_of_days'           => $room->num_of_days + $nights,
            ]);

            // Cập nhật booking.departure_date nếu đây là phòng có ngày đi muộn nhất
            $booking = $room->booking;
            if ($booking) {
                $maxDep = $booking->bookingRooms()
                    ->where('status', '!=', 4)
                    ->max('departure_date');
                if ($maxDep && Carbon::parse($maxDep)->gt(Carbon::parse($booking->departure_date))) {
                    $booking->update(['departure_date' => $maxDep]);
                }
            }
        });

        return response()->json([
            'success'        => true,
            'message'        => "Gia hạn thành công {$nights} đêm. Ngày đi mới: " . $newDeparture->toDateString(),
            'departure_date' => $newDeparture->toDateString(),
        ]);
    }

    /**
     * Helper: Post tiền phòng cho 1 đêm
     */
    private function postSingleNightCharge($room, $date, $chargeOption, $user, $reason, $isNoshow = false)
    {
        $booking = $room->booking;
        $primaryGuest = $room->guests()->where('is_primary', 1)->with('guest')->first()
                        ?: $room->guests()->with('guest')->first();
        $guestId   = $primaryGuest?->guest_id;
        $guestName = $primaryGuest?->guest?->full_name ?: ($booking?->booking_name ?: 'Khách lẻ');
        $sendRoomRateToMaster = (bool) ($booking?->is_master_room_rate);
        $currentGuestName = $sendRoomRateToMaster ? ($booking?->booking_name ?: 'Khách lẻ') : $guestName;

        // 1. Xác định giá phòng
        $rate = 0;
        $rmService = BookingRoomService::where('booking_room_id', $room->id)
            ->where('service_code', BookingRoomService::CODE_ROOM ?? 'RM')
            ->whereDate('service_date', $date->toDateString())
            ->first();

        if ($rmService && (float)$rmService->rate > 0) {
            $rate = (float)$rmService->rate;
        } elseif ((float)$room->rate > 0) {
            $rate = (float)$room->rate;
        } elseif ((float)$room->base_price > 0) {
            $rate = (float)$room->base_price;
        }

        // Tra cứu giá từ rate code
        if ($rate <= 0 && !empty($room->rate_code)) {
            $plan = \App\Models\RoomRatePlan::where('RateCode', $room->rate_code)->first();
            if ($plan && is_array($plan->Period)) {
                foreach ($plan->Period as $row) {
                    if (isset($row['roomClassId']) && (string)$row['roomClassId'] === (string)$room->room_class_id) {
                        $rate = (float)($row['price'] ?? 0);
                        if ($rate > 0) break;
                    }
                }
            }
        }

        // Tra cứu giá chuẩn
        if ($rate <= 0 && !empty($room->room_class_id)) {
            $stdRate = \App\Models\StandardRate::where('room_class_id', $room->room_class_id)->value('room_price');
            if ($stdRate && (float)$stdRate > 0) {
                $rate = (float)$stdRate;
            }
        }

        $totalAmount = $rate;

        // 2. Ăn sáng (chỉ tính nếu không phải noshow và (all_charged hoặc phòng có bao gồm ăn sáng))
        $breakfastAmount = 0;
        $breakfastRate = 0;
        $breakfastAdults = max(1, (int) $room->adults);
        $setting = HotelSetting::first();
        if (!$isNoshow && $room->breakfast) {
            $breakfastRate   = (float)($setting?->breakfast_adult_rate ?? 0);
            $breakfastAmount = $breakfastRate * $breakfastAdults;
        }

        $roomService = HotelService::where('code', 'RM')->first();
        $breakfastService = HotelService::where('code', 'BF')->first();
        $roomTaxProfile = HotelService::taxProfile($roomService);
        $breakfastTaxProfile = HotelService::taxProfile($breakfastService);
        $description = $roomService
            ? $roomService->billDescription($room->room_number, 'FO')
            : 'Dịch vụ phòng nghỉ' . ($room->room_number ? ' - Phòng ' . $room->room_number : '');
        $breakfastDescription = $breakfastService
            ? $breakfastService->billDescription($room->room_number, 'FO')
            : 'Tiền ăn sáng người lớn' . ($room->room_number ? ' - Phòng ' . $room->room_number : '');
        $finalReason = $description;

        // 3. Tạo ServiceBill
        $bill = ServiceBill::create([
            'Date'               => $date->startOfDay()->toDateTimeString(),
            'OpenTime'           => now()->format('H:i'),
            'Guest'              => $currentGuestName,
            'DepartmentId'       => 'FO',
            'ServiceId'          => 'RM',
            'DescriptionServive' => $finalReason,
            'Quantity'           => 1,
            'Amount'             => $totalAmount,
            'ServiceCharge'      => $roomTaxProfile['service_charge'],
            'SpecialTax'         => $roomTaxProfile['special_tax'],
            'Tax'                => $roomTaxProfile['tax'],
            'Currency'           => 'VND',
            'Exchange'           => 1,
            'Edit'               => 0,
            'Folio'              => '1',
            'RegisterId1'        => $booking?->id,
            'RentalRoomId1'      => $room->id,
            'CustomerId1'        => $guestId,
            'CompanyId1'         => $booking?->company_id,
            'RegisterID2'        => $booking?->id,
            // Noshow: set RentalRoomId2=null để frontend gom về Master Folio
            // (isMasterBillRecord: bill không có RentalRoomId2 → luôn vào master)
            'RentalRoomId2'      => ($isNoshow || $sendRoomRateToMaster) ? null : $room->id,
            'CustomerId2'        => $sendRoomRateToMaster ? null : $guestId,
            'CompanyId2'         => $booking?->company_id,
            'Username'           => $user,
            'Status'             => 1,
            'Outlet'             => 'FO',
            'Year'               => $date->year,
            'Month'              => $date->month,
            'Day'                => $date->day,
            'CreatedUser'        => $user,
            'CreatedDate'        => now(),
            'CreatedHour'        => now()->format('H:i'),
        ]);

        // 4. Chi tiết ServiceBillDetail
        if ($room->breakfast && $breakfastAmount > 0) {
            // Dòng RM gốc
            $rmBreakdown = TaxBreakdownService::breakdown($totalAmount, $roomTaxProfile['service_charge'], $roomTaxProfile['special_tax'], $roomTaxProfile['tax']);
            ServiceBillDetail::create([
                'BillServiceId'            => $bill->Ma,
                'Ma'                       => 1,
                'DepartmentId'             => 'FO',
                'ServiceId'                => 'RM',
                'DescriptionServive'       => $reason ?: $description,
                'OriginalRate'             => $rmBreakdown['original_rate'],
                'ServiceCharge'            => $roomTaxProfile['service_charge'],
                'SpecialTax'               => $roomTaxProfile['special_tax'],
                'Tax'                      => $roomTaxProfile['tax'],
                'ServiceChargeAmount'      => $rmBreakdown['service_charge_amount'],
                'SpecialTaxAmount'         => $rmBreakdown['special_tax_amount'],
                'TaxAmount'                => $rmBreakdown['tax_amount'],
                'Amount'                   => $totalAmount,
                'Currency'                 => 'VND',
                'Exchange'                 => 1,
                'DetailBillOriginalAmount' => $rmBreakdown['net_total'],
                'OriginalAmount'           => $rmBreakdown['net_total'],
            ]);

            // Dòng BF ăn sáng
            $bfBreakdown = TaxBreakdownService::breakdown($breakfastAmount, $breakfastTaxProfile['service_charge'], $breakfastTaxProfile['special_tax'], $breakfastTaxProfile['tax'], $breakfastAdults);
            ServiceBillDetail::create([
                'BillServiceId'            => $bill->Ma,
                'Ma'                       => 2,
                'DepartmentId'             => 'FO',
                'ServiceId'                => 'BF',
                'DescriptionServive'       => $breakfastDescription,
                // SP3001: đơn giá 1 suất × số lượng khách = thành tiền.
                'OriginalRate'             => $bfBreakdown['original_rate'],
                'Quantity'                 => $breakfastAdults,
                'ServiceCharge'            => $breakfastTaxProfile['service_charge'],
                'SpecialTax'               => $breakfastTaxProfile['special_tax'],
                'Tax'                      => $breakfastTaxProfile['tax'],
                'ServiceChargeAmount'      => $bfBreakdown['service_charge_amount'],
                'SpecialTaxAmount'         => $bfBreakdown['special_tax_amount'],
                'TaxAmount'                => $bfBreakdown['tax_amount'],
                'Amount'                   => $breakfastAmount,
                'Currency'                 => 'VND',
                'Exchange'                 => 1,
                'DetailBillOriginalAmount' => $bfBreakdown['net_total'],
                'OriginalAmount'           => $bfBreakdown['net_total'],
            ]);

            // Dòng khấu trừ RM
            $rmMinusBreakdown = TaxBreakdownService::breakdown(-$breakfastAmount, $roomTaxProfile['service_charge'], $roomTaxProfile['special_tax'], $roomTaxProfile['tax']);
            ServiceBillDetail::create([
                'BillServiceId'            => $bill->Ma,
                'Ma'                       => 3,
                'DepartmentId'             => 'FO',
                'ServiceId'                => 'RM',
                'DescriptionServive'       => 'Trừ ' . $breakfastDescription,
                'OriginalRate'             => $rmMinusBreakdown['original_rate'],
                'ServiceCharge'            => $roomTaxProfile['service_charge'],
                'SpecialTax'               => $roomTaxProfile['special_tax'],
                'Tax'                      => $roomTaxProfile['tax'],
                'ServiceChargeAmount'      => $rmMinusBreakdown['service_charge_amount'],
                'SpecialTaxAmount'         => $rmMinusBreakdown['special_tax_amount'],
                'TaxAmount'                => $rmMinusBreakdown['tax_amount'],
                'Amount'                   => -$breakfastAmount,
                'Currency'                 => 'VND',
                'Exchange'                 => 1,
                'DetailBillOriginalAmount' => $rmMinusBreakdown['net_total'],
                'OriginalAmount'           => $rmMinusBreakdown['net_total'],
            ]);
        } else {
            $singleBreakdown = TaxBreakdownService::breakdown($totalAmount, $roomTaxProfile['service_charge'], $roomTaxProfile['special_tax'], $roomTaxProfile['tax']);
            ServiceBillDetail::create([
                'BillServiceId'            => $bill->Ma,
                'Ma'                       => 1,
                'DepartmentId'             => 'FO',
                'ServiceId'                => 'RM',
                'DescriptionServive'       => $reason ?: $description,
                'OriginalRate'             => $singleBreakdown['original_rate'],
                'ServiceCharge'            => $roomTaxProfile['service_charge'],
                'SpecialTax'               => $roomTaxProfile['special_tax'],
                'Tax'                      => $roomTaxProfile['tax'],
                'ServiceChargeAmount'      => $singleBreakdown['service_charge_amount'],
                'SpecialTaxAmount'         => $singleBreakdown['special_tax_amount'],
                'TaxAmount'                => $singleBreakdown['tax_amount'],
                'Amount'                   => $totalAmount,
                'Currency'                 => 'VND',
                'Exchange'                 => 1,
                'DetailBillOriginalAmount' => $singleBreakdown['net_total'],
                'OriginalAmount'           => $singleBreakdown['net_total'],
            ]);
        }

        // 5. RoomNightBill
        RoomNightBill::create([
            'bill_id'          => $bill->Ma,
            'adult'            => max(1, (int)$room->adults),
            'child'            => (int)$room->children_qty,
            'is_room_night'    => $this->roomNightFlag($room, $booking),
            'breakfast_amount' => $breakfastAmount,
            'extrabed_amount'  => (float)($room->extra_bed_rate ?? 0) * (int)($room->extra_bed_qty ?? 0),
            'date'             => $date->toDateString(),
            'room'             => $room->room_number,
            'room_type_id'     => $room->room_class_id,
            'breakfast'        => $room->breakfast ? max(1, (int)$room->adults) : 0,
            'extra_bed'        => (int)($room->extra_bed_qty ?? 0),
            'rate_code'        => $room->rate_code,
            'rate'             => $rate,
        ]);

        // 6. Liên kết hoặc tạo trong booking_room_services
        if ($sendRoomRateToMaster) {
            BookingRoomService::where('booking_room_id', $room->id)
                ->where('service_bill_id', $bill->Ma)
                ->delete();
        } else {
            BookingRoomService::updateOrCreate(
                [
                    'booking_room_id' => $room->id,
                    'service_code'    => 'RM',
                    'service_date'    => $date->toDateString(),
                ],
                [
                    'service_name'           => BookingRoomService::catalogName(BookingRoomService::CODE_ROOM, 'Tiền phòng'),
                    'service_bill_id'        => $bill->Ma,
                    'service_bill_detail_no' => 1,
                    'quantity'               => 1,
                    'rate'                   => $rate,
                    'total_amount'           => $totalAmount,
                    'department'             => 'FO',
                    'note'                   => $reason ?: $description,
                    'tax'                    => $roomTaxProfile['tax'],
                    'service_charge'         => $roomTaxProfile['service_charge'],
                    'is_posted'              => 1,
                    'posted_at'              => now(),
                    'created_by'             => $user,
                ]
            );
        }
    }

    /**
     * Helper: Post các dịch vụ tự động đi kèm (Setup trước)
     */
    private function postSetupServiceBill($room, $service, $user)
    {
        $booking = $room->booking;
        $primaryGuest = $room->guests()->where('is_primary', 1)->with('guest')->first()
                        ?: $room->guests()->with('guest')->first();
        $guestId   = $primaryGuest?->guest_id;
        $guestName = $primaryGuest?->guest?->full_name ?: ($booking?->booking_name ?: 'Khách lẻ');

        $foService = HotelService::where('code', $service->service_code)->first();
        if (!$foService) return;

        $qty         = (float)$service->quantity;
        $rate        = (float)$service->rate;
        $totalAmount = $qty * $rate;
        $description = $this->setupServiceBillDescription($service, $foService, $room->room_number);
        $isRoomFolio = (int) $service->is_room === 1;

        $bill = ServiceBill::create([
            'Date'               => Carbon::parse($service->service_date)->startOfDay()->toDateTimeString(),
            'OpenTime'           => now()->format('H:i'),
            'Guest'              => $guestName,
            'DepartmentId'       => 'FO',
            'ServiceId'          => $foService->code,
            'DescriptionServive' => $description,
            'Quantity'           => $qty,
            'Amount'             => $totalAmount,
            'ServiceCharge'      => (float)($foService->service_charge ?? 0),
            'SpecialTax'         => (float)($foService->special_tax ?? 0),
            'Tax'                => (float)($foService->tax ?? 0),
            'Currency'           => 'VND',
            'Exchange'           => 1,
            'Edit'               => 0,
            'Folio'              => (string)$service->folio,
            'RegisterId1'        => $booking?->id,
            'RentalRoomId1'      => $room->id,
            'CustomerId1'        => $guestId,
            'RegisterID2'        => $booking?->id,
            'RentalRoomId2'      => $isRoomFolio ? $room->id : null,
            'CustomerId2'        => $isRoomFolio ? $guestId : null,
            'CompanyId2'         => $booking?->company_id,
            'Username'           => $user,
            'Status'             => 1,
            'Outlet'             => 'FO',
            'Year'               => Carbon::parse($service->service_date)->year,
            'Month'              => Carbon::parse($service->service_date)->month,
            'Day'                => Carbon::parse($service->service_date)->day,
            'CreatedUser'        => $user,
            'CreatedDate'        => now(),
            'CreatedHour'        => now()->format('H:i'),
        ]);

        $bd = TaxBreakdownService::breakdown($totalAmount, (float)($foService->service_charge ?? 0), (float)($foService->special_tax ?? 0), (float)($foService->tax ?? 0), $qty);
        ServiceBillDetail::create([
            'BillServiceId'            => $bill->Ma,
            'Ma'                       => 1,
            'DepartmentId'             => 'FO',
            'ServiceId'                => $foService->code,
            'DescriptionServive'       => $description,
            'OriginalRate'             => $bd['original_rate'],
            'Quantity'                 => $qty,
            'ServiceCharge'            => (float)($foService->service_charge ?? 0),
            'SpecialTax'               => (float)($foService->special_tax ?? 0),
            'Tax'                      => (float)($foService->tax ?? 0),
            'ServiceChargeAmount'      => $bd['service_charge_amount'],
            'SpecialTaxAmount'         => $bd['special_tax_amount'],
            'TaxAmount'                => $bd['tax_amount'],
            'Amount'                   => $totalAmount,
            'Currency'                 => 'VND',
            'Exchange'                 => 1,
            'DetailBillOriginalAmount' => $bd['net_total'],
            'OriginalAmount'           => $bd['net_total'],
        ]);

        $service->update([
            'service_bill_id'        => $bill->Ma,
            'service_bill_detail_no' => 1,
            'department'             => 'FO',
            'tax'                    => (float)($foService->tax ?? 0),
            'service_charge'         => (float)($foService->service_charge ?? 0),
            'is_posted'              => 1,
            'posted_at'              => now(),
        ]);
    }

    private function setupServiceBillDescription(
        BookingRoomService $service,
        HotelService $hotelService,
        ?string $roomNumber
    ): string {
        $childBreakfastCode = (string) (HotelConfig::where('name', 'Booking_BFChildSetServiceId')->value('value')
            ?: BookingRoomService::CODE_BF_CHILD);
        $note = trim((string) $service->note);

        if (strcasecmp((string) $service->service_code, $childBreakfastCode) === 0 && $note !== '') {
            return preg_replace(
                '/^Phụ thu ăn sáng trẻ em\s*:\s*/iu',
                'Phụ thu ăn sáng trẻ em - ',
                $note
            ) ?: $note;
        }

        return $hotelService->billDescription($roomNumber, 'FO');
    }

    /**
     * Day-use revenue is always posted, but occupancy is controlled by the
     * legacy IsRoomNightRoomDayUse setting. Late check-in rows keep is_day_use
     * false, so equal dates alone never flip this flag.
     */
    private function roomNightFlag(?BookingRoom $room, ?Booking $booking = null): int
    {
        $isDayUse = (bool) ($room?->is_day_use ?? false)
            || (bool) ($booking?->is_day_use ?? false);
        if (!$isDayUse) {
            return 1;
        }

        return (int) (HotelConfig::where('name', 'IsRoomNightRoomDayUse')->value('value') ?? '1') === 1
            ? 1
            : 0;
    }
}
