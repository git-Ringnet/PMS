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

        $this->assertDatabaseCount('modules', 3);
        $this->assertDatabaseHas('modules', ['code' => 'PMS', 'portal_key' => 'pms', 'route' => '/frontdesk']);
        $this->assertDatabaseHas('modules', ['code' => 'POS', 'portal_key' => 'fnb', 'route' => '/fnb']);
        $this->assertDatabaseHas('modules', ['code' => 'SYS', 'portal_key' => 'system', 'route' => '/system']);
    }
}
