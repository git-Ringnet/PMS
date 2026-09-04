<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Province;

class ProvinceSeeder extends Seeder
{
    /**
     * Run the database seeds.
     * Nạp 63 tỉnh/thành phố chuẩn Việt Nam vào bảng provinces
     */
    public function run(): void
    {
        $jsonPath = __DIR__ . '/provinces.json';
        if (!file_exists($jsonPath)) {
            return;
        }

        $items = json_decode(file_get_contents($jsonPath), true);
        if (!is_array($items)) {
            return;
        }

        foreach ($items as $index => $item) {
            Province::updateOrCreate(
                ['name' => $item['name']],
                [
                    'code' => (string)($item['code'] ?? ''),
                    'type' => $item['division_type'] ?? 'tỉnh',
                    'order_index' => $index + 1,
                    'is_active' => true,
                ]
            );
        }
    }
}
