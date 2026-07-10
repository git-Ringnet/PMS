<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\FbProductCategory;
use App\Models\FbProduct;
use App\Models\UnitOfMeasure;
use App\Models\Outlet;
use Illuminate\Support\Facades\DB;

class FbProductSampleSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Setup đa dạng Đơn vị tính (Units)
        $units = [
            'Phần', 'Ly', 'Chai', 'Lon', 'Đĩa', 'Bát', 'Chén', 'Nồi', 'Lít', 'Kg', 'Con', 'Suất', 'Combo', 'Cái'
        ];
        $unitModels = [];
        foreach ($units as $uName) {
            $code = strtoupper(str_replace([' ', 'đ', 'Đ'], ['', 'D', 'D'], $this->removeAccents($uName)));
            $unitModels[$uName] = UnitOfMeasure::firstOrCreate(['code' => $code], ['name' => $uName]);
        }

        // 2. Setup đa dạng Danh mục (Categories)
        $catFood = FbProductCategory::firstOrCreate(['code' => 'FOOD'], ['name' => 'Đồ ăn', 'order_index' => 1]);
        $catDrinks = FbProductCategory::firstOrCreate(['code' => 'DRINK'], ['name' => 'Đồ uống', 'order_index' => 2]);
        $catOther = FbProductCategory::firstOrCreate(['code' => 'OTHER'], ['name' => 'Dịch vụ & Khác', 'order_index' => 3]);

        $subCats = [
            // Food
            ['name' => 'Khai vị', 'code' => 'APP', 'parent_id' => $catFood->id, 'baseName' => ['Súp cua', 'Salad cá ngừ', 'Gỏi ngó sen', 'Chả giò rế', 'Nem cua bể', 'Salad trộn', 'Bánh xèo mini', 'Há cảo'], 'units' => ['Phần', 'Đĩa', 'Bát']],
            ['name' => 'Món chính', 'code' => 'MAIN', 'parent_id' => $catFood->id, 'baseName' => ['Cơm chiên hải sản', 'Bò tơ nướng', 'Gà hấp lá chanh', 'Cá chép om dưa', 'Heo rừng xào lăn', 'Sườn chua ngọt', 'Vịt quay Bắc Kinh', 'Mực hấp gừng'], 'units' => ['Phần', 'Đĩa', 'Con', 'Kg']],
            ['name' => 'Hải sản', 'code' => 'SEA', 'parent_id' => $catFood->id, 'baseName' => ['Tôm hùm nướng phô mai', 'Cua biển rang me', 'Mực một nắng', 'Nghêu hấp xả', 'Hàu nướng mỡ hành', 'Ghẹ hấp', 'Sò huyết rang muối'], 'units' => ['Đĩa', 'Kg', 'Con', 'Phần']],
            ['name' => 'Lẩu', 'code' => 'HOT', 'parent_id' => $catFood->id, 'baseName' => ['Lẩu Thái chua cay', 'Lẩu hải sản', 'Lẩu bò nhúng dấm', 'Lẩu gà lá giang', 'Lẩu nấm', 'Lẩu cá kèo'], 'units' => ['Nồi', 'Phần']],
            ['name' => 'Tráng miệng', 'code' => 'DES', 'parent_id' => $catFood->id, 'baseName' => ['Chè khúc bạch', 'Sữa chua hạt đác', 'Bánh flan', 'Kem vani', 'Trái cây theo mùa', 'Bánh Tiramisu', 'Rau câu dừa'], 'units' => ['Phần', 'Đĩa', 'Chén', 'Ly']],
            ['name' => 'Món chay', 'code' => 'VEG', 'parent_id' => $catFood->id, 'baseName' => ['Đậu hũ tứ xuyên', 'Gỏi chay', 'Cơm chiên chay', 'Canh nấm chay', 'Nấm kho tiêu', 'Bún xào chay'], 'units' => ['Phần', 'Đĩa']],
            
            // Drinks
            ['name' => 'Nước có cồn', 'code' => 'ALC', 'parent_id' => $catDrinks->id, 'baseName' => ['Bia Heineken', 'Bia Tiger', 'Rượu Vang Đỏ', 'Rượu Vang Trắng', 'Rượu Soju', 'Bia Saigon', 'Cocktail Margarita'], 'units' => ['Chai', 'Lon', 'Ly', 'Lít']],
            ['name' => 'Nước giải khát', 'code' => 'SOFT', 'parent_id' => $catDrinks->id, 'baseName' => ['Coca Cola', 'Pepsi', 'Nước suối', '7Up', 'Sting', 'Redbull', 'Trà đá'], 'units' => ['Chai', 'Lon', 'Ly']],
            ['name' => 'Nước ép & Sinh tố', 'code' => 'JUICE', 'parent_id' => $catDrinks->id, 'baseName' => ['Nước ép cam', 'Nước ép lựu', 'Sinh tố dâu', 'Sinh tố bơ', 'Nước ép dưa hấu', 'Nước chanh dây'], 'units' => ['Ly', 'Phần']],
            
            // Other
            ['name' => 'Set Menu', 'code' => 'SET', 'parent_id' => $catOther->id, 'baseName' => ['Set menu Tiệc cưới', 'Set menu Gia đình', 'Combo BBQ', 'Set Hải sản VIP', 'Combo ăn sáng'], 'units' => ['Combo', 'Suất']],
            ['name' => 'Dịch vụ thêm', 'code' => 'SVC', 'parent_id' => $catOther->id, 'baseName' => ['Phí mang rượu', 'Phí phòng VIP', 'Karaoke', 'Trang trí tiệc', 'Thuê máy chiếu'], 'units' => ['Phần', 'Cái']],
        ];

        $categoryModels = [];
        $order = 1;
        foreach ($subCats as $c) {
            $cat = FbProductCategory::firstOrCreate(['code' => $c['code']], ['name' => $c['name'], 'parent_id' => $c['parent_id'], 'order_index' => $order++]);
            $c['model'] = $cat;
            $categoryModels[] = $c;
        }

        $outlets = Outlet::all();

        // 3. Xóa các món cũ và tạo 400 món mới
        DB::table('fb_product_outlets')->delete();
        DB::table('fb_products')->delete();

        for ($i = 1; $i <= 400; $i++) {
            $catInfo = $categoryModels[array_rand($categoryModels)];
            
            $baseName = $catInfo['baseName'][array_rand($catInfo['baseName'])];
            $suffixes = ['Đặc biệt', 'Thường', 'VIP', 'Hảo hạng', 'Size L', 'Size M', 'Size S', 'Nóng', 'Lạnh', 'Ngon', 'Truyền thống', 'Cay', 'Không cay'];
            $name = $baseName . ' ' . $suffixes[array_rand($suffixes)] . ' ' . rand(1, 999);
            
            $unitName = $catInfo['units'][array_rand($catInfo['units'])];
            $unitId = $unitModels[$unitName]->id;

            $price = rand(15, 1500) * 1000;
            $original = round($price * (rand(40, 85) / 100));
            $taxPercent = rand(0, 1) ? 8 : 10;
            $serviceCharge = rand(0, 1) ? 5 : 0;

            $product = FbProduct::create([
                'name' => $name,
                'product_code' => $catInfo['code'] . str_pad($i, 4, '0', STR_PAD_LEFT),
                'fb_product_category_id' => $catInfo['model']->id,
                'unit_id' => $unitId,
                'price' => $price,
                'original_amount' => $original,
                'service_charge_percent' => $serviceCharge,
                'tax_percent' => $taxPercent,
                'special_tax_percent' => rand(0, 100) > 90 ? 10 : 0,
                'is_active' => rand(1, 100) <= 90, // 90% active
                'is_combo' => $catInfo['code'] === 'SET',
                'open_key' => rand(1, 100) <= 10,
                'is_alcohol' => $catInfo['code'] === 'ALC',
                'track_stock' => rand(1, 100) <= 50,
                'is_in_stock' => rand(1, 100) <= 95 ? 1 : 0,
                'note' => rand(1, 100) <= 20 ? 'Nguyên liệu nhập mới trong ngày' : null,
                'service_group' => in_array($catInfo['code'], ['SET', 'SVC']) ? 'Dịch vụ khác' : 'Ăn uống',
            ]);

            // Setup giá riêng theo Outlet
            foreach ($outlets as $outlet) {
                if (rand(1, 100) <= 80) { // 80% có mặt ở outlet
                    $hasCustomPrice = rand(1, 100) <= 40;
                    
                    $product->outletPrices()->create([
                        'outlet_id' => $outlet->id,
                        'is_active' => rand(1, 100) <= 90,
                        'update_price' => $hasCustomPrice,
                        'price' => $hasCustomPrice ? $price + (rand(-5, 10) * 1000) : $price,
                        'original_amount' => $original,
                        'service_charge_percent' => $serviceCharge,
                        'tax_percent' => $taxPercent,
                        'special_tax_percent' => 0,
                        'combo_price' => 0,
                        'combo_original' => 0,
                        'combo_service' => 0,
                        'combo_tax' => 0,
                        'combo_special' => 0,
                        'update_combo_price' => false,
                        'is_expanded' => rand(1, 100) <= 20,
                        'selectedCounterOutlets' => [],
                    ]);
                }
            }
        }
    }

    private function removeAccents($str) {
        $str = preg_replace("/(à|á|ạ|ả|ã|â|ầ|ấ|ậ|ẩ|ẫ|ă|ằ|ắ|ặ|ẳ|ẵ)/", "a", $str);
        $str = preg_replace("/(è|é|ẹ|ẻ|ẽ|ê|ề|ế|ệ|ể|ễ)/", "e", $str);
        $str = preg_replace("/(ì|í|ị|ỉ|ĩ)/", "i", $str);
        $str = preg_replace("/(ò|ó|ọ|ỏ|õ|ô|ồ|ố|ộ|ổ|ỗ|ơ|ờ|ớ|ợ|ở|ỡ)/", "o", $str);
        $str = preg_replace("/(ù|ú|ụ|ủ|ũ|ư|ừ|ứ|ự|ử|ữ)/", "u", $str);
        $str = preg_replace("/(ỳ|ý|ỵ|ỷ|ỹ)/", "y", $str);
        $str = preg_replace("/(đ)/", "d", $str);
        $str = preg_replace("/(À|Á|Ạ|Ả|Ã|Â|Ầ|Ấ|Ậ|Ẩ|Ẫ|Ă|Ằ|Ắ|Ặ|Ẳ|Ẵ)/", "A", $str);
        $str = preg_replace("/(È|É|Ẹ|Ẻ|Ẽ|Ê|Ề|Ế|Ệ|Ể|Ễ)/", "E", $str);
        $str = preg_replace("/(Ì|Í|Ị|Ỉ|Ĩ)/", "I", $str);
        $str = preg_replace("/(Ò|Ó|Ọ|Ỏ|Õ|Ô|Ồ|Ố|Ộ|Ổ|Ỗ|Ơ|Ờ|Ớ|Ợ|Ở|Ỡ)/", "O", $str);
        $str = preg_replace("/(Ù|Ú|Ụ|Ủ|Ũ|Ư|Ừ|Ứ|Ự|Ử|Ữ)/", "U", $str);
        $str = preg_replace("/(Ỳ|Ý|Ỵ|Ỷ|Ỹ)/", "Y", $str);
        $str = preg_replace("/(Đ)/", "D", $str);
        return $str;
    }
}
