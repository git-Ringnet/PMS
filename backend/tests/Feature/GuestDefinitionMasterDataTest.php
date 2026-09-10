<?php

namespace Tests\Feature;

use App\Models\BorderGate;
use App\Models\EntryPurpose;
use App\Models\GuestTitle;
use App\Models\GuestType;
use App\Models\IdType;
use App\Models\ResidenceType;
use App\Models\User;
use Database\Seeders\GuestDefinitionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GuestDefinitionMasterDataTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_definitions_api_returns_all_six_categories_properly(): void
    {
        $this->seed(GuestDefinitionSeeder::class);
        $user = User::factory()->create();

        $response = $this->actingAs($user)->getJson('/api/guest-definitions');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'data' => [
                'titles',
                'border_gates',
                'entry_purposes',
                'guest_types',
                'id_types',
                'residence_types',
                'provinces',
            ],
        ]);

        $data = $response->json('data');

        // Check exact counts based on Excel definition
        $this->assertCount(6, $data['titles'], 'Titles must have 6 records');
        $this->assertCount(87, $data['border_gates'], 'Border gates must have 87 records');
        $this->assertCount(14, $data['entry_purposes'], 'Entry purposes must have 14 records');
        $this->assertCount(5, $data['guest_types'], 'Guest types must have 5 records');
        $this->assertCount(4, $data['id_types'], 'Id types must have 4 records');
        $this->assertCount(3, $data['residence_types'], 'Residence types must have 3 records');

        // Verify residence types content
        $residenceNames = collect($data['residence_types'])->pluck('name_new_form')->toArray();
        $this->assertContains('Thường trú', $residenceNames);
        $this->assertContains('Tạm trú', $residenceNames);
        $this->assertContains('Khác', $residenceNames);
    }
}
