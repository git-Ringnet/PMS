<?php

namespace Tests\Feature;

use App\Models\SystemBranch;
use App\Models\User;
use App\Models\UserBranch;
use App\Http\Middleware\BlockNightAuditRequests;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MultiDatabaseArchitectureTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // This test verifies branch routing; the night-audit middleware has a
        // separate hotel-settings dependency and is outside this test scope.
        $this->withoutMiddleware(BlockNightAuditRequests::class);
    }

    public function test_authenticated_user_can_only_access_an_assigned_branch(): void
    {
        config(['database_domains.branch_connections' => [
            'HKT1' => 'sqlite',
            'HKT2' => 'sqlite',
        ]]);

        $hkt1 = SystemBranch::create([
            'code' => 'HKT1',
            'name' => 'HKT 1',
            'is_active' => true,
        ]);
        $hkt2 = SystemBranch::create([
            'code' => 'HKT2',
            'name' => 'HKT 2',
            'is_active' => true,
        ]);
        $user = User::factory()->create();

        UserBranch::create([
            'user_id' => $user->id,
            'system_branch_id' => $hkt1->id,
            'is_primary' => true,
        ]);

        Sanctum::actingAs($user);

        $this->withHeaders([
            'X-Branch-Code' => 'HKT1',
            'X-Branch-Id' => (string) $hkt1->id,
        ])->getJson('/api/me')->assertOk();

        $this->withHeaders([
            'X-Branch-Code' => 'HKT2',
            'X-Branch-Id' => (string) $hkt2->id,
        ])->getJson('/api/me')->assertForbidden();
    }

    public function test_branch_code_and_id_must_match(): void
    {
        config(['database_domains.branch_connections' => ['HKT1' => 'sqlite']]);

        $branch = SystemBranch::create([
            'code' => 'HKT1',
            'name' => 'HKT 1',
            'is_active' => true,
        ]);
        $user = User::factory()->create(['primary_branch_id' => $branch->id]);

        Sanctum::actingAs($user);

        $this->withHeaders([
            'X-Branch-Code' => 'HKT1',
            'X-Branch-Id' => (string) ($branch->id + 100),
        ])->getJson('/api/me')->assertStatus(422);
    }
}
