<?php

namespace App\Services;

use App\Models\BookingRoom;
use App\Models\HotelSetting;
use App\Models\Room;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Dữ liệu trang Tổng quan (dashboard) của module Báo cáo quản lý.
 *
 * Nguồn nghiệp vụ theo dashboard legacy ProVista DTX:
 *  - sp_178: phòng đến/đi/đang ở, ăn sáng (số phòng đến/đi/đang ở dùng chung RoomOccupancyStatisticsService
 *    để khớp số liệu Sơ đồ phòng của hệ thống mới);
 *  - sp_131: doanh thu từng tháng năm nay so với năm trước;
 *  - sp_117: doanh thu hôm qua / tuần này / tháng này, đã và chưa thanh toán;
 *  - sp_136: dự đoán bán phòng Ngày / Tháng / Năm và thống kê theo công ty, thị trường, quốc tịch
 *    (chỉ số phòng/doanh thu lấy từ thủ tục rpt_room_forecast đã port từ báo cáo dự đoán bán phòng).
 */
class DashboardOverviewService
{
    private const FORECAST_PROCEDURE = 'rpt_room_forecast';

    public function __construct(
        private readonly RoomAvailabilityService $availability,
        private readonly RoomOccupancyStatisticsService $occupancy,
    ) {
    }

    public function overview(): array
    {
        $date = $this->availability->getSystemDate()->copy()->startOfDay();

        return [
            'system_date' => $date->toDateString(),
            'activity' => $this->activity($date),
            'housekeeping' => $this->housekeeping(),
            'revenue_monthly' => $this->monthlyRevenue($date),
            'revenue_summary' => $this->revenueSummary($date),
            'forecast' => $this->forecast($date),
        ];
    }

    /**
     * Danh sách chi tiết cho popup Khách đến / Khách đi / Đang ở trong ngày hệ thống.
     */
    public function activityRooms(string $type): array
    {
        $date = $this->availability->getSystemDate()->copy()->startOfDay();
        $day = $date->toDateString();

        $query = BookingRoom::query()
            ->stayOnly()
            ->whereHas('booking.registrationStatus', fn ($status) => $status->where('is_availability', 1))
            ->with([
                'booking:id,booking_name,company_id,external_booking_code,note',
                'booking.company:id,name',
                'roomClass:id,name,code',
                'guests:id,booking_room_id,guest_id,is_primary',
                'guests.guest:id,full_name,nationality_code',
            ]);

        if ($type === 'arrivals') {
            $query->where(function ($rows) use ($day) {
                $rows->where(fn ($pending) => $pending->where('status', BookingRoom::STATUS_BOOKED)->whereDate('arrival_date', $day))
                    ->orWhere(fn ($arrived) => $arrived
                        ->whereIn('status', [BookingRoom::STATUS_CHECKED_IN, BookingRoom::STATUS_CHECKED_OUT])
                        ->whereDate('actual_arrival_date', $day));
            });
        } elseif ($type === 'departures') {
            $query->where(function ($rows) use ($day) {
                $rows->where(fn ($inhouse) => $inhouse->where('status', BookingRoom::STATUS_CHECKED_IN)->whereDate('departure_date', '<=', $day))
                    ->orWhere(fn ($sameDay) => $sameDay->where('status', BookingRoom::STATUS_BOOKED)
                        ->whereDate('arrival_date', $day)->whereDate('departure_date', $day))
                    ->orWhere(fn ($done) => $done->where('status', BookingRoom::STATUS_CHECKED_OUT)->whereDate('CheckoutDate', $day));
            });
        } else {
            $query->where('status', BookingRoom::STATUS_CHECKED_IN);
        }

        $prefix = strtoupper(HotelSetting::query()->value('prefix_booking_id') ?: 'GAL');

        return $query->orderBy('room_number')->get()->map(function (BookingRoom $room) use ($prefix, $type, $day) {
            $guest = $room->guests->sortBy([fn ($a, $b) => (int) $b->is_primary <=> (int) $a->is_primary, fn ($a, $b) => $a->id <=> $b->id])
                ->first()?->guest;
            $done = $type === 'arrivals'
                ? in_array((int) $room->status, [BookingRoom::STATUS_CHECKED_IN, BookingRoom::STATUS_CHECKED_OUT], true)
                : ($type === 'departures'
                    ? (int) $room->status === BookingRoom::STATUS_CHECKED_OUT && $this->dateString($room->CheckoutDate) === $day
                    : true);

            return [
                'id' => $room->id,
                'booking_id' => $room->booking_id,
                'booking_code' => $prefix.$room->booking_id,
                'reference_code' => $room->booking?->external_booking_code,
                'booking_name' => $room->booking?->booking_name,
                'guest_name' => $guest?->full_name,
                'nationality' => $guest?->nationality_code,
                'company' => $room->booking?->company?->name,
                'status' => (int) $room->status,
                'done' => $done,
                'arrival_date' => $this->dateString($room->arrival_date),
                'departure_date' => $this->dateString($room->departure_date),
                'nights' => (int) $room->NumOfDays,
                'room_number' => $room->room_number,
                'room_class' => $room->roomClass?->name,
                'adults' => (int) $room->adults,
                'children' => (int) $room->children_qty,
                'note' => $room->note ?: $room->booking?->note,
            ];
        })->values()->all();
    }

    private function activity(Carbon $date): array
    {
        $stats = $this->occupancy->calculate($date);

        return [
            'arrivals_total' => (int) $stats['arrivals_total'],
            'arrivals_checked_in' => (int) $stats['arrivals_checked_in'],
            'arrivals_pending' => (int) $stats['arrivals_pending'],
            'departures_total' => (int) $stats['departures_total'],
            'departures_checked_out' => (int) $stats['departures_checked_out'],
            'departures_pending' => (int) $stats['departures_pending'],
            'inhouse_current' => (int) $stats['occupied_current'],
            'inhouse_current_pax' => (int) $stats['occupied_current_pax'],
            'inhouse_projected' => (int) $stats['occupied_projected'],
            'total_rooms' => (int) $stats['total_rooms'],
            'saleable_rooms' => (int) $stats['saleable_rooms'],
            'occupancy_rate' => (float) $stats['occupancy_rate'],
            'breakfast' => $this->breakfast($date),
        ];
    }

    /**
     * sp_178: phòng đã ở đêm qua (ngày đến < ngày hệ thống <= ngày đi) sáng nay ăn sáng.
     */
    private function breakfast(Carbon $date): array
    {
        $day = $date->toDateString();
        $rooms = BookingRoom::query()
            ->stayOnly()
            ->whereIn('status', [
                BookingRoom::STATUS_BOOKED,
                BookingRoom::STATUS_CHECKED_IN,
                BookingRoom::STATUS_CHECKED_OUT,
                BookingRoom::STATUS_MOVED,
            ])
            ->whereDate('arrival_date', '<', $day)
            ->whereRaw(
                'DATE(CASE WHEN status IN (?, ?) AND CheckoutDate IS NOT NULL THEN CheckoutDate ELSE departure_date END) >= ?',
                [BookingRoom::STATUS_CHECKED_OUT, BookingRoom::STATUS_MOVED, $day]
            )
            ->get(['id', 'breakfast', 'adults']);

        $withBreakfast = $rooms->filter(fn ($room) => (bool) $room->breakfast);
        $children = $withBreakfast->isEmpty() ? 0 : (int) DB::table('booking_child_breakfast_details as d')
            ->join('booking_room_children as c', 'c.booking_child_id', '=', 'd.booking_child_id')
            ->whereIn('c.booking_room_id', $withBreakfast->pluck('id'))
            ->whereDate('d.service_date', $day)
            ->where('d.breakfast', 1)
            ->distinct()
            ->count('d.booking_child_id');

        return [
            'rooms_with' => $withBreakfast->count(),
            'rooms_without' => $rooms->count() - $withBreakfast->count(),
            'adults' => (int) $withBreakfast->sum('adults'),
            'children' => $children,
        ];
    }

    private function housekeeping(): array
    {
        $groups = [
            'ready' => ['vacant_ready', 'occupied_ready'],
            'clean' => ['vacant_clean', 'occupied_clean'],
            'dirty' => ['vacant_dirty', 'occupied_dirty', 'vacant_priority'],
            'ooo' => ['ooo', 'occupied_ooo'],
            'oos' => ['oos'],
        ];
        $occupiedCodes = DB::table('room_statuses')->where('is_occupied', 1)->pluck('code')->all();
        $counts = Room::query()->physical()
            ->select('room_status_code', DB::raw('COUNT(*) as total'))
            ->groupBy('room_status_code')
            ->pluck('total', 'room_status_code');

        $rows = [];
        $known = [];
        foreach ($groups as $key => $codes) {
            $known = array_merge($known, $codes);
            $rows[] = $this->housekeepingRow($key, $codes, $counts, $occupiedCodes);
        }
        $others = $counts->keys()->diff($known)->values()->all();
        $other = $this->housekeepingRow('other', $others, $counts, $occupiedCodes);
        if ($other['occ'] + $other['vac'] > 0) {
            $rows[] = $other;
        }

        return $rows;
    }

    private function housekeepingRow(string $key, array $codes, Collection $counts, array $occupiedCodes): array
    {
        $occ = 0;
        $vac = 0;
        foreach ($codes as $code) {
            $total = (int) ($counts[$code] ?? 0);
            if (in_array($code, $occupiedCodes, true)) {
                $occ += $total;
            } else {
                $vac += $total;
            }
        }

        return ['key' => $key, 'occ' => $occ, 'vac' => $vac];
    }

    /**
     * sp_131: doanh thu (Amount chi tiết hóa đơn dịch vụ) theo tháng từ đầu năm tới tháng hệ thống,
     * so với cùng tháng năm trước; chỉ tính ngày trước ngày hệ thống, bỏ thanh toán Complimentary (CL).
     */
    private function monthlyRevenue(Carbon $date): array
    {
        $year = $date->year;
        $rows = DB::table('service_bills as hd')
            ->join('service_bill_details as d', 'd.BillServiceId', '=', 'hd.Ma')
            ->whereDate('hd.Date', '<', $date->toDateString())
            ->whereDate('hd.Date', '>=', sprintf('%04d-01-01', $year - 1))
            // Bỏ cặp hóa đơn gốc/âm của dịch vụ đã hủy hoặc chuyển (Edit = 1), kể cả khi chưa thanh toán.
            ->where('hd.Edit', 0)
            ->where(function ($bills) {
                $bills->whereNull('hd.PaymentId')
                    ->orWhereIn('hd.PaymentId', $this->validPaymentIds(fn ($methods) => $methods->where('pm.code', '<>', 'CL')));
            })
            ->selectRaw('YEAR(hd.Date) as y, MONTH(hd.Date) as m, SUM(d.Amount) as total')
            ->groupByRaw('YEAR(hd.Date), MONTH(hd.Date)')
            ->get()
            ->keyBy(fn ($row) => $row->y.'-'.$row->m);

        $months = [];
        for ($month = 1; $month <= $date->month; $month++) {
            $months[] = [
                'month' => $month,
                'current' => (float) ($rows[$year.'-'.$month]->total ?? 0),
                'previous' => (float) ($rows[($year - 1).'-'.$month]->total ?? 0),
            ];
        }

        return ['year' => $year, 'months' => $months];
    }

    /**
     * sp_117: doanh thu hôm qua, tuần này (thứ Hai tới hôm qua) và tháng này (mùng 1 tới hôm qua),
     * tách đã thanh toán / chưa thanh toán; bỏ thanh toán bằng phương thức miễn phí.
     */
    private function revenueSummary(Carbon $date): array
    {
        $yesterday = $date->copy()->subDay();
        $weekStart = $date->copy()->startOfWeek(Carbon::MONDAY);
        $weekEnd = $yesterday->lt($weekStart) ? $date->copy() : $yesterday;
        $monthStart = $date->copy()->startOfMonth();

        $periods = [
            'yesterday' => [$yesterday, $yesterday],
            'week' => [$weekStart, $weekEnd],
            'month' => [$monthStart, $yesterday],
        ];
        $from = collect($periods)->min(fn ($range) => $range[0]->toDateString());

        $rows = DB::table('service_bills as hd')
            ->join('service_bill_details as d', 'd.BillServiceId', '=', 'hd.Ma')
            ->whereDate('hd.Date', '>=', $from)
            ->whereDate('hd.Date', '<=', $date->toDateString())
            // Bỏ cặp hóa đơn gốc/âm của dịch vụ đã hủy hoặc chuyển (Edit = 1), kể cả khi chưa thanh toán.
            ->where('hd.Edit', 0)
            ->where(function ($bills) {
                $bills->whereNull('hd.PaymentId')
                    ->orWhereIn('hd.PaymentId', $this->validPaymentIds(fn ($methods) => $methods->where('pm.is_free', 0)));
            })
            ->selectRaw('DATE(hd.Date) as day, hd.DepartmentId as department, CASE WHEN hd.PaymentId IS NULL THEN 0 ELSE 1 END as paid, SUM(d.Amount) as total')
            ->groupByRaw('DATE(hd.Date), hd.DepartmentId, CASE WHEN hd.PaymentId IS NULL THEN 0 ELSE 1 END')
            ->get();

        $departmentNames = DB::table('departments')->pluck('name', 'code');
        $result = [];
        foreach ($periods as $key => [$start, $end]) {
            $inRange = $rows->filter(fn ($row) => $row->day >= $start->toDateString() && $row->day <= $end->toDateString());
            $departments = $inRange->groupBy(fn ($row) => (string) ($row->department ?? ''))
                ->map(fn ($items, $code) => [
                    'code' => $code,
                    'name' => $departmentNames[$code] ?? $code,
                    'total' => (float) $items->sum('total'),
                ])
                ->sortByDesc('total')
                ->values()
                ->all();

            $result[$key] = [
                'from' => $start->toDateString(),
                'to' => $end->toDateString(),
                'total' => (float) $inRange->sum('total'),
                'paid' => (float) $inRange->where('paid', 1)->sum('total'),
                'unpaid' => (float) $inRange->where('paid', 0)->sum('total'),
                'departments' => $departments,
            ];
        }

        return $result;
    }

    private function validPaymentIds(callable $methodFilter)
    {
        $query = DB::table('payments as p')
            // payments.payment_method_id lưu mã phương thức (CA, AC, CL...), không phải id.
            ->join('payment_methods as pm', 'pm.code', '=', 'p.payment_method_id')
            ->whereNotNull('p.payment_id')
            ->where('p.edit_flag', 0)
            ->whereNull('p.deleted_at')
            ->select('p.payment_id');
        $methodFilter($query);

        return $query;
    }

    /**
     * sp_136: dự đoán bán phòng theo ngày hệ thống, tháng hiện tại và cả năm.
     */
    private function forecast(Carbon $date): array
    {
        $yearStart = $date->copy()->startOfYear();
        $yearEnd = $date->copy()->endOfYear()->startOfDay();
        $windows = [
            'day' => [$date->copy(), $date->copy()],
            'month' => [$date->copy()->startOfMonth(), $date->copy()->endOfMonth()->startOfDay()],
            'year' => [$yearStart, $yearEnd],
        ];

        $withBreakfast = $this->forecastRows($yearStart, $yearEnd, true);
        $withoutBreakfast = $this->forecastRows($yearStart, $yearEnd, false);
        $nights = $this->roomNights($yearStart, $yearEnd);
        $created = $this->createdBookings($yearStart, $yearEnd);

        $result = [];
        foreach ($windows as $key => [$start, $end]) {
            $from = $start->toDateString();
            $to = $end->toDateString();
            $inWindow = fn ($day) => $day >= $from && $day <= $to;
            $windowNights = $nights->filter(fn ($night) => $inWindow($night->night_date));

            $result[$key] = [
                'from' => $from,
                'to' => $to,
                'with_breakfast' => $this->forecastMetrics($withBreakfast->filter(fn ($row) => $inWindow($row['date']))),
                'without_breakfast' => $this->forecastMetrics($withoutBreakfast->filter(fn ($row) => $inWindow($row['date']))),
                // Tổng số lượt đăng ký có phòng ở theo từng đêm (sp_136 "Booking").
                'bookings' => $windowNights->groupBy('night_date')->sum(fn ($items) => $items->pluck('booking_id')->unique()->count()),
                'created_bookings' => (int) $created->filter(fn ($row) => $inWindow($row->day))->sum('total'),
                'top' => [
                    'company' => $this->topGroups($windowNights, 'company_name'),
                    'market' => $this->topGroups($windowNights, 'market_name'),
                    'nationality' => $this->topGroups($windowNights, 'nationality_name'),
                ],
            ];
        }

        return $result;
    }

    private function forecastRows(Carbon $from, Carbon $to, bool $includeBreakfast): Collection
    {
        $rows = DB::select(
            'CALL '.self::FORECAST_PROCEDURE.'(?, ?, ?, ?, ?, ?, ?, ?)',
            [$from->toDateString(), $to->toDateString(), '', '', '', 0, $includeBreakfast ? 1 : 0, '']
        );

        return collect($rows)->map(function ($row) {
            $row = (array) $row;

            return [
                'date' => Carbon::createFromFormat('d/m/Y', $row['Date'])->toDateString(),
                'occ_rooms' => (int) $row['OccRooms'],
                'occ_adults' => (int) $row['OccAdult'],
                'room_sales' => (int) $row['RoomSales'],
                'revenue' => (float) $row['Revenue'],
                'avg_rate' => (float) $row['AvgRate'],
                'available' => (int) $row['RoomAvible'],
            ];
        });
    }

    private function forecastMetrics(Collection $rows): array
    {
        $available = (int) $rows->sum('available');
        $occupied = (int) $rows->sum('occ_rooms');
        $roomSales = (int) $rows->sum('room_sales');
        $revenue = (float) $rows->sum('revenue');
        // Giá TB từng ngày của thủ tục đã theo cấu hình AverageRoomRateIncludedOthersRoomRevenue;
        // gộp nhiều ngày theo bình quân gia quyền số phòng bán.
        $rateAmount = (float) $rows->sum(fn ($row) => $row['avg_rate'] * $row['room_sales']);

        return [
            'available' => $available,
            'occupied' => $occupied,
            'guests' => (int) $rows->sum('occ_adults'),
            'unsold' => max(0, $available - $occupied),
            'occupancy_rate' => $available > 0 ? round($occupied / $available * 100, 2) : 0,
            'revenue' => round($revenue),
            'average_rate' => $roomSales > 0 ? round($rateAmount / $roomSales) : 0,
            'revpar' => $available > 0 ? round($revenue / $available) : 0,
        ];
    }

    /**
     * Mỗi dòng là một đêm phòng của đăng ký có tình trạng tính availability (sp_136 #tempAvailable).
     */
    private function roomNights(Carbon $from, Carbon $to): Collection
    {
        $sql = <<<'SQL'
WITH RECURSIVE days AS (
    SELECT CAST(? AS DATE) AS night_date
    UNION ALL
    SELECT night_date + INTERVAL 1 DAY FROM days WHERE night_date < CAST(? AS DATE)
)
SELECT
    DATE_FORMAT(days.night_date, '%Y-%m-%d') AS night_date,
    br.booking_id,
    c.name AS company_name,
    m.name AS market_name,
    g.nationality_code AS nationality_name
FROM days
JOIN booking_rooms AS br
    ON DATE(br.arrival_date) <= days.night_date
   AND days.night_date < DATE(CASE WHEN br.status IN (2, 100) AND br.CheckoutDate IS NOT NULL THEN br.CheckoutDate ELSE br.departure_date END)
JOIN bookings AS b ON b.id = br.booking_id AND b.deleted_at IS NULL AND COALESCE(b.is_service_only, 0) = 0
JOIN registration_statuses AS rs ON rs.id = b.registration_status_id AND rs.is_availability = 1
LEFT JOIN rooms AS r ON r.room_number = br.room_number
LEFT JOIN companies AS c ON c.id = b.company_id
LEFT JOIN markets AS m ON m.id = b.market_id
LEFT JOIN booking_room_guests AS brg ON brg.id = (
    SELECT x.id FROM booking_room_guests AS x
    WHERE x.booking_room_id = br.id
    ORDER BY x.is_primary DESC, x.id
    LIMIT 1
)
LEFT JOIN guests AS g ON g.id = brg.guest_id
WHERE br.deleted_at IS NULL
  AND br.status IN (0, 1, 2, 100)
  AND COALESCE(br.is_day_use, 0) = 0
  AND (br.room_number IS NULL OR br.room_number = '' OR (br.room_number NOT LIKE '0%' AND COALESCE(r.is_internal, 0) = 0))
SQL;

        // Tối đa 366 ngày: nằm trong giới hạn đệ quy mặc định của MySQL (1000) và MariaDB.
        return collect(DB::select($sql, [$from->toDateString(), $to->toDateString()]));
    }

    private function createdBookings(Carbon $from, Carbon $to): Collection
    {
        return DB::table('bookings')
            ->whereNull('deleted_at')
            ->where(fn ($query) => $query->whereNull('is_service_only')->orWhere('is_service_only', 0))
            ->whereBetween(DB::raw('DATE(COALESCE(booking_date, created_at))'), [$from->toDateString(), $to->toDateString()])
            ->selectRaw("DATE_FORMAT(COALESCE(booking_date, created_at), '%Y-%m-%d') as day, COUNT(*) as total")
            ->groupByRaw("DATE_FORMAT(COALESCE(booking_date, created_at), '%Y-%m-%d')")
            ->get();
    }

    private function topGroups(Collection $nights, string $field, int $limit = 5): array
    {
        $total = $nights->count();
        if ($total === 0) {
            return [];
        }

        return $nights->filter(fn ($night) => trim((string) $night->{$field}) !== '')
            ->groupBy(fn ($night) => trim((string) $night->{$field}))
            ->map(fn ($items, $name) => [
                'name' => $name,
                'room_nights' => $items->count(),
                'share' => round($items->count() / $total * 100, 1),
            ])
            ->sortByDesc('room_nights')
            ->take($limit)
            ->values()
            ->all();
    }

    private function dateString($value): ?string
    {
        return $value ? Carbon::parse($value)->toDateString() : null;
    }
}
