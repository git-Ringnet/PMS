<?php

namespace Database\Seeders;

use App\Models\Market;
use App\Models\CustomerSource;
use App\Models\Branch;
use App\Models\Booker;
use App\Models\Company;
use Illuminate\Database\Seeder;

class CompanyAndPartnerSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // 1. Seed Markets (Thị trường)
        $markets = [
            ['code' => 'OTA', 'name' => 'Online Travel Agent'],
            ['code' => 'FIT', 'name' => 'Khách lẻ (Frequent Individual Traveler)'],
            ['code' => 'GIT', 'name' => 'Khách đoàn (Group Inclusive Tour)'],
            ['code' => 'TA', 'name' => 'Đại lý lữ hành (Travel Agent)'],
            ['code' => 'CORP', 'name' => 'Khách doanh nghiệp (Corporate)'],
            ['code' => 'FOC', 'name' => 'Nội bộ / Miễn phí (Free of Charge)'],
        ];

        $marketModels = [];
        foreach ($markets as $m) {
            $marketModels[$m['code']] = Market::updateOrCreate(
                ['code' => $m['code']],
                ['name' => $m['name']]
            );
        }

        // 2. Seed Customer Sources (Nguồn khách)
        $sources = [
            ['code' => 'DIRECT', 'name' => 'Khách trực tiếp'],
            ['code' => 'AGODA', 'name' => 'Agoda'],
            ['code' => 'BOOKING', 'name' => 'Booking.com'],
            ['code' => 'EXPEDIA', 'name' => 'Expedia'],
            ['code' => 'FACEBOOK', 'name' => 'Facebook'],
            ['code' => 'WEBSITE', 'name' => 'Website'],
            ['code' => 'TRAVELOKA', 'name' => 'Traveloka'],
            ['code' => 'WALKIN', 'name' => 'Khách vãng lai'],
        ];

        $sourceModels = [];
        foreach ($sources as $s) {
            $sourceModels[$s['code']] = CustomerSource::updateOrCreate(
                ['code' => $s['code']],
                ['name' => $s['name']]
            );
        }

        // 3. Seed Branches (Chi nhánh)
        $branches = [
            [
                'code' => 'HKT1',
                'name' => 'HKT 1',
                'api_url' => 'https://hotel.hktsolution.vn/bepms1',
                'api_report_url' => 'https://hotel.hktsolution.vn/rppms1/',
                'is_master' => true
            ],
            [
                'code' => 'HKT2',
                'name' => 'HKT 2',
                'api_url' => 'https://hotel.hktsolution.vn/bepms2',
                'api_report_url' => 'https://hotel.hktsolution.vn/rppms2/',
                'is_master' => false
            ],
            [
                'code' => 'HKT3',
                'name' => 'HKT 3',
                'api_url' => 'https://hotel.hktsolution.vn/bepms3',
                'api_report_url' => 'https://hotel.hktsolution.vn/rppms3/',
                'is_master' => false
            ],
        ];

        $branchModels = [];
        foreach ($branches as $b) {
            $branchModels[$b['code']] = Branch::updateOrCreate(
                ['code' => $b['code']],
                [
                    'name' => $b['name'],
                    'api_url' => $b['api_url'],
                    'api_report_url' => $b['api_report_url'],
                    'is_master' => $b['is_master']
                ]
            );
        }

        // 4. Seed Bookers (Người đặt phòng)
        $bookers = [
            [
                'name' => 'Nguyễn Văn A',
                'email' => 'nguyenvana@gmail.com',
                'phone' => '0901234567',
                'address' => 'Hoàn Kiếm, Hà Nội',
                'notes' => 'Khách hàng VIP đặt phòng nhiều lần',
            ],
            [
                'name' => 'Trần Thị B',
                'email' => 'tranthib@gmail.com',
                'phone' => '0912345678',
                'address' => 'Quận 1, TP. Hồ Chí Minh',
                'notes' => 'Người đại diện đặt phòng đoàn của Vietravel',
            ],
            [
                'name' => 'Lê Văn C',
                'email' => 'levanc@gmail.com',
                'phone' => '0923456789',
                'address' => 'Hải Châu, Đà Nẵng',
                'notes' => 'Chuyên viên phụ trách booking công ty HKT Solution',
            ],
            [
                'name' => 'Phạm Thị D',
                'email' => 'phamthid@gmail.com',
                'phone' => '0934567890',
                'address' => 'Nha Trang, Khánh Hòa',
                'notes' => 'Liên hệ từ đối tác Saigontourist',
            ],
        ];

        $bookerModels = [];
        foreach ($bookers as $bk) {
            $bookerModels[] = Booker::updateOrCreate(
                ['phone' => $bk['phone']],
                [
                    'name' => $bk['name'],
                    'email' => $bk['email'],
                    'address' => $bk['address'],
                    'notes' => $bk['notes'],
                ]
            );
        }

        // 5. Seed Companies (Công ty)
        $companies = [
            [
                'code' => 'CTY0001',
                'name' => 'Công ty TNHH Du lịch HKT',
                'trading_name' => 'HKT Travel',
                'address' => 'Quận 1, TP. Hồ Chí Minh',
                'tax_code' => '0102030405',
                'phone' => '02839999999',
                'email' => 'info@hkttravel.vn',
                'customer_source_id' => $sourceModels['AGODA']->id,
                'market_id' => $marketModels['OTA']->id,
                'sync_acc' => true,
                'max_debt' => 50000000.00,
                'bank_account' => '123456789 (Vietcombank)',
                'booker_id' => $bookerModels[0]->id,
                'rate_code' => 'FIT',
                'branch_id' => $branchModels['HKT1']->id,
                'is_active' => true,
            ],
            [
                'code' => 'CTY0002',
                'name' => 'Công ty Cổ phần Vietravel',
                'trading_name' => 'Vietravel',
                'address' => 'Quận 3, TP. Hồ Chí Minh',
                'tax_code' => '0300456789',
                'phone' => '19001839',
                'email' => 'info@vietravel.com',
                'customer_source_id' => $sourceModels['WALKIN']->id,
                'market_id' => $marketModels['GIT']->id,
                'sync_acc' => false,
                'max_debt' => 100000000.00,
                'bank_account' => '987654321 (Techcombank)',
                'booker_id' => $bookerModels[1]->id,
                'rate_code' => 'GIT',
                'branch_id' => $branchModels['HKT1']->id,
                'is_active' => true,
            ],
            [
                'code' => 'CTY0003',
                'name' => 'Công ty TNHH Saigontourist',
                'trading_name' => 'Saigontourist',
                'address' => 'Quận 1, TP. Hồ Chí Minh',
                'tax_code' => '0300567890',
                'phone' => '02838272727',
                'email' => 'info@saigontourist.net',
                'customer_source_id' => $sourceModels['TRAVELOKA']->id,
                'market_id' => $marketModels['TA']->id,
                'sync_acc' => true,
                'max_debt' => 150000000.00,
                'bank_account' => '1122334455 (BIDV)',
                'booker_id' => $bookerModels[3]->id,
                'rate_code' => 'TA',
                'branch_id' => $branchModels['HKT2']->id,
                'is_active' => true,
            ],
            [
                'code' => 'CTY0004',
                'name' => 'Công ty TNHH HKT Solution',
                'trading_name' => 'HKT Solution',
                'address' => 'Quận Bình Thạnh, TP. Hồ Chí Minh',
                'tax_code' => '0312345678',
                'phone' => '02862828282',
                'email' => 'contact@hktsolution.vn',
                'customer_source_id' => $sourceModels['DIRECT']->id,
                'market_id' => $marketModels['CORP']->id,
                'sync_acc' => true,
                'max_debt' => 20000000.00,
                'bank_account' => '2233445566 (ACB)',
                'booker_id' => $bookerModels[2]->id,
                'rate_code' => 'FIT',
                'branch_id' => $branchModels['HKT3']->id,
                'is_active' => true,
            ],
        ];

        foreach ($companies as $c) {
            Company::updateOrCreate(
                ['code' => $c['code']],
                [
                    'name' => $c['name'],
                    'trading_name' => $c['trading_name'],
                    'address' => $c['address'],
                    'tax_code' => $c['tax_code'],
                    'phone' => $c['phone'],
                    'email' => $c['email'],
                    'customer_source_id' => $c['customer_source_id'],
                    'market_id' => $c['market_id'],
                    'sync_acc' => $c['sync_acc'],
                    'max_debt' => $c['max_debt'],
                    'bank_account' => $c['bank_account'],
                    'booker_id' => $c['booker_id'],
                    'rate_code' => $c['rate_code'],
                    'branch_id' => $c['branch_id'],
                    'is_active' => $c['is_active'],
                ]
            );
        }
    }
}
