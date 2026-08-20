<?php

namespace Database\Seeders;

use App\Models\Module;
use Illuminate\Database\Seeder;

class ModuleSeeder extends Seeder
{
    public function run(): void
    {
        // Xóa modules cũ để đồng bộ chuẩn 3 ứng dụng
        Module::query()->delete();

        $modules = [
            [
                'code' => 'PMS',
                'name' => 'PROVISTA PMS',
                'portal_key' => 'pms',
                'route' => '/frontdesk',
                'icon' => 'concierge',
                'sort_order' => 1,
                'is_active' => true,
            ],
            [
                'code' => 'POS',
                'name' => 'PROVISTA F&B',
                'portal_key' => 'fnb',
                'route' => '/fnb',
                'icon' => 'restaurant',
                'sort_order' => 2,
                'is_active' => true,
            ],
            [
                'code' => 'SYS',
                'name' => 'PROVISTA SYSTEM',
                'portal_key' => 'system',
                'route' => '/system',
                'icon' => 'settings',
                'sort_order' => 3,
                'is_active' => true,
            ],
        ];

        foreach ($modules as $module) {
            Module::create($module);
        }
    }
}
