<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\ProductCategory;
use App\Models\Product;
use App\Models\Inventory;
use Faker\Factory as Faker;

class MenuProductSeeder extends Seeder
{
    public function run()
    {
        $faker = Faker::create('vi_VN');

        // Create some inventories
        $inventories = [
            'Kho Minibar',
            'Kho Bếp',
            'Kho Giặt ủi',
            'Kho Tiêu hao',
            'Kho Tổng',
        ];

        foreach ($inventories as $invName) {
            Inventory::firstOrCreate(['name' => $invName]);
        }
        
        $inventoryIds = Inventory::pluck('id')->toArray();

        // 4 tabs
        $outlets = [
            'Minibar' => [
                'Nước ngọt', 'Bia', 'Nước suối', 'Bánh snack', 'Trái cây', 'Rượu mini'
            ],
            'Giặt ủi' => [
                'Giặt khô', 'Giặt ướt', 'Ủi đồ', 'Giặt & Ủi', 'Làm sạch đặc biệt'
            ],
            'Hàng đền bù' => [
                'Đồ dùng phòng', 'Thiết bị điện tử', 'Trang trí', 'Đồ gốm sứ', 'Đồ vải'
            ],
            'Amenity' => [
                'Sữa tắm/Gội', 'Đồ vệ sinh cá nhân', 'Dép đi trong nhà', 'Trà & Cà phê', 'Vật dụng khác'
            ]
        ];

        $productNames = [
            'Minibar' => [
                'Nước ngọt' => ['Coca Cola', 'Pepsi', 'Sprite', '7Up', 'Fanta', 'Mirinda', 'Sting', 'Redbull', 'Nutriboost', 'Trà Oolong Tea Plus'],
                'Bia' => ['Bia Tiger', 'Bia Heineken', 'Bia 333', 'Bia Saigon', 'Bia Sapporo', 'Bia Budweiser', 'Bia Corona', 'Bia Hoegaarden'],
                'Nước suối' => ['Lavie 500ml', 'Aquafina 500ml', 'Dasani 500ml', 'Evian 330ml', 'Vĩnh Hảo 500ml'],
                'Bánh snack' => ['Oishi Snack', 'Poca', 'Lay\'s', 'Kinh Đô', 'Choco-pie', 'Bánh Danisa', 'Mực bento', 'Snack rong biển'],
                'Trái cây' => ['Trái cây sấy', 'Nho khô', 'Hạt điều rang muối', 'Đậu phộng Tân Tân', 'Mít sấy'],
                'Rượu mini' => ['Chivas 12 50ml', 'Vodka Hanoi', 'Gin 50ml', 'Hennessy 50ml']
            ],
            'Giặt ủi' => [
                'Giặt khô' => ['Áo vest', 'Quần âu', 'Áo dạ', 'Váy dạ hội', 'Áo len'],
                'Giặt ướt' => ['Áo sơ mi', 'Quần jean', 'Áo phông', 'Quần short', 'Đồ thể thao'],
                'Ủi đồ' => ['Ủi áo sơ mi', 'Ủi quần âu', 'Ủi váy', 'Ủi áo vest'],
                'Giặt & Ủi' => ['Bộ suit hoàn chỉnh', 'Váy cưới', 'Áo dài', 'Đồ ngủ'],
                'Làm sạch đặc biệt' => ['Làm sạch giày', 'Tẩy vết ố', 'Làm sạch túi xách', 'Vệ sinh áo lông']
            ],
            'Hàng đền bù' => [
                'Đồ dùng phòng' => ['Khăn tắm', 'Khăn mặt', 'Thảm chùi chân', 'Rèm cửa', 'Móc treo quần áo', 'Khay xà phòng'],
                'Thiết bị điện tử' => ['Điều khiển TV', 'Điều khiển điều hòa', 'Máy sấy tóc', 'Ấm siêu tốc', 'Đèn bàn'],
                'Trang trí' => ['Lọ hoa', 'Khung tranh', 'Gạt tàn thuốc', 'Tượng trang trí'],
                'Đồ gốm sứ' => ['Cốc sứ', 'Đĩa', 'Bát', 'Bình trà', 'Tách trà'],
                'Đồ vải' => ['Vỏ gối', 'Ga trải giường', 'Ruột chăn', 'Vỏ chăn']
            ],
            'Amenity' => [
                'Sữa tắm/Gội' => ['Sữa tắm 30ml', 'Dầu gội 30ml', 'Dầu xả 30ml', 'Sữa dưỡng thể 30ml', 'Bánh xà phòng'],
                'Đồ vệ sinh cá nhân' => ['Bàn chải đánh răng', 'Kem đánh răng mini', 'Lược chải đầu', 'Dao cạo râu', 'Tăm bông', 'Chụp tóc', 'Bông tẩy trang'],
                'Dép đi trong nhà' => ['Dép xốp', 'Dép vải bông', 'Dép cao su'],
                'Trà & Cà phê' => ['Trà Lipton', 'Trà sen', 'Cà phê G7', 'Cà phê Nescafe', 'Đường gói', 'Sữa đặc túi'],
                'Vật dụng khác' => ['Xi đánh giày', 'Bút bi', 'Giấy note', 'Phong bì', 'Túi giặt ủi', 'Muỗng nhựa', 'Diêm']
            ]
        ];

        // Ensure exactly 150 items
        $totalItems = 0;
        $targetItems = 150;

        foreach ($outlets as $outlet => $categories) {
            foreach ($categories as $categoryName) {
                $category = ProductCategory::firstOrCreate([
                    'name' => $categoryName,
                    'outlet' => $outlet
                ]);

                $names = $productNames[$outlet][$categoryName] ?? [];
                
                foreach ($names as $name) {
                    if ($totalItems >= $targetItems) {
                        break 3; // Break all loops
                    }
                    
                    $price = $faker->randomElement([15000, 20000, 30000, 50000, 100000, 150000, 200000]);
                    
                    Product::firstOrCreate(
                        ['name' => $name, 'product_category_id' => $category->id],
                        [
                            'currency' => 'VNĐ',
                            'price' => $price,
                            'note' => $faker->sentence(),
                            'change_table' => false,
                            'open_key' => false,
                            'is_alcohol' => ($outlet === 'Minibar' && in_array($categoryName, ['Bia', 'Rượu mini'])),
                            'goods' => 'Hàng hóa',
                            'is_in_stock' => $faker->numberBetween(0, 100),
                            'service_charge_percent' => 5,
                            'tax_percent' => 10,
                            'special_tax_percent' => 0,
                            'original_amount' => $price,
                            'service_charge_amount' => $price * 0.05,
                            'tax_amount' => $price * 0.1,
                            'special_tax_amount' => 0,
                            'inventory_id' => $faker->randomElement($inventoryIds),
                            'flexible_price' => $faker->boolean(20),
                            'is_active' => $faker->boolean(90),
                            'track_stock' => $faker->boolean(80)
                        ]
                    );
                    
                    $totalItems++;
                }
            }
        }
        
        // Fill remaining items if any
        while ($totalItems < $targetItems) {
            $outlet = $faker->randomElement(array_keys($outlets));
            $categoryName = $faker->randomElement($outlets[$outlet]);
            $category = ProductCategory::firstOrCreate([
                'name' => $categoryName,
                'outlet' => $outlet
            ]);
            
            $price = $faker->randomElement([15000, 20000, 30000, 50000, 100000, 150000, 200000]);
            
            Product::create([
                'product_category_id' => $category->id,
                'name' => $categoryName . ' - Mẫu ' . $faker->unique()->randomNumber(4),
                'currency' => 'VNĐ',
                'price' => $price,
                'note' => $faker->sentence(),
                'change_table' => false,
                'open_key' => false,
                'is_alcohol' => false,
                'goods' => 'Hàng hóa',
                'is_in_stock' => $faker->numberBetween(0, 100),
                'service_charge_percent' => 5,
                'tax_percent' => 10,
                'special_tax_percent' => 0,
                'original_amount' => $price,
                'service_charge_amount' => $price * 0.05,
                'tax_amount' => $price * 0.1,
                'special_tax_amount' => 0,
                'inventory_id' => $faker->randomElement($inventoryIds),
                'flexible_price' => $faker->boolean(20),
                'is_active' => $faker->boolean(90),
                'track_stock' => $faker->boolean(80)
            ]);
            $totalItems++;
        }
    }
}
