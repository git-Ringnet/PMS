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
                'system_date' => '2025-07-26 08:55:15.863',
                'actual_date' => '2025-07-26 08:55:15.867',
                'shift' => '1',
                'username' => 'admin',
            ],
            [
                'system_date' => '2025-07-27 10:56:37.053',
                'actual_date' => '2025-07-27 10:56:37.057',
                'shift' => '1',
                'username' => 'FOKhanh',
            ],
            [
                'system_date' => '2025-07-28 09:08:50.863',
                'actual_date' => '2025-07-28 09:08:50.867',
                'shift' => '1',
                'username' => 'FOLinh',
            ],
            [
                'system_date' => '2025-07-29 08:55:59.433',
                'actual_date' => '2025-07-29 08:55:59.437',
                'shift' => '1',
                'username' => 'FOLinh',
            ],
            [
                'system_date' => '2025-07-30 08:32:26.573',
                'actual_date' => '2025-07-30 08:32:26.577',
                'shift' => '1',
                'username' => 'FOLinh',
            ],
            [
                'system_date' => '2025-07-31 05:22:35.557',
                'actual_date' => '2025-07-31 05:22:35.557',
                'shift' => '0',
                'username' => 'FOVu',
            ],
            [
                'system_date' => '2025-08-01 05:30:11.803',
                'actual_date' => '2025-08-01 05:30:11.807',
                'shift' => '0',
                'username' => 'FOQuan',
            ],
            [
                'system_date' => '2025-08-02 05:55:10.817',
                'actual_date' => '2025-08-02 05:55:10.820',
                'shift' => '0',
                'username' => 'FOHuy',
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
