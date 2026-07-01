<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\RoomClass;
use App\Models\Room;
use App\Models\RoomLock;
use App\Models\SystemDateRoll;
use Illuminate\Http\Request;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class AvailabilityController extends Controller
{
    /**
     * Get availability grid data and statistics.
     */
    public function index(Request $request)
    {
        // 1. Determine start_date and end_date
        $startDateInput = $request->input('start_date');
        $endDateInput = $request->input('end_date');

        if ($startDateInput) {
            $startDate = Carbon::parse($startDateInput)->startOfDay();
        } else {
            // Default to the current actual host date (today) instead of seeded database historical rolls
            $startDate = now()->timezone('Asia/Ho_Chi_Minh')->startOfDay();
        }

        if ($endDateInput) {
            $endDate = Carbon::parse($endDateInput)->endOfDay();
        } else {
            $endDate = $startDate->copy()->addDays(29)->endOfDay();
        }

        // Generate date string list Y-m-d
        $dates = [];
        $temp = $startDate->copy();
        while ($temp->lte($endDate)) {
            $dates[] = $temp->toDateString();
            $temp->addDay();
        }

        // 2. Fetch room classes
        $roomClasses = RoomClass::where('is_active', true)->get();

        // Get room counts grouped by class
        $roomCounts = Room::select('room_class_id', DB::raw('count(*) as total'), DB::raw('max(extra_beds_limit) as max_extra'))
            ->groupBy('room_class_id')
            ->get()
            ->keyBy('room_class_id');

        $roomClassesData = [];
        $grandTotalRooms = 0;
        $grandMaxExtraBeds = 0;

        foreach ($roomClasses as $rc) {
            $stats = $roomCounts->get($rc->id);
            $total = $stats ? $stats->total : 0;
            $maxExtra = $stats ? $stats->max_extra : 0;

            $roomClassesData[] = [
                'id' => $rc->id,
                'code' => $rc->code,
                'name' => $rc->name,
                'total' => $total,
                'max_extra_beds' => $maxExtra,
            ];

            $grandTotalRooms += $total;
            $grandMaxExtraBeds += $maxExtra;
        }

        // 3. Fetch active room locks within the range
        // start_date of lock <= end_date of range AND end_date of lock >= start_date of range
        $locks = RoomLock::where('is_active', true)
            ->where('start_date', '<=', $endDate->toDateTimeString())
            ->where('end_date', '>=', $startDate->toDateTimeString())
            ->with('room')
            ->get();

        // Build a grid helper to easily check OOO/OOS count per class per date
        // Structure: $lockCounts[class_code][date_str][lock_type] = count
        $lockCounts = [];
        foreach ($locks as $lock) {
            if (!$lock->room || !$lock->room->roomClass) {
                continue;
            }
            $classCode = $lock->room->roomClass->code;
            $lockType = strtoupper($lock->lock_type); // OOO, OOS

            $lockStart = Carbon::parse($lock->start_date)->startOfDay();
            $lockEnd = Carbon::parse($lock->end_date)->endOfDay();

            // Iterate over each date in the query range
            foreach ($dates as $dStr) {
                $dateObj = Carbon::parse($dStr)->startOfDay();
                if ($dateObj->gte($lockStart) && $dateObj->lte($lockEnd)) {
                    if (!isset($lockCounts[$classCode][$dStr][$lockType])) {
                        $lockCounts[$classCode][$dStr][$lockType] = 0;
                    }
                    $lockCounts[$classCode][$dStr][$lockType]++;
                }
            }
        }

        // 4. Generate grid and statistics
        $grid = [];
        $statistics = [];

        // Pre-initialize statistics arrays
        foreach ($dates as $dStr) {
            $statistics[$dStr] = [
                'total_rooms' => $grandTotalRooms,
                'ooo' => 0,
                'oos' => 0,
                'sellable' => $grandTotalRooms,
                'series' => 0,             // TODO: SP2100 bookings integration
                'allotment' => 0,          // TODO: SP2100 bookings integration
                'bk_guaranteed' => 0,      // TODO: SP2100 bookings integration
                'bk_nonguaranteed' => 0,  // TODO: SP2100 bookings integration
                'total_occupied' => 0,     // TODO: SP2100 bookings integration
                'occupied_pct' => 0,       // TODO: SP2100 bookings integration
                'av' => $grandTotalRooms,
                'internal_rooms' => 0,     // TODO: SP2100 RoomRateCode='HU' integration
                'free_rooms' => 0,         // TODO: SP2100 Room price 0đ integration
                'total_guests' => 0,       // TODO: SP2100 bookings integration
                'arrivals_rooms' => 0,     // TODO: SP2100 bookings integration
                'arrivals_pax' => 0,       // TODO: SP2100 bookings integration
                'inhouse' => 0,            // TODO: SP2100 bookings integration
                'extra_beds' => 0,         // TODO: SP2102 extra beds integration
                'cancellations' => 0,      // TODO: SP2100 bookings integration
                'noshow' => 0              // TODO: SP2100 bookings integration
            ];
        }

        foreach ($roomClassesData as $rc) {
            $code = $rc['code'];
            $total = $rc['total'];
            $grid[$code] = [];

            foreach ($dates as $dStr) {
                $oooCount = isset($lockCounts[$code][$dStr]['OOO']) ? $lockCounts[$code][$dStr]['OOO'] : 0;
                $oosCount = isset($lockCounts[$code][$dStr]['OOS']) ? $lockCounts[$code][$dStr]['OOS'] : 0;
                $bkCount = 0; // TODO: SP2100 - bookings integration

                $av = $total - $oooCount - $oosCount - $bkCount;
                if ($av < 0) {
                    $av = 0;
                }

                $grid[$code][$dStr] = [
                    'av' => $av,
                    'ooo' => $oooCount,
                    'oos' => $oosCount,
                    'occ' => $bkCount,
                    'eb' => 0,
                    'alm' => 0,
                    'sofab' => 0,
                ];

                // Add to statistics
                $statistics[$dStr]['ooo'] += $oooCount;
                $statistics[$dStr]['oos'] += $oosCount;
            }
        }

        // Adjust statistics sellable and av totals based on aggregated locks
        foreach ($dates as $dStr) {
            $ooo = $statistics[$dStr]['ooo'];
            $oos = $statistics[$dStr]['oos'];
            $sellable = $grandTotalRooms - $ooo - $oos;
            if ($sellable < 0) {
                $sellable = 0;
            }
            $statistics[$dStr]['sellable'] = $sellable;

            // AV = Sellable - total_occupied (where total_occupied is 0 for now)
            $statistics[$dStr]['av'] = $sellable;
        }

        return response()->json([
            'success' => true,
            'start_date' => $startDate->toDateString(),
            'end_date' => $endDate->toDateString(),
            'room_classes' => $roomClassesData,
            'dates' => $dates,
            'grid' => $grid,
            'statistics' => $statistics,
            'totals' => [
                'grand_total' => $grandTotalRooms,
                'grand_max_extra_beds' => $grandMaxExtraBeds
            ]
        ]);
    }
}
