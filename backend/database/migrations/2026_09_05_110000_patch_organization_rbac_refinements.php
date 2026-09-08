<?php

use App\Models\OrganizationDepartment;
use App\Models\Position;
use App\Models\PositionBranchRole;
use App\Models\Role;
use App\Models\SystemBranch;
use App\Models\User;
use App\Models\UserBranchPosition;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $system = config('database_domains.system_connection', 'mysql_system');

        // 1. Mở rộng mã động để không tràn khi ghép application/module/screen/action.
        if (Schema::connection($system)->hasTable('permissions')) {
            Schema::connection($system)->table('permissions', function (Blueprint $table) {
                $table->string('code', 100)->change();
                $table->string('screen_code', 100)->nullable()->change();
            });
        }

        if (Schema::connection($system)->hasTable('positions')) {
            Schema::connection($system)->table('positions', function (Blueprint $table) {
                $table->string('code', 100)->change();
            });
        }

        // 2. Chạy lại backfill cho Custom Roles (nếu migration trước chưa xử lý hoặc có role mới tạo)
        $this->backfillCustomRolesAndPositions($system);

        // 3. Chạy lại backfill cho Super Admin trên tất cả chi nhánh hoạt động
        $this->backfillSuperAdminAcrossBranches($system);

        // 4. Chuẩn hóa cờ must_change_password cho các tài khoản hiện có
        if (Schema::connection($system)->hasColumn('users', 'must_change_password')) {
            DB::connection($system)->table('users')
                ->whereNull('must_change_password')
                ->update(['must_change_password' => false]);
        }
    }

    public function down(): void
    {
        // Giữ nguyên dữ liệu khi rollback migration phụ
    }

    private function backfillCustomRolesAndPositions(string $connection): void
    {
        $roles = DB::connection($connection)->table('roles')
            ->whereIn('id', DB::connection($connection)->table('user_roles')->select('role_id'))
            ->get();
        if ($roles->isEmpty()) {
            return;
        }

        $branches = DB::connection($connection)->table('system_branches')
            ->where('is_active', true)
            ->get();

        $defaultApplication = strtoupper(config('database_domains.default_application_code'));

        foreach ($roles as $role) {
            $hasPositionMapping = DB::connection($connection)->table('position_branch_roles')
                ->where('role_id', $role->id)
                ->exists();
            if ($hasPositionMapping) {
                continue;
            }

            $deptId = null;
            if (!empty($role->department_scope)) {
                $deptId = DB::connection($connection)->table('organization_departments')
                    ->where('code', strtoupper($role->department_scope))
                    ->value('id');
            }
            $deptId ??= DB::connection($connection)->table('organization_departments')
                ->where('is_active', true)
                ->orderBy('sort_order')
                ->value('id');
            if (!$deptId) {
                continue;
            }

            $posCode = 'POS_' . strtoupper(preg_replace('/[^A-Za-z0-9_]/', '', $role->code));
            if (strlen($posCode) > 100) {
                $posCode = substr($posCode, 0, 91).'_'.substr(sha1($role->code), 0, 8);
            }

            $position = Position::on($connection)->firstOrCreate(
                ['code' => $posCode, 'organization_department_id' => $deptId],
                [
                    'name' => $role->name,
                    'is_active' => true,
                    'sort_order' => 99,
                ]
            );

            foreach ($branches as $branch) {
                PositionBranchRole::on($connection)->firstOrCreate(
                    [
                        'position_id' => $position->id,
                        'system_branch_id' => $branch->id,
                        'application_code' => $defaultApplication,
                    ],
                    [
                        'role_id' => $role->id,
                        'is_active' => true,
                    ]
                );
            }
        }
    }

    private function backfillSuperAdminAcrossBranches(string $connection): void
    {
        $superAdminRoles = DB::connection($connection)->table('roles')
            ->where('code', 'super_admin')
            ->orWhere('level', 1)
            ->pluck('id');

        if ($superAdminRoles->isEmpty()) {
            return;
        }

        $superUserIds = DB::connection($connection)->table('user_roles')
            ->whereIn('role_id', $superAdminRoles)
            ->pluck('user_id')
            ->unique();

        $branches = DB::connection($connection)->table('system_branches')
            ->where('is_active', true)
            ->get();

        if ($branches->isEmpty()) {
            return;
        }

        foreach ($superUserIds as $userId) {
            foreach ($branches as $branch) {
                $mapping = PositionBranchRole::on($connection)
                    ->where('system_branch_id', $branch->id)
                    ->whereIn('role_id', $superAdminRoles)
                    ->where('is_active', true)
                    ->first();
                if (!$mapping) {
                    continue;
                }
                UserBranchPosition::on($connection)->firstOrCreate(
                    [
                        'user_id' => $userId,
                        'system_branch_id' => $branch->id,
                        'application_code' => $mapping->application_code,
                    ],
                    [
                        'position_id' => $mapping->position_id,
                    ]
                );
            }
        }
    }
};
