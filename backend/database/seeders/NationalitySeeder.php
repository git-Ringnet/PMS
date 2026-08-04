<?php

namespace Database\Seeders;

use App\Models\Nationality;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\File;

class NationalitySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $jsonPath = database_path('seeders/data/merged_countries.json');
        if (!File::exists($jsonPath)) {
            $this->command->error("Seed data file merged_countries.json not found!");
            return;
        }

        $jsonStr = File::get($jsonPath);
        $countries = json_decode($jsonStr, true);

        if (empty($countries)) {
            $this->command->error("Seed data is empty or invalid JSON!");
            return;
        }

        $this->command->info("Seeding " . count($countries) . " nationalities with all original columns...");

        // Use chunk to insert to avoid too many placeholder limits
        $chunks = array_chunk($countries, 100);
        foreach ($chunks as $chunk) {
            $insertData = [];
            foreach ($chunk as $country) {
                $insertData[] = [
                    'nationality_id'        => $country['nationality_id'] ?? null,
                    'nationality_id2'       => $country['nationality_id2'] ?? null,
                    'nationality_id_number' => $country['nationality_id_number'] ?? null,
                    'nationality_name'      => $country['nationality_name'] ?? null,
                    'nationality_name_en'   => $country['nationality_name_en'] ?? null,
                    'nationality_id_uid'    => $country['nationality_id_uid'] ?? null,
                    'nationality_id_shift'  => $country['nationality_id_shift'] ?? null,
                    'nationality_code'      => $country['nationality_code'] ?? null,
                    'continent_code'        => $country['continent_code'] ?? null,
                    
                    'asm_id'                => $country['asm_id'] ?? null,
                    'asm_code'              => $country['asm_code'] ?? null,
                    'asm_name'              => $country['asm_name'] ?? null,
                    'asm_description'       => $country['asm_description'] ?? null,

                    'created_at'            => now(),
                    'updated_at'            => now(),
                ];
            }
            Nationality::insert($insertData);
        }

        $this->command->info("Seeded nationalities successfully!");
    }
}
