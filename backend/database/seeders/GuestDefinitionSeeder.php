<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\GuestTitle;
use App\Models\BorderGate;
use App\Models\EntryPurpose;
use App\Models\GuestType;
use App\Models\IdType;
use App\Models\ResidenceType;

class GuestDefinitionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     * Danh mục thông tin khách hàng định nghĩa chuẩn theo nghiệp vụ khách sạn
     */
    public function run(): void
    {
        // 1. Danh xưng (guest_titles)
        $titles = [
            ['code' => 'Boy', 'name' => 'Boy.', 'gender' => 1, 'is_adult' => false, 'is_infant' => false, 'order_index' => 1, 'is_active' => true],
            ['code' => 'Girl', 'name' => 'Girl.', 'gender' => 2, 'is_adult' => false, 'is_infant' => false, 'order_index' => 2, 'is_active' => true],
            ['code' => 'Inf', 'name' => 'Inf', 'gender' => 1, 'is_adult' => false, 'is_infant' => true, 'order_index' => 3, 'is_active' => true],
            ['code' => 'Kid', 'name' => 'Kid.', 'gender' => 1, 'is_adult' => false, 'is_infant' => false, 'order_index' => 4, 'is_active' => true],
            ['code' => 'Mr', 'name' => 'Mr.', 'gender' => 1, 'is_adult' => true, 'is_infant' => false, 'order_index' => 5, 'is_active' => true],
            ['code' => 'Ms', 'name' => 'Ms.', 'gender' => 2, 'is_adult' => true, 'is_infant' => false, 'order_index' => 6, 'is_active' => true],
        ];
        foreach ($titles as $item) {
            GuestTitle::updateOrCreate(['code' => $item['code']], $item);
        }
        GuestTitle::whereNotIn('code', array_column($titles, 'code'))->delete();

        // 2. Cảng / Cửa khẩu (border_gates)
        $gates = [
            ['code' => 'STS', 'name' => 'SB Tân Sơn Nhất', 'gate_type' => null, 'order_index' => 1, 'is_active' => true],
            ['code' => 'SNB', 'name' => 'SBQT Nội Bài', 'gate_type' => null, 'order_index' => 2, 'is_active' => true],
            ['code' => 'CNT', 'name' => 'Cảng Nha Trang', 'gate_type' => null, 'order_index' => 3, 'is_active' => true],
            ['code' => 'CSG', 'name' => 'Cảng Sài Gòn', 'gate_type' => null, 'order_index' => 4, 'is_active' => true],
            ['code' => 'CDN', 'name' => 'Cảng Đà Nẵng', 'gate_type' => null, 'order_index' => 5, 'is_active' => true],
            ['code' => 'CHP', 'name' => 'Cảng Hải Phòng', 'gate_type' => null, 'order_index' => 6, 'is_active' => true],
            ['code' => 'CHG', 'name' => 'Cảng Hòn Gai', 'gate_type' => null, 'order_index' => 7, 'is_active' => true],
            ['code' => 'CCT', 'name' => 'Cảng Cần Thơ', 'gate_type' => null, 'order_index' => 8, 'is_active' => true],
            ['code' => 'CVT', 'name' => 'Cảng Vũng Tàu', 'gate_type' => null, 'order_index' => 9, 'is_active' => true],
            ['code' => 'SDN', 'name' => 'SBQT Đà Nẵng', 'gate_type' => null, 'order_index' => 10, 'is_active' => true],
            ['code' => 'SVT', 'name' => 'SB Vũng Tàu', 'gate_type' => null, 'order_index' => 11, 'is_active' => true],
            ['code' => 'KHN', 'name' => 'CK Hữu Nghị', 'gate_type' => null, 'order_index' => 12, 'is_active' => true],
            ['code' => 'KLB', 'name' => 'CK Lao Bảo', 'gate_type' => null, 'order_index' => 13, 'is_active' => true],
            ['code' => 'KMB', 'name' => 'CK Mộc Bài', 'gate_type' => null, 'order_index' => 14, 'is_active' => true],
            ['code' => 'KMC', 'name' => 'CK Móng Cái', 'gate_type' => null, 'order_index' => 15, 'is_active' => true],
            ['code' => 'KLC', 'name' => 'CK Lào Cai', 'gate_type' => null, 'order_index' => 16, 'is_active' => true],
            ['code' => 'KCT', 'name' => 'CK Cầu Treo', 'gate_type' => null, 'order_index' => 17, 'is_active' => true],
            ['code' => 'KDD', 'name' => 'CK Đồng Đăng', 'gate_type' => null, 'order_index' => 18, 'is_active' => true],
            ['code' => 'CCL', 'name' => 'Cảng Cửa Lò', 'gate_type' => null, 'order_index' => 19, 'is_active' => true],
            ['code' => 'XXX', 'name' => 'Chưa xác định', 'gate_type' => null, 'order_index' => 20, 'is_active' => true],
            ['code' => 'CCV', 'name' => 'Cảng Cửa Việt', 'gate_type' => null, 'order_index' => 21, 'is_active' => true],
            ['code' => 'KLL', 'name' => 'CK La Lay', 'gate_type' => null, 'order_index' => 22, 'is_active' => true],
            ['code' => 'KCK', 'name' => 'Cửa khẩu Chiềng Khương', 'gate_type' => null, 'order_index' => 23, 'is_active' => true],
            ['code' => 'KLS', 'name' => 'CK Lóng Sập', 'gate_type' => null, 'order_index' => 24, 'is_active' => true],
            ['code' => 'CNS', 'name' => 'Cảng Nghi Sơn', 'gate_type' => null, 'order_index' => 25, 'is_active' => true],
            ['code' => 'KNM', 'name' => 'Cửa khẩu Na Mèo', 'gate_type' => null, 'order_index' => 26, 'is_active' => true],
            ['code' => 'CTH', 'name' => 'Cảng Thanh Hóa', 'gate_type' => null, 'order_index' => 27, 'is_active' => true],
            ['code' => 'CDD', 'name' => 'Cảng Diêm Điền', 'gate_type' => null, 'order_index' => 28, 'is_active' => true],
            ['code' => 'CTA', 'name' => 'Cảng Thuận An', 'gate_type' => null, 'order_index' => 29, 'is_active' => true],
            ['code' => 'CCM', 'name' => 'Cảng Chân Mây', 'gate_type' => null, 'order_index' => 30, 'is_active' => true],
            ['code' => 'CMT', 'name' => 'Cảng Mỹ Tho', 'gate_type' => null, 'order_index' => 31, 'is_active' => true],
            ['code' => 'KXM', 'name' => 'CK Xa Mat', 'gate_type' => null, 'order_index' => 32, 'is_active' => true],
            ['code' => 'CVL', 'name' => 'Cảng Vĩnh Long', 'gate_type' => null, 'order_index' => 33, 'is_active' => true],
            ['code' => 'KTR', 'name' => 'Cửa khẩu Tây Trang', 'gate_type' => null, 'order_index' => 34, 'is_active' => true],
            ['code' => 'KBR', 'name' => 'CK Buprăng', 'gate_type' => null, 'order_index' => 35, 'is_active' => true],
            ['code' => 'CDG', 'name' => 'Cảng Đồng Nai', 'gate_type' => null, 'order_index' => 36, 'is_active' => true],
            ['code' => 'CPT', 'name' => 'Cảng Phước Thái', 'gate_type' => null, 'order_index' => 37, 'is_active' => true],
            ['code' => 'CDT', 'name' => 'Cảng Đồng Tháp', 'gate_type' => null, 'order_index' => 38, 'is_active' => true],
            ['code' => 'KTP', 'name' => 'CK Thường Phước', 'gate_type' => null, 'order_index' => 39, 'is_active' => true],
            ['code' => 'KDB', 'name' => 'CK Dinh Bà', 'gate_type' => null, 'order_index' => 40, 'is_active' => true],
            ['code' => 'CMH', 'name' => 'Cảng Mỹ Thới', 'gate_type' => null, 'order_index' => 41, 'is_active' => true],
            ['code' => 'CVX', 'name' => 'Cảng đường sông Vĩnh Xương', 'gate_type' => null, 'order_index' => 42, 'is_active' => true],
            ['code' => 'CTB', 'name' => 'Cảng Tịnh Biên', 'gate_type' => null, 'order_index' => 43, 'is_active' => true],
            ['code' => 'KST', 'name' => 'Sông Tiền', 'gate_type' => null, 'order_index' => 44, 'is_active' => true],
            ['code' => 'KTB', 'name' => 'CK Tịnh Biên', 'gate_type' => null, 'order_index' => 45, 'is_active' => true],
            ['code' => 'KKB', 'name' => 'CK Khánh Bình', 'gate_type' => null, 'order_index' => 46, 'is_active' => true],
            ['code' => 'CBU', 'name' => 'Cảng Bình Dương', 'gate_type' => null, 'order_index' => 47, 'is_active' => true],
            ['code' => 'KBN', 'name' => 'CK BôNuê', 'gate_type' => null, 'order_index' => 48, 'is_active' => true],
            ['code' => 'CBD', 'name' => 'Cảng Bình Định', 'gate_type' => null, 'order_index' => 49, 'is_active' => true],
            ['code' => 'CQN', 'name' => 'Cảng Quy Nhơn', 'gate_type' => null, 'order_index' => 50, 'is_active' => true],
            ['code' => 'CTN', 'name' => 'Cảng Thị Nại', 'gate_type' => null, 'order_index' => 51, 'is_active' => true],
            ['code' => 'KTL', 'name' => 'Tà Lùng', 'gate_type' => null, 'order_index' => 52, 'is_active' => true],
            ['code' => 'KSG', 'name' => 'Cửa khẩu Sóc Giang', 'gate_type' => null, 'order_index' => 53, 'is_active' => true],
            ['code' => 'KTI', 'name' => 'CK Trà Lĩnh', 'gate_type' => null, 'order_index' => 54, 'is_active' => true],
            ['code' => 'KLT', 'name' => 'CK Lệ Thanh', 'gate_type' => null, 'order_index' => 55, 'is_active' => true],
            ['code' => 'KTT', 'name' => 'Thanh Thủy', 'gate_type' => null, 'order_index' => 56, 'is_active' => true],
            ['code' => 'CXH', 'name' => 'Cảng Xuân Hải', 'gate_type' => null, 'order_index' => 57, 'is_active' => true],
            ['code' => 'CVA', 'name' => 'Cảng Vũng Áng', 'gate_type' => null, 'order_index' => 58, 'is_active' => true],
            ['code' => 'SCB', 'name' => 'SBQT Cát Bi', 'gate_type' => null, 'order_index' => 59, 'is_active' => true],
            ['code' => 'CBN', 'name' => 'Cảng Ba Ngòi', 'gate_type' => null, 'order_index' => 60, 'is_active' => true],
            ['code' => 'CDM', 'name' => 'Cảng Đầm Môn', 'gate_type' => null, 'order_index' => 61, 'is_active' => true],
            ['code' => 'CBR', 'name' => 'Cảng Bình Trị', 'gate_type' => null, 'order_index' => 62, 'is_active' => true],
            ['code' => 'CCC', 'name' => 'Cảng Cửa Cạn', 'gate_type' => null, 'order_index' => 63, 'is_active' => true],
            ['code' => 'CPQ', 'name' => 'Cảng Phú Quốc', 'gate_type' => null, 'order_index' => 64, 'is_active' => true],
            ['code' => 'CHC', 'name' => 'Cảng Hòn Chông', 'gate_type' => null, 'order_index' => 65, 'is_active' => true],
            ['code' => 'KXX', 'name' => 'CK Xà Xía', 'gate_type' => null, 'order_index' => 66, 'is_active' => true],
            ['code' => 'KBY', 'name' => 'Cửa khẩu Bờ Y', 'gate_type' => null, 'order_index' => 67, 'is_active' => true],
            ['code' => 'KML', 'name' => 'Ma Lùng Thàng', 'gate_type' => null, 'order_index' => 68, 'is_active' => true],
            ['code' => 'CBL', 'name' => 'Cảng Bến Lức', 'gate_type' => null, 'order_index' => 69, 'is_active' => true],
            ['code' => 'KBH', 'name' => 'CK Bình Hiệp', 'gate_type' => null, 'order_index' => 70, 'is_active' => true],
            ['code' => 'KTH', 'name' => 'Tân Thanh', 'gate_type' => null, 'order_index' => 71, 'is_active' => true],
            ['code' => 'CNC', 'name' => 'Cảng Năm Căn', 'gate_type' => null, 'order_index' => 72, 'is_active' => true],
            ['code' => 'CHT', 'name' => 'Cảng Hải Thịnh', 'gate_type' => null, 'order_index' => 73, 'is_active' => true],
            ['code' => 'CBT', 'name' => 'Cảng Bến Thủy', 'gate_type' => null, 'order_index' => 74, 'is_active' => true],
            ['code' => 'KNC', 'name' => 'Cửa khẩu Nậm Cắn', 'gate_type' => null, 'order_index' => 75, 'is_active' => true],
            ['code' => 'CVR', 'name' => 'Cảng Vũng Rô', 'gate_type' => null, 'order_index' => 76, 'is_active' => true],
            ['code' => 'CGI', 'name' => 'Cảng Gianh', 'gate_type' => null, 'order_index' => 77, 'is_active' => true],
            ['code' => 'KCL', 'name' => 'Cửa khẩu Cha Lo', 'gate_type' => null, 'order_index' => 78, 'is_active' => true],
            ['code' => 'CKH', 'name' => 'Cảng Kỳ Hòa', 'gate_type' => null, 'order_index' => 79, 'is_active' => true],
            ['code' => 'CSK', 'name' => 'Cảng Sa Kỳ', 'gate_type' => null, 'order_index' => 80, 'is_active' => true],
            ['code' => 'CDQ', 'name' => 'Cảng Dung Quất', 'gate_type' => null, 'order_index' => 81, 'is_active' => true],
            ['code' => 'CCP', 'name' => 'Cảng Cẩm Phả', 'gate_type' => null, 'order_index' => 82, 'is_active' => true],
            ['code' => 'CCO', 'name' => 'Cảng Cửa Ông', 'gate_type' => null, 'order_index' => 83, 'is_active' => true],
            ['code' => 'CVG', 'name' => 'Cảng Vạn Gia', 'gate_type' => null, 'order_index' => 84, 'is_active' => true],
            ['code' => 'SLK', 'name' => 'SBQT Liên Khương', 'gate_type' => null, 'order_index' => 85, 'is_active' => true],
            ['code' => 'SCR', 'name' => 'SBQT Cam Ranh', 'gate_type' => null, 'order_index' => 86, 'is_active' => true],
            ['code' => 'SCT', 'name' => 'SBQT Cần Thơ', 'gate_type' => null, 'order_index' => 87, 'is_active' => true],
        ];
        foreach ($gates as $item) {
            BorderGate::updateOrCreate(['code' => $item['code']], $item);
        }
        BorderGate::whereNotIn('code', array_column($gates, 'code'))->delete();

        // 3. Mục đích lưu trú (entry_purposes)
        $purposes = [
            ['code' => 'DL', 'name' => 'Du lịch', 'order_index' => 1, 'is_active' => true],
            ['code' => 'CT', 'name' => 'Công tác', 'order_index' => 2, 'is_active' => true],
            ['code' => 'TM', 'name' => 'Thương mại', 'order_index' => 3, 'is_active' => true],
            ['code' => 'MK', 'name' => 'Mục đích khác', 'order_index' => 4, 'is_active' => true],
            ['code' => 'HN', 'name' => 'Hội nghị', 'order_index' => 5, 'is_active' => true],
            ['code' => 'TT', 'name' => 'Thăm thân', 'order_index' => 6, 'is_active' => true],
            ['code' => 'VT', 'name' => 'Viện trợ', 'order_index' => 7, 'is_active' => true],
            ['code' => 'DT', 'name' => 'Đầu tư', 'order_index' => 8, 'is_active' => true],
            ['code' => 'BC', 'name' => 'Báo chí', 'order_index' => 9, 'is_active' => true],
            ['code' => 'DC', 'name' => 'Định cư', 'order_index' => 10, 'is_active' => true],
            ['code' => 'HT', 'name' => 'Học tập', 'order_index' => 11, 'is_active' => true],
            ['code' => 'KH', 'name' => 'Kết Hôn', 'order_index' => 12, 'is_active' => true],
            ['code' => 'LD', 'name' => 'Lao động', 'order_index' => 13, 'is_active' => true],
            ['code' => 'TH', 'name' => 'Tiếp thị', 'order_index' => 14, 'is_active' => true],
        ];
        foreach ($purposes as $item) {
            EntryPurpose::updateOrCreate(['code' => $item['code']], $item);
        }
        EntryPurpose::whereNotIn('code', array_column($purposes, 'code'))->delete();

        // 4. Loại khách (guest_types)
        $guestTypes = [
            ['id' => 1, 'code' => 'VIP1', 'name' => 'VIP 1', 'description' => 'VIP 1', 'order_index' => 1, 'is_active' => true],
            ['id' => 2, 'code' => 'VIP2', 'name' => 'VIP 2', 'description' => 'VIP 2', 'order_index' => 2, 'is_active' => true],
            ['id' => 3, 'code' => 'VIP3', 'name' => 'VIP 3', 'description' => 'VIP 3', 'order_index' => 3, 'is_active' => true],
            ['id' => 4, 'code' => 'VIP4', 'name' => 'VIP 4', 'description' => 'VIP 4', 'order_index' => 4, 'is_active' => true],
            ['id' => 6, 'code' => 'RegularGuest', 'name' => 'RegularGuest', 'description' => 'RegularGuest', 'order_index' => 6, 'is_active' => true],
        ];
        GuestType::unguarded(function () use ($guestTypes) {
            foreach ($guestTypes as $item) {
                GuestType::updateOrCreate(['id' => $item['id']], $item);
            }
        });
        GuestType::whereNotIn('id', array_column($guestTypes, 'id'))->delete();

        // 5. Loại giấy tờ (id_types)
        $idTypes = [
            ['code' => 'CCCD', 'name' => 'Căn cước công dân', 'order_index' => 1, 'is_active' => true],
            ['code' => 'Passport', 'name' => 'Hộ chiếu', 'order_index' => 2, 'is_active' => true],
            ['code' => 'GPLX', 'name' => 'Giấy phép lái xe', 'order_index' => 3, 'is_active' => true],
            ['code' => 'Other', 'name' => 'Khác', 'order_index' => 4, 'is_active' => true],
        ];
        foreach ($idTypes as $item) {
            IdType::updateOrCreate(['code' => $item['code']], $item);
        }
        IdType::whereNotIn('code', array_column($idTypes, 'code'))->delete();

        // 6. Thường trú / Tạm trú (residence_types)
        $residenceTypes = [
            ['code' => '1', 'name' => 'Địa chỉ thường trú', 'name_new_form' => 'Thường trú', 'order_index' => 1, 'is_active' => true],
            ['code' => '2', 'name' => 'Địa chỉ tạm trú', 'name_new_form' => 'Tạm trú', 'order_index' => 2, 'is_active' => true],
            ['code' => '3', 'name' => 'Địa chỉ khác', 'name_new_form' => 'Khác', 'order_index' => 3, 'is_active' => true],
        ];
        foreach ($residenceTypes as $item) {
            ResidenceType::updateOrCreate(['code' => $item['code']], $item);
        }
        ResidenceType::whereNotIn('code', array_column($residenceTypes, 'code'))->delete();
    }
}
