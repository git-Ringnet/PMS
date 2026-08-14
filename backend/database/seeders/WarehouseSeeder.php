<?php

namespace Database\Seeders;

use App\Models\Warehouse;
use Illuminate\Database\Seeder;

class WarehouseSeeder extends Seeder
{
    public function run(): void
    {
        $defaultWarehouses = [
            [
                'name'      => 'Kho Minibar',
                'outlet_id' => 'MB',
                'is_active' => true,
            ],
            [
                'name'      => 'Kho Giặt ủi',
                'outlet_id' => 'LA',
                'is_active' => true,
            ],
            [
                'name'      => 'Kho Hàng đền bù',
                'outlet_id' => 'BR',
                'is_active' => true,
            ],
            [
                'name'      => 'Kho Amenity',
                'outlet_id' => 'AM',
                'is_active' => true,
            ],
        ];

        foreach ($defaultWarehouses as $wh) {
            Warehouse::firstOrCreate(
                ['name' => $wh['name']],
                $wh
            );
        }
    }
}
