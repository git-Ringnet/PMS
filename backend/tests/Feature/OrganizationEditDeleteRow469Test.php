<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\OrganizationDepartment;
use App\Models\Position;
use App\Models\Role;
use App\Models\SystemBranch;
use App\Models\User;
use App\Models\UserBranchPosition;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

// Dòng 469: Cơ cấu tổ chức - sửa/xóa bộ phận, sửa/xóa vị trí công việc.
class OrganizationEditDeleteRow469Test extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $admin = User::factory()->create();
        $super = Role::firstOrCreate(['code' => 'super_admin'], ['name' => 'Super Admin', 'level' => 1, 'is_active' => true]);
        $admin->roles()->attach($super->id);
        Sanctum::actingAs($admin);
    }

    private function department(string $code = 'QA', string $name = 'Quality'): OrganizationDepartment
    {
        return OrganizationDepartment::create(['code' => $code, 'name' => $name, 'is_active' => true, 'sort_order' => 1]);
    }

    public function test_department_update_changes_name_but_not_code(): void
    {
        $dept = $this->department();

        $this->putJson("/api/organization/departments/{$dept->id}", ['name' => 'QA mới', 'code' => 'ZZ'])->assertOk();

        $this->assertDatabaseHas('organization_departments', ['id' => $dept->id, 'code' => 'QA', 'name' => 'QA mới']);
    }

    public function test_department_with_positions_cannot_be_deleted(): void
    {
        $dept = $this->department();
        Position::create(['organization_department_id' => $dept->id, 'code' => 'P1', 'name' => 'Vị trí 1', 'is_active' => true, 'sort_order' => 1]);

        $this->deleteJson("/api/organization/departments/{$dept->id}")
            ->assertStatus(422)
            ->assertJsonPath('message', 'Bộ phận đã có vị trí công việc, không thể xóa.');
        $this->assertDatabaseHas('organization_departments', ['id' => $dept->id]);
    }

    public function test_empty_department_is_deleted_and_not_reimported_from_legacy_table(): void
    {
        Department::create(['code' => 'QA', 'name' => 'Quality', 'phone' => '1', 'show' => 1]);
        $this->getJson('/api/organization')->assertOk();
        $dept = OrganizationDepartment::where('code', 'QA')->firstOrFail();

        $this->deleteJson("/api/organization/departments/{$dept->id}")->assertOk();
        $this->getJson('/api/organization')->assertOk();

        $this->assertNull(OrganizationDepartment::where('code', 'QA')->first());
    }

    public function test_renamed_department_is_not_overwritten_by_legacy_sync(): void
    {
        Department::create(['code' => 'QA', 'name' => 'Quality', 'phone' => '1', 'show' => 1]);
        $this->getJson('/api/organization')->assertOk();
        $dept = OrganizationDepartment::where('code', 'QA')->firstOrFail();

        $this->putJson("/api/organization/departments/{$dept->id}", ['name' => 'Tên đã sửa'])->assertOk();
        $this->getJson('/api/organization')->assertOk();

        $this->assertSame('Tên đã sửa', $dept->fresh()->name);
    }

    public function test_position_update_changes_name_but_not_code(): void
    {
        $dept = $this->department();
        $pos = Position::create(['organization_department_id' => $dept->id, 'code' => 'P1', 'name' => 'Cũ', 'is_active' => true, 'sort_order' => 1]);

        $this->putJson("/api/organization/positions/{$pos->id}", ['name' => 'Mới', 'code' => 'P9'])->assertOk();

        $this->assertDatabaseHas('positions', ['id' => $pos->id, 'code' => 'P1', 'name' => 'Mới']);
    }

    public function test_position_assigned_to_staff_cannot_be_deleted_with_required_message(): void
    {
        $dept = $this->department();
        $pos = Position::create(['organization_department_id' => $dept->id, 'code' => 'P1', 'name' => 'Vị trí', 'is_active' => true, 'sort_order' => 1]);
        $branch = SystemBranch::create(['code' => 'B1', 'name' => 'CN 1', 'database_name' => 'x', 'is_active' => true]);
        UserBranchPosition::create(['user_id' => User::factory()->create()->id, 'system_branch_id' => $branch->id, 'position_id' => $pos->id, 'is_active' => true]);

        $this->deleteJson("/api/organization/positions/{$pos->id}")
            ->assertStatus(422)
            ->assertJsonPath('message', 'Vị trí đã được gán cho nhân viên, không thể xóa');
        $this->assertDatabaseHas('positions', ['id' => $pos->id]);
    }
}
