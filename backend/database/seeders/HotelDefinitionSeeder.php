<?php

namespace Database\Seeders;

use App\Models\HotelService;
use App\Models\Department;
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
                'short_name' => 'Ăn Sáng Trẻ Em',
                'unit' => 'Người',
                'price' => 0,
                'department' => 'Reception/ Lê Tân'
            ],
            [
                'code' => 'BF',
                'name' => 'Breakfast/Ăn Sáng Người Lớn',
                'service_charge' => 5,
                'tax' => 8,
                'special_tax' => 0,
                'include_service_charge' => true,
                'include_tax' => true,
                'include_special_tax' => true,
                'folio' => 1,
                'short_name' => 'Ăn Sáng Người Lớn',
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
                'short_name' => 'Phí Hư Hỏng',
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
                'short_name' => 'Tiễn sân bay',
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
                'short_name' => 'Thêm Giường',
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
                'short_name' => 'Phụ Thu Nhận Phòng Sớm',
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
                'short_name' => 'Phụ Thu Thêm Người',
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
                'short_name' => 'Phụ thu tiền phòng',
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
                'short_name' => 'Dịch Vụ Ăn Uống',
                'unit' => '',
                'price' => 0,
                'department' => 'Restaurant/Nhà Hàng'
            ],
            [
                'code' => 'KC',
                'name' => 'Kid Surcharge/ Phụ thu trẻ em',
                'service_charge' => 5, 'tax' => 8, 'special_tax' => 0,
                'include_service_charge' => true, 'include_tax' => true, 'include_special_tax' => true,
                'folio' => 1, 'short_name' => 'Phụ thu trẻ em', 'unit' => 'Người', 'price' => 0,
                'department' => 'Reception/ Lê Tân'
            ],
            [
                'code' => 'KE',
                'name' => 'Key card/ Thu Phí Thẻ (mất/hỏng)',
                'service_charge' => 5, 'tax' => 8, 'special_tax' => 0,
                'include_service_charge' => true, 'include_tax' => true, 'include_special_tax' => true,
                'folio' => 1, 'short_name' => 'Thu Phí Thẻ (mất/hỏng)', 'unit' => 'Thẻ', 'price' => 0,
                'department' => 'Reception/ Lê Tân'
            ],
            [
                'code' => 'LA',
                'name' => 'Laundry/Giặt Ủi',
                'service_charge' => 5, 'tax' => 8, 'special_tax' => 0,
                'include_service_charge' => true, 'include_tax' => true, 'include_special_tax' => true,
                'folio' => 1, 'short_name' => 'Giặt Ủi', 'unit' => 'Dịch vụ', 'price' => 0,
                'department' => 'House Keeping/Buồng Phòng'
            ],
            [
                'code' => 'LO',
                'name' => 'Late Checkout/Phụ Thu Trả Phòng Trễ',
                'service_charge' => 5, 'tax' => 8, 'special_tax' => 0,
                'include_service_charge' => true, 'include_tax' => true, 'include_special_tax' => true,
                'folio' => 1, 'short_name' => 'Phụ Thu Trả Phòng Trễ', 'unit' => 'Lần', 'price' => 0,
                'department' => 'Reception/ Lê Tân'
            ],
            [
                'code' => 'MB',
                'name' => 'Minibar/Phí Minibar',
                'service_charge' => 5, 'tax' => 8, 'special_tax' => 0,
                'include_service_charge' => true, 'include_tax' => true, 'include_special_tax' => true,
                'folio' => 1, 'short_name' => 'Phí Minibar', 'unit' => 'Dịch vụ', 'price' => 0,
                'department' => 'House Keeping/Buồng Phòng'
            ],
            [
                'code' => 'MR',
                'name' => 'Meeting / Phòng họp',
                'service_charge' => 5, 'tax' => 8, 'special_tax' => 0,
                'include_service_charge' => true, 'include_tax' => true, 'include_special_tax' => true,
                'folio' => 1, 'short_name' => 'Phòng họp', 'unit' => 'Lần', 'price' => 0,
                'department' => 'Reception/ Lê Tân'
            ],
            [
                'code' => 'MS',
                'name' => 'Other/Dịch Vụ Khác',
                'service_charge' => 5, 'tax' => 8, 'special_tax' => 0,
                'include_service_charge' => true, 'include_tax' => true, 'include_special_tax' => true,
                'folio' => 1, 'short_name' => 'Dịch Vụ Khác', 'unit' => 'Dịch vụ', 'price' => 0,
                'department' => 'Reception/ Lê Tân'
            ],
            [
                'code' => 'OT',
                'name' => 'Other/ Dịch vụ khác (FB)',
                'service_charge' => 5, 'tax' => 8, 'special_tax' => 0,
                'include_service_charge' => true, 'include_tax' => true, 'include_special_tax' => true,
                'folio' => 1, 'short_name' => 'Dịch vụ khác (FB)', 'unit' => 'Dịch vụ', 'price' => 0,
                'department' => 'Restaurant/Nhà Hàng'
            ],
            [
                'code' => 'PE',
                'name' => 'Penalty/ Phí phạt',
                'service_charge' => 5, 'tax' => 8, 'special_tax' => 0,
                'include_service_charge' => true, 'include_tax' => true, 'include_special_tax' => true,
                'folio' => 1, 'short_name' => 'Phí phạt', 'unit' => 'Lần', 'price' => 0,
                'department' => 'Reception/ Lê Tân'
            ],
            [
                'code' => 'PU',
                'name' => 'Pick up from the airport/ Đưa đón sân bay',
                'service_charge' => 5, 'tax' => 8, 'special_tax' => 0,
                'include_service_charge' => true, 'include_tax' => true, 'include_special_tax' => true,
                'folio' => 1, 'short_name' => 'Đưa đón sân bay', 'unit' => 'Lần', 'price' => 0,
                'department' => 'Reception/ Lê Tân'
            ],
            [
                'code' => 'RB',
                'name' => 'Beverage/ Thức uống',
                'service_charge' => 5, 'tax' => 8, 'special_tax' => 0,
                'include_service_charge' => true, 'include_tax' => true, 'include_special_tax' => true,
                'folio' => 1, 'short_name' => 'Thức uống', 'unit' => 'Dịch vụ', 'price' => 0,
                'department' => 'Restaurant/Nhà Hàng'
            ],
            [
                'code' => 'RF',
                'name' => 'Food/ Thức ăn',
                'service_charge' => 5, 'tax' => 8, 'special_tax' => 0,
                'include_service_charge' => true, 'include_tax' => true, 'include_special_tax' => true,
                'folio' => 1, 'short_name' => 'Thức ăn', 'unit' => 'Dịch vụ', 'price' => 0,
                'department' => 'Restaurant/Nhà Hàng'
            ],
            [
                'code' => 'RM',
                'name' => 'Dịch vụ phòng nghỉ',
                'service_charge' => 0, 'tax' => 0, 'special_tax' => 0,
                'include_service_charge' => false, 'include_tax' => false, 'include_special_tax' => false,
                'folio' => 1, 'short_name' => 'Dịch vụ phòng nghỉ', 'unit' => 'Đêm', 'price' => 0,
                'department' => 'Reception/ Lê Tân'
            ],
            [
                'code' => 'TO',
                'name' => 'Tour/ Vé Tham Quan',
                'service_charge' => 5, 'tax' => 8, 'special_tax' => 0,
                'include_service_charge' => true, 'include_tax' => true, 'include_special_tax' => true,
                'folio' => 1, 'short_name' => 'Vé Tham Quan', 'unit' => 'Lần', 'price' => 0,
                'department' => 'Reception/ Lê Tân'
            ],
            [
                'code' => 'UP',
                'name' => 'Upgrade/ Phí nâng hạng phòng',
                'service_charge' => 5, 'tax' => 8, 'special_tax' => 0,
                'include_service_charge' => true, 'include_tax' => true, 'include_special_tax' => true,
                'folio' => 1, 'short_name' => 'Phí nâng hạng phòng', 'unit' => 'Lần', 'price' => 0,
                'department' => 'Reception/ Lê Tân'
            ]
        ];

        $departmentCodes = [
            'Reception/ Lê Tân' => 'FO',
            'House Keeping/Buồng Phòng' => 'HK',
            'Restaurant/Nhà Hàng' => 'FB',
            'SPA' => 'SP',
            'Spa' => 'SP',
        ];
        $departmentIds = Department::pluck('id', 'code');
        $serviceDescriptions = [
            'BC' => 'Phụ thu ăn sáng buffet trẻ em',
            'BD' => 'Phụ thu ăn sáng trẻ em',
            'BF' => 'Tiền ăn sáng người lớn',
            'BK' => 'Phí đổ vỡ',
            'BR' => 'Phí hư hỏng',
            'DN' => 'Tiền ăn tối',
            'DO' => 'Phí tiễn sân bay',
            'EB' => 'Phụ thu thêm giường',
            'EI' => 'Phụ thu nhận phòng sớm',
            'EP' => 'Phụ thu thêm người',
            'ER' => 'Phụ thu tiền phòng',
            'FB' => 'Dịch vụ ăn uống',
            'KC' => 'Phụ thu trẻ em',
            'KE' => 'Phí thẻ phòng mất/hỏng',
            'LA' => 'Dịch vụ giặt ủi',
            'LO' => 'Phụ thu trả phòng trễ',
            'MB' => 'Dịch vụ minibar',
            'MR' => 'Dịch vụ phòng họp',
            'MS' => 'Dịch vụ khác',
            'OT' => 'Dịch vụ khác nhà hàng',
            'PE' => 'Phí phạt',
            'PU' => 'Dịch vụ đưa đón sân bay',
            'RB' => 'Dịch vụ thức uống',
            'RF' => 'Dịch vụ thức ăn',
            'RM' => 'Dịch vụ phòng nghỉ',
            'TO' => 'Vé tham quan',
            'UP' => 'Phí nâng hạng phòng',
        ];

        foreach ($services as $s) {
            $departmentCode = $departmentCodes[$s['department']] ?? $s['department'];
            unset($s['department']);
            $s['department_id'] = $departmentIds[$departmentCode];
            $service = HotelService::updateOrCreate(['code' => $s['code']], $s);
            $service->departments()->syncWithoutDetaching([
                $s['department_id'] => ['description' => $serviceDescriptions[$s['code']] ?? $s['name']],
            ]);
        }

        // 2. Seed Shifts
        $shifts = [
            ['name' => '0', 'start_time' => '22:00', 'end_time' => '05:59'],
            ['name' => '1', 'start_time' => '06:00', 'end_time' => '13:59'],
            ['name' => '2', 'start_time' => '14:00', 'end_time' => '21:59'],
        ];

        foreach ($shifts as $sh) {
            Shift::firstOrCreate(['name' => $sh['name']], $sh);
        }

        // 3. Seed Configs
        $configs = [
            ['name' => 'AllowChangeRoomStatusAtReception', 'value' => '1', 'description' => 'AllowChangeRoomStatusAtReception', 'is_visible' => false],
            ['name' => 'AllowCheckinNoShow', 'value' => '', 'description' => 'AllowCheckinNoShow', 'is_visible' => true],
            ['name' => 'AllowCheckinVacantClean', 'value' => '0', 'description' => 'Cho phép nhận phòng khi phòng ở trạng thái chờ kiểm tra/dirty (0: không cho phép, 1: cho phép)', 'is_visible' => true],
            ['name' => 'AllowChckcinNoShow', 'value' => '1', 'description' => 'AllowChckcinNoShow', 'is_visible' => false],
            ['name' => 'IsCheckBookingStatusWhenCheckin', 'value' => '0', 'description' => 'Kiểm tra tình trạng đăng ký khi nhận phòng (0: không check, 1: chỉ check-in Guaranteed/Allotment)', 'is_visible' => true],
            ['name' => 'AllowCreateOrUpdateBKCauseUnassignableRoomBK', 'value' => '1', 'description' => 'AllowCreateOrUpdateBKCauseUnassignableRoomBK', 'is_visible' => false],
            ['name' => 'AllowEarlyCheckout', 'value' => '1', 'description' => 'AllowEarlyCheckout', 'is_visible' => false],
            ['name' => 'AllowExtendDateRoomOverDateBooking', 'value' => '1', 'description' => 'Cho phép gia hạn phòng vượt quá ngày của booking', 'is_visible' => false],
            ['name' => 'AllowInputOverAV', 'value' => '0', 'description' => 'AllowInputOverAV', 'is_visible' => false],
            ['name' => 'AllowOverRoomTypeRoomKind', 'value' => '0', 'description' => 'Cho phép khóa phòng dẫn đến âm phòng (0: không cho, 1: cho phép kèm cảnh báo)', 'is_visible' => true],
            ['name' => 'AllowLockRoomCauseUnassignableRoomBK', 'value' => '0', 'description' => 'Cho phép khóa phòng khi vẫn còn trống dẫn đến các booking không thể gán số phòng (0: không cho, 1: cho phép kèm cảnh báo)', 'is_visible' => true],
            ['name' => 'AllowNegativeAmountDeposit', 'value' => '', 'description' => 'AllowNegativeAmountDeposit', 'is_visible' => false],
            ['name' => 'OOOCheckDepartment', 'value' => '0', 'description' => 'Kiểm tra bộ phận khi mở khóa OOO (0: không kiểm tra, 1: kiểm tra)', 'is_visible' => true],
            ['name' => 'OOSCheckDepartment', 'value' => '0', 'description' => 'Kiểm tra bộ phận khi mở khóa OOS (0: không kiểm tra, 1: kiểm tra)', 'is_visible' => true],
            ['name' => 'OOORoleUserUnlock', 'value' => 'Admin,FOM,Sales,HKM', 'description' => 'Quyền user được phép mở khóa OOO', 'is_visible' => true],
            ['name' => 'OOSRoleUserUnlock', 'value' => 'Admin,FOM,Sales,HKM', 'description' => 'Quyền user được phép mở khóa OOS', 'is_visible' => true],
            ['name' => 'FrmOOO_DefineLockByTime', 'value' => '23:59', 'description' => 'Thời gian kết thúc khóa mặc định', 'is_visible' => true],
            ['name' => 'ColorDefaultBookingRoomMap', 'value' => '#97D5FF', 'description' => 'Màu sắc booking mặc định trên sơ đồ phòng', 'is_visible' => true],
            ['name' => 'DefaultBreakfast', 'value' => '1', 'description' => 'Mặc định tick ăn sáng (1: có, 0: không)', 'is_visible' => true],
            ['name' => 'RoomPlan_ColorRoomReservation', 'value' => '#E3E8C4', 'description' => 'màu phòng đặt trước trên room plan', 'is_visible' => true],
            ['name' => 'RoomPlan_ColorRoomInhouse', 'value' => '#4a90e2', 'description' => 'màu phòng đang ở trên room plan', 'is_visible' => true],
            ['name' => 'RoomPlan_ColorRoomLateCheckout', 'value' => '#FCF55F', 'description' => 'màu phòng trả phòng trễ', 'is_visible' => true],
            ['name' => 'RoomPlan_ColorOOO', 'value' => '#107eeb', 'description' => 'màu phòng khóa OOO', 'is_visible' => true],
            ['name' => 'RoomPlan_ColorOOS', 'value' => '#107eeb', 'description' => 'màu phòng khóa OOS', 'is_visible' => true],
            ['name' => 'RoomPlan_AllowChangeArrivalDate', 'value' => '0', 'description' => 'Cho phép kéo thay đổi ngày đến của phòng trên Room Plan (0: không cho, 1: cho phép)', 'is_visible' => true],
            ['name' => 'AllowPostBillCheckedOutRoom', 'value' => '0', 'description' => 'Cho phép post bill sau khi phòng đã check-out (0: không cho, 1: cho phép)', 'is_visible' => true],
            ['name' => 'SyncRoomDateByBookingDate', 'value' => '1', 'description' => 'Tự động đồng bộ ngày của phòng theo ngày của booking (0: không đồng bộ, 1: đồng bộ cho phòng chưa check-in)', 'is_visible' => true],
            // Tham số trẻ em - ăn sáng
            ['name' => 'Booking_AutoExtraChargeBFChild', 'value' => '0', 'description' => 'Mặc định tự động tính phụ phí ăn sáng trẻ em khi thêm trẻ em vào phòng (0: không tính, 1: tự động tính phụ phí theo giá hotel_settings.breakfast_child_rate)', 'is_visible' => true],
            ['name' => 'Booking_BFChildSetServiceId', 'value' => 'BD', 'description' => 'Mã dịch vụ mặc định dùng khi post extra charge ăn sáng trẻ em (ví dụ: BD)', 'is_visible' => true],
            ['name' => 'BreakfastRateChild', 'value' => '0', 'description' => 'Giá ăn sáng trẻ em mặc định khi KHÔNG có extra charge (is_extra_charge = 0). Đơn vị: VND', 'is_visible' => true],
        ];

        foreach ($configs as $cfg) {
            HotelConfig::updateOrCreate(['name' => $cfg['name']], $cfg);
        }

        // 4. Seed Branches
        $branches = [
            ['code' => 'HKT1', 'name' => 'HKT 1', 'api_url' => 'https://hotel.hktsolution.vn/bepms1', 'api_report_url' => 'https://hotel.hktsolution.vn/rppms1/', 'is_master' => true],
            ['code' => 'HKT2', 'name' => 'HKT 2', 'api_url' => 'https://hotel.hktsolution.vn/bepms2', 'api_report_url' => 'https://hotel.hktsolution.vn/rppms2/', 'is_master' => false],
            ['code' => 'HKT3', 'name' => 'HKT 3', 'api_url' => 'https://hotel.hktsolution.vn/bepms3', 'api_report_url' => 'https://hotel.hktsolution.vn/rppms3/', 'is_master' => false],
        ];

        foreach ($branches as $br) {
            BranchTotal::firstOrCreate(['code' => $br['code']], $br);
        }

        // 5. Seed Templates
        $templates = [
            // Booking Confirmation group
            ['group' => 'Booking Confirmation', 'name' => 'Booking Confirmation Main', 'report' => 'BookingConfirmationGalliot'],
            ['group' => 'Booking Confirmation', 'name' => 'Booking Confirmation Sub', 'report' => 'BookingConfirmationGalliotVN'],
            ['group' => 'Booking Confirmation', 'name' => 'Booking Confirmation For Sales', 'report' => 'BookingConfirmationForSalesTulip'],
            ['group' => 'Booking Confirmation', 'name' => 'Booking Confirmation Navy Da Lat', 'report' => 'BookingConfirmationNavyDalat'],
            ['group' => 'Booking Confirmation', 'name' => 'Booking Confirmation Navy Nha Trang', 'report' => 'BookingConfirmationNavyNhatrang'],
            
            // Other groups
            ['group' => 'Registration Card', 'name' => 'Registration Card Main', 'report' => 'RegistrationCardGalliot'],
            ['group' => 'Registration Card', 'name' => 'Registration Card Navy', 'report' => 'RegistrationCardNavy'],
            ['group' => 'Receipt', 'name' => 'Navy Hotel Receipt', 'report' => 'ReceiptNavy'],
            ['group' => 'Deposit', 'name' => 'Deposit Main', 'report' => 'DepositReceiptGalliot'],
            ['group' => 'Room Morning Worksheet', 'name' => 'Room Morning Worksheet Main', 'report' => 'RoomMorningWorksheetGalliot'],
            ['group' => 'Invoice', 'name' => 'Invoice Galliot', 'report' => 'InvoiceGalliot'],
            ['group' => 'Total revenue report', 'name' => 'Total revenue report Main', 'report' => 'TotalRevenueGalliot'],
            ['group' => 'Breakfast Ticket', 'name' => 'Breakfast Ticket Main', 'report' => 'BreakfastTicketGalliot'],
            ['group' => 'Report', 'name' => 'Report Main', 'report' => 'GeneralReportGalliot'],
        ];

        foreach ($templates as $tpl) {
            Template::firstOrCreate(['report' => $tpl['report']], $tpl);
        }
    }
}
