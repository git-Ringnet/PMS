<?php

namespace Tests\Feature;

use App\Http\Middleware\BlockNightAuditRequests;
use App\Models\Permission;
use App\Models\Role;
use App\Models\SystemBranch;
use App\Models\User;
use App\Models\UserRole;
use App\Services\DashboardOverviewService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Mockery;
use Tests\TestCase;

/**
 * API trang Tổng quan module Báo cáo quản lý: quyền xem và tham số popup chi tiết.
 * Số liệu do thủ tục MySQL (rpt_room_forecast) nên phần tính toán được kiểm tra trên DB MySQL thật.
 */
class DashboardOverviewApiTest extends TestCase
{
    use RefreshDatabase;

    private SystemBranch $branch;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(BlockNightAuditRequests::class);
        config(['database_domains.branch_connections' => ['HKT1' => 'sqlite']]);
        $this->branch = SystemBranch::firstOrCreate(['code' => 'HKT1'], ['name' => 'Chi nhánh 1', 'is_active' => true]);

        $service = Mockery::mock(DashboardOverviewService::class);
        $service->shouldReceive('overview')->andReturn(['system_date' => '2026-09-03']);
        $service->shouldReceive('activityRooms')->with('arrivals')->andReturn([['booking_code' => 'GAL10']]);
        $this->app->instance(DashboardOverviewService::class, $service);
    }

    private function userWithPermissions(array $permissionCodes): User
    {
        $role = Role::firstOrCreate(['code' => 'dash_role'], ['name' => 'dash_role', 'level' => 3, 'is_active' => true]);
        $role->permissions()->sync(collect($permissionCodes)->map(
            fn ($code) => Permission::firstOrCreate(['code' => $code], ['name' => $code, 'module' => 'MGMT'])->id
        )->all());

        $user = User::create([
            'name' => 'dash',
            'username' => 'dash_'.uniqid(),
            'email' => 'dash'.uniqid().'@pms.test',
            'password' => bcrypt('Secret@123'),
            'primary_branch_id' => $this->branch->id,
            'is_active_user' => true,
        ]);
        UserRole::create(['user_id' => $user->id, 'role_id' => $role->id, 'system_branch_id' => null]);

        return $user;
    }

    public function test_dashboard_requires_management_report_permission(): void
    {
        Sanctum::actingAs($this->userWithPermissions(['fo.booking.view']));

        $this->getJson('/api/dashboard/overview')->assertStatus(403);
        $this->getJson('/api/dashboard/activity-rooms?type=arrivals')->assertStatus(403);
    }

    public function test_dashboard_returns_overview_and_activity_rooms(): void
    {
        Sanctum::actingAs($this->userWithPermissions(['mgmt.report.view']));

        $this->getJson('/api/dashboard/overview')
            ->assertOk()
            ->assertJsonPath('data.system_date', '2026-09-03');
        $this->getJson('/api/dashboard/activity-rooms?type=arrivals')
            ->assertOk()
            ->assertJsonPath('data.0.booking_code', 'GAL10');
        $this->getJson('/api/dashboard/activity-rooms?type=unknown')
            ->assertStatus(422)
            ->assertJsonValidationErrors('type');
    }
}
