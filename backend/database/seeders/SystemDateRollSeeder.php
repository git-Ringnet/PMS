<?php

namespace Database\Seeders;

use App\Models\SystemDateRoll;
use Illuminate\Database\Seeder;

class SystemDateRollSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $rolls = [
            [
                'system_date' => '2026-07-16 00:00:00',
                'actual_date' => '2026-07-16 00:00:00',
                'shift' => '1',
                'username' => 'admin',
            ],
        ];

        foreach ($rolls as $r) {
            SystemDateRoll::updateOrCreate(
                [
                    'system_date' => $r['system_date'],
                    'shift' => $r['shift'],
                ],
                [
                    'actual_date' => $r['actual_date'],
                    'username' => $r['username'],
                ]
            );
        }
    }
}
