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
        // 1. Seed Hotel Settings
        HotelSetting::firstOrCreate(
            ['hotel_name' => 'Galliot Hotel Nha Trang'],
            [
                'first_name' => null,
                'hotel_name1' => null,
                'address' => '195 Nguyễn Thiện Thuật, Phường Nha Trang, Tỉnh Khánh Hòa, Việt Nam',
                'address1' => '3A Quản Trạn, Phường Nha Trang, Tỉnh Khánh Hòa, Việt Nam',
                'phone' => '+84 258 3528 555',
                'fax' => '',
                'email' => 'fo.galliot@navyhotelgroup.com',
                'website' => 'www.galliothotel.vn',
                'account' => '3427247085770',
                'bank_code' => 'MB',
                'bank' => 'Ngân hàng TMCP Quân Đội - CN Khánh Hòa',
                'tax_code' => '0313161911-001',
                'account_name' => 'CN CTCP DV BAY VA DL BIEN TAN CANG TAI KHANH HOA',
                'serial' => null,
                'invoice_number' => null,
                'invoice_number_length' => null,
                'form_no' => null,
                'logo' => null,
                'invoice_address' => '195 Nguyễn Thiện Thuật, P. Nha Trang, T. Khánh Hòa',
                'breakfast_adult_rate' => 180000,
                'breakfast_child_rate' => 90000,
                'extra_bed_rate' => 300000,
                'room_number' => 131,
                'division' => 'GAL',
                'currency' => 'VND',
                'prefix_booking_id' => 'GAL',
                'channel_manager' => 'HotelLink',
                'facebook' => '',
                'hotel_link' => null,
                'pos_serial' => null,
                'pos_invoice_number' => null,
                'pos_invoice_number_length' => null,
                'pos_invoice_form_no' => null,
                'pos_invoice_symbol' => null,
                'logo_url' => 'assets/hotel-logo.png',
                'qr_code_url' => 'assets/hotel-qr.png',
                'allow_over_room_type' => false,
                'booking_hidden_bk_info' => json_encode(['PaymentMethod']),
                'booking_bf_child_set_service_id' => 'BF_CHILD',
                'booking_auto_extra_charge_bf_child' => false,
                'check_module_before_delete' => true,
            ]
        );

        // Seed Room Class Groups
        $hotelGroup = \App\Models\RoomClassGroup::firstOrCreate(
            ['code' => 'hotel'],
            [
                'name' => 'Khách sạn',
                'is_active' => true,
            ]
        );

        // 2. Seed Room Classes (Tên loại phòng)
        $classes = [
            ['name' => 'Superior Double', 'code' => 'SUPD', 'color' => '#ffffff', 'is_active' => true, 'orders' => 1, 'room_class_group_id' => $hotelGroup->id],
            ['name' => 'Superior Twin', 'code' => 'SUPT', 'color' => '#ffffff', 'is_active' => true, 'orders' => 2, 'room_class_group_id' => $hotelGroup->id],
            ['name' => 'Superior Triple', 'code' => 'SUPTR', 'color' => '#ffffff', 'is_active' => true, 'orders' => 3, 'room_class_group_id' => $hotelGroup->id],
            ['name' => 'Deluxe Double City view', 'code' => 'DLXD', 'color' => '#ffffff', 'is_active' => true, 'orders' => 4, 'room_class_group_id' => $hotelGroup->id],
            ['name' => 'Deluxe Twin City View', 'code' => 'DLXT', 'color' => '#ffffff', 'is_active' => true, 'orders' => 5, 'room_class_group_id' => $hotelGroup->id],
            ['name' => 'Deluxe Double with Balcony', 'code' => 'DLXDB', 'color' => '#ffffff', 'is_active' => true, 'orders' => 6, 'room_class_group_id' => $hotelGroup->id],
            ['name' => 'Deluxe Twin with Balcony', 'code' => 'DLXTB', 'color' => '#ffffff', 'is_active' => true, 'orders' => 7, 'room_class_group_id' => $hotelGroup->id],
            ['name' => 'Family City View', 'code' => 'FAM', 'color' => '#ffffff', 'is_active' => true, 'orders' => 8, 'room_class_group_id' => $hotelGroup->id],
            ['name' => 'Suite', 'code' => 'JST', 'color' => '#ffffff', 'is_active' => true, 'orders' => 9, 'room_class_group_id' => $hotelGroup->id],
            ['name' => 'DỰ PHÒNG', 'code' => 'DP', 'color' => '#ffffff', 'is_active' => false, 'orders' => 10, 'room_class_group_id' => $hotelGroup->id],
        ];

        $classModels = [];
        foreach ($classes as $c) {
            $classModels[$c['code']] = RoomClass::firstOrCreate(
                ['code' => $c['code']],
                $c
            );
        }

        // 3. Seed Room Forms (Dạng phòng)
        $forms = [
            ['name' => 'Double', 'max_adults' => 2],
            ['name' => 'Twin', 'max_adults' => 2],
            ['name' => 'Triple', 'max_adults' => 3],
            ['name' => 'Family', 'max_adults' => 4],
            ['name' => 'King', 'max_adults' => 2],
        ];

        $formModels = [];
        foreach ($forms as $f) {
            $formModels[$f['name']] = RoomForm::firstOrCreate(
                ['name' => $f['name']],
                $f
            );
        }

        // 4. Seed Standard Rates (Giá phòng chuẩn)
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
            StandardRate::firstOrCreate(
                [
                    'room_class_id' => $classModels[$r['class']]->id,
                    'room_form_id' => $formModels[$r['form']]->id,
                ],
                [
                    'room_price' => $r['price'],
                    'extra_bed_price' => $r['extra'],
                ]
            );
        }

        // 5. Seed Rooms (15 floors, 12 rooms per floor)
        $roomsData = [];
        $floorsList = [1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12, 13, 14, 15];

        foreach ($floorsList as $floorIndex => $floor) {
            for ($col = 1; $col <= 12; $col++) {
                $roomNumber = $floor . str_pad($col, 2, '0', STR_PAD_LEFT);

                $classCode = 'SUPD';
                $formName = 'Double';
                $guests = 2;

                if ($roomNumber === '404') {
                    $classCode = 'SUPT';
                    $formName = 'Twin';
                    $guests = 2;
                } elseif ($col == 1 || $col == 2) {
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

        $orderCounter = 1;
        foreach ($roomsData as $r) {
            Room::firstOrCreate(
                ['room_number' => $r['room_number']],
                [
                    'room_class_id' => $classModels[$r['class']]->id,
                    'room_form_id' => $formModels[$r['form']]->id,
                    'max_guests' => $r['guests'],
                    'floor' => $r['floor'],
                    'area' => 'Khu A',
                    'extra_beds_limit' => 1,
                    'grid_row' => $r['row'],
                    'grid_column' => $r['col'],
                    'orders' => $orderCounter++,
                    'is_internal' => false,
                    'room_status_code' => 'vacant_ready',
                    'notes' => 'Phòng tự động tạo bằng seeder',
                ]
            );
        }

        // Seed 2 Phòng ảo thử nghiệm (PM01, PM02) với is_internal = true
        Room::firstOrCreate(
            ['room_number' => '001'],
            [
                'room_class_id' => $classModels['SUPD']->id ?? 1,
                'room_form_id' => $formModels['Double']->id ?? 1,
                'max_guests' => 2,
                'floor' => '0',
                'area' => 'Virtual',
                'extra_beds_limit' => 0,
                'grid_row' => 0,
                'grid_column' => 0,
                'orders' => 9991,
                'is_internal' => true,
                'status' => 'available',
                'notes' => 'Phòng ảo 001',
            ]
        );

        Room::firstOrCreate(
            ['room_number' => '002'],
            [
                'room_class_id' => $classModels['SUPD']->id ?? 1,
                'room_form_id' => $formModels['Double']->id ?? 1,
                'max_guests' => 2,
                'floor' => '0',
                'area' => 'Virtual',
                'extra_beds_limit' => 0,
                'grid_row' => 0,
                'grid_column' => 0,
                'orders' => 9992,
                'is_internal' => true,
                'status' => 'available',
                'notes' => 'Phòng ảo 002',
            ]
        );

        // Seed some room locks and histories
        $room501 = Room::where('room_number', '501')->first();
        $room502 = Room::where('room_number', '502')->first();
        $room503 = Room::where('room_number', '503')->first();

        if ($room501 && $room502 && $room503) {
            // Room 501: Active OOS lock
            \App\Models\RoomLock::firstOrCreate(
                ['room_number' => $room501->room_number, 'start_date' => '2026-06-09'],
                [
                    'end_date' => '2026-06-15',
                    'reason' => 'Sửa máy lạnh rò nước',
                    'maintenance_percent' => 50,
                    'status' => 'Active',
                    'username' => 'NB0016',
                    'lock_type' => 'OOS',
                    'is_active' => true,
                ]
            );

            // Room 502: Inactive OOS lock
            \App\Models\RoomLock::firstOrCreate(
                ['room_number' => $room502->room_number, 'start_date' => '2026-05-08'],
                [
                    'end_date' => '2026-05-12',
                    'reason' => 'tháo rèm giặt',
                    'maintenance_percent' => 0,
                    'status' => 'New',
                    'username' => 'NB0016',
                    'lock_type' => 'OOS',
                    'is_active' => false,
                ]
            );

            // Room 503: Active OOO lock
            \App\Models\RoomLock::firstOrCreate(
                ['room_number' => $room503->room_number, 'start_date' => '2026-06-10'],
                [
                    'end_date' => '2026-06-20',
                    'reason' => 'Thay đệm giường hỏng',
                    'maintenance_percent' => 100,
                    'status' => 'Active',
                    'username' => 'NB0031',
                    'lock_type' => 'OOO',
                    'is_active' => true,
                ]
            );
        }

        // Seed CheckModuleBeforeDelete system parameter
        \Illuminate\Support\Facades\DB::table('hotel_configs')->updateOrInsert(
            ['name' => 'CheckModuleBeforeDelete'],
            [
                'value' => '1',
                'description' => 'Kiểm tra bộ phận trước khi thực hiện xóa phòng hoặc BK (0: Không xét, 1: Có xét)',
                'updated_at' => now(),
                'created_at' => now(),
            ]
        );
    }
}
