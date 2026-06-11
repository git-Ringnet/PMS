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

        // 5. Seed Rooms ( khớp RoomPlanPage.vue mock data & Screenshot 5 modal)
        $roomsData = [
            // Floor 4
            ['room_number' => '401', 'class' => 'DLXD', 'form' => 'Double', 'guests' => 2, 'floor' => '4', 'row' => 1, 'col' => 1],
            ['room_number' => '402', 'class' => 'DLXTB', 'form' => 'Twin', 'guests' => 2, 'floor' => '4', 'row' => 1, 'col' => 2],
            ['room_number' => '403', 'class' => 'DLXTB', 'form' => 'Twin', 'guests' => 2, 'floor' => '4', 'row' => 1, 'col' => 3],
            ['room_number' => '404', 'class' => 'SUPT', 'form' => 'Twin', 'guests' => 2, 'floor' => '4', 'row' => 1, 'col' => 4],
            ['room_number' => '405', 'class' => 'FAM', 'form' => 'Family', 'guests' => 4, 'floor' => '4', 'row' => 1, 'col' => 5],
            ['room_number' => '406', 'class' => 'SUPT', 'form' => 'Twin', 'guests' => 2, 'floor' => '4', 'row' => 1, 'col' => 6],
            ['room_number' => '407', 'class' => 'SUPTR', 'form' => 'Triple', 'guests' => 3, 'floor' => '4', 'row' => 1, 'col' => 7],
            ['room_number' => '408', 'class' => 'SUPD', 'form' => 'Double', 'guests' => 2, 'floor' => '4', 'row' => 1, 'col' => 8],
            ['room_number' => '409', 'class' => 'SUPTR', 'form' => 'Triple', 'guests' => 3, 'floor' => '4', 'row' => 1, 'col' => 9],
            ['room_number' => '410', 'class' => 'SUPT', 'form' => 'Twin', 'guests' => 2, 'floor' => '4', 'row' => 1, 'col' => 10],
            ['room_number' => '411', 'class' => 'DLXDB', 'form' => 'Double', 'guests' => 2, 'floor' => '4', 'row' => 1, 'col' => 11],
            ['room_number' => '412', 'class' => 'DLXDB', 'form' => 'Double', 'guests' => 2, 'floor' => '4', 'row' => 1, 'col' => 12],

            // Floor 5
            ['room_number' => '501', 'class' => 'DLXD', 'form' => 'Double', 'guests' => 2, 'floor' => '5', 'row' => 2, 'col' => 1],
            ['room_number' => '502', 'class' => 'DLXTB', 'form' => 'Twin', 'guests' => 2, 'floor' => '5', 'row' => 2, 'col' => 2],
            ['room_number' => '503', 'class' => 'DLXTB', 'form' => 'Twin', 'guests' => 2, 'floor' => '5', 'row' => 2, 'col' => 3],
            ['room_number' => '504', 'class' => 'SUPT', 'form' => 'Twin', 'guests' => 2, 'floor' => '5', 'row' => 2, 'col' => 4],
            ['room_number' => '505', 'class' => 'FAM', 'form' => 'Family', 'guests' => 4, 'floor' => '5', 'row' => 2, 'col' => 5],
            ['room_number' => '506', 'class' => 'SUPT', 'form' => 'Twin', 'guests' => 2, 'floor' => '5', 'row' => 2, 'col' => 6],
        ];

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
    }
}
