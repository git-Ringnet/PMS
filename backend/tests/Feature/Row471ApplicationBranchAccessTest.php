<?php

namespace Tests\Feature;

use App\Models\OrganizationDepartment;
use App\Models\Position;
use App\Models\PositionBranchRole;
use App\Models\Role;
use App\Models\SystemBranch;
use App\Models\User;
use App\Models\UserBranch;
use App\Models\UserBranchPosition;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/** Task 471 (TC_LT_22 dòng 297/299): ứng dụng và chi nhánh khi đăng nhập theo vị trí còn hiệu lực. */
class Row471ApplicationBranchAccessTest extends TestCase
{
    use RefreshDatabase;

    private function setUpUser(): array
    {
        $department = OrganizationDepartment::firstOrCreate(['code' => 'FO'], ['name' => 'Lễ tân', 'is_active' => true]);
        $position = Position::create([
            'organization_department_id' => $department->id, 'code' => 'P471', 'name' => 'Vị trí 471', 'is_active' => true,
        ]);
        $role = Role::create(['code' => 'role_471', 'name' => 'Role 471', 'level' => 3, 'is_active' => true]);
        $branches = collect([1, 2, 3])->map(fn ($i) => SystemBranch::create(['code' => 'B471'.$i, 'name' => 'CN '.$i]));

        foreach ($branches as $branch) {
            PositionBranchRole::create([
                'position_id' => $position->id, 'system_branch_id' => $branch->id,
                'application_code' => 'PMS', 'role_id' => $role->id, 'is_active' => true,
            ]);
            UserBranchPosition::create([
                'user_id' => ($user ??= User::factory()->create([
                    'username' => 'user471', 'password' => Hash::make('password123'), 'is_active_user' => true,
                ]))->id,
                'system_branch_id' => $branch->id, 'application_code' => 'PMS', 'position_id' => $position->id,
            ]);
            UserBranch::create(['user_id' => $user->id, 'system_branch_id' => $branch->id, 'is_primary' => $branch->is($branches[0])]);
        }

        return [$user, $position, $branches];
    }

    private function login(): \Illuminate\Testing\TestResponse
    {
        return $this->postJson('/api/login', ['username' => 'user471', 'password' => 'password123']);
    }

    public function test_login_returns_only_the_assigned_applications(): void
    {
        $this->setUpUser();

        $response = $this->login()->assertOk();

        $this->assertSame(['PMS'], $response->json('applications'));
    }

    public function test_branch_removed_from_position_disappears_after_relogin(): void
    {
        [$user, $position, $branches] = $this->setUpUser();
        $this->assertCount(3, $this->login()->json('branches'));

        // Bỏ check chi nhánh 2, 3 ở cấu hình vị trí (user_branches/user_branch_positions cũ vẫn còn)
        PositionBranchRole::where('position_id', $position->id)
            ->whereIn('system_branch_id', [$branches[1]->id, $branches[2]->id])->delete();

        $response = $this->login()->assertOk();

        $this->assertSame([(int) $branches[0]->id], collect($response->json('branches'))->pluck('id')->map(fn ($id) => (int) $id)->all());
        $this->assertFalse($user->fresh()->hasBranchAccess($branches[1]->id));
    }

    public function test_application_removed_from_position_disappears_after_relogin(): void
    {
        [$user, $position] = $this->setUpUser();
        $user->userBranchPositions()->create([
            'system_branch_id' => SystemBranch::first()->id, 'application_code' => 'POS', 'position_id' => $position->id,
        ]);
        PositionBranchRole::create([
            'position_id' => $position->id, 'system_branch_id' => SystemBranch::first()->id,
            'application_code' => 'POS', 'role_id' => Role::where('code', 'role_471')->value('id'), 'is_active' => true,
        ]);
        $this->assertEqualsCanonicalizing(['PMS', 'POS'], $this->login()->json('applications'));

        PositionBranchRole::where('position_id', $position->id)->where('application_code', 'POS')->delete();

        $this->assertSame(['PMS'], $this->login()->json('applications'));
    }

    public function test_super_admin_sees_all_applications_and_branches(): void
    {
        $admin = User::factory()->create(['username' => 'user471', 'password' => Hash::make('password123'), 'is_active_user' => true]);
        $role = Role::firstOrCreate(['code' => 'super_admin'], ['name' => 'Super', 'level' => 1, 'is_active' => true]);
        $admin->roles()->attach($role->id);
        SystemBranch::create(['code' => 'ADM1', 'name' => 'A1']);

        $response = $this->login()->assertOk();

        $this->assertEqualsCanonicalizing(['PMS', 'POS', 'SYS'], $response->json('applications'));
        $this->assertNotEmpty($response->json('branches'));
    }
}
