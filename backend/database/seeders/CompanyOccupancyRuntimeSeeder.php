<?php

namespace Database\Seeders;

use App\Models\NightAuditRun;
use App\Services\NightAuditSnapshotService;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class CompanyOccupancyRuntimeSeeder extends Seeder
{
    public function run(): void
    {
        $connections = ['mysql', 'mysql_hkt1'];

        foreach ($connections as $conn) {
            $this->seedConnection($conn);
        }
    }

    private function seedConnection(string $conn): void
    {
        $db = DB::connection($conn);

        // 1. Ensure the 14 companies exist
        $companies = [
            ['code' => 'CTY0051', 'name' => 'PEGAS', 'market' => 'QT', 'source' => 'TA'],
            ['code' => 'CTY0001', 'name' => 'KHÁCH LẺ', 'market' => 'ND', 'source' => 'DIRECT'],
            ['code' => 'CTY0210', 'name' => 'TRAVEL CONCIERGE - (COMMITMENT)', 'market' => 'QT', 'source' => 'TA'],
            ['code' => 'CTY0040', 'name' => 'GREENTRAVEL GROUP', 'market' => 'ND', 'source' => 'TA'],
            ['code' => 'CTY0132', 'name' => 'ANEX TOUR', 'market' => 'QT', 'source' => 'TA'],
            ['code' => 'CTY0004', 'name' => 'TRIP.COM', 'market' => 'QT', 'source' => 'OTA'],
            ['code' => 'CTY0087', 'name' => 'FUN & SUN TRAVEL', 'market' => 'QT', 'source' => 'TA'],
            ['code' => 'CTY0003', 'name' => 'BOOKING.COM', 'market' => 'QT', 'source' => 'OTA'],
            ['code' => 'CTY0018', 'name' => 'THIÊN NHÂN TRAVEL', 'market' => 'ND', 'source' => 'TA'],
            ['code' => 'CTY0052', 'name' => 'SELFIE TRAVEL', 'market' => 'QT', 'source' => 'TA'],
            ['code' => 'CTY0121', 'name' => 'ODEON TOURS', 'market' => 'QT', 'source' => 'TA'],
            ['code' => 'CTY0166', 'name' => 'AMEGA TRAVEL', 'market' => 'QT', 'source' => 'TA'],
            ['code' => 'CTY0002', 'name' => 'AGODA', 'market' => 'QT', 'source' => 'OTA'],
            ['code' => 'CTY0050', 'name' => 'ALLOTMENT', 'market' => 'ND', 'source' => 'TA'],
        ];

        $companyIdMap = [];
        foreach ($companies as $c) {
            $existing = $db->table('companies')->where('code', $c['code'])->first();
            if ($existing) {
                $db->table('companies')->where('id', $existing->id)->update([
                    'name' => $c['name'],
                    'updated_at' => now(),
                ]);
                $companyIdMap[$c['code']] = $existing->id;
            } else {
                $newId = $db->table('companies')->insertGetId([
                    'code' => $c['code'],
                    'name' => $c['name'],
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                $companyIdMap[$c['code']] = $newId;
            }
        }

        // 2. Ensure Market & Customer Sources exist
        $marketNd = $db->table('markets')->where('code', 'ND')->first();
        $marketNdId = $marketNd ? $marketNd->id : $db->table('markets')->insertGetId(['code' => 'ND', 'name' => 'Nội địa', 'created_at' => now(), 'updated_at' => now()]);

        $marketQt = $db->table('markets')->where('code', 'QT')->first();
        $marketQtId = $marketQt ? $marketQt->id : $db->table('markets')->insertGetId(['code' => 'QT', 'name' => 'Quốc tế', 'created_at' => now(), 'updated_at' => now()]);

        $sourceDirect = $db->table('customer_sources')->where('code', 'DIRECT')->first();
        $sourceDirectId = $sourceDirect ? $sourceDirect->id : $db->table('customer_sources')->insertGetId(['code' => 'DIRECT', 'name' => 'Trực tiếp', 'created_at' => now(), 'updated_at' => now()]);

        $sourceTa = $db->table('customer_sources')->where('code', 'TA')->first();
        $sourceTaId = $sourceTa ? $sourceTa->id : $db->table('customer_sources')->insertGetId(['code' => 'TA', 'name' => 'Đại lý / Công ty', 'created_at' => now(), 'updated_at' => now()]);

        $sourceOta = $db->table('customer_sources')->where('code', 'OTA')->first();
        $sourceOtaId = $sourceOta ? $sourceOta->id : $db->table('customer_sources')->insertGetId(['code' => 'OTA', 'name' => 'OTA', 'created_at' => now(), 'updated_at' => now()]);

        // 3. Ensure room areas are set for area filtering testing
        $db->table('rooms')->whereIn('room_number', ['101', '102', '103', '104', '105'])->update(['area' => 'Khu A']);
        $db->table('rooms')->whereIn('room_number', ['106', '107', '108', '109', '110'])->update(['area' => 'Khu B']);

        // 4. Ensure Room Available Snapshots
        $db->table('night_audit_room_type_snapshots')->where('snapshot_date', '2026-08-20')->delete();
        $db->table('night_audit_room_type_snapshots')->insert([
            'night_audit_run_id' => 1,
            'snapshot_date' => '2026-08-20',
            'room_type_id' => 1,
            'room_type' => 'Standard',
            'room_type_code' => 'STD',
            'inventory' => 131,
            'ooo' => 2,
            'room_available' => 129,
            'no_of_night' => 93,
            'adr' => 594380,
            'revenue' => 55277333,
            'occupancy_percent' => 72.09,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $db->table('night_audit_room_type_snapshots')->where('snapshot_date', '2026-08-21')->delete();
        $db->table('night_audit_room_type_snapshots')->insert([
            'night_audit_run_id' => 1,
            'snapshot_date' => '2026-08-21',
            'room_type_id' => 1,
            'room_type' => 'Standard',
            'room_type_code' => 'STD',
            'inventory' => 131,
            'ooo' => 5,
            'room_available' => 126,
            'no_of_night' => 20,
            'adr' => 650000,
            'revenue' => 13000000,
            'occupancy_percent' => 15.87,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // 5. Clean up previous test seed bookings
        $oldBookingIds = $db->table('bookings')->where('note', 'like', 'SEED_COMPANY_OCCUPANCY_%')->pluck('id')->all();
        if (!empty($oldBookingIds)) {
            $db->table('room_night_bills')->whereIn('bill_id', function ($q) use ($oldBookingIds) {
                $q->select('Ma')->from('service_bills')->whereIn('RegisterID2', $oldBookingIds);
            })->delete();
            $db->table('service_bills')->whereIn('RegisterID2', $oldBookingIds)->delete();
            $db->table('booking_rooms')->whereIn('booking_id', $oldBookingIds)->delete();
            $db->table('bookings')->whereIn('id', $oldBookingIds)->delete();
        }

        // 6. Specs exactly matching legacy report 20/08/2026
        // CTY0001 (KHÁCH LẺ): room_rev = 7,760,000 (11 nights), extra_bed_rev = 1,000,000, other_rev = 775,000.
        // CTY0040 (GREENTRAVEL): bf_rev = 400,000 (50k/night x 8) for testing breakfast filter.
        $specsDay1 = [
            ['code' => 'CTY0051', 'nights' => 29, 'guests' => 65, 'foc' => 5, 'room_rev' => 17830000, 'extra_bed_rev' => 0,       'bf_rev' => 0,      'fb_rev' => 0, 'other_rev' => 1445000, 'market' => $marketQtId, 'source' => $sourceTaId,     'room' => '101'],
            ['code' => 'CTY0001', 'nights' => 11, 'guests' => 23, 'foc' => 0, 'room_rev' => 7760000,  'extra_bed_rev' => 1000000, 'bf_rev' => 0,      'fb_rev' => 0, 'other_rev' => 775000,  'market' => $marketNdId, 'source' => $sourceDirectId, 'room' => '106'],
            ['code' => 'CTY0210', 'nights' => 12, 'guests' => 25, 'foc' => 0, 'room_rev' => 7920000,  'extra_bed_rev' => 0,       'bf_rev' => 0,      'fb_rev' => 0, 'other_rev' => -300000, 'market' => $marketQtId, 'source' => $sourceTaId,     'room' => '102'],
            ['code' => 'CTY0040', 'nights' => 8,  'guests' => 22, 'foc' => 1, 'room_rev' => 4840000,  'extra_bed_rev' => 0,       'bf_rev' => 400000, 'fb_rev' => 0, 'other_rev' => 500000,  'market' => $marketNdId, 'source' => $sourceTaId,     'room' => '103'],
            ['code' => 'CTY0132', 'nights' => 6,  'guests' => 13, 'foc' => 0, 'room_rev' => 4405000,  'extra_bed_rev' => 0,       'bf_rev' => 0,      'fb_rev' => 0, 'other_rev' => 105000,  'market' => $marketQtId, 'source' => $sourceTaId,     'room' => '104'],
            ['code' => 'CTY0004', 'nights' => 4,  'guests' => 12, 'foc' => 0, 'room_rev' => 3942000,  'extra_bed_rev' => 0,       'bf_rev' => 0,      'fb_rev' => 0, 'other_rev' => 200000,  'market' => $marketQtId, 'source' => $sourceOtaId,    'room' => '105'],
            ['code' => 'CTY0087', 'nights' => 5,  'guests' => 12, 'foc' => 0, 'room_rev' => 4030000,  'extra_bed_rev' => 0,       'bf_rev' => 0,      'fb_rev' => 0, 'other_rev' => 0,       'market' => $marketQtId, 'source' => $sourceTaId,     'room' => '107'],
            ['code' => 'CTY0003', 'nights' => 3,  'guests' => 9,  'foc' => 0, 'room_rev' => 2890000,  'extra_bed_rev' => 0,       'bf_rev' => 0,      'fb_rev' => 0, 'other_rev' => 1055000, 'market' => $marketQtId, 'source' => $sourceOtaId,    'room' => '108'],
            ['code' => 'CTY0018', 'nights' => 5,  'guests' => 10, 'foc' => 0, 'room_rev' => 2500000,  'extra_bed_rev' => 0,       'bf_rev' => 0,      'fb_rev' => 0, 'other_rev' => 115000,  'market' => $marketNdId, 'source' => $sourceTaId,     'room' => '109'],
            ['code' => 'CTY0052', 'nights' => 2,  'guests' => 4,  'foc' => 0, 'room_rev' => 1500000,  'extra_bed_rev' => 0,       'bf_rev' => 0,      'fb_rev' => 0, 'other_rev' => 1040000, 'market' => $marketQtId, 'source' => $sourceTaId,     'room' => '110'],
            ['code' => 'CTY0121', 'nights' => 2,  'guests' => 4,  'foc' => 0, 'room_rev' => 1170000,  'extra_bed_rev' => 0,       'bf_rev' => 0,      'fb_rev' => 0, 'other_rev' => 0,       'market' => $marketQtId, 'source' => $sourceTaId,     'room' => '101'],
            ['code' => 'CTY0166', 'nights' => 1,  'guests' => 1,  'foc' => 0, 'room_rev' => 650000,   'extra_bed_rev' => 0,       'bf_rev' => 0,      'fb_rev' => 0, 'other_rev' => 0,       'market' => $marketQtId, 'source' => $sourceTaId,     'room' => '102'],
            ['code' => 'CTY0002', 'nights' => 0,  'guests' => 0,  'foc' => 0, 'room_rev' => 600000,   'extra_bed_rev' => 0,       'bf_rev' => 0,      'fb_rev' => 0, 'other_rev' => 0,       'market' => $marketQtId, 'source' => $sourceOtaId,    'room' => '103'],
            ['code' => 'CTY0050', 'nights' => 9,  'guests' => 21, 'foc' => 8, 'room_rev' => 300000,   'extra_bed_rev' => 0,       'bf_rev' => 0,      'fb_rev' => 0, 'other_rev' => 60000,   'market' => $marketNdId, 'source' => $sourceTaId,     'room' => '104'],
        ];

        // Specs for Day 2 (2026-08-21) to support multi-day aggregation & group by date
        $specsDay2 = [
            ['code' => 'CTY0051', 'nights' => 10, 'guests' => 22, 'foc' => 1, 'room_rev' => 6500000, 'extra_bed_rev' => 0, 'bf_rev' => 0, 'fb_rev' => 0, 'other_rev' => 500000, 'market' => $marketQtId, 'source' => $sourceTaId,     'room' => '101'],
            ['code' => 'CTY0001', 'nights' => 5,  'guests' => 11, 'foc' => 0, 'room_rev' => 3500000, 'extra_bed_rev' => 0, 'bf_rev' => 0, 'fb_rev' => 0, 'other_rev' => 200000, 'market' => $marketNdId, 'source' => $sourceDirectId, 'room' => '106'],
            ['code' => 'CTY0040', 'nights' => 5,  'guests' => 12, 'foc' => 0, 'room_rev' => 3000000, 'extra_bed_rev' => 0, 'bf_rev' => 0, 'fb_rev' => 0, 'other_rev' => 300000, 'market' => $marketNdId, 'source' => $sourceTaId,     'room' => '103'],
        ];

        $billIdSeq = 920000;
        $this->seedDateBookings($db, '2026-08-20', $specsDay1, $billIdSeq, $companyIdMap, $companies);
        $this->seedDateBookings($db, '2026-08-21', $specsDay2, $billIdSeq, $companyIdMap, $companies);

        // 7. Re-capture snapshots for both dates
        $snapshotService = app(NightAuditSnapshotService::class);
        foreach (['2026-08-20', '2026-08-21'] as $snapshotDate) {
            $run = NightAuditRun::query()->create([
                'source_system_date' => $snapshotDate,
                'target_system_date' => $snapshotDate,
                'actual_started_at' => now(),
                'shift' => 'backfill',
                'username' => 'seeder',
                'status' => 'running',
                'idempotency_key' => 'company-occ-seed-' . $snapshotDate . '-' . uniqid(),
            ]);
            $snapshotService->captureCompanyOccupancyReport(Carbon::parse($snapshotDate), $run);
            $run->update(['status' => 'succeeded', 'actual_finished_at' => now()]);
        }
    }

    private function seedDateBookings($db, string $date, array $specs, int &$billIdSeq, array $companyIdMap, array $companies): void
    {
        $compNameMap = array_column($companies, 'name', 'code');

        foreach ($specs as $idx => $s) {
            $cId = $companyIdMap[$s['code']];
            $cName = $compNameMap[$s['code']];
            $roomNumber = $s['room'] ?? '101';

            $bookingId = $db->table('bookings')->insertGetId([
                'booking_name' => $cName,
                'arrival_date' => $date,
                'departure_date' => date('Y-m-d', strtotime($date . ' +1 day')),
                'num_of_days' => 1,
                'booking_date' => $date,
                'status' => 1,
                'company_id' => $cId,
                'market_id' => $s['market'],
                'customer_source_id' => $s['source'],
                'branch_id' => 1,
                'note' => 'SEED_COMPANY_OCCUPANCY_' . str_replace('-', '', $date),
                'created_by' => 'Demo',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $brId = (string)(820000 + $billIdSeq % 100000);
            $db->table('booking_rooms')->insert([
                'id' => $brId,
                'booking_id' => $bookingId,
                'room_number' => $roomNumber,
                'room_class_id' => 1,
                'adults' => $s['guests'],
                'children_qty' => 0,
                'arrival_date' => $date,
                'departure_date' => date('Y-m-d', strtotime($date . ' +1 day')),
                'status' => 1,
                'rate' => $s['nights'] > 0 ? round($s['room_rev'] / $s['nights'], 0) : $s['room_rev'],
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $guestDist = [];
            if ($s['nights'] > 0) {
                $baseGuest = intdiv($s['guests'], $s['nights']);
                $remGuest = $s['guests'] % $s['nights'];
                for ($n = 0; $n < $s['nights']; $n++) {
                    $guestDist[$n] = $baseGuest + ($n < $remGuest ? 1 : 0);
                }
            }

            if ($s['nights'] > 0) {
                $remRev = $s['room_rev'];
                $paidCount = $s['nights'] - $s['foc'];
                $avgPaid = $paidCount > 0 ? round($s['room_rev'] / $paidCount, 0) : 0;
                $bfPerNight = ($s['bf_rev'] > 0 && $s['nights'] > 0) ? round($s['bf_rev'] / $s['nights'], 0) : 0;

                for ($n = 0; $n < $s['nights']; $n++) {
                    $billIdSeq++;
                    $isFoc = $n < $s['foc'];
                    $amount = $isFoc ? 0 : ($n == $s['nights'] - 1 ? $remRev : $avgPaid);
                    if (!$isFoc) {
                        $remRev -= $amount;
                    }

                    $db->table('service_bills')->insert([
                        'Ma' => $billIdSeq,
                        'Date' => $date,
                        'OpenTime' => '12:00',
                        'Guest' => $cName,
                        'DepartmentId' => 'FO',
                        'ServiceId' => 'RM',
                        'DescriptionServive' => 'Tiền phòng ' . ($isFoc ? '(FOC)' : ''),
                        'Quantity' => 1,
                        'Amount' => $amount,
                        'Currency' => 'VND',
                        'RentalRoomId1' => $brId,
                        'RentalRoomId2' => $brId,
                        'RegisterId1' => $bookingId,
                        'RegisterID2' => $bookingId,
                        'CompanyId1' => $cId,
                        'CompanyId2' => $cId,
                        'Username' => 'demo',
                        'Status' => 1,
                        'Edit' => 0,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);

                    $db->table('room_night_bills')->insert([
                        'bill_id' => $billIdSeq,
                        'adult' => $guestDist[$n] ?? 1,
                        'child' => 0,
                        'is_room_night' => 1,
                        'breakfast_amount' => $bfPerNight,
                        'extrabed_amount' => 0,
                        'date' => $date,
                        'room' => $roomNumber,
                        'breakfast' => $bfPerNight > 0 ? 1 : 0,
                        'extra_bed' => 0,
                        'rate_code' => $isFoc ? 'FOC' : 'STD',
                        'rate' => $amount,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            } else {
                $billIdSeq++;
                $db->table('service_bills')->insert([
                    'Ma' => $billIdSeq,
                    'Date' => $date,
                    'OpenTime' => '12:00',
                    'Guest' => $cName,
                    'DepartmentId' => 'FO',
                    'ServiceId' => 'RM',
                    'DescriptionServive' => 'Phí phòng giữ chỗ',
                    'Quantity' => 1,
                    'Amount' => $s['room_rev'],
                    'Currency' => 'VND',
                    'RentalRoomId1' => $brId,
                    'RentalRoomId2' => $brId,
                    'RegisterId1' => $bookingId,
                    'RegisterID2' => $bookingId,
                    'CompanyId1' => $cId,
                    'CompanyId2' => $cId,
                    'Username' => 'demo',
                    'Status' => 1,
                    'Edit' => 0,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                $db->table('room_night_bills')->insert([
                    'bill_id' => $billIdSeq,
                    'adult' => 0,
                    'child' => 0,
                    'is_room_night' => 0,
                    'breakfast_amount' => 0,
                    'extrabed_amount' => 0,
                    'date' => $date,
                    'room' => $roomNumber,
                    'breakfast' => 0,
                    'extra_bed' => 0,
                    'rate_code' => 'STD',
                    'rate' => $s['room_rev'],
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            if (!empty($s['extra_bed_rev']) && $s['extra_bed_rev'] > 0) {
                $billIdSeq++;
                $db->table('service_bills')->insert([
                    'Ma' => $billIdSeq,
                    'Date' => $date,
                    'OpenTime' => '12:30',
                    'Guest' => $cName,
                    'DepartmentId' => 'FO',
                    'ServiceId' => 'EB',
                    'DescriptionServive' => 'Phụ thu giường phụ',
                    'Quantity' => 1,
                    'Amount' => $s['extra_bed_rev'],
                    'Currency' => 'VND',
                    'RentalRoomId1' => $brId,
                    'RentalRoomId2' => $brId,
                    'RegisterId1' => $bookingId,
                    'RegisterID2' => $bookingId,
                    'CompanyId1' => $cId,
                    'CompanyId2' => $cId,
                    'Username' => 'demo',
                    'Status' => 1,
                    'Edit' => 0,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            if (!empty($s['other_rev']) && $s['other_rev'] != 0) {
                $billIdSeq++;
                $db->table('service_bills')->insert([
                    'Ma' => $billIdSeq,
                    'Date' => $date,
                    'OpenTime' => '14:00',
                    'Guest' => $cName,
                    'DepartmentId' => 'FO',
                    'ServiceId' => 'OTHER',
                    'DescriptionServive' => 'Dịch vụ khác',
                    'Quantity' => 1,
                    'Amount' => $s['other_rev'],
                    'Currency' => 'VND',
                    'RentalRoomId1' => $brId,
                    'RentalRoomId2' => $brId,
                    'RegisterId1' => $bookingId,
                    'RegisterID2' => $bookingId,
                    'CompanyId1' => $cId,
                    'CompanyId2' => $cId,
                    'Username' => 'demo',
                    'Status' => 1,
                    'Edit' => 0,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }
}
