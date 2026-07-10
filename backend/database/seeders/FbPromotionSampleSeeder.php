<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\FbPromotion;
use App\Models\FbPromotionProduct;
use App\Models\FbProduct;
use App\Models\Outlet;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class FbPromotionSampleSeeder extends Seeder
{
    public function run(): void
    {
        DB::table('fb_promotion_products')->delete();
        DB::table('fb_promotions')->delete();

        $outlets = Outlet::all();
        $products = FbProduct::all();

        if ($outlets->isEmpty() || $products->isEmpty()) {
            return;
        }

        // 1. Giảm 10% toàn bộ thực đơn
        FbPromotion::create([
            'name' => 'Giảm 10% Toàn Bộ Thực Đơn (Khai Trương)',
            'outlet_id' => $outlets->first()->id,
            'discount_percent' => 10,
            'increase_percent' => 0,
            'discount_amount' => 0,
            'increase_amount' => 0,
            'is_auto_apply' => true,
            'is_active' => true,
            'start_date' => Carbon::now()->subDays(5),
            'end_date' => Carbon::now()->addDays(30),
            'apply_by_time' => false,
            'is_all_product' => true,
        ]);

        // 2. Happy Hour Giảm 20% Đồ Uống
        $happyHour = FbPromotion::create([
            'name' => 'Happy Hour (16:00 - 19:00) Giảm 20% Đồ Uống',
            'outlet_id' => null, // Áp dụng mọi outlet
            'discount_percent' => 20,
            'increase_percent' => 0,
            'discount_amount' => 0,
            'increase_amount' => 0,
            'is_auto_apply' => true,
            'is_active' => true,
            'start_date' => Carbon::now()->subDays(10),
            'end_date' => Carbon::now()->addDays(60),
            'apply_by_time' => true,
            'start_time' => '16:00:00',
            'end_time' => '19:00:00',
            'is_all_product' => false,
        ]);

        // Gán các sản phẩm đồ uống vào Happy Hour
        $drinkProducts = FbProduct::where('is_alcohol', true)
            ->orWhere('service_group', 'LIKE', '%Nước%')
            ->limit(20)
            ->get();
            
        foreach ($drinkProducts as $drink) {
            FbPromotionProduct::create([
                'fb_promotion_id' => $happyHour->id,
                'fb_product_id' => $drink->id,
            ]);
        }

        // 3. Phụ thu ngày Lễ 15%
        FbPromotion::create([
            'name' => 'Phụ thu Lễ Tết 15%',
            'outlet_id' => null,
            'discount_percent' => 0,
            'increase_percent' => 15,
            'discount_amount' => 0,
            'increase_amount' => 0,
            'is_auto_apply' => false, // Nhân viên tự chọn khi cần
            'is_active' => true,
            'start_date' => Carbon::now()->subDays(1),
            'end_date' => Carbon::now()->addDays(5),
            'apply_by_time' => false,
            'is_all_product' => true,
        ]);

        // 4. Giảm trực tiếp 50,000 VND
        $voucher50k = FbPromotion::create([
            'name' => 'Voucher Giảm 50.000 VNĐ (Món Chính)',
            'outlet_id' => null,
            'discount_percent' => 0,
            'increase_percent' => 0,
            'discount_amount' => 50000,
            'increase_amount' => 0,
            'is_auto_apply' => false,
            'is_active' => true,
            'start_date' => Carbon::now()->subDays(20),
            'end_date' => Carbon::now()->addDays(100),
            'apply_by_time' => false,
            'is_all_product' => false,
        ]);

        // Gán các món chính có giá > 100k vào Voucher 50k
        $mainCourses = FbProduct::where('price', '>=', 100000)
            ->where('service_group', 'LIKE', '%Ăn%')
            ->limit(10)
            ->get();

        foreach ($mainCourses as $main) {
            FbPromotionProduct::create([
                'fb_promotion_id' => $voucher50k->id,
                'fb_product_id' => $main->id,
            ]);
        }
    }
}
