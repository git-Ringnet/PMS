<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // User::factory(10)->create();

        User::updateOrCreate(
            ['email' => 'test@example.com'],
            [
                'name' => 'Test User',
                'username' => 'testuser',
                'password' => \Illuminate\Support\Facades\Hash::make('PmsPass@123'),
                'email_verified_at' => now(),
            ]
        );

        $this->call([
            BookingStatusSeeder::class,
            SystemConfigurationSeeder::class,
            HotelDefinitionSeeder::class,
            TemplateContentSeeder::class,
            SystemDefinitionSeeder::class,
            CompanyAndPartnerSeeder::class,
            SystemDateRollSeeder::class,
            SystemBranchSeeder::class,
            InfoBusinessSeeder::class,
            EmployeeSeeder::class,
            // ---- Module Đặt phòng ----
            // SpecialRequestSeeder::class,   // Danh mục yêu cầu đặc biệt (Epic 15)
            CancelReasonSeeder::class,     // Danh mục lý do hủy phòng (Epic 9)
        ]);
    }
}
