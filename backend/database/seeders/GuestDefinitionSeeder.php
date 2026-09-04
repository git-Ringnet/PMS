<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\GuestTitle;
use App\Models\BorderGate;
use App\Models\EntryPurpose;
use App\Models\GuestType;
use App\Models\IdType;

class GuestDefinitionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // 1. Danh xưng (guest_titles)
        $titles = [
            ['code' => 'MR',   'name' => 'Mr.',   'gender' => 0, 'is_adult' => true,  'is_infant' => false, 'order_index' => 1],
            ['code' => 'MRS',  'name' => 'Mrs.',  'gender' => 1, 'is_adult' => true,  'is_infant' => false, 'order_index' => 2],
            ['code' => 'MS',   'name' => 'Ms.',   'gender' => 1, 'is_adult' => true,  'is_infant' => false, 'order_index' => 3],
            ['code' => 'MISS', 'name' => 'Miss.', 'gender' => 1, 'is_adult' => true,  'is_infant' => false, 'order_index' => 4],
            ['code' => 'KID',  'name' => 'Kid.',  'gender' => 2, 'is_adult' => false, 'is_infant' => false, 'order_index' => 5],
            ['code' => 'BABY', 'name' => 'Baby.', 'gender' => 2, 'is_adult' => false, 'is_infant' => true,  'order_index' => 6],
            ['code' => 'DR',   'name' => 'Dr.',   'gender' => 2, 'is_adult' => true,  'is_infant' => false, 'order_index' => 7],
            ['code' => 'PROF', 'name' => 'Prof.', 'gender' => 2, 'is_adult' => true,  'is_infant' => false, 'order_index' => 8],
        ];
        foreach ($titles as $item) {
            GuestTitle::updateOrCreate(['code' => $item['code']], $item);
        }

        // 2. Cửa khẩu (border_gates)
        $gates = [
            ['code' => 'NBA',    'name' => 'Sân bay Quốc tế Nội Bài',       'gate_type' => 'air',  'order_index' => 1],
            ['code' => 'TSN',    'name' => 'Sân bay Quốc tế Tân Sơn Nhất',   'gate_type' => 'air',  'order_index' => 2],
            ['code' => 'DAD',    'name' => 'Sân bay Quốc tế Đà Nẵng',       'gate_type' => 'air',  'order_index' => 3],
            ['code' => 'CXR',    'name' => 'Sân bay Quốc tế Cam Ranh',      'gate_type' => 'air',  'order_index' => 4],
            ['code' => 'PQC',    'name' => 'Sân bay Quốc tế Phú Quốc',      'gate_type' => 'air',  'order_index' => 5],
            ['code' => 'HNG',    'name' => 'Cửa khẩu Quốc tế Hữu Nghị',     'gate_type' => 'land', 'order_index' => 6],
            ['code' => 'MBA',    'name' => 'Cửa khẩu Quốc tế Mộc Bài',      'gate_type' => 'land', 'order_index' => 7],
            ['code' => 'LBO',    'name' => 'Cửa khẩu Quốc tế Lao Bảo',      'gate_type' => 'land', 'order_index' => 8],
            ['code' => 'CTO',    'name' => 'Cửa khẩu Quốc tế Cầu Treo',     'gate_type' => 'land', 'order_index' => 9],
            ['code' => 'CLO',    'name' => 'Cửa khẩu Quốc tế Cha Lo',       'gate_type' => 'land', 'order_index' => 10],
            ['code' => 'SEA_SG', 'name' => 'Cảng biển Quốc tế Sài Gòn',    'gate_type' => 'sea',  'order_index' => 11],
            ['code' => 'SEA_HP', 'name' => 'Cảng biển Quốc tế Hải Phòng',  'gate_type' => 'sea',  'order_index' => 12],
            ['code' => 'OTHER',  'name' => 'Cửa khẩu khác',                'gate_type' => null,   'order_index' => 99],
        ];
        foreach ($gates as $item) {
            BorderGate::updateOrCreate(['code' => $item['code']], $item);
        }

        // 3. Mục đích lưu trú / nhập cảnh (entry_purposes)
        $purposes = [
            ['code' => 'DL', 'name' => 'Du lịch',             'order_index' => 1],
            ['code' => 'CT', 'name' => 'Công tác',            'order_index' => 2],
            ['code' => 'TT', 'name' => 'Thăm thân',           'order_index' => 3],
            ['code' => 'HT', 'name' => 'Học tập',             'order_index' => 4],
            ['code' => 'DT', 'name' => 'Đầu tư',              'order_index' => 5],
            ['code' => 'LD', 'name' => 'Lao động / Làm việc', 'order_index' => 6],
            ['code' => 'KH', 'name' => 'Khác',                'order_index' => 99],
        ];
        foreach ($purposes as $item) {
            EntryPurpose::updateOrCreate(['code' => $item['code']], $item);
        }

        // 4. Loại khách (guest_types)
        $guestTypes = [
            ['code' => 'FIT',      'name' => 'Khách lẻ (FIT)',              'description' => 'Free Independent Travelers', 'order_index' => 1],
            ['code' => 'GIT',      'name' => 'Khách đoàn (GIT)',             'description' => 'Group Inclusive Tour',        'order_index' => 2],
            ['code' => 'VIP',      'name' => 'Khách VIP',                   'description' => 'Very Important Person',      'order_index' => 3],
            ['code' => 'CREW',     'name' => 'Phi hành đoàn (Crew)',         'description' => 'Airline / Ship Crew',         'order_index' => 4],
            ['code' => 'LONGSTAY', 'name' => 'Khách lưu trú dài hạn',       'description' => 'Long Stay Guests',            'order_index' => 5],
            ['code' => 'CORP',     'name' => 'Khách công ty (Corporate)',   'description' => 'Corporate Clients',          'order_index' => 6],
        ];
        foreach ($guestTypes as $item) {
            GuestType::updateOrCreate(['code' => $item['code']], $item);
        }

        // 5. Loại giấy tờ (id_types)
        $idTypes = [
            ['code' => 'CCCD',     'name' => 'Căn cước công dân (CCCD)',    'order_index' => 1],
            ['code' => 'CMND',     'name' => 'Chứng minh nhân dân (CMND)',  'order_index' => 2],
            ['code' => 'PASSPORT', 'name' => 'Hộ chiếu (Passport)',         'order_index' => 3],
            ['code' => 'OTHER',    'name' => 'Giấy tờ khác',                'order_index' => 99],
        ];
        foreach ($idTypes as $item) {
            IdType::updateOrCreate(['code' => $item['code']], $item);
        }
    }
}
