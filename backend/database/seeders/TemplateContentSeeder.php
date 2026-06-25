<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Artisan;

class TemplateContentSeeder extends Seeder
{
    /**
     * Seed full HTML content for designed templates (run after HotelDefinitionSeeder).
     */
    public function run(): void
    {
        Artisan::call('seed:receipt-template');
        $this->command?->info(Artisan::output());

        Artisan::call('seed:booking-confirmation-navy-dalat');
        $this->command?->info(Artisan::output());

        Artisan::call('seed:booking-confirmation-navy-nhatrang');
        $this->command?->info(Artisan::output());

        Artisan::call('template:seed-registration-card-navy');
        $this->command?->info(Artisan::output());
    }
}
