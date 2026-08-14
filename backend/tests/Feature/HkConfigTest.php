<?php

namespace Tests\Feature;

use App\Models\HkPrintCol;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HkConfigTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->artisan('db:seed', ['--class' => 'HkConfigSeeder']);
    }

    public function test_get_hk_config()
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->getJson('/api/hk-config');

        $response->assertSuccessful();
        $response->assertJsonStructure([
            'symbols',
            'printCols'
        ]);

        // Check if parent_label is present in some seeded print columns
        $printCols = $response->json('printCols');
        $this->assertNotEmpty($printCols);
        
        // Find "VÀO" column and verify its parent is "GIỜ"
        $vaoCol = collect($printCols)->firstWhere('label', 'VÀO');
        $this->assertNotNull($vaoCol);
        $this->assertEquals('GIỜ', $vaoCol['parent_label']);
    }

    public function test_update_print_cols_stores_parent_label()
    {
        $user = User::factory()->create();

        // Get existing columns
        $existingCols = HkPrintCol::where('template', 'worksheet')->get()->toArray();

        // Let's modify one non-fixed column's parent_label
        foreach ($existingCols as &$col) {
            if ($col['label'] === 'ÁO GỐI') {
                $col['parent_label'] = 'TEST_GROUP';
            }
        }

        $payload = [
            'template' => 'worksheet',
            'cols' => $existingCols
        ];

        $response = $this->actingAs($user)
            ->putJson('/api/hk-config/print-cols', $payload);

        $response->assertSuccessful();

        // Assert parent_label was saved in DB
        $savedCol = HkPrintCol::where('template', 'worksheet')
            ->where('label', 'ÁO GỐI')
            ->first();

        $this->assertNotNull($savedCol);
        $this->assertEquals('TEST_GROUP', $savedCol->parent_label);
    }

    public function test_reset_config_reloads_defaults()
    {
        $user = User::factory()->create();

        // Delete all columns to simulate custom setup
        HkPrintCol::query()->delete();

        $response = $this->actingAs($user)
            ->postJson('/api/hk-config/reset');

        $response->assertSuccessful();

        // Assert columns were recreated with default seed data
        $vaoCol = HkPrintCol::where('template', 'worksheet')
            ->where('label', 'VÀO')
            ->first();

        $this->assertNotNull($vaoCol);
        $this->assertEquals('GIỜ', $vaoCol->parent_label);
    }
}
