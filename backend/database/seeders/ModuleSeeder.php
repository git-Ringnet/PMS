<?php

namespace Database\Seeders;

use App\Models\Module;
use Illuminate\Database\Seeder;

class ModuleSeeder extends Seeder
{
    public function run(): void
    {
        $modules = [
            ['code' => 'SALE', 'name' => 'Đặt phòng', 'portal_key' => 'reservation', 'route' => '/reservation', 'icon' => 'calendar', 'sort_order' => 1],
            ['code' => 'FO', 'name' => 'Lễ tân', 'portal_key' => 'frontdesk', 'route' => '/frontdesk', 'icon' => 'concierge', 'sort_order' => 2],
            ['code' => 'HK', 'name' => 'Buồng phòng', 'portal_key' => 'housekeeping', 'route' => '/housekeeping', 'icon' => 'bed', 'sort_order' => 3],
            ['code' => 'RPPMS', 'name' => 'Báo cáo quản lý', 'portal_key' => 'reports', 'route' => '/reports', 'icon' => 'chart', 'sort_order' => 4],
            ['code' => 'ST', 'name' => 'Cấu hình hệ thống', 'portal_key' => 'config', 'route' => '/config', 'icon' => 'settings', 'sort_order' => 5],
            ['code' => 'FB&SK', 'name' => 'F&B', 'portal_key' => 'fnb', 'route' => '/fnb', 'icon' => 'restaurant', 'sort_order' => 6],
        ];

        foreach ($modules as $module) {
            Module::updateOrCreate(
                ['code' => $module['code']],
                [...$module, 'is_active' => true]
            );
        }
    }
}
