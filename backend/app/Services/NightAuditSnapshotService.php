<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\BookingRoom;
use App\Models\BookingRoomGuest;
use App\Models\BookingRoomService;
use App\Models\Company;
use App\Models\Guest;
use App\Models\HotelConfig;
use App\Models\HotelSetting;
use App\Models\Nationality;
use App\Models\NightAuditAgencyProductivityKpiSnapshot;
use App\Models\NightAuditAgencyProductivitySnapshot;
use App\Models\NightAuditInhouseSnapshot;
use App\Models\NightAuditRoomSalesForecastDetailSnapshot;
use App\Models\NightAuditRoomSalesForecastSnapshot;
use App\Models\NightAuditRoomTypeSnapshot;
use App\Models\NightAuditRun;
use App\Models\NightAuditRunStep;
use App\Models\Room;
use App\Models\RoomClass;
use App\Models\RoomLock;
use App\Models\RoomNightBill;
use App\Models\ServiceBill;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class NightAuditSnapshotService
{
    /**
     * Khởi tạo hoặc lấy step log theo thứ tự.
     */
    public function createStep(NightAuditRun $run, string $stepCode, string $stepName, int $order): NightAuditRunStep
    {
        return NightAuditRunStep::firstOrCreate(
            [
                'run_id'    => $run->id,
                'step_code' => $stepCode,
            ],
            [
                'step_name'  => $stepName,
                'step_order' => $order,
                'status'     => 'pending',
            ]
        );
    }

    public function markStepRunning(NightAuditRunStep $step): void
    {
        $step->update([
            'status'     => 'running',
            'started_at' => now(),
        ]);
    }

    public function markStepSucceeded(NightAuditRunStep $step, int $affectedRows = 0, ?array $summary = null): void
    {
        $step->update([
            'status'        => 'succeeded',
            'finished_at'   => now(),
            'affected_rows' => $affectedRows,
            'summary'       => $summary,
        ]);
    }

    public function markStepFailed(NightAuditRunStep $step, string $errorMessage): void
    {
        $step->update([
            'status'        => 'failed',
            'finished_at'   => now(),
            'error_message' => $errorMessage,
        ]);
    }

    public function markStepSkipped(NightAuditRunStep $step, string $reason): void
    {
        $step->update([
            'status'        => 'skipped_unconfigured',
            'started_at'    => now(),
            'finished_at'   => now(),
            'affected_rows' => 0,
            'summary'       => ['reason' => $reason],
        ]);
    }

    /**
     * SP7000: Năng suất công ty/đại lý theo ngày (Agency Productivity)
     */
    public function captureAgencyProductivity(Carbon $closingDate, NightAuditRun $run): int
    {
        $dateStr = $closingDate->toDateString();

        // Xóa snapshot cũ cùng ngày nếu rerun được cho phép trong cùng transaction
        NightAuditAgencyProductivitySnapshot::whereDate('snapshot_date', $dateStr)->delete();

        // 1. Tính tổng phòng khả dụng (RAV) của khách sạn tại ngày đóng
        $totalPhysicalRooms = Room::where(function ($q) {
            $q->whereNull('is_internal')->orWhere('is_internal', 0);
        })->where(function ($q) {
            $q->whereNull('room_number')->orWhere('room_number', 'not like', '0%');
        })->count();

        $totalOOO = RoomLock::where('lock_type', 'OOO')
            ->where('is_active', 1)
            ->whereDate('start_date', '<=', $dateStr)
            ->whereDate('end_date', '>=', $dateStr)
            ->count();

        $hotelRAV = max(1, $totalPhysicalRooms - $totalOOO);

        // 2. Lấy bills tiền phòng và dịch vụ cho ngày đóng
        $bills = ServiceBill::whereDate('Date', $dateStr)
            ->where('DepartmentId', 'FO')
            ->where('Edit', 0)
            ->get();

        $billIds = $bills->pluck('Ma');
        $roomNightBills = RoomNightBill::whereIn('bill_id', $billIds)->get()->keyBy('bill_id');
        $supplementalBillIds = ServiceBill::whereIn('Ma', $billIds)
            ->onlySupplementalRoomCharge()
            ->pluck('Ma')
            ->flip();

        $groups = [];
        $totalHotelRevenue = 0.0;

        foreach ($bills as $bill) {
            $rnb = $roomNightBills->get($bill->Ma);
            $serviceId = strtoupper((string) $bill->ServiceId);
            $amount = (float) $bill->Amount;

            $booking = $bill->RegisterId1 ? Booking::find($bill->RegisterId1) : null;
            $companyId = $bill->CompanyId1 ?: ($booking?->company_id ?: 0);
            $companyName = 'Khách lẻ';
            if ($companyId > 0) {
                $comp = Company::find($companyId);
                if ($comp) {
                    $companyName = $comp->name;
                }
            }

            $bookingRoom = $bill->RentalRoomId1 ? BookingRoom::find($bill->RentalRoomId1) : null;
            $marketSegment = (string) ($booking?->market_segment_id ?: '1');
            $sourceCode = (string) ($booking?->customer_source_code ?: '');
            $userSale = (string) ($booking?->sales_person ?: $bill->CreatedUser ?: '');
            $areaId = '1';

            $groupKey = "{$companyId}_{$marketSegment}_{$sourceCode}_{$userSale}_{$areaId}";

            if (!isset($groups[$groupKey])) {
                $groups[$groupKey] = [
                    'company_id'             => $companyId ?: null,
                    'company_name'           => $companyName,
                    'market_segment_id'      => $marketSegment,
                    'source_code'            => $sourceCode,
                    'user_sale'              => $userSale,
                    'area_id'                => $areaId,
                    'room_ids'               => [],
                    'num_of_rooms'           => 0,
                    'room_nights'            => 0,
                    'foc'                    => 0,
                    'house_use'              => 0,
                    'guest_nights'           => 0,
                    'num_of_guests'          => 0,
                    'room_revenue'           => 0.0,
                    'extra_bed_revenue'      => 0.0,
                    'extra_rollaway_revenue' => 0.0,
                    'total_revenue'          => 0.0,
                ];
            }

            if ($serviceId === 'RM') {
                $groups[$groupKey]['room_revenue'] += $amount;
                $groups[$groupKey]['total_revenue'] += $amount;
                $totalHotelRevenue += $amount;

                // Bill bổ sung tiền phòng chỉ cộng doanh thu, không tính khách/FOC/HU.
                if ($rnb && !$supplementalBillIds->has($bill->Ma)) {
                    if ($rnb->is_room_night) {
                        $groups[$groupKey]['room_nights']++;
                    }
                    $groups[$groupKey]['guest_nights'] += (int) $rnb->adult;
                    $groups[$groupKey]['num_of_guests'] += (int) $rnb->adult;

                    $rateCode = strtoupper((string) ($rnb->rate_code ?: ''));
                    if ($rateCode === 'HU') {
                        $groups[$groupKey]['house_use']++;
                    } elseif (str_starts_with($rateCode, 'FOC') || (float) $rnb->rate === 0.0) {
                        $groups[$groupKey]['foc']++;
                    }
                }

                if ($bill->RentalRoomId1 && !in_array($bill->RentalRoomId1, $groups[$groupKey]['room_ids'], true)) {
                    $groups[$groupKey]['room_ids'][] = $bill->RentalRoomId1;
                    $groups[$groupKey]['num_of_rooms']++;
                }
            } elseif ($serviceId === 'EB') {
                $groups[$groupKey]['extra_bed_revenue'] += $amount;
                $groups[$groupKey]['total_revenue'] += $amount;
                $totalHotelRevenue += $amount;
            } elseif ($serviceId === 'ER') {
                $groups[$groupKey]['extra_rollaway_revenue'] += $amount;
                $groups[$groupKey]['total_revenue'] += $amount;
                $totalHotelRevenue += $amount;
            }
        }

        $records = [];
        foreach ($groups as $g) {
            unset($g['room_ids']);
            $g['night_audit_run_id'] = $run->id;
            $g['snapshot_date'] = $dateStr;

            $rev = $g['total_revenue'];
            $rn = $g['room_nights'];
            $hu = $g['house_use'];

            $g['revenue_per_room_night'] = $hotelRAV > 0 ? round(($rn / $hotelRAV) * 100, 4) : 0;
            $g['revenue_percent'] = $totalHotelRevenue > 0 ? round(($rev / $totalHotelRevenue) * 100, 4) : 0;
            $g['average_revenue'] = ($rn - $hu) > 0 ? round($rev / ($rn - $hu), 4) : 0;
            $g['rav'] = $hotelRAV;
            $g['rav3'] = $hotelRAV;
            $g['created_at'] = now();
            $g['updated_at'] = now();

            $records[] = $g;
        }

        if (!empty($records)) {
            NightAuditAgencyProductivitySnapshot::insert($records);
        }

        return count($records);
    }

    /**
     * Snapshot bất biến cho Báo cáo công suất công ty (legacy sp_055/sp_078).
     *
     * SP7000 cũ không có BF, F&B, doanh thu khác, TotalByDate hoặc OriginalTotal,
     * vì vậy không được dùng nó làm nguồn lịch sử của hai báo cáo này.
     */
    public function captureCompanyOccupancyReport(Carbon $closingDate, NightAuditRun $run): int
    {
        $snapshotDate = $closingDate->toDateString();
        $now = now();

        DB::table('night_audit_company_occupancy_snapshots')->whereDate('snapshot_date', $snapshotDate)->delete();
        DB::table('night_audit_company_occupancy_daily_snapshots')->whereDate('snapshot_date', $snapshotDate)->delete();

        $config = HotelConfig::query()
            ->get(['name', 'value'])
            ->mapWithKeys(static fn (HotelConfig $item): array => [strtolower((string) $item->name) => (string) $item->value]);
        $revenueCodes = $this->reportServiceCodes($config->get('revenue'), ['RM']);
        $extraRollawayCodes = $this->reportServiceCodes($config->get('revenueer'), ['ER']);
        $averageIncludesOther = $this->reportBooleanConfig($config->get('averageroomrateincludedothersroomrevenue'), true);
        $focIncludesCompliment = $this->reportBooleanConfig($config->get('focbaogomcompliment'), false);

        $totalRooms = Room::query()
            ->where(static function ($query): void {
                $query->whereNull('is_internal')->orWhere('is_internal', 0);
            })
            ->where('room_number', 'not like', '0%')
            ->count();
        $oooRooms = RoomLock::query()
            ->where('lock_type', 'OOO')
            ->where('is_active', 1)
            ->whereDate('start_date', '<=', $snapshotDate)
            ->whereDate('end_date', '>=', $snapshotDate)
            ->distinct('room_number')
            ->count('room_number');
        $roomAvailable = max(0, $totalRooms - $oooRooms);

        DB::table('night_audit_company_occupancy_daily_snapshots')->insert([
            'night_audit_run_id' => $run->id,
            'snapshot_date' => $snapshotDate,
            'total_rooms' => $totalRooms,
            'ooo_rooms' => $oooRooms,
            'room_available' => $roomAvailable,
            'revenue_service_codes' => implode(',', $revenueCodes),
            'revenue_er_service_codes' => implode(',', $extraRollawayCodes),
            'average_room_rate_include_other_revenue' => $averageIncludesOther,
            'foc_include_compliment' => $focIncludesCompliment,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $bills = DB::table('service_bills as sb')
            ->leftJoin('room_night_bills as rnb', 'rnb.bill_id', '=', 'sb.Ma')
            ->leftJoin('booking_rooms as br', 'br.id', '=', DB::raw('COALESCE(sb.RentalRoomId2, sb.RentalRoomId1)'))
            ->leftJoin('bookings as b', 'b.id', '=', DB::raw('COALESCE(br.booking_id, NULLIF(sb.RegisterID2, 0), sb.RegisterId1)'))
            ->leftJoin('companies as c', 'c.id', '=', DB::raw('COALESCE(b.company_id, sb.CompanyId2, sb.CompanyId1)'))
            ->leftJoin('markets as m', 'm.id', '=', 'b.market_id')
            ->leftJoin('customer_sources as cs', 'cs.id', '=', 'b.customer_source_id')
            ->leftJoin('registration_statuses as rs', 'rs.id', '=', 'b.registration_status_id')
            ->leftJoin('rooms as r', 'r.room_number', '=', 'br.room_number')
            ->leftJoin('room_classes as rc', 'rc.id', '=', 'br.room_class_id')
            ->whereDate('sb.Date', $snapshotDate)
            ->where('sb.Edit', 0)
            ->where('sb.Status', '<>', 3)
            ->whereNotNull('b.id')
            ->whereNull('b.deleted_at')
            ->whereNotIn('b.status', [3, 4])
            ->where(static function ($query): void {
                $query->whereNull('rs.is_availability')->orWhere('rs.is_availability', 1);
            })
            ->where(static function ($query): void {
                $query->whereNull('r.is_internal')->orWhere('r.is_internal', 0);
            })
            ->where(static function ($query): void {
                $query->whereNull('r.room_number')->orWhere('r.room_number', 'not like', '0%');
            })
            ->get([
                'sb.Ma as bill_id', 'sb.DepartmentId as department_id', 'sb.ServiceId as service_id', 'sb.Amount as amount',
                'rnb.is_room_night', 'rnb.adult as rnb_adult', 'rnb.child as rnb_child', 'rnb.breakfast_amount', 'rnb.rate_code as rnb_rate_code', 'rnb.rate as rnb_rate',
                'b.id as booking_id', 'b.booking_name', 'b.booking_date', 'b.arrival_date', 'b.departure_date', 'b.sales_person', 'b.created_by', 'b.external_booking_code',
                'br.id as booking_room_id', 'br.adults as booking_room_adults', 'br.children_qty as booking_room_children', 'br.babies as booking_room_babies',
                'c.id as company_id', 'c.code as company_code', 'c.name as company_name',
                'm.id as market_id', 'm.code as market_code', 'm.name as market_name',
                'cs.id as source_id', 'cs.code as source_code', 'cs.name as source_name',
                'r.area as area_code', 'rc.code as room_type',
            ]);

        if ($bills->isEmpty()) {
            return 0;
        }

        $billIds = $bills->pluck('bill_id')->unique()->values()->all();
        $detailByBill = [];
        foreach (DB::table('service_bill_details')->whereIn('BillServiceId', $billIds)->get() as $detail) {
            $billId = (int) $detail->BillServiceId;
            $code = strtoupper(trim((string) $detail->ServiceId));
            $detailByBill[$billId] ??= ['breakfast' => 0.0, 'original' => 0.0];
            if ($code === 'BF') {
                $detailByBill[$billId]['breakfast'] += (float) $detail->Amount;
            }
            if (in_array($code, $revenueCodes, true) || $code === 'EB' || in_array($code, $extraRollawayCodes, true)) {
                $detailByBill[$billId]['original'] += (float) ($detail->OriginalAmount ?? $detail->DetailBillOriginalAmount ?? $detail->Amount);
            }
        }

        $prefix = (string) (HotelSetting::query()->value('prefix_booking_id') ?? '');
        $bookingIds = $bills->pluck('booking_id')->filter()->unique()->values()->all();
        $nationalitiesByBooking = [];
        if ($bookingIds !== []) {
            foreach (DB::table('booking_room_guests as brg')
                ->join('booking_rooms as br', 'br.id', '=', 'brg.booking_room_id')
                ->join('guests as g', 'g.id', '=', 'brg.guest_id')
                ->whereIn('br.booking_id', $bookingIds)
                ->where('brg.status', '<>', 3)
                ->get(['br.booking_id', 'g.nationality_code']) as $guest) {
                if (! empty($guest->nationality_code)) {
                    $nationalitiesByBooking[$guest->booking_id][(string) $guest->nationality_code] = true;
                }
            }
        }

        $groups = [];
        foreach ($bills as $bill) {
            $bookingId = (int) $bill->booking_id;
            if ($bookingId <= 0) {
                continue;
            }

            $groups[$bookingId] ??= [
                'night_audit_run_id' => $run->id,
                'snapshot_date' => $snapshotDate,
                'booking_id' => $bookingId,
                'booking_code' => $prefix.$bookingId,
                'booking_name' => $bill->booking_name,
                'booking_date' => $bill->booking_date,
                'arrival_date' => $bill->arrival_date,
                'departure_date' => $bill->departure_date,
                'company_id' => $bill->company_id,
                'company_code' => $bill->company_code,
                'company_name' => $bill->company_name ?: 'KHÁCH LẺ',
                'market_id' => $bill->market_id,
                'market_code' => $bill->market_code,
                'market_name' => $bill->market_name,
                'source_id' => $bill->source_id,
                'source_code' => $bill->source_code,
                'source_name' => $bill->source_name,
                'user_sale' => $bill->sales_person ?: $bill->created_by,
                'area_codes' => [],
                'room_types' => [],
                'nationalities' => $nationalitiesByBooking[$bookingId] ?? [],
                'room_ids' => [],
                'room_qty' => 0,
                'guest_qty' => 0,
                'room_night' => 0,
                'guest_night' => 0,
                'foc_room_nights' => 0,
                'hu_room_nights' => 0,
                'room_revenue' => 0.0,
                'breakfast_revenue' => 0.0,
                'extra_bed_revenue' => 0.0,
                'extra_rollaway_revenue' => 0.0,
                'fb_revenue' => 0.0,
                'other_revenue' => 0.0,
                'total_by_date' => 0.0,
                'original_total' => 0.0,
            ];

            $group = &$groups[$bookingId];
            $serviceCode = strtoupper(trim((string) $bill->service_id));
            $department = strtoupper(trim((string) $bill->department_id));
            $amount = (float) $bill->amount;
            $detail = $detailByBill[(int) $bill->bill_id] ?? ['breakfast' => 0.0, 'original' => 0.0];
            $isRoomRevenue = in_array($serviceCode, $revenueCodes, true);
            $isExtraBed = $serviceCode === 'EB';
            $isExtraRollaway = in_array($serviceCode, $extraRollawayCodes, true);

            if (! empty($bill->area_code)) {
                $group['area_codes'][(string) $bill->area_code] = true;
            }
            if (! empty($bill->room_type)) {
                $group['room_types'][(string) $bill->room_type] = true;
            }

            if ($department === 'FB') {
                $group['fb_revenue'] += $amount;
            } elseif ($isRoomRevenue) {
                $group['room_revenue'] += $amount;
                $group['total_by_date'] += $amount;
                $group['breakfast_revenue'] += (float) ($bill->breakfast_amount ?: $detail['breakfast']);
                $group['original_total'] += $detail['original'] ?: $amount;
            } elseif ($isExtraBed) {
                $group['extra_bed_revenue'] += $amount;
                $group['original_total'] += $detail['original'] ?: $amount;
            } elseif ($isExtraRollaway) {
                $group['extra_rollaway_revenue'] += $amount;
                $group['original_total'] += $detail['original'] ?: $amount;
            } elseif ($serviceCode === 'BF') {
                $group['room_revenue'] += $amount;
                $group['breakfast_revenue'] += $amount;
                $group['total_by_date'] += $amount;
            } else {
                $group['other_revenue'] += $amount;
            }

            $isRoomNight = $isRoomRevenue && ($bill->is_room_night === null || (int) $bill->is_room_night === 1);
            if ($isRoomNight) {
                $group['room_night']++;
                $guests = (int) ($bill->rnb_adult ?? $bill->booking_room_adults ?? 0) + (int) ($bill->rnb_child ?? $bill->booking_room_children ?? 0);
                $group['guest_night'] += $guests;
                $rateCode = strtoupper((string) ($bill->rnb_rate_code ?? ''));
                if ($rateCode === 'HU') {
                    $group['hu_room_nights']++;
                } elseif (str_starts_with($rateCode, 'FOC') || (float) ($bill->rnb_rate ?? $amount) === 0.0) {
                    $group['foc_room_nights']++;
                }
                if (! empty($bill->booking_room_id) && ! isset($group['room_ids'][$bill->booking_room_id])) {
                    $group['room_ids'][$bill->booking_room_id] = true;
                    $group['room_qty']++;
                    $group['guest_qty'] += (int) ($bill->booking_room_adults ?? 0) + (int) ($bill->booking_room_children ?? 0) + (int) ($bill->booking_room_babies ?? 0);
                }
            }
            unset($group);
        }

        $records = [];
        foreach ($groups as $group) {
            unset($group['room_ids']);
            $group['area_codes'] = implode(',', array_keys($group['area_codes']));
            $group['room_types'] = implode(', ', array_keys($group['room_types']));
            $group['nationalities'] = implode(', ', array_keys($group['nationalities']));
            $group['created_at'] = $now;
            $group['updated_at'] = $now;
            $records[] = $group;
        }

        foreach (array_chunk($records, 100) as $chunk) {
            DB::table('night_audit_company_occupancy_snapshots')->insert($chunk);
        }

        return count($records);
    }

    private function reportServiceCodes(?string $value, array $fallback): array
    {
        $codes = preg_split('/[\s,;|]+/', strtoupper((string) $value), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        return $codes === [] ? $fallback : array_values(array_unique($codes));
    }

    private function reportBooleanConfig(?string $value, bool $fallback): bool
    {
        if ($value === null || trim($value) === '') {
            return $fallback;
        }

        return ! in_array(strtolower(trim($value)), ['0', 'false', 'no', 'off'], true);
    }

            /**
     * SP7001: Chi tiết khách & phòng in-house theo ngày (Inhouse Backup)
     */
    public function captureInhouse(Carbon $closingDate, NightAuditRun $run): int
    {
        $dateStr = $closingDate->toDateString();

        // Xóa snapshot cũ cùng ngày nếu rerun trong transaction
        NightAuditInhouseSnapshot::whereDate('snapshot_date', $dateStr)->delete();

        // Khách & phòng đang ở tại ngày đóng (status = 1, arrival_date <= closingDate, departure_date >= closingDate)
        $inhouseRooms = BookingRoom::with(['booking.company', 'roomClass', 'room.roomForm', 'guests.guest'])
            ->where('status', BookingRoom::STATUS_CHECKED_IN)
            ->where('status', '!=', BookingRoom::STATUS_MOVED)
            ->whereDate('arrival_date', '<=', $dateStr)
            ->whereDate('departure_date', '>=', $dateStr)
            ->get();

        $nationalities = Nationality::all();
        $natMap = [];
        foreach ($nationalities as $n) {
            if ($n->nationality_id) $natMap[$n->nationality_id] = $n->nationality_name;
            if ($n->nationality_code) $natMap[$n->nationality_code] = $n->nationality_name;
            if ($n->asm_code) $natMap[$n->asm_code] = $n->nationality_name;
        }

        $records = [];

        foreach ($inhouseRooms as $br) {
            $booking = $br->booking;
            $company = $booking?->company;
            $roomClass = $br->roomClass;
            $roomForm = $br->room?->roomForm;

            $guestEntries = $br->guests;
            if ($guestEntries->isEmpty()) {
                // Nếu phòng chưa có guest entry cụ thể, lưu 1 bản ghi đại diện
                $records[] = [
                    'night_audit_run_id' => $run->id,
                    'snapshot_date'      => $dateStr,
                    'rental_room_id'     => $br->id,
                    'customer_id'        => null,
                    'status'             => $br->status,
                    'checkout_date'      => $br->departure_date,
                    'checkout_time'      => null,
                    'position_id'        => null,
                    'user_checkin'       => $br->check_in_user ?? 'system',
                    'user_checkout'      => null,
                    'breakfast'          => $br->breakfast ? 1 : 0,
                    'position'           => null,
                    'guest'              => $booking?->booking_name ?: 'Khách lẻ',
                    'note'               => $br->note,
                    'country'            => null,
                    'nationality'        => null,
                    'nationality_name'   => null,
                    'passport'           => null,
                    'birthday'           => null,
                    'guest_name'         => $booking?->booking_name ?: 'Khách lẻ',
                    'address'            => null,
                    'phone'              => $booking?->phone,
                    'fax'                => null,
                    'email'              => $booking?->email,
                    'issue_date'         => null,
                    'visa'               => null,
                    'visa_date'          => null,
                    'booking_id'         => $booking?->id,
                    'arrival_date'       => $br->arrival_date,
                    'arrival_time'       => null,
                    'num_of_days'        => $br->NumOfDays ?? ($br->ActutalNumOfDays ?? 1),
                    'room'               => $br->room_number,
                    'adult'              => $br->adults ?? 1,
                    'child'              => $br->children_qty ?? 0,
                    'extra_bed'          => $br->extra_bed_qty ?? 0,
                    'rate'               => $br->rate ?? 0,
                    'room_rate_code'     => $br->rate_code,
                    'breakfast_room'     => $br->breakfast ? 1 : 0,
                    'breakfast_child'    => 0,
                    'room_kind'          => $roomForm?->name,
                    'room_type'          => $roomClass?->code,
                    'departure_date'     => $br->departure_date,
                    'booking_name'       => $booking?->booking_name,
                    'contact'            => $booking?->contact_name,
                    'company'            => $company?->name ?: 'Khách lẻ',
                    'company_id'         => $booking?->company_id,
                    'group_status'       => 0,
                    'orders'             => 0,
                    'house_use'          => strtoupper((string)$br->rate_code) === 'HU' ? 1 : 0,
                    'pack3'              => null,
                    'guest_type'         => 6,
                    'day_use'            => (int) ($br->is_day_use ?? 0),
                    'baby_cot'           => (int) ($br->baby_cot_qty ?? 0),
                    'gender'             => 0,
                    'created_at'         => now(),
                    'updated_at'         => now(),
                ];
            } else {
                foreach ($guestEntries as $brg) {
                    $g = $brg->guest;
                    $natCode = $g?->nationality_code;
                    $natName = $natCode ? ($natMap[$natCode] ?? $natCode) : null;
                    $records[] = [
                        'night_audit_run_id' => $run->id,
                        'snapshot_date'      => $dateStr,
                        'rental_room_id'     => $br->id,
                        'customer_id'        => $g?->id,
                        'status'             => $br->status,
                        'checkout_date'      => $br->departure_date,
                        'checkout_time'      => null,
                        'position_id'        => null,
                        'user_checkin'       => $br->check_in_user ?? 'system',
                        'user_checkout'      => null,
                        'breakfast'          => $br->breakfast ? 1 : 0,
                        'position'           => null,
                        'guest'              => $g?->full_name ?: $booking?->booking_name,
                        'note'               => $br->note ?: $g?->note,
                        'country'            => $natCode,
                        'nationality'        => $natCode,
                        'nationality_name'   => $natName,
                        'passport'           => $g?->passport_number ?: $g?->id_number,
                        'birthday'           => $g?->dob?->toDateString(),
                        'guest_name'         => $g?->full_name ?: $booking?->booking_name,
                        'address'            => $g?->address,
                        'phone'              => $g?->phone ?: $booking?->phone,
                        'fax'                => null,
                        'email'              => $g?->email ?: $booking?->email,
                        'issue_date'         => $g?->id_issue_date?->toDateString(),
                        'visa'               => $g?->visa_no,
                        'visa_date'          => $g?->visa_expiry_date?->toDateString(),
                        'booking_id'         => $booking?->id,
                        'arrival_date'       => $brg->actual_arrival_date ?: $br->arrival_date,
                        'arrival_time'       => null,
                        'num_of_days'        => $br->NumOfDays ?? ($br->ActutalNumOfDays ?? 1),
                        'room'               => $br->room_number,
                        'adult'              => $br->adults ?? 1,
                        'child'              => $br->children_qty ?? 0,
                        'extra_bed'          => $br->extra_bed_qty ?? 0,
                        'rate'               => $br->rate ?? 0,
                        'room_rate_code'     => $br->rate_code,
                        'breakfast_room'     => $br->breakfast ? 1 : 0,
                        'breakfast_child'    => 0,
                        'room_kind'          => $roomForm?->name,
                        'room_type'          => $roomClass?->code,
                        'departure_date'     => $br->departure_date,
                        'booking_name'       => $booking?->booking_name,
                        'contact'            => $booking?->contact_name,
                        'company'            => $company?->name ?: 'Khách lẻ',
                        'company_id'         => $booking?->company_id,
                        'group_status'       => 0,
                        'orders'             => 0,
                        'house_use'          => strtoupper((string)$br->rate_code) === 'HU' ? 1 : 0,
                        'pack3'              => null,
                        'guest_type'         => 6,
                        'day_use'            => (int) ($br->is_day_use ?? 0),
                        'baby_cot'           => (int) ($br->baby_cot_qty ?? 0),
                        'gender'             => (int) ($g?->gender ?? 0),
                        'created_at'         => now(),
                        'updated_at'         => now(),
                    ];
                }
            }
        }

        if (!empty($records)) {
            // Bulk insert in chunks to keep memory usage low
            foreach (array_chunk($records, 100) as $chunk) {
                NightAuditInhouseSnapshot::insert($chunk);
            }
        }

        return count($records);
    }


/**
     * SP7003: Dự báo & tổng hợp bán phòng theo ngày (Room Sales Forecast)
     */
    public function captureRoomSalesForecast(Carbon $closingDate, NightAuditRun $run): int
    {
        $dateStr = $closingDate->toDateString();

        // Xóa snapshot cũ cùng ngày nếu rerun trong transaction
        NightAuditRoomSalesForecastSnapshot::whereDate('snapshot_date', $dateStr)->delete();

        // 1. Departures on closingDate
        $depRooms = BookingRoom::whereDate('departure_date', $dateStr)
            ->whereIn('status', [BookingRoom::STATUS_CHECKED_IN, BookingRoom::STATUS_CHECKED_OUT])
            ->where('status', '!=', BookingRoom::STATUS_MOVED)
            ->get();
        $depRoomsCount = $depRooms->count();
        $depAdult = (int) $depRooms->sum('adults');
        $depChild = (int) $depRooms->sum('children_qty');

        // 2. Arrivals on closingDate
        $arrRooms = BookingRoom::whereDate('arrival_date', $dateStr)
            ->whereIn('status', [BookingRoom::STATUS_BOOKED, BookingRoom::STATUS_CHECKED_IN])
            ->where('status', '!=', BookingRoom::STATUS_MOVED)
            ->get();
        $arrRoomsCount = $arrRooms->count();
        $arrAdult = (int) $arrRooms->sum('adults');
        $arrChild = (int) $arrRooms->sum('children_qty');

        // 3. In-house on closingDate
        $inhouseRooms = BookingRoom::where('status', BookingRoom::STATUS_CHECKED_IN)
            ->where('status', '!=', BookingRoom::STATUS_MOVED)
            ->whereDate('arrival_date', '<=', $dateStr)
            ->whereDate('departure_date', '>=', $dateStr)
            ->get();
        $occRooms = $inhouseRooms->count();
        $occAdult = (int) $inhouseRooms->sum('adults');
        $occChild = (int) $inhouseRooms->sum('children_qty');

        $houseUse = $inhouseRooms->filter(fn ($r) => strtoupper((string)$r->rate_code) === 'HU')->count();
        $focAll = $inhouseRooms->filter(fn ($r) => str_starts_with(strtoupper((string)$r->rate_code), 'FOC') || (float)$r->rate === 0.0)->count();
        $foc = $inhouseRooms->filter(fn ($r) => strtoupper((string)$r->rate_code) === 'FOC')->count();
        $focOwner = $inhouseRooms->filter(fn ($r) => strtoupper((string)$r->rate_code) === 'FOC_OWNER' || strtoupper((string)$r->rate_code) === 'FOCOWNER')->count();

        $roomSales = max(0, $occRooms - $houseUse);
        $extraBed = (int) $inhouseRooms->sum('extra_bed_qty');
        $babyCot = (int) $inhouseRooms->sum('baby_cot_qty');

        // 4. Doanh thu RM, EB, ER từ ServiceBill đã post
        $rmBills = ServiceBill::whereDate('Date', $dateStr)
            ->where('DepartmentId', 'FO')
            ->where('ServiceId', 'RM')
            ->where('Edit', 0)
            ->sum('Amount');

        $ebBills = ServiceBill::whereDate('Date', $dateStr)
            ->where('DepartmentId', 'FO')
            ->where('ServiceId', 'EB')
            ->where('Edit', 0)
            ->sum('Amount');

        $erBills = ServiceBill::whereDate('Date', $dateStr)
            ->where('DepartmentId', 'FO')
            ->where('ServiceId', 'ER')
            ->where('Edit', 0)
            ->sum('Amount');

        $totalRevenue = (float) $rmBills + (float) $ebBills + (float) $erBills;

        $avgRate = $roomSales > 0 ? round($totalRevenue / $roomSales, 2) : 0;
        $avgRate2 = ($roomSales - $focAll) > 0 ? round($totalRevenue / ($roomSales - $focAll), 2) : 0;

        // 5. Total Rooms & OOO
        $totalPhysicalRooms = Room::where(function ($q) {
            $q->whereNull('is_internal')->orWhere('is_internal', 0);
        })->where(function ($q) {
            $q->whereNull('room_number')->orWhere('room_number', 'not like', '0%');
        })->count();

        $oooRoom = RoomLock::where('lock_type', 'OOO')
            ->where('is_active', 1)
            ->whereDate('start_date', '<=', $dateStr)
            ->whereDate('end_date', '>=', $dateStr)
            ->count();

        $roomAvailable = max(1, $totalPhysicalRooms - $oooRoom);
        $percentOccupancy = round(($occRooms / $roomAvailable) * 100, 4);
        $percentOccupancy2 = round((max(0, $occRooms - $houseUse - $focAll) / $roomAvailable) * 100, 4);

        NightAuditRoomSalesForecastSnapshot::create([
            'night_audit_run_id'  => $run->id,
            'snapshot_date'       => $dateStr,
            'dep_adult'           => $depAdult,
            'dep_child'           => $depChild,
            'dep_rooms'           => $depRoomsCount,
            'arr_adult'           => $arrAdult,
            'arr_child'           => $arrChild,
            'arr_rooms'           => $arrRoomsCount,
            'occ_adult'           => $occAdult,
            'occ_child'           => $occChild,
            'occ_rooms'           => $occRooms,
            'house_use'           => $houseUse,
            'foc_all'             => $focAll,
            'foc'                 => $foc,
            'foc_owner'           => $focOwner,
            'room_sales'          => $roomSales,
            'extra_bed'           => $extraBed,
            'revenue'             => $totalRevenue,
            'avg_rate'            => $avgRate,
            'avg_rate_2'          => $avgRate2,
            'room_available'      => $roomAvailable,
            'percent_occupancy'   => $percentOccupancy,
            'percent_occupancy_2' => $percentOccupancy2,
            'baby_cot'            => $babyCot,
            'rm'                  => (float) $rmBills,
            'eb'                  => (float) $ebBills,
            'er'                  => (float) $erBills,
            'ooo_room'            => $oooRoom,
        ]);

        return 1;
    }

    /**
     * SP7005: Thống kê số lượng phòng và loại phòng (Room Type Statistics)
     */
    public function captureRoomType(Carbon $closingDate, NightAuditRun $run): int
    {
        $dateStr = $closingDate->toDateString();

        // Xóa snapshot cũ cùng ngày nếu rerun trong transaction
        NightAuditRoomTypeSnapshot::whereDate('snapshot_date', $dateStr)->delete();

        // Lấy tất cả room classes
        $roomClasses = RoomClass::all();

        // Lấy toàn bộ bills RM và RNB cho ngày đóng
        $bills = ServiceBill::whereDate('Date', $dateStr)
            ->where('DepartmentId', 'FO')
            ->where('ServiceId', 'RM')
            ->where('Edit', 0)
            ->get();
        $billIds = $bills->pluck('Ma');
        $rnbs = RoomNightBill::whereIn('bill_id', $billIds)->get();

        $totalHotelRevenue = (float) $bills->sum('Amount');
        $records = [];

        foreach ($roomClasses as $rc) {
            $classRooms = Room::where('room_class_id', $rc->id)
                ->where(function ($q) {
                    $q->whereNull('is_internal')->orWhere('is_internal', 0);
                })
                ->where(function ($q) {
                    $q->whereNull('room_number')->orWhere('room_number', 'not like', '0%');
                })
                ->pluck('room_number');

            $inventory = $classRooms->count();

            // OOO locks cho loại phòng này
            $ooo = RoomLock::whereIn('room_number', $classRooms)
                ->where('lock_type', 'OOO')
                ->where('is_active', 1)
                ->whereDate('start_date', '<=', $dateStr)
                ->whereDate('end_date', '>=', $dateStr)
                ->count();

            $available = max(0, $inventory - $ooo);

            // Số đêm (room-nights) và doanh thu của loại phòng này
            $classRnbs = $rnbs->filter(fn ($rnb) => (string)$rnb->room_type_id === (string)$rc->id && (int)$rnb->is_room_night === 1);
            $noOfNight = $classRnbs->count();

            $classBillIds = $classRnbs->pluck('bill_id');
            $classBills = $bills->whereIn('Ma', $classBillIds);
            $rev = (float) $classBills->sum('Amount');

            $adr = $noOfNight > 0 ? round($rev / $noOfNight, 2) : 0;
            $revPercent = $totalHotelRevenue > 0 ? round(($rev / $totalHotelRevenue) * 100, 4) : 0;
            $occPercent = $available > 0 ? round(($noOfNight / $available) * 100, 4) : 0;

            $records[] = [
                'night_audit_run_id' => $run->id,
                'snapshot_date'      => $dateStr,
                'room_type_id'       => $rc->id,
                'room_type'          => $rc->name,
                'room_type_code'     => $rc->code ?: (string)$rc->id,
                'inventory'          => $inventory,
                'ooo'                => $ooo,
                'room_available'     => $available,
                'no_of_night'        => $noOfNight,
                'adr'                => $adr,
                'revenue'            => $rev,
                'revenue_percent'    => $revPercent,
                'occupancy_percent'  => $occPercent,
                'created_at'         => now(),
                'updated_at'         => now(),
            ];
        }

        if (!empty($records)) {
            NightAuditRoomTypeSnapshot::insert($records);
        }

        return count($records);
    }

    /**
     * Xác minh tính toàn vẹn (Invariants & Totals Verification) trước khi commit.
     */
    public function verifyInvariants(Carbon $closingDate, NightAuditRun $run): array
    {
        $dateStr = $closingDate->toDateString();

        // 1. Kiểm tra forecast snapshot đã được ghi
        $forecast = NightAuditRoomSalesForecastSnapshot::whereDate('snapshot_date', $dateStr)
            ->where('night_audit_run_id', $run->id)
            ->first();

        if (!$forecast) {
            throw new RuntimeException("Lỗi toàn vẹn: Không tìm thấy snapshot dự báo bán phòng cho ngày {$dateStr}.");
        }

        // 2. Kiểm tra room available không âm
        if ($forecast->room_available < 0) {
            throw new RuntimeException("Lỗi toàn vẹn: Số lượng phòng khả dụng bị âm ({$forecast->room_available}).");
        }

        // 3. Kiểm tra tổng doanh thu RM giữa bills và forecast snapshot
        $postedRoomRevenue = (float) ServiceBill::whereDate('Date', $dateStr)
            ->where('DepartmentId', 'FO')
            ->where('ServiceId', 'RM')
            ->where('Edit', 0)
            ->sum('Amount');

        if (abs($postedRoomRevenue - (float)$forecast->rm) > 0.01) {
            throw new RuntimeException(
                "Lỗi toàn vẹn: Doanh thu tiền phòng lệch giữa bills ({$postedRoomRevenue}) và snapshot ({$forecast->rm})."
            );
        }

        return [
            'forecast_verified'   => true,
            'room_available'      => $forecast->room_available,
            'posted_room_revenue' => $postedRoomRevenue,
            'snapshot_rm_revenue' => (float) $forecast->rm,
        ];
    }
}
