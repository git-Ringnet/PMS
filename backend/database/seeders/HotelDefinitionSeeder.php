<?php

namespace Database\Seeders;

use App\Models\HotelService;
use App\Models\Shift;
use App\Models\HotelConfig;
use App\Models\Branch;
use App\Models\BranchTotal;
use App\Models\Template;
use Illuminate\Database\Seeder;

class HotelDefinitionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // 1. Seed Hotel Services
        $services = [
            [
                'code' => 'BC',
                'name' => 'Ăn sáng buffet trẻ em',
                'service_charge' => 5,
                'tax' => 8,
                'special_tax' => 0,
                'include_service_charge' => true,
                'include_tax' => true,
                'include_special_tax' => true,
                'folio' => 1,
                'short_name' => 'Ăn sáng trẻ em',
                'unit' => 'Người',
                'price' => 100000,
                'department' => 'Reception/ Lê Tân'
            ],
            [
                'code' => 'BD',
                'name' => 'Breakfast Child/Ăn Sáng Trẻ Em',
                'service_charge' => 5,
                'tax' => 8,
                'special_tax' => 0,
                'include_service_charge' => true,
                'include_tax' => true,
                'include_special_tax' => true,
                'folio' => 1,
                'short_name' => 'Breakfast Child',
                'unit' => 'Người',
                'price' => 0,
                'department' => 'Reception/ Lê Tân'
            ],
            [
                'code' => 'BF',
                'name' => 'Ăn sáng buffet người lớn',
                'service_charge' => 5,
                'tax' => 8,
                'special_tax' => 0,
                'include_service_charge' => true,
                'include_tax' => true,
                'include_special_tax' => true,
                'folio' => 1,
                'short_name' => 'Ăn sáng buffet',
                'unit' => 'Người',
                'price' => 180000,
                'department' => 'Reception/ Lê Tân'
            ],
            [
                'code' => 'BK',
                'name' => 'Broken / Đổ vỡ',
                'service_charge' => 5,
                'tax' => 8,
                'special_tax' => 0,
                'include_service_charge' => true,
                'include_tax' => true,
                'include_special_tax' => true,
                'folio' => 1,
                'short_name' => 'Broken / Đổ vỡ',
                'unit' => 'Dịch Vụ',
                'price' => 0,
                'department' => 'Reception/ Lê Tân'
            ],
            [
                'code' => 'BR',
                'name' => 'Broken/Phí Hư Hỏng',
                'service_charge' => 5,
                'tax' => 8,
                'special_tax' => 0,
                'include_service_charge' => true,
                'include_tax' => true,
                'include_special_tax' => true,
                'folio' => 1,
                'short_name' => 'Broken',
                'unit' => '',
                'price' => 0,
                'department' => 'House Keeping/Buồng Phòng'
            ],
            [
                'code' => 'DN',
                'name' => 'Dinner / Ăn tối',
                'service_charge' => 5,
                'tax' => 8,
                'special_tax' => 0,
                'include_service_charge' => true,
                'include_tax' => true,
                'include_special_tax' => true,
                'folio' => 1,
                'short_name' => 'Dinner / Ăn tối',
                'unit' => 'Người',
                'price' => 0,
                'department' => 'Reception/ Lê Tân'
            ],
            [
                'code' => 'DO',
                'name' => 'Drop off to the airport/ Tiễn sân bay',
                'service_charge' => 5,
                'tax' => 8,
                'special_tax' => 0,
                'include_service_charge' => true,
                'include_tax' => true,
                'include_special_tax' => true,
                'folio' => 1,
                'short_name' => 'Drop off',
                'unit' => '',
                'price' => 0,
                'department' => 'Reception/ Lê Tân'
            ],
            [
                'code' => 'EB',
                'name' => 'Extrabed/Thêm Giường',
                'service_charge' => 5,
                'tax' => 8,
                'special_tax' => 0,
                'include_service_charge' => true,
                'include_tax' => true,
                'include_special_tax' => true,
                'folio' => 1,
                'short_name' => 'Extrabed',
                'unit' => '',
                'price' => 0,
                'department' => 'Reception/ Lê Tân'
            ],
            [
                'code' => 'EI',
                'name' => 'Early Checkin/Phụ Thu Nhận Phòng Sớm',
                'service_charge' => 5,
                'tax' => 8,
                'special_tax' => 0,
                'include_service_charge' => true,
                'include_tax' => true,
                'include_special_tax' => true,
                'folio' => 1,
                'short_name' => 'Early Checkin',
                'unit' => '',
                'price' => 0,
                'department' => 'Reception/ Lê Tân'
            ],
            [
                'code' => 'EP',
                'name' => 'Extra Person/ Phụ Thu Thêm Người',
                'service_charge' => 5,
                'tax' => 8,
                'special_tax' => 0,
                'include_service_charge' => true,
                'include_tax' => true,
                'include_special_tax' => true,
                'folio' => 1,
                'short_name' => 'Extra Person',
                'unit' => '',
                'price' => 0,
                'department' => 'Reception/ Lê Tân'
            ],
            [
                'code' => 'ER',
                'name' => 'Extra RoomCharge / Phụ thu tiền phòng',
                'service_charge' => 5,
                'tax' => 8,
                'special_tax' => 0,
                'include_service_charge' => true,
                'include_tax' => true,
                'include_special_tax' => true,
                'folio' => 1,
                'short_name' => 'Extra RoomCharge',
                'unit' => 'Dịch vụ',
                'price' => 0,
                'department' => 'Reception/ Lê Tân'
            ],
            [
                'code' => 'FB',
                'name' => 'Food and Beverage/ Dịch Vụ Ăn Uống',
                'service_charge' => 5,
                'tax' => 8,
                'special_tax' => 0,
                'include_service_charge' => true,
                'include_tax' => true,
                'include_special_tax' => true,
                'folio' => 1,
                'short_name' => 'FB',
                'unit' => '',
                'price' => 0,
                'department' => 'Restaurant/Nhà Hàng'
            ]
        ];

        foreach ($services as $s) {
            HotelService::create($s);
        }

        // 2. Seed Shifts
        $shifts = [
            ['name' => '0', 'start_time' => '22:00', 'end_time' => '05:59'],
            ['name' => '1', 'start_time' => '06:00', 'end_time' => '13:59'],
            ['name' => '2', 'start_time' => '14:00', 'end_time' => '21:59'],
        ];

        foreach ($shifts as $sh) {
            Shift::create($sh);
        }

        // 3. Seed Configs
        $configs = [
            ['name' => 'AllowChangeRoomStatusAtReception', 'value' => '1', 'description' => 'AllowChangeRoomStatusAtReception', 'is_visible' => false],
            ['name' => 'AllowCheckinNoShow', 'value' => '', 'description' => 'AllowCheckinNoShow', 'is_visible' => true],
            ['name' => 'AllowCheckinVacantClean', 'value' => '0', 'description' => 'AllowCheckinVacantClean', 'is_visible' => false],
            ['name' => 'AllowChckcinNoShow', 'value' => '1', 'description' => 'AllowChckcinNoShow', 'is_visible' => false],
            ['name' => 'AllowCreateOrUpdateBKCauseUnassignableRoomBK', 'value' => '1', 'description' => 'AllowCreateOrUpdateBKCauseUnassignableRoomBK', 'is_visible' => false],
            ['name' => 'AllowEarlyCheckout', 'value' => '1', 'description' => 'AllowEarlyCheckout', 'is_visible' => false],
            ['name' => 'AllowExtendDateRoomOverDateBooking', 'value' => '1', 'description' => 'Cho phép gia hạn phòng vượt quá ngày của booking', 'is_visible' => false],
            ['name' => 'AllowInputOverAV', 'value' => '0', 'description' => 'AllowInputOverAV', 'is_visible' => false],
            ['name' => 'AllowLockRoomCauseUnassignableRoomBK', 'value' => '0', 'description' => 'AllowLockRoomCauseUnassignableRoomBK', 'is_visible' => false],
            ['name' => 'AllowNegativeAmountDeposit', 'value' => '', 'description' => 'AllowNegativeAmountDeposit', 'is_visible' => false],
        ];

        foreach ($configs as $cfg) {
            HotelConfig::create($cfg);
        }

        // 4. Seed Branches
        $branches = [
            ['code' => 'HKT1', 'name' => 'HKT 1', 'api_url' => 'https://hotel.hktsolution.vn/bepms1', 'api_report_url' => 'https://hotel.hktsolution.vn/rppms1/', 'is_master' => true],
            ['code' => 'HKT2', 'name' => 'HKT 2', 'api_url' => 'https://hotel.hktsolution.vn/bepms2', 'api_report_url' => 'https://hotel.hktsolution.vn/rppms2/', 'is_master' => false],
            ['code' => 'HKT3', 'name' => 'HKT 3', 'api_url' => 'https://hotel.hktsolution.vn/bepms3', 'api_report_url' => 'https://hotel.hktsolution.vn/rppms3/', 'is_master' => false],
        ];

        foreach ($branches as $br) {
            BranchTotal::create($br);
        }

        // 5. Seed Templates
        $templates = [
            // Booking Confirmation group
            ['group' => 'Booking Confirmation', 'name' => 'Booking Confirmation Main', 'report' => 'BookingConfirmationGalliot'],
            ['group' => 'Booking Confirmation', 'name' => 'Booking Confirmation Sub', 'report' => 'BookingConfirmationGalliotVN'],
            ['group' => 'Booking Confirmation', 'name' => 'Booking Confirmation For Sales', 'report' => 'BookingConfirmationForSalesTulip'],
            
            // Other groups
            ['group' => 'Registration Card', 'name' => 'Registration Card Main', 'report' => 'RegistrationCardGalliot'],
            ['group' => 'Deposit', 'name' => 'Deposit Main', 'report' => 'DepositReceiptGalliot'],
            ['group' => 'Room Morning Worksheet', 'name' => 'Room Morning Worksheet Main', 'report' => 'RoomMorningWorksheetGalliot'],
            ['group' => 'Invoice', 'name' => 'Invoice Galliot', 'report' => 'InvoiceGalliot'],
            ['group' => 'Total revenue report', 'name' => 'Total revenue report Main', 'report' => 'TotalRevenueGalliot'],
            ['group' => 'Breakfast Ticket', 'name' => 'Breakfast Ticket Main', 'report' => 'BreakfastTicketGalliot'],
            ['group' => 'Report', 'name' => 'Report Main', 'report' => 'GeneralReportGalliot'],
        ];

        foreach ($templates as $tpl) {
            Template::create($tpl);
        }
    }
}
