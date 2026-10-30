<?php

namespace Database\Seeders;

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

        // 3. Snapshot for Room Available: 129 on 2026-08-20
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

        // 4. Clean up previous test seed for 2026-08-20
        $oldBookingIds = $db->table('bookings')->where('booking_date', '2026-08-20')->where('note', 'SEED_COMPANY_OCCUPANCY_20260820')->pluck('id')->all();
        if (!empty($oldBookingIds)) {
            $db->table('room_night_bills')->whereIn('bill_id', function ($q) use ($oldBookingIds) {
                $q->select('Ma')->from('service_bills')->whereIn('RegisterID2', $oldBookingIds);
            })->delete();
            $db->table('service_bills')->whereIn('RegisterID2', $oldBookingIds)->delete();
            $db->table('booking_rooms')->whereIn('booking_id', $oldBookingIds)->delete();
            $db->table('bookings')->whereIn('id', $oldBookingIds)->delete();
        }

        // 5. Specs exactly matching legacy report 20/08/2026
        $specs = [
            ['code' => 'CTY0051', 'nights' => 29, 'guests' => 65, 'foc' => 5, 'room_rev' => 17830000, 'fb_rev' => 0, 'other_rev' => 1445000, 'market' => $marketQtId, 'source' => $sourceTaId],
            ['code' => 'CTY0001', 'nights' => 11, 'guests' => 23, 'foc' => 0, 'room_rev' => 8760000,  'fb_rev' => 0, 'other_rev' => 775000,  'market' => $marketNdId, 'source' => $sourceDirectId],
            ['code' => 'CTY0210', 'nights' => 12, 'guests' => 25, 'foc' => 0, 'room_rev' => 7920000,  'fb_rev' => 0, 'other_rev' => -300000, 'market' => $marketQtId, 'source' => $sourceTaId],
            ['code' => 'CTY0040', 'nights' => 8,  'guests' => 22, 'foc' => 1, 'room_rev' => 4840000,  'fb_rev' => 0, 'other_rev' => 500000,  'market' => $marketNdId, 'source' => $sourceTaId],
            ['code' => 'CTY0132', 'nights' => 6,  'guests' => 13, 'foc' => 0, 'room_rev' => 4405000,  'fb_rev' => 0, 'other_rev' => 105000,  'market' => $marketQtId, 'source' => $sourceTaId],
            ['code' => 'CTY0004', 'nights' => 4,  'guests' => 12, 'foc' => 0, 'room_rev' => 3942000,  'fb_rev' => 0, 'other_rev' => 200000,  'market' => $marketQtId, 'source' => $sourceOtaId],
            ['code' => 'CTY0087', 'nights' => 5,  'guests' => 12, 'foc' => 0, 'room_rev' => 4030000,  'fb_rev' => 0, 'other_rev' => 0,       'market' => $marketQtId, 'source' => $sourceTaId],
            ['code' => 'CTY0003', 'nights' => 3,  'guests' => 9,  'foc' => 0, 'room_rev' => 2890000,  'fb_rev' => 0, 'other_rev' => 1055000, 'market' => $marketQtId, 'source' => $sourceOtaId],
            ['code' => 'CTY0018', 'nights' => 5,  'guests' => 10, 'foc' => 0, 'room_rev' => 2500000,  'fb_rev' => 0, 'other_rev' => 115000,  'market' => $marketNdId, 'source' => $sourceTaId],
            ['code' => 'CTY0052', 'nights' => 2,  'guests' => 4,  'foc' => 0, 'room_rev' => 1500000,  'fb_rev' => 0, 'other_rev' => 1040000, 'market' => $marketQtId, 'source' => $sourceTaId],
            ['code' => 'CTY0121', 'nights' => 2,  'guests' => 4,  'foc' => 0, 'room_rev' => 1170000,  'fb_rev' => 0, 'other_rev' => 0,       'market' => $marketQtId, 'source' => $sourceTaId],
            ['code' => 'CTY0166', 'nights' => 1,  'guests' => 1,  'foc' => 0, 'room_rev' => 650000,   'fb_rev' => 0, 'other_rev' => 0,       'market' => $marketQtId, 'source' => $sourceTaId],
            ['code' => 'CTY0002', 'nights' => 0,  'guests' => 0,  'foc' => 0, 'room_rev' => 600000,   'fb_rev' => 0, 'other_rev' => 0,       'market' => $marketQtId, 'source' => $sourceOtaId],
            ['code' => 'CTY0050', 'nights' => 9,  'guests' => 21, 'foc' => 8, 'room_rev' => 300000,   'fb_rev' => 0, 'other_rev' => 60000,   'market' => $marketNdId, 'source' => $sourceTaId],
        ];

        $availableRooms = $db->table('rooms')->where(function($q) {
            $q->whereNull('is_internal')->orWhere('is_internal', 0);
        })->pluck('room_number')->all();
        if (empty($availableRooms)) {
            $availableRooms = ['101'];
        }

        $billIdSeq = 900000;

        foreach ($specs as $idx => $s) {
            $cId = $companyIdMap[$s['code']];
            $roomNumber = $availableRooms[$idx % count($availableRooms)];

            // Insert Booking
            $bookingId = $db->table('bookings')->insertGetId([
                'booking_name' => $companies[$idx]['name'],
                'arrival_date' => '2026-08-20',
                'departure_date' => '2026-08-21',
                'num_of_days' => 1,
                'booking_date' => '2026-08-20',
                'status' => 1,
                'company_id' => $cId,
                'market_id' => $s['market'],
                'customer_source_id' => $s['source'],
                'branch_id' => 1,
                'note' => 'SEED_COMPANY_OCCUPANCY_20260820',
                'created_by' => 'Demo',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // Insert Booking Room
            $brId = (string)(800000 + $idx);
            $db->table('booking_rooms')->insert([
                'id' => $brId,
                'booking_id' => $bookingId,
                'room_number' => $roomNumber,
                'adults' => $s['guests'],
                'children_qty' => 0,
                'arrival_date' => '2026-08-20',
                'departure_date' => '2026-08-21',
                'status' => 1,
                'rate' => $s['nights'] > 0 ? round($s['room_rev'] / $s['nights'], 0) : $s['room_rev'],
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // Distribute guests across rooms
            $guestDist = [];
            if ($s['nights'] > 0) {
                $baseGuest = intdiv($s['guests'], $s['nights']);
                $remGuest = $s['guests'] % $s['nights'];
                for ($n = 0; $n < $s['nights']; $n++) {
                    $guestDist[$n] = $baseGuest + ($n < $remGuest ? 1 : 0);
                }
            }

            // Insert Room Nights and Service Bills
            if ($s['nights'] > 0) {
                $remRev = $s['room_rev'];
                $paidCount = $s['nights'] - $s['foc'];
                $avgPaid = $paidCount > 0 ? round($s['room_rev'] / $paidCount, 0) : 0;

                for ($n = 0; $n < $s['nights']; $n++) {
                    $billIdSeq++;
                    $isFoc = $n < $s['foc'];
                    $amount = $isFoc ? 0 : ($n == $s['nights'] - 1 ? $remRev : $avgPaid);
                    if (!$isFoc) {
                        $remRev -= $amount;
                    }

                    $db->table('service_bills')->insert([
                        'Ma' => $billIdSeq,
                        'Date' => '2026-08-20',
                        'OpenTime' => '12:00',
                        'Guest' => $companies[$idx]['name'],
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
                        'breakfast_amount' => 0,
                        'extrabed_amount' => 0,
                        'date' => '2026-08-20',
                        'room' => $roomNumber,
                        'breakfast' => 0,
                        'extra_bed' => 0,
                        'rate_code' => $isFoc ? 'FOC' : 'STD',
                        'rate' => $amount,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            } else {
                // Case AGODA: 0 nights, 600,000 revenue
                $billIdSeq++;
                $db->table('service_bills')->insert([
                    'Ma' => $billIdSeq,
                    'Date' => '2026-08-20',
                    'OpenTime' => '12:00',
                    'Guest' => $companies[$idx]['name'],
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
                    'is_room_night' => 0, // not counted as room night
                    'breakfast_amount' => 0,
                    'extrabed_amount' => 0,
                    'date' => '2026-08-20',
                    'room' => $roomNumber,
                    'breakfast' => 0,
                    'extra_bed' => 0,
                    'rate_code' => 'STD',
                    'rate' => $s['room_rev'],
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            // Other revenue service bills (e.g. Minibar, Giặt là, v.v.)
            if ($s['other_rev'] != 0) {
                $billIdSeq++;
                $db->table('service_bills')->insert([
                    'Ma' => $billIdSeq,
                    'Date' => '2026-08-20',
                    'OpenTime' => '14:00',
                    'Guest' => $companies[$idx]['name'],
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

