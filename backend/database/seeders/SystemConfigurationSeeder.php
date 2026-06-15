<?php

namespace Database\Seeders;

use App\Models\HotelSetting;
use App\Models\RoomClass;
use App\Models\RoomForm;
use App\Models\StandardRate;
use App\Models\Room;
use Illuminate\Database\Seeder;

class SystemConfigurationSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // 1. Seed Hotel Settings (from Screenshot 1)
        HotelSetting::create([
            'code' => 'Galliot',
            'name' => 'Galliot Hotel Nha Trang',
            'address' => '195 Nguyễn Thiện Thuật, Phường Nha Trang, Tỉnh Khánh Hòa, Việt Nam',
            'tax_code' => '0313161911-001',
            'phone' => '+84 258 3528 555',
            'fax' => '',
            'email' => 'fo.galliot@navyhotelgroup.com',
            'facebook' => '',
            'channel_manager' => 'HotelLink',
            'currency' => 'VND',
            'bank_name' => 'Ngân hàng TMCP Quân Đội - CN Khánh Hòa',
            'bank_account_name' => 'CN CTCP DV BAY VA DL BIEN TAN CANG TAI KHANH HOA',
            'bank_account_number' => '3427247085770',
            'adult_breakfast_price' => 180000,
            'child_breakfast_price' => 90000,
            'extra_bed_price' => 300000,
            'total_rooms' => 131,
            'website' => 'www.galliothotel.vn',
            'booking_prefix' => 'GAL',
            'logo_url' => 'assets/hotel-logo.png',
            'qr_code_url' => 'assets/hotel-qr.png',
        ]);

        // 2. Seed Room Classes (Tên loại phòng - from Screenshot 2)
        $classes = [
            ['name' => 'Superior Double', 'code' => 'SUPD', 'color' => '#ffffff', 'is_active' => true, 'group' => 'hotel'],
            ['name' => 'Superior Twin', 'code' => 'SUPT', 'color' => '#ffffff', 'is_active' => true, 'group' => 'hotel'],
            ['name' => 'Superior Triple', 'code' => 'SUPTR', 'color' => '#ffffff', 'is_active' => true, 'group' => 'hotel'],
            ['name' => 'Deluxe Double City view', 'code' => 'DLXD', 'color' => '#ffffff', 'is_active' => true, 'group' => 'hotel'],
            ['name' => 'Deluxe Twin City View', 'code' => 'DLXT', 'color' => '#ffffff', 'is_active' => true, 'group' => 'hotel'],
            ['name' => 'Deluxe Double with Balcony', 'code' => 'DLXDB', 'color' => '#ffffff', 'is_active' => true, 'group' => 'hotel'],
            ['name' => 'Deluxe Twin with Balcony', 'code' => 'DLXTB', 'color' => '#ffffff', 'is_active' => true, 'group' => 'hotel'],
            ['name' => 'Family City View', 'code' => 'FAM', 'color' => '#ffffff', 'is_active' => true, 'group' => 'hotel'],
            ['name' => 'Suite', 'code' => 'JST', 'color' => '#ffffff', 'is_active' => true, 'group' => 'hotel'],
            ['name' => 'DỰ PHÒNG', 'code' => 'DP', 'color' => '#ffffff', 'is_active' => false, 'group' => 'hotel'],
        ];

        $classModels = [];
        foreach ($classes as $c) {
            $classModels[$c['code']] = RoomClass::create($c);
        }

        // 3. Seed Room Forms (Dạng phòng - from Screenshot 3)
        $forms = [
            ['name' => 'Double', 'max_adults' => 2],
            ['name' => 'Twin', 'max_adults' => 2],
            ['name' => 'Triple', 'max_adults' => 3],
            ['name' => 'Family', 'max_adults' => 4],
            ['name' => 'King', 'max_adults' => 2],
        ];

        $formModels = [];
        foreach ($forms as $f) {
            $formModels[$f['name']] = RoomForm::create($f);
        }

        // 4. Seed Standard Rates (Giá phòng chuẩn - from Screenshot 4)
        $rates = [
            ['class' => 'SUPT', 'form' => 'Twin', 'price' => 540000, 'extra' => 300000],
            ['class' => 'DLXD', 'form' => 'Double', 'price' => 650000, 'extra' => 300000],
            ['class' => 'DLXT', 'form' => 'Twin', 'price' => 650000, 'extra' => 300000],
            ['class' => 'DLXDB', 'form' => 'Double', 'price' => 830000, 'extra' => 300000],
            ['class' => 'FAM', 'form' => 'Family', 'price' => 1180000, 'extra' => 300000],
            ['class' => 'JST', 'form' => 'King', 'price' => 1500000, 'extra' => 300000],
            ['class' => 'SUPTR', 'form' => 'Triple', 'price' => 890000, 'extra' => 300000],
            ['class' => 'SUPD', 'form' => 'Double', 'price' => 540000, 'extra' => 300000],
            ['class' => 'DP', 'form' => 'Double', 'price' => 0, 'extra' => 0],
            ['class' => 'DLXTB', 'form' => 'Twin', 'price' => 890000, 'extra' => 300000],
        ];

        foreach ($rates as $r) {
            StandardRate::create([
                'room_class_id' => $classModels[$r['class']]->id,
                'room_form_id' => $formModels[$r['form']]->id,
                'room_price' => $r['price'],
                'extra_bed_price' => $r['extra'],
            ]);
        }

        // 5. Seed Rooms (15 floors, 12 rooms per floor)
        $roomsData = [];
        $floorsList = [1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12, 13, 14, 15];

        foreach ($floorsList as $floorIndex => $floor) {
            for ($col = 1; $col <= 12; $col++) {
                $roomNumber = $floor . str_pad($col, 2, '0', STR_PAD_LEFT);

                // Phân bổ loại phòng và hạng phòng
                $classCode = 'SUPD';
                $formName = 'Double';
                $guests = 2;

                if ($col == 1 || $col == 2) {
                    $classCode = 'DLXD';
                    $formName = 'Double';
                    $guests = 2;
                } elseif ($col == 3 || $col == 4) {
                    $classCode = 'DLXT';
                    $formName = 'Twin';
                    $guests = 2;
                } elseif ($col == 5 || $col == 6) {
                    $classCode = 'SUPD';
                    $formName = 'Double';
                    $guests = 2;
                } elseif ($col == 7 || $col == 8) {
                    $classCode = 'SUPT';
                    $formName = 'Twin';
                    $guests = 2;
                } elseif ($col == 9) {
                    $classCode = 'SUPTR';
                    $formName = 'Triple';
                    $guests = 3;
                } elseif ($col == 10) {
                    $classCode = 'DLXDB';
                    $formName = 'Double';
                    $guests = 2;
                } elseif ($col == 11) {
                    $classCode = 'DLXTB';
                    $formName = 'Twin';
                    $guests = 2;
                } elseif ($col == 12) {
                    if ($floor >= 12) {
                        $classCode = 'JST';
                        $formName = 'King';
                        $guests = 2;
                    } else {
                        $classCode = 'FAM';
                        $formName = 'Family';
                        $guests = 4;
                    }
                }

                $roomsData[] = [
                    'room_number' => $roomNumber,
                    'class' => $classCode,
                    'form' => $formName,
                    'guests' => $guests,
                    'floor' => (string)$floor,
                    'row' => $floorIndex + 1,
                    'col' => $col
                ];
            }
        }

        foreach ($roomsData as $r) {
            Room::create([
                'room_number' => $r['room_number'],
                'room_class_id' => $classModels[$r['class']]->id,
                'room_form_id' => $formModels[$r['form']]->id,
                'max_guests' => $r['guests'],
                'floor' => $r['floor'],
                'area' => 'Khu A',
                'extra_beds_limit' => 1,
                'grid_row' => $r['row'],
                'grid_column' => $r['col'],
                'is_internal' => false,
                'status' => 'available',
                'notes' => 'Phòng tự động tạo bằng seeder',
            ]);
        }

        // Seed some room locks and histories
        $room501 = Room::where('room_number', '501')->first();
        $room502 = Room::where('room_number', '502')->first();
        $room503 = Room::where('room_number', '503')->first();

        if ($room501 && $room502 && $room503) {
            // Room 501: Active OOS lock
            \App\Models\RoomLock::create([
                'room_id' => $room501->id,
                'start_date' => '2026-06-09',
                'end_date' => '2026-06-15',
                'reason' => 'Sửa máy lạnh rò nước',
                'maintenance_percent' => 50,
                'status' => 'Active',
                'username' => 'NB0016',
                'lock_type' => 'OOS',
                'is_active' => true,
            ]);
            $room501->update(['status' => 'maintenance']);

            // Room 502: Inactive OOS lock (History example)
            \App\Models\RoomLock::create([
                'room_id' => $room502->id,
                'start_date' => '2026-05-08',
                'end_date' => '2026-05-12',
                'reason' => 'tháo rèm giặt',
                'maintenance_percent' => 0,
                'status' => 'New',
                'username' => 'NB0016',
                'lock_type' => 'OOS',
                'is_active' => false,
            ]);

            // Room 503: Active OOO lock
            \App\Models\RoomLock::create([
                'room_id' => $room503->id,
                'start_date' => '2026-06-10',
                'end_date' => '2026-06-20',
                'reason' => 'Thay đệm giường hỏng',
                'maintenance_percent' => 100,
                'status' => 'Active',
                'username' => 'NB0031',
                'lock_type' => 'OOO',
                'is_active' => true,
            ]);
            $room503->update(['status' => 'maintenance']);
        }
    }
}
