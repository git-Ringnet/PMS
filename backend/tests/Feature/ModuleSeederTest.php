<?php

namespace Tests\Feature;

use Database\Seeders\ModuleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ModuleSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_seeds_the_current_pms_modules_idempotently(): void
    {
        $this->seed(ModuleSeeder::class);
        $this->seed(ModuleSeeder::class);

        $this->assertDatabaseCount('modules', 6);
        $this->assertDatabaseHas('modules', ['code' => 'SALE', 'portal_key' => 'reservation', 'route' => '/reservation']);
        $this->assertDatabaseHas('modules', ['code' => 'FO', 'portal_key' => 'frontdesk', 'route' => '/frontdesk']);
        $this->assertDatabaseHas('modules', ['code' => 'HK', 'portal_key' => 'housekeeping', 'route' => '/housekeeping']);
        $this->assertDatabaseHas('modules', ['code' => 'RPPMS', 'portal_key' => 'reports', 'route' => '/reports']);
        $this->assertDatabaseHas('modules', ['code' => 'ST', 'portal_key' => 'config', 'route' => '/config']);
        $this->assertDatabaseHas('modules', ['code' => 'FB&SK', 'portal_key' => 'fnb', 'route' => '/fnb']);
    }
}
