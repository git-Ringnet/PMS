<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class BranchDatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $this->call([
            ModuleSeeder::class,
            BookingStatusSeeder::class,
            SystemConfigurationSeeder::class,
            DepartmentSeeder::class,
            HotelDefinitionSeeder::class,
            TemplateContentSeeder::class,
            SystemDefinitionSeeder::class,
            CompanyAndPartnerSeeder::class,
            SystemDateRollSeeder::class,
            MenuProductSeeder::class,
            FnbComprehensiveSeeder::class,
            HkStaffSeeder::class,
            HkConfigSeeder::class,
            WarehouseSeeder::class,
            CancelReasonSeeder::class,
            SpecialRequestSeeder::class,
            NationalitySeeder::class,
            GuestDefinitionSeeder::class,
            ProvinceSeeder::class,
        ]);
    }
}
