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
use App\Models\HotelService;
use App\Events\NightAuditUpdated;
use App\Events\RoomStatusUpdated;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

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

        // 1. Phòng cần check in nhưng chưa check in (arrival_date <= system_date và status = 0)
        $pendingCheckIns = BookingRoom::with(['booking', 'roomClass'])
            ->whereDate('arrival_date', '<=', $systemDate)
            ->where('status', BookingRoom::STATUS_BOOKED)
            ->get();

        // 2. Phòng có lịch check out hôm nay/trước đây nhưng vẫn ở trạng thái in-house (departure_date <= system_date và status = 1)
        $pendingCheckOuts = BookingRoom::with(['booking', 'roomClass'])
            ->whereDate('departure_date', '<=', $systemDate)
            ->where('status', BookingRoom::STATUS_CHECKED_IN)
            ->get();

        return response()->json([
            'success' => true,
            'data' => [
                'system_date' => $systemDate,
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
        if ($room->status !== BookingRoom::STATUS_BOOKED) {
            return response()->json(['success' => false, 'message' => 'Phòng không ở trạng thái Đặt trước để late check-in.'], 422);
        }

        $systemDate = $this->getSystemDate();
        $nextDate   = $systemDate->copy()->addDay();
        $username   = Auth::user()?->username ?? 'admin';
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
                $this->postSingleNightCharge($room, $systemDate, $chargeOption, $username, $reasonText);
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
        if ($room->status !== BookingRoom::STATUS_BOOKED) {
            return response()->json(['success' => false, 'message' => 'Phòng không ở trạng thái Đặt trước để noshow.'], 422);
        }

        $systemDate = $this->getSystemDate();
        $username   = Auth::user()?->username ?? 'admin';
        $shift      = $this->getSystemShift();

        DB::transaction(function () use ($room, $systemDate, $chargeOption, $userReason, $username, $shift) {
            // 1. Cập nhật trạng thái phòng thuê = 4 (Noshow)
            $room->update(['status' => 4]);

            // Cập nhật khách và trẻ em gán vào phòng
            BookingRoomGuest::where('booking_room_id', $room->id)->update(['status' => 4]);
            BookingChild::where('booking_room_id', $room->id)->update(['child_status' => 4]);

            // 2. Giải phóng phòng vật lý
            if ($room->room_number) {
                $physicalRoom = Room::where('room_number', $room->room_number)->first();
                if ($physicalRoom) {
                    $physicalRoom->update(['room_status_code' => 'vacant_ready']);
                    event(new RoomStatusUpdated($physicalRoom->id, 'vacant_ready', 'Phòng trống do Noshow'));
                }
            }

            // 3. Nếu toàn bộ phòng trong booking noshow -> update booking status = 4
            $booking = $room->booking;
            $allNoShow = $booking->bookingRooms()->where('status', '!=', 4)->count() === 0;

            // Nếu noshow có charge: giữ booking status = 1 (Reservation) để kế toán thanh toán
            if ($allNoShow && $chargeOption === 'no_charge') {
                $booking->update(['status' => 4]);
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

            // 5. Post phí noshow (tính cho toàn bộ đêm đã đặt)
            if ($chargeOption !== 'no_charge') {
                $current = Carbon::parse($room->arrival_date);
                $depDate = Carbon::parse($room->departure_date);
                while ($current->lt($depDate)) {
                    $this->postSingleNightCharge($room, $current, $chargeOption, $username, $reasonText);
                    $current->addDay();
                }
            }
        });

        return response()->json([
            'success' => true,
            'message' => 'Đã ghi nhận khách không đến và giải phóng phòng thành công.',
        ]);
    }

    /**
     * POST: Sang ngày hệ thống (Night Audit)
     * POST /api/night-audit/run
     */
    public function runNightAudit(Request $request)
    {
        $request->validate([
            'occupied_to_dirty' => 'required|boolean',
            'empty_to_inspect'   => 'required|boolean',
        ]);

        $occupiedToDirty = $request->occupied_to_dirty;
        $emptyToInspect   = $request->empty_to_inspect;

        $systemDate = $this->getSystemDate();
        $nextDate   = $systemDate->copy()->addDay();
        $username   = Auth::user()?->username ?? 'admin';
        $shift      = $this->getSystemShift();

        // 1. Kích hoạt flag khóa hệ thống
        $settings = HotelSetting::first();
        if ($settings) {
            $settings->update(['is_night_audit_running' => true]);
        }
        event(new NightAuditUpdated('started', 'Hệ thống đang tiến hành sang ngày mới...'));

        try {
            DB::transaction(function () use ($systemDate, $nextDate, $username, $shift, $occupiedToDirty, $emptyToInspect) {
                // 2. Kiểm tra lại điều kiện chặn
                $pendingCheckIns = BookingRoom::whereDate('arrival_date', '<=', $systemDate->toDateString())
                    ->where('status', BookingRoom::STATUS_BOOKED)
                    ->count();

                $pendingCheckOuts = BookingRoom::whereDate('departure_date', '<=', $systemDate->toDateString())
                    ->where('status', BookingRoom::STATUS_CHECKED_IN)
                    ->count();

                if ($pendingCheckIns > 0 || $pendingCheckOuts > 0) {
                    throw new \Exception('Không thể sang ngày vì vẫn còn phòng chưa check-in hoặc chưa check-out.');
                }

                // 3. Tự động post tiền phòng + các dịch vụ tự động cho phòng đang ở
                $inhouseRooms = BookingRoom::where('status', BookingRoom::STATUS_CHECKED_IN)->get();
                foreach ($inhouseRooms as $targetRoom) {
                    // Check xem ngày hôm nay đã post tiền phòng chưa (để tránh double post)
                    $existingRM = ServiceBill::where('RegisterId1', $targetRoom->booking_id)
                        ->where('RentalRoomId1', $targetRoom->id)
                        ->where('ServiceId', 'RM')
                        ->whereDate('Date', $systemDate->toDateString())
                        ->where('Edit', 0)
                        ->first();

                    if (!$existingRM) {
                        // Post RM cho đêm hiện tại
                        $this->postSingleNightCharge($targetRoom, $systemDate, 'room_only', $username, 'Tự động post tiền phòng - Sang ngày');
                    }

                    // Post các dịch vụ tự động đã set-up sẵn trong booking cho đêm nay (is_posted = 0)
                    $autoServices = BookingRoomService::where('booking_room_id', $targetRoom->id)
                        ->where('service_date', $systemDate->toDateString())
                        ->where('is_posted', 0)
                        ->where('service_code', '!=', 'RM')
                        ->get();

                    foreach ($autoServices as $service) {
                        $this->postSetupServiceBill($targetRoom, $service, $username);
                    }
                }

                // 4. Chuyển ngày hệ thống (Tạo SystemDateRoll mới)
                SystemDateRoll::create([
                    'system_date' => $nextDate->toDateTimeString(),
                    'actual_date' => now()->timezone('Asia/Ho_Chi_Minh')->toDateTimeString(),
                    'shift'       => $shift,
                    'username'    => $username,
                ]);

                // 5. Cập nhật trạng thái hiển thị sơ đồ phòng
                if ($occupiedToDirty) {
                    // Phòng đang ở -> dirty (occupied_dirty)
                    $occupiedNumbers = BookingRoom::where('status', BookingRoom::STATUS_CHECKED_IN)
                        ->whereNotNull('room_number')
                        ->pluck('room_number');

                    Room::whereIn('room_number', $occupiedNumbers)
                        ->whereNotIn('room_status_code', ['ooo', 'oos'])
                        ->update(['room_status_code' => 'occupied_dirty']);
                }

                if ($emptyToInspect) {
                    // Phòng trống sẵn sàng -> chờ kiểm tra (vacant_clean - Phòng sạch)
                    $occupiedNumbers = BookingRoom::where('status', BookingRoom::STATUS_CHECKED_IN)
                        ->whereNotNull('room_number')
                        ->pluck('room_number');

                    Room::whereNotIn('room_number', $occupiedNumbers)
                        ->whereIn('room_status_code', ['vacant_ready'])
                        ->update(['room_status_code' => 'vacant_clean']);
                }

                // 6. Xử lý phòng khóa (Room Locks)
                // A. Mở các phòng hết hạn khóa hôm nay
                $expiredLocks = RoomLock::where('is_active', 1)
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

                // B. Kích hoạt lịch khóa mới bắt đầu vào ngày hệ thống mới
                $startingLocks = RoomLock::where('is_active', 1)
                    ->whereDate('start_date', '<=', $nextDate->toDateString())
                    ->where('status', 'New')
                    ->get();

                foreach ($startingLocks as $lock) {
                    // Kiểm tra xem phòng có khách đang ở không
                    $hasInhouse = BookingRoom::where('room_number', $lock->room_number)
                        ->where('status', BookingRoom::STATUS_CHECKED_IN)
                        ->exists();

                    if ($hasInhouse) {
                        // Nếu có khách, giữ status là New (planned) và cảnh báo, không OOO vật lý
                        continue;
                    }

                    $lock->update(['status' => 'Active']);
                    $lockCode = $lock->lock_type === 'OOS' ? 'oos' : 'ooo';
                    Room::where('room_number', $lock->room_number)->update([
                        'room_status_code' => $lockCode
                    ]);
                    event(new RoomStatusUpdated($lock->room->id ?? 0, $lockCode, 'Phòng tự động khóa bảo trì'));
                }
            });

            // 7. Giải phóng khóa hệ thống thành công
            if ($settings) {
                $settings->update(['is_night_audit_running' => false]);
            }
            event(new NightAuditUpdated('completed', 'Chuyển ngày hệ thống thành công sang: ' . $nextDate->toDateString()));

            return response()->json([
                'success' => true,
                'message' => 'Chuyển ngày hệ thống thành công sang ' . $nextDate->toDateString(),
            ]);

        } catch (\Throwable $e) {
            // Rollback và giải phóng khóa hệ thống khi thất bại
            if ($settings) {
                $settings->update(['is_night_audit_running' => false]);
            }
            event(new NightAuditUpdated('failed', 'Sang ngày thất bại: ' . $e->getMessage()));

            return response()->json([
                'success' => false,
                'message' => 'Lỗi khi thực hiện sang ngày: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Helper: Post tiền phòng cho 1 đêm
     */
    private function postSingleNightCharge($room, $date, $chargeOption, $user, $reason)
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
            ->where('service_date', $date->toDateString())
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

        // 2. Ăn sáng (chỉ tính nếu all_charged hoặc phòng có bao gồm ăn sáng)
        $breakfastAmount = 0;
        $setting = HotelSetting::first();
        if ($room->breakfast) {
            $breakfastRate   = (float)($setting?->breakfast_adult_rate ?? 0);
            $breakfastAmount = $breakfastRate * max(1, (int)$room->adults);
        }

        $description = 'Dịch vụ phòng nghỉ' . ($room->room_number ? ' - Phòng ' . $room->room_number : '');
        $finalReason = $reason ?: $description;
        if ($reason && $room->room_number) {
            $finalReason .= ' - Phòng ' . $room->room_number;
        }

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
            'ServiceCharge'      => 0,
            'SpecialTax'         => 0,
            'Tax'                => 0,
            'Currency'           => 'VND',
            'Exchange'           => 1,
            'Edit'               => 0,
            'Folio'              => '1',
            'RegisterId1'        => $booking?->id,
            'RentalRoomId1'      => $room->id,
            'CustomerId1'        => $guestId,
            'CompanyId1'         => $booking?->company_id,
            'RegisterID2'        => $booking?->id,
            'RentalRoomId2'      => $room->id,
            'CustomerId2'        => $guestId,
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
            ServiceBillDetail::create([
                'BillServiceId'            => $bill->Ma,
                'Ma'                       => 1,
                'DepartmentId'             => 'FO',
                'ServiceId'                => 'RM',
                'DescriptionServive'       => $reason ?: $description,
                'OriginalRate'             => $rate,
                'Amount'                   => $totalAmount,
                'Currency'                 => 'VND',
                'Exchange'                 => 1,
                'DetailBillOriginalAmount' => $rate,
            ]);

            // Dòng BF ăn sáng
            ServiceBillDetail::create([
                'BillServiceId'            => $bill->Ma,
                'Ma'                       => 2,
                'DepartmentId'             => 'FO',
                'ServiceId'                => 'BF',
                'DescriptionServive'       => 'Tiền ăn sáng',
                'OriginalRate'             => $breakfastAmount,
                'Amount'                   => $breakfastAmount,
                'Currency'                 => 'VND',
                'Exchange'                 => 1,
                'DetailBillOriginalAmount' => $breakfastAmount,
            ]);

            // Dòng khấu trừ RM
            ServiceBillDetail::create([
                'BillServiceId'            => $bill->Ma,
                'Ma'                       => 3,
                'DepartmentId'             => 'FO',
                'ServiceId'                => 'RM',
                'DescriptionServive'       => 'Trừ tiền ăn sáng',
                'OriginalRate'             => -$breakfastAmount,
                'Amount'                   => -$breakfastAmount,
                'Currency'                 => 'VND',
                'Exchange'                 => 1,
                'DetailBillOriginalAmount' => -$breakfastAmount,
            ]);
        } else {
            ServiceBillDetail::create([
                'BillServiceId'            => $bill->Ma,
                'Ma'                       => 1,
                'DepartmentId'             => 'FO',
                'ServiceId'                => 'RM',
                'DescriptionServive'       => $reason ?: $description,
                'OriginalRate'             => $rate,
                'Amount'                   => $totalAmount,
                'Currency'                 => 'VND',
                'Exchange'                 => 1,
                'DetailBillOriginalAmount' => $rate,
            ]);
        }

        // 5. RoomNightBill
        RoomNightBill::create([
            'bill_id'          => $bill->Ma,
            'adult'            => max(1, (int)$room->adults),
            'child'            => (int)$room->children_qty,
            'is_room_night'    => 1,
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
                    'service_name'           => 'Tiền phòng',
                    'service_bill_id'        => $bill->Ma,
                    'service_bill_detail_no' => 1,
                    'quantity'               => 1,
                    'rate'                   => $rate,
                    'total_amount'           => $totalAmount,
                    'department'             => 'FO',
                    'note'                   => $reason ?: $description,
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
        $description = $service->note ?: $foService->name;

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
            'SpecialTax'         => 0,
            'Tax'                => (float)($foService->tax ?? 0),
            'Currency'           => 'VND',
            'Exchange'           => 1,
            'Edit'               => 0,
            'Folio'              => (string)$service->folio,
            'RegisterId1'        => $booking?->id,
            'RentalRoomId1'      => $room->id,
            'CustomerId1'        => $guestId,
            'RegisterID2'        => $booking?->id,
            'RentalRoomId2'      => $room->id,
            'CustomerId2'        => $guestId,
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

        ServiceBillDetail::create([
            'BillServiceId'            => $bill->Ma,
            'Ma'                       => 1,
            'DepartmentId'             => 'FO',
            'ServiceId'                => $foService->code,
            'DescriptionServive'       => $description,
            'OriginalRate'             => $rate,
            'ServiceCharge'            => (float)($foService->service_charge ?? 0),
            'SpecialTax'               => 0,
            'Tax'                      => (float)($foService->tax ?? 0),
            'Amount'                   => $totalAmount,
            'Currency'                 => 'VND',
            'Exchange'                 => 1,
            'DetailBillOriginalAmount' => $rate * $qty,
        ]);

        $service->update([
            'service_bill_id'        => $bill->Ma,
            'service_bill_detail_no' => 1,
            'is_posted'              => 1,
            'posted_at'              => now(),
        ]);
    }
}
