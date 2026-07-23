<?php

namespace Database\Seeders;

use App\Models\PaymentMethod;
use App\Models\Currency;
use App\Models\UnitOfMeasure;
use App\Models\RoomRateCode;
use App\Models\RegistrationStatus;
use Illuminate\Database\Seeder;

class SystemDefinitionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // 1. Payment Methods
        $paymentMethods = [
            [
                'code' => 'CL',
                'name' => 'Complimentary',
                'account' => '',
                'account_name' => '',
                'bank_name' => 'Complimentary',
                'service_charge' => 0.00,
                'department' => '',
                'payment_group' => 5, // 5: MIỄN PHÍ
                'is_free' => true,
                'is_inactive' => true,
            ],
            [
                'code' => 'CA',
                'name' => 'Cash',
                'account' => '',
                'account_name' => '',
                'bank_name' => 'Cash',
                'service_charge' => 0.00,
                'department' => '',
                'payment_group' => 1, // 1: TIỀN MẶT
                'is_free' => false,
                'is_inactive' => false,
            ],
            [
                'code' => 'AC',
                'name' => 'City ledger',
                'account' => '',
                'account_name' => '',
                'bank_name' => 'City ledger',
                'service_charge' => 0.00,
                'department' => '',
                'payment_group' => 4, // 4: CÔNG NỢ
                'is_free' => false,
                'is_inactive' => false,
            ],
            [
                'code' => 'BT',
                'name' => 'Bank transfer',
                'account' => '',
                'account_name' => '',
                'bank_name' => 'Bank transfer',
                'service_charge' => 0.00,
                'department' => 'Reception/ Lê Tân, Restaurant/Nhà Hàng',
                'payment_group' => 2, // 2: THẺ CK
                'is_free' => false,
                'is_inactive' => false,
            ],
            [
                'code' => 'VO',
                'name' => 'Voucher',
                'account' => '',
                'account_name' => '',
                'bank_name' => 'Voucher',
                'service_charge' => 0.00,
                'department' => '',
                'payment_group' => 3, // 3: VOUCHER
                'is_free' => false,
                'is_inactive' => false,
            ],
            [
                'code' => 'CD',
                'name' => 'Credit Card',
                'account' => '',
                'account_name' => '',
                'bank_name' => 'Credit Card',
                'service_charge' => 0.00,
                'department' => '',
                'payment_group' => 2, // 2: THẺ CK
                'is_free' => false,
                'is_inactive' => false,
            ],
        ];

        foreach ($paymentMethods as $pm) {
            PaymentMethod::firstOrCreate(['code' => $pm['code']], $pm);
        }

        // 2. Currencies
        $currencies = [
            [
                'code' => 'VND',
                'name' => 'Viet Nam Dong',
                'country' => 'Vietnam',
                'short_name' => 'đ',
                'decimals_to_round' => 0,
                'is_main' => true,
                'is_active' => true,
                'exchange_rate' => 1.0000,
                'image_path' => null,
            ]
        ];

        foreach ($currencies as $curr) {
            Currency::firstOrCreate(['code' => $curr['code']], $curr);
        }

        // 3. Units of Measure
        $units = [
            ['code' => '001', 'name' => 'Bịch', 'symbol' => 'Bịch', 'is_inactive' => false],
            ['code' => '00153', 'name' => 'glass 6', 'symbol' => 'glass 6', 'is_inactive' => false],
            ['code' => '00158', 'name' => 'Jug', 'symbol' => 'Jug', 'is_inactive' => false],
            ['code' => '002', 'name' => 'Bộ', 'symbol' => 'Bộ', 'is_inactive' => false],
            ['code' => '003', 'name' => 'Cái', 'symbol' => 'Cái', 'is_inactive' => false],
            ['code' => '004', 'name' => 'Cây', 'symbol' => 'Cây', 'is_inactive' => false],
            ['code' => '005', 'name' => 'Chai', 'symbol' => 'Chai', 'is_inactive' => false],
            ['code' => '006', 'name' => 'Chiếc', 'symbol' => 'Chiếc', 'is_inactive' => false],
            ['code' => '007', 'name' => 'Cuộn', 'symbol' => 'Cuộn', 'is_inactive' => false],
            ['code' => '008', 'name' => 'Đôi', 'symbol' => 'Đôi', 'is_inactive' => false],
        ];

        foreach ($units as $u) {
            UnitOfMeasure::firstOrCreate(['code' => $u['code']], $u);
        }

        // 4. Room Rate Codes & Plans
        $rateCodes = [
            [
                'Ma' => 'STANDARD',
                'Description' => 'Giá Tiêu Chuẩn / Công Bố',
                'BeginDate' => '2020-01-01',
                'EndDate' => '2030-12-31',
                'IncludeBF' => true,
                'Currency' => 'VND',
                'Type' => 'FIT',
                'Value' => 950000,
                'Disable' => false,
                'AllowChangeRate' => true,
                'IsChannelManager' => false,
            ],
            [
                'Ma' => 'PROMO2026',
                'Description' => 'Chương Trình Khuyến Mãi Hè 2026',
                'BeginDate' => '2026-01-01',
                'EndDate' => '2026-12-31',
                'IncludeBF' => true,
                'Currency' => 'VND',
                'Type' => 'PROMO',
                'Value' => 750000,
                'Disable' => false,
                'AllowChangeRate' => true,
                'IsChannelManager' => false,
            ],
            [
                'Ma' => 'CORP',
                'Description' => 'Giá Ưu Đãi Khách Doanh Nghiệp',
                'BeginDate' => '2020-01-01',
                'EndDate' => '2030-12-31',
                'IncludeBF' => true,
                'Currency' => 'VND',
                'Type' => 'CORP',
                'Value' => 850000,
                'Disable' => false,
                'AllowChangeRate' => true,
                'IsChannelManager' => false,
            ],
            [
                'Ma' => 'FOC',
                'Description' => 'Miễn Phí (Free of Charge)',
                'BeginDate' => '2018-12-31',
                'EndDate' => '2029-12-31',
                'IncludeBF' => true,
                'Currency' => 'VND',
                'Type' => 'FIT',
                'Value' => 0,
                'Disable' => false,
                'AllowChangeRate' => false,
                'IsChannelManager' => false,
            ],
            [
                'Ma' => 'HU',
                'Description' => 'Phục Vụ Nội Bộ (House Use)',
                'BeginDate' => '2019-06-03',
                'EndDate' => '2030-06-03',
                'IncludeBF' => true,
                'Currency' => 'VND',
                'Type' => 'FIT',
                'Value' => 0,
                'Disable' => false,
                'AllowChangeRate' => false,
                'IsChannelManager' => false,
            ]
        ];

        foreach ($rateCodes as $rc) {
            $createdRc = RoomRateCode::updateOrCreate(['Ma' => $rc['Ma']], $rc);
            
            // Khởi tạo bảng giá plan tương ứng
            \App\Models\RoomRatePlan::updateOrCreate(
                ['RateCode' => $createdRc->Ma, 'Code' => 'DEFAULT'],
                [
                    'Description' => $createdRc->Description,
                    'Period' => [
                        1 => $rc['Value'] > 0 ? $rc['Value'] : 0,
                        2 => $rc['Value'] > 0 ? $rc['Value'] + 200000 : 0,
                        3 => $rc['Value'] > 0 ? $rc['Value'] + 400000 : 0,
                        4 => $rc['Value'] > 0 ? $rc['Value'] + 600000 : 0,
                    ]
                ]
            );
        }

        // 5. Registration Statuses (Bảng SP1311 - TinhTrangDangKy với booking_status_id và id tự tăng 1..8)
        $statuses = [
            [
                'id' => 1,
                'booking_status_id' => 1,
                'name' => 'Guaranteed',
                'color' => '#4ce410',
                'cut_off_day' => 0,
                'description' => 'Guaranteed',
                'is_hidden' => false,
                'is_availability' => true,
                'bk_definite' => 1,
                'vietnamese' => null,
                'english' => null,
                'order_index' => null,
            ],
            [
                'id' => 2,
                'booking_status_id' => 20,
                'name' => 'None Guaranteed',
                'color' => '#c7d9e0',
                'cut_off_day' => 0,
                'description' => 'None Guaranteed',
                'is_hidden' => false,
                'is_availability' => true,
                'bk_definite' => 0,
                'vietnamese' => null,
                'english' => null,
                'order_index' => null,
            ],
            [
                'id' => 3,
                'booking_status_id' => 24,
                'name' => 'Cancelled with Penalty',
                'color' => '#f76b15',
                'cut_off_day' => 0,
                'description' => 'Cancelled',
                'is_hidden' => false,
                'is_availability' => false,
                'bk_definite' => 2,
                'vietnamese' => null,
                'english' => null,
                'order_index' => null,
            ],
            [
                'id' => 4,
                'booking_status_id' => 25,
                'name' => 'Noshow with Penalty',
                'color' => '#5e189e',
                'cut_off_day' => 0,
                'description' => 'Noshow with Penalty',
                'is_hidden' => false,
                'is_availability' => false,
                'bk_definite' => 1,
                'vietnamese' => null,
                'english' => null,
                'order_index' => 0,
            ],
            [
                'id' => 5,
                'booking_status_id' => 26,
                'name' => 'Cancelled without Penalty',
                'color' => '#4086f7',
                'cut_off_day' => 0,
                'description' => 'Hủy phòng không tính phí',
                'is_hidden' => false,
                'is_availability' => false,
                'bk_definite' => 1,
                'vietnamese' => null,
                'english' => null,
                'order_index' => 0,
            ],
            [
                'id' => 6,
                'booking_status_id' => 27,
                'name' => 'Allotment',
                'color' => '#f77940',
                'cut_off_day' => 5,
                'description' => '',
                'is_hidden' => false,
                'is_availability' => true,
                'bk_definite' => 3,
                'vietnamese' => null,
                'english' => null,
                'order_index' => null,
            ],
            [
                'id' => 7,
                'booking_status_id' => 28,
                'name' => 'Cancelled',
                'color' => '#f74043',
                'cut_off_day' => 0,
                'description' => 'Hủy phòng',
                'is_hidden' => false,
                'is_availability' => false,
                'bk_definite' => 4, // Trạng thái chuyển mặc định khi hủy booking
                'vietnamese' => null,
                'english' => null,
                'order_index' => null,
            ],
            [
                'id' => 8,
                'booking_status_id' => 29,
                'name' => 'Waiting',
                'color' => '#f74b40',
                'cut_off_day' => 5,
                'description' => 'Chờ xếp phòng',
                'is_hidden' => false,
                'is_availability' => false,
                'bk_definite' => 0,
                'vietnamese' => null,
                'english' => null,
                'order_index' => null,
            ],
        ];

        RegistrationStatus::whereNotIn('id', array_column($statuses, 'id'))->delete();

        foreach ($statuses as $st) {
            RegistrationStatus::updateOrCreate(['id' => $st['id']], $st);
        }
    }
}
