<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BookingRoom;
use App\Models\RoomClass;
use App\Models\Room;
use App\Models\RoomLock;
use App\Models\SystemDateRoll;
use App\Services\RoomAvailabilityService;
use Illuminate\Http\Request;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class AvailabilityController extends Controller
{
    public function __construct(protected RoomAvailabilityService $avService) {}

    /**
     * Get availability grid data and statistics.
     * Đã tích hợp booking_rooms (SP2100) — không còn TODO.
     */
    public function index(Request $request)
    {
        // 1. Xác định start_date và end_date
        $startDateInput = $request->input('start_date');
        $endDateInput   = $request->input('end_date');

        $startDate = $startDateInput
            ? Carbon::parse($startDateInput)->startOfDay()
            : now()->timezone('Asia/Ho_Chi_Minh')->startOfDay();

        $endDate = $endDateInput
            ? Carbon::parse($endDateInput)->endOfDay()
            : $startDate->copy()->addDays(29)->endOfDay();

        $startStr = $startDate->toDateString();
        $endStr   = $endDate->copy()->addDay()->toDateString(); // exclusive upper bound

        // Generate danh sách ngày Y-m-d
        $dates = [];
        $temp  = $startDate->copy();
        while ($temp->lte($endDate)) {
            $dates[] = $temp->toDateString();
            $temp->addDay();
        }

        // 2. Lấy room classes
        $roomClasses = RoomClass::where('is_active', true)->get();

        $roomCounts = Room::select('room_class_id', DB::raw('count(*) as total'), DB::raw('max(extra_beds_limit) as max_extra'))
            ->groupBy('room_class_id')
            ->get()
            ->keyBy('room_class_id');

        $roomClassesData = [];
        $grandTotalRooms = 0;
        $grandMaxExtraBeds = 0;

        foreach ($roomClasses as $rc) {
            $stats   = $roomCounts->get($rc->id);
            $total   = $stats ? $stats->total : 0;
            $maxExtra = $stats ? $stats->max_extra : 0;

            $roomClassesData[] = [
                'id'             => $rc->id,
                'code'           => $rc->code,
                'name'           => $rc->name,
                'total'          => $total,
                'max_extra_beds' => $maxExtra,
            ];

            $grandTotalRooms   += $total;
            $grandMaxExtraBeds += $maxExtra;
        }

        // 3. Tính OOO/OOS per class per date
        $locks = RoomLock::where('is_active', true)
            ->where('start_date', '<', $endStr)
            ->where('end_date', '>', $startStr)
            ->with('room.roomClass')
            ->get();

        $lockCounts = [];
        foreach ($locks as $lock) {
            if (!$lock->room || !$lock->room->roomClass) continue;
            $classCode = $lock->room->roomClass->code;
            $lockType  = strtoupper($lock->lock_type);
            $lockStart = Carbon::parse($lock->start_date)->startOfDay();
            $lockEnd   = Carbon::parse($lock->end_date)->endOfDay();

            foreach ($dates as $dStr) {
                $dateObj = Carbon::parse($dStr)->startOfDay();
                if ($dateObj->gte($lockStart) && $dateObj->lte($lockEnd)) {
                    $lockCounts[$classCode][$dStr][$lockType] = ($lockCounts[$classCode][$dStr][$lockType] ?? 0) + 1;
                }
            }
        }

        // 4. Tính booking_rooms per class per date (★ tích hợp SP2100)
        $bookings = BookingRoom::whereIn('status', [
                BookingRoom::STATUS_BOOKED,
                BookingRoom::STATUS_CHECKED_IN,
            ])
            ->where('arrival_date', '<', $endStr)
            ->where('departure_date', '>', $startStr)
            ->select('room_class_id', 'arrival_date', 'departure_date', 'status', 'extra_bed_qty')
            ->with('roomClass:id,code')
            ->get();

        // Tính booked/inhouse count per class per date
        $bookedCounts   = []; // $bookedCounts[classCode][dateStr] = count
        $inhouseCounts  = [];
        $arrivalCounts  = []; // Arrivals: arrival_date = dStr
        $departureCounts = [];
        $extraBedCounts = [];

        foreach ($bookings as $br) {
            if (!$br->roomClass) continue;
            $classCode = $br->roomClass->code;
            $arrDate   = $br->arrival_date->toDateString();
            $depDate   = $br->departure_date->toDateString();

            foreach ($dates as $dStr) {
                if ($dStr >= $arrDate && $dStr < $depDate) {
                    if ($br->status === BookingRoom::STATUS_BOOKED) {
                        $bookedCounts[$classCode][$dStr] = ($bookedCounts[$classCode][$dStr] ?? 0) + 1;
                    } elseif ($br->status === BookingRoom::STATUS_CHECKED_IN) {
                        $inhouseCounts[$classCode][$dStr] = ($inhouseCounts[$classCode][$dStr] ?? 0) + 1;
                    }
                    if ($br->extra_bed_qty > 0) {
                        $extraBedCounts[$classCode][$dStr] = ($extraBedCounts[$classCode][$dStr] ?? 0) + $br->extra_bed_qty;
                    }
                }
            }
            // Arrivals và Departures
            if (in_array($arrDate, $dates)) {
                $arrivalCounts[$classCode][$arrDate] = ($arrivalCounts[$classCode][$arrDate] ?? 0) + 1;
            }
            if (in_array($depDate, $dates)) {
                $departureCounts[$classCode][$depDate] = ($departureCounts[$classCode][$depDate] ?? 0) + 1;
            }
        }

        // 5. Build grid và statistics
        $grid       = [];
        $statistics = [];

        foreach ($dates as $dStr) {
            $statistics[$dStr] = [
                'total_rooms'      => $grandTotalRooms,
                'ooo'              => 0,
                'oos'              => 0,
                'sellable'         => $grandTotalRooms,
                'bk_reserved'      => 0,  // Phòng đặt (Booked, chưa check-in)
                'inhouse'          => 0,  // Phòng đang ở
                'total_occupied'   => 0,  // Booked + Inhouse
                'av'               => $grandTotalRooms,
                'extra_beds'       => 0,
                'arrivals_rooms'   => 0,  // Số phòng check-in ngày này
                'departures_rooms' => 0,  // Số phòng check-out ngày này
            ];
        }

        foreach ($roomClassesData as $rc) {
            $code  = $rc['code'];
            $total = $rc['total'];
            $grid[$code] = [];

            foreach ($dates as $dStr) {
                $oooCount    = $lockCounts[$code][$dStr]['OOO'] ?? 0;
                $oosCount    = $lockCounts[$code][$dStr]['OOS'] ?? 0;
                $bkCount     = $bookedCounts[$code][$dStr] ?? 0;
                $inhouseCount = $inhouseCounts[$code][$dStr] ?? 0;
                $occupied    = $bkCount + $inhouseCount;
                $ebCount     = $extraBedCounts[$code][$dStr] ?? 0;
                $arrivals    = $arrivalCounts[$code][$dStr] ?? 0;
                $departures  = $departureCounts[$code][$dStr] ?? 0;

                $sellable = max(0, $total - $oooCount - $oosCount);
                $av       = max(0, $sellable - $occupied);

                $grid[$code][$dStr] = [
                    'av'         => $av,
                    'ooo'        => $oooCount,
                    'oos'        => $oosCount,
                    'bk'         => $bkCount,    // Reserved (chưa check-in)
                    'occ'        => $inhouseCount, // Inhouse
                    'total_occ'  => $occupied,
                    'eb'         => $ebCount,
                    'arr'        => $arrivals,
                    'dep'        => $departures,
                    'sellable'   => $sellable,
                ];

                // Tổng hợp vào statistics
                $statistics[$dStr]['ooo']              += $oooCount;
                $statistics[$dStr]['oos']              += $oosCount;
                $statistics[$dStr]['bk_reserved']      += $bkCount;
                $statistics[$dStr]['inhouse']          += $inhouseCount;
                $statistics[$dStr]['total_occupied']   += $occupied;
                $statistics[$dStr]['extra_beds']       += $ebCount;
                $statistics[$dStr]['arrivals_rooms']   += $arrivals;
                $statistics[$dStr]['departures_rooms'] += $departures;
            }
        }

        // Tính lại sellable và av tổng
        foreach ($dates as $dStr) {
            $totalOOO = $statistics[$dStr]['ooo'];
            $totalOOS = $statistics[$dStr]['oos'];
            $occ      = $statistics[$dStr]['total_occupied'];
            $sellable = max(0, $grandTotalRooms - $totalOOO - $totalOOS);
            $av       = max(0, $sellable - $occ);

            $statistics[$dStr]['sellable'] = $sellable;
            $statistics[$dStr]['av']       = $av;
        }

        return response()->json([
            'success'      => true,
            'start_date'   => $startDate->toDateString(),
            'end_date'     => $endDate->toDateString(),
            'room_classes' => $roomClassesData,
            'dates'        => $dates,
            'grid'         => $grid,
            'statistics'   => $statistics,
            'totals'       => [
                'grand_total'          => $grandTotalRooms,
                'grand_max_extra_beds' => $grandMaxExtraBeds,
            ],
        ]);
    }

    /**
     * API kiểm tra AV nhanh cho một loại phòng & khoảng ngày.
     * GET /availability/check?room_class_id=1&arrival_date=2026-07-10&departure_date=2026-07-12
     */
    public function check(Request $request)
    {
        $request->validate([
            'room_class_id' => 'required|exists:room_classes,id',
            'arrival_date'  => 'required|date',
            'departure_date'=> 'required|date|after:arrival_date',
        ]);

        $av = $this->avService->getAvailability(
            $request->room_class_id,
            $request->arrival_date,
            $request->departure_date,
            $request->exclude_booking_room_id
        );

        return response()->json([
            'success'       => true,
            'av'            => $av,
            'is_available'  => $av > 0,
        ]);
    }
}
