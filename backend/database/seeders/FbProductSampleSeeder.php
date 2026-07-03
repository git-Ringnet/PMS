<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\FbProductCategory;
use App\Models\FbProduct;
use App\Models\UnitOfMeasure;
use App\Models\Outlet;
use Illuminate\Support\Str;

class FbProductSampleSeeder extends Seeder
{
    public function run(): void
    {
        // Sample Unit
        $unit = UnitOfMeasure::firstOrCreate(
            ['name' => 'Phần'],
            ['code' => 'PHAN']
        );
        $unitLy = UnitOfMeasure::firstOrCreate(
            ['name' => 'Ly'],
            ['code' => 'LY']
        );

        // Sample Categories
        $catFood = FbProductCategory::firstOrCreate(['code' => 'FOOD'], ['name' => 'Đồ ăn', 'order_index' => 1]);
        $catAppetizers = FbProductCategory::firstOrCreate(['code' => 'APP'], ['name' => 'Khai vị', 'parent_id' => $catFood->id, 'order_index' => 1]);
        $catMain = FbProductCategory::firstOrCreate(['code' => 'MAIN'], ['name' => 'Món chính', 'parent_id' => $catFood->id, 'order_index' => 2]);
        $catDessert = FbProductCategory::firstOrCreate(['code' => 'DES'], ['name' => 'Tráng miệng', 'parent_id' => $catFood->id, 'order_index' => 3]);
        
        $catDrinks = FbProductCategory::firstOrCreate(['code' => 'DRINK'], ['name' => 'Đồ uống', 'order_index' => 2]);
        $catAlcoholic = FbProductCategory::firstOrCreate(['code' => 'ALC'], ['name' => 'Nước có cồn', 'parent_id' => $catDrinks->id, 'order_index' => 1]);
        $catSoft = FbProductCategory::firstOrCreate(['code' => 'SOFT'], ['name' => 'Nước giải khát', 'parent_id' => $catDrinks->id, 'order_index' => 2]);

        $categories = [
            ['cat' => $catAppetizers, 'prefix' => 'APP', 'unit' => $unit->id, 'baseName' => 'Món khai vị'],
            ['cat' => $catMain, 'prefix' => 'MAIN', 'unit' => $unit->id, 'baseName' => 'Món chính'],
            ['cat' => $catDessert, 'prefix' => 'DES', 'unit' => $unit->id, 'baseName' => 'Tráng miệng'],
            ['cat' => $catAlcoholic, 'prefix' => 'ALC', 'unit' => $unitLy->id, 'baseName' => 'Đồ uống có cồn'],
            ['cat' => $catSoft, 'prefix' => 'SOFT', 'unit' => $unitLy->id, 'baseName' => 'Nước giải khát'],
        ];

        $outlets = Outlet::all();

        // Optional: clear out the old records before generating 100 new ones
        FbProduct::where('product_code', 'LIKE', 'APP%')
            ->orWhere('product_code', 'LIKE', 'MAIN%')
            ->orWhere('product_code', 'LIKE', 'DES%')
            ->orWhere('product_code', 'LIKE', 'ALC%')
            ->orWhere('product_code', 'LIKE', 'SOFT%')
            ->delete();

        for ($i = 1; $i <= 100; $i++) {
            $catInfo = $categories[$i % count($categories)];
            
            $price = rand(10, 500) * 1000;
            $original = round($price * (rand(50, 90) / 100));
            $taxPercent = rand(0, 1) ? 8 : 10;
            $serviceCharge = rand(0, 1) ? 5 : 0;

            $product = FbProduct::create([
                'name' => $catInfo['baseName'] . ' ' . str_pad($i, 3, '0', STR_PAD_LEFT),
                'product_code' => $catInfo['prefix'] . str_pad($i, 3, '0', STR_PAD_LEFT),
                'fb_product_category_id' => $catInfo['cat']->id,
                'unit_id' => $catInfo['unit'],
                'price' => $price,
                'original_amount' => $original,
                'service_charge_percent' => $serviceCharge,
                'tax_percent' => $taxPercent,
                'special_tax_percent' => rand(0, 1) ? 10 : 0,
                'is_active' => rand(1, 100) <= 80, // 80% active
                'is_combo' => false,
                'open_key' => rand(1, 100) <= 20, // 20% open item
                'is_alcohol' => $catInfo['prefix'] === 'ALC',
                'track_stock' => rand(1, 100) <= 40, // 40% track stock
                'is_in_stock' => rand(1, 100) <= 90 ? 1 : 0, // 90% in stock
                'note' => rand(1, 100) <= 30 ? 'Ghi chú cho món ăn số ' . $i : null,
                'service_group' => rand(1, 100) <= 50 ? 'Ăn uống' : 'Dịch vụ khác',
            ]);

            // Add diverse outlet prices
            foreach ($outlets as $outlet) {
                // 70% chance to have an outlet configuration record at all
                if (rand(1, 100) <= 70) {
                    $hasCustomPrice = rand(1, 100) <= 50; // 50% chance to override price
                    
                    $product->outletPrices()->create([
                        'outlet_id' => $outlet->id,
                        'is_active' => rand(1, 100) <= 85, // 85% chance to be active in this specific outlet
                        'update_price' => $hasCustomPrice,
                        'price' => $hasCustomPrice ? $price + (rand(-10, 20) * 1000) : $price, // randomize custom price
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
                        'is_expanded' => rand(1, 100) <= 30, // 30% chance they expanded location config
                        'selectedCounterOutlets' => [], // Default to all if empty
                    ]);
                }
            }
        }
    }
}
