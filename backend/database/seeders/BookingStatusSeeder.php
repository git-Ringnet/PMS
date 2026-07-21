<?php

namespace Database\Seeders;

use App\Models\BookingStatus;
use Illuminate\Database\Seeder;

class BookingStatusSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $statuses = [
            [
                'id' => 0,
                'name' => 'Đăng ký',
                'name_en' => 'Reservation',
                'description' => 'Tình trạng đăng ký (đặt trước), dùng cho bảng DangKy, PhongThue, Khach',
            ],
            [
                'id' => 1,
                'name' => 'Checked in',
                'name_en' => 'Checked in',
                'description' => 'Tình trạng đã checkin, dùng cho bảng DangKy, PhongThue, Khach',
            ],
            [
                'id' => 2,
                'name' => 'Checked out',
                'name_en' => 'Checked out',
                'description' => 'Tình trạng đã checkout, dùng cho bảng DangKy, PhongThue, Khach',
            ],
            [
                'id' => 3,
                'name' => 'Deleted',
                'name_en' => 'Deleted',
                'description' => 'Tình trạng đã xoá, dùng cho bảng DangKy, PhongThue, Khach',
            ],
            [
                'id' => 4,
                'name' => 'No show',
                'name_en' => 'No show',
                'description' => 'Phòng không check in, dùng cho bảng DangKy, PhongThue, Khach',
            ],
            [
                'id' => 100,
                'name' => 'Chuyển phòng',
                'name_en' => 'Change Room',
                'description' => 'Phòng đã chuyển, dùng trong bảng PhongThue, Khach',
            ],
        ];

        foreach ($statuses as $status) {
            BookingStatus::updateOrCreate(
                ['id' => $status['id']],
                $status
            );
        }
    }
}
