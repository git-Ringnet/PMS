<?php

namespace Database\Seeders;

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
        $connection = \Illuminate\Support\Facades\DB::getDefaultConnection();

        if ($connection === config('database_domains.system_connection', 'mysql_system')) {
            foreach (config('database_domains.system_seeders', []) as $seeder) {
                $this->call($seeder);
            }

            return;
        }

        $this->call(BranchDatabaseSeeder::class);
    }
}
