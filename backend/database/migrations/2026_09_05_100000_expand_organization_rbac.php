<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('roles', function (Blueprint $table) {
            $table->boolean('allow_historical_date_actions')->default(false)->after('is_active')
                ->comment('Cho phép thao tác dữ liệu trước ngày nghiệp vụ hiện tại');
        });

        Schema::table('permissions', function (Blueprint $table) {
            $table->string('application_code', 20)->default('PMS')->after('module');
            $table->string('screen_code', 80)->nullable()->after('application_code');
            $table->string('screen_name', 120)->nullable()->after('screen_code');
            $table->string('path', 150)->nullable()->after('screen_name');
            $table->string('action', 10)->nullable()->after('path')
                ->comment('view | add | edit | delete');
            $table->string('screen_type', 20)->default('screen')->after('action')
                ->comment('screen | report | feature');
            $table->unsignedSmallInteger('sort_order')->default(0)->after('screen_type');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->boolean('must_change_password')->default(false)->after('password');
        });

        Schema::create('organization_departments', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique();
            $table->string('name', 200);
            $table->string('phone', 100)->nullable();
            $table->smallInteger('legacy_show')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('positions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_department_id')->constrained('organization_departments')->cascadeOnDelete();
            $table->string('code', 30);
            $table->string('name', 120);
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
            $table->unique(['organization_department_id', 'code'], 'dept_position_code_unique');
        });

        Schema::create('position_branch_roles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('position_id')->constrained('positions')->cascadeOnDelete();
            $table->foreignId('system_branch_id')->constrained('system_branches')->cascadeOnDelete();
            $table->string('application_code', 20)->default('PMS');
            $table->foreignId('role_id')->constrained('roles')->restrictOnDelete();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['position_id', 'system_branch_id', 'application_code'], 'position_branch_app_unique');
        });

        Schema::create('user_branch_positions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('system_branch_id')->constrained('system_branches')->cascadeOnDelete();
            $table->string('application_code', 20)->default('PMS');
            $table->foreignId('position_id')->constrained('positions')->restrictOnDelete();
            $table->timestamps();
            $table->unique(['user_id', 'system_branch_id', 'application_code'], 'user_branch_app_position_unique');
        });

        Schema::create('branch_role_permissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('system_branch_id')->constrained('system_branches')->cascadeOnDelete();
            $table->foreignId('role_id')->constrained('roles')->cascadeOnDelete();
            $table->foreignId('permission_id')->constrained('permissions')->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['system_branch_id', 'role_id', 'permission_id'], 'branch_role_permission_unique');
        });

        Schema::create('user_warehouse_permissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('system_branch_id')->constrained('system_branches')->cascadeOnDelete();
            // Kho nằm trong database tenant, vì vậy không tạo FK chéo database.
            $table->unsignedBigInteger('warehouse_id');
            $table->timestamps();
            $table->unique(['user_id', 'system_branch_id', 'warehouse_id'], 'user_branch_warehouse_unique');
        });

        $this->seedLegacyDepartments();
        $this->seedDefaultPositions();
        $this->normalizePermissions();
        $this->backfillBranchPermissions();
        $this->backfillPositionAssignments();
    }

    private function seedLegacyDepartments(): void
    {
        $departments = [
            ['code' => 'AC', 'name' => 'Accounting / Kế Toán'],
            ['code' => 'BT', 'name' => 'Maintenance / Bảo Trì'],
            ['code' => 'BV', 'name' => 'Security / Bảo Vệ'],
            ['code' => 'EI', 'name' => 'Manage / Điều Hành'],
            ['code' => 'FB', 'name' => 'Restaurant / Nhà Hàng'],
            ['code' => 'FO', 'name' => 'Reception / Lễ Tân'],
            ['code' => 'HK', 'name' => 'House Keeping / Buồng Phòng'],
            ['code' => 'KI', 'name' => 'Kitchen / Bếp'],
            ['code' => 'MA', 'name' => 'Owner / Chủ Đầu Tư'],
            ['code' => 'MR', 'name' => 'Reservation / Kinh Doanh'],
            ['code' => 'NS', 'name' => 'Human Resource / Nhân Sự'],
            ['code' => 'OT', 'name' => 'Others / Khác'],
            ['code' => 'SA', 'name' => 'System Administration'],
            ['code' => 'SK', 'name' => 'Spa'],
        ];

        foreach ($departments as $index => $department) {
            DB::table('organization_departments')->updateOrInsert(
                ['code' => $department['code']],
                $department + [
                    'legacy_show' => 0,
                    'is_active' => true,
                    'sort_order' => $index + 1,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
        }
    }

    private function seedDefaultPositions(): void
    {
        $positions = [
            ['department' => 'SA', 'code' => 'SUPER_ADMIN', 'name' => 'Super Administrator', 'role' => 'super_admin'],
            ['department' => 'SA', 'code' => 'BRANCH_ADMIN', 'name' => 'Quản Trị Chi Nhánh', 'role' => 'branch_admin'],
            ['department' => 'EI', 'code' => 'MGMT', 'name' => 'Quản Lý', 'role' => 'mgmt'],
            ['department' => 'FO', 'code' => 'FOM', 'name' => 'Trưởng Bộ Phận Lễ Tân', 'role' => 'fo_manager'],
            ['department' => 'FO', 'code' => 'FO', 'name' => 'Nhân Viên Lễ Tân', 'role' => 'fo_staff'],
            ['department' => 'HK', 'code' => 'HKM', 'name' => 'Trưởng Bộ Phận Buồng Phòng', 'role' => 'hk_manager'],
            ['department' => 'HK', 'code' => 'HK', 'name' => 'Nhân Viên Buồng Phòng', 'role' => 'hk_staff'],
            ['department' => 'FB', 'code' => 'FBM', 'name' => 'Trưởng Bộ Phận Nhà Hàng', 'role' => 'fb_manager'],
            ['department' => 'FB', 'code' => 'FB', 'name' => 'Nhân Viên Nhà Hàng', 'role' => 'fb_staff'],
        ];

        $branches = DB::table('system_branches')->pluck('id');

        foreach ($positions as $index => $position) {
            $departmentId = DB::table('organization_departments')->where('code', $position['department'])->value('id');
            if (!$departmentId) {
                continue;
            }

            DB::table('positions')->updateOrInsert(
                [
                    'organization_department_id' => $departmentId,
                    'code' => $position['code'],
                ],
                [
                    'name' => $position['name'],
                    'is_active' => true,
                    'sort_order' => $index + 1,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );

            $positionId = DB::table('positions')
                ->where('organization_department_id', $departmentId)
                ->where('code', $position['code'])
                ->value('id');
            $roleId = DB::table('roles')->where('code', $position['role'])->value('id');
            if (!$positionId || !$roleId) {
                continue;
            }

            foreach ($branches as $branchId) {
                DB::table('position_branch_roles')->updateOrInsert(
                    [
                        'position_id' => $positionId,
                        'system_branch_id' => $branchId,
                        'application_code' => 'PMS',
                    ],
                    [
                        'role_id' => $roleId,
                        'is_active' => true,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]
                );
            }
        }

        // Tự động tạo Position và ánh xạ cho các Custom Role chưa có vị trí mặc định
        $handledRoleCodes = collect($positions)->pluck('role')->all();
        $customRoles = DB::table('roles')->whereNotIn('code', $handledRoleCodes)->get();
        $fallbackDeptId = DB::table('organization_departments')->where('code', 'OT')->value('id')
            ?? DB::table('organization_departments')->value('id');

        foreach ($customRoles as $idx => $role) {
            $deptId = null;
            if (!empty($role->department_scope)) {
                $deptId = DB::table('organization_departments')->where('code', strtoupper($role->department_scope))->value('id');
            }
            $deptId = $deptId ?: $fallbackDeptId;
            if (!$deptId) {
                continue;
            }

            $posCode = 'POS_' . strtoupper($role->code);
            DB::table('positions')->updateOrInsert(
                [
                    'organization_department_id' => $deptId,
                    'code' => $posCode,
                ],
                [
                    'name' => $role->name,
                    'is_active' => true,
                    'sort_order' => count($positions) + $idx + 1,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );

            $posId = DB::table('positions')
                ->where('organization_department_id', $deptId)
                ->where('code', $posCode)
                ->value('id');

            if ($posId) {
                foreach ($branches as $branchId) {
                    DB::table('position_branch_roles')->updateOrInsert(
                        [
                            'position_id' => $posId,
                            'system_branch_id' => $branchId,
                            'application_code' => 'PMS',
                        ],
                        [
                            'role_id' => $role->id,
                            'is_active' => true,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]
                    );
                }
            }
        }
    }

    private function normalizePermissions(): void
    {
        $actionAliases = [
            'view' => 'view',
            'create' => 'add',
            'add' => 'add',
            'edit' => 'edit',
            'update' => 'edit',
            'manage' => 'edit',
            'assign' => 'edit',
            'move' => 'edit',
            'delete' => 'delete',
            'cancel' => 'delete',
        ];

        foreach (DB::table('permissions')->orderBy('id')->get() as $permission) {
            $parts = explode('.', $permission->code);
            $lastPart = strtolower(end($parts));
            $action = $actionAliases[$lastPart] ?? 'view';
            if (isset($actionAliases[$lastPart])) {
                array_pop($parts);
            }

            $applicationCode = match (strtoupper($permission->module)) {
                'FB' => 'POS',
                'SYSTEM' => 'SYS',
                default => 'PMS',
            };
            DB::table('permissions')->where('id', $permission->id)->update([
                'application_code' => $applicationCode,
                'screen_code' => implode('.', $parts) ?: $permission->code,
                'screen_name' => $permission->name,
                'path' => null,
                'action' => $action,
                'screen_type' => str_contains(strtolower($permission->code), 'report') ? 'report' : 'screen',
                'sort_order' => $permission->id,
            ]);
        }
    }

    private function backfillBranchPermissions(): void
    {
        $branches = DB::table('system_branches')->pluck('id');
        $rolePermissions = DB::table('role_permissions')->get();

        foreach ($branches as $branchId) {
            foreach ($rolePermissions as $permission) {
                DB::table('branch_role_permissions')->insertOrIgnore([
                    'system_branch_id' => $branchId,
                    'role_id' => $permission->role_id,
                    'permission_id' => $permission->permission_id,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    private function backfillPositionAssignments(): void
    {
        $branches = DB::table('system_branches')->pluck('id');

        // 1. Backfill cho user_roles có system_branch_id cụ thể
        $assignments = DB::table('user_roles')->whereNotNull('system_branch_id')->orderBy('id')->get();
        foreach ($assignments as $assignment) {
            $positionId = DB::table('position_branch_roles')
                ->where('system_branch_id', $assignment->system_branch_id)
                ->where('application_code', 'PMS')
                ->where('role_id', $assignment->role_id)
                ->value('position_id');

            if (!$positionId) {
                continue;
            }

            DB::table('user_branch_positions')->insertOrIgnore([
                'user_id' => $assignment->user_id,
                'system_branch_id' => $assignment->system_branch_id,
                'application_code' => 'PMS',
                'position_id' => $positionId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // 2. Backfill cho user_roles toàn cục (system_branch_id IS NULL, ví dụ Super Admin): gán trên tất cả chi nhánh
        $globalAssignments = DB::table('user_roles')->whereNull('system_branch_id')->orderBy('id')->get();
        foreach ($globalAssignments as $assignment) {
            foreach ($branches as $branchId) {
                $positionId = DB::table('position_branch_roles')
                    ->where('system_branch_id', $branchId)
                    ->where('application_code', 'PMS')
                    ->where('role_id', $assignment->role_id)
                    ->value('position_id');

                if (!$positionId) {
                    continue;
                }

                DB::table('user_branch_positions')->insertOrIgnore([
                    'user_id' => $assignment->user_id,
                    'system_branch_id' => $branchId,
                    'application_code' => 'PMS',
                    'position_id' => $positionId,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('user_warehouse_permissions');
        Schema::dropIfExists('branch_role_permissions');
        Schema::dropIfExists('user_branch_positions');
        Schema::dropIfExists('position_branch_roles');
        Schema::dropIfExists('positions');
        Schema::dropIfExists('organization_departments');

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('must_change_password');
        });
        Schema::table('permissions', function (Blueprint $table) {
            $table->dropColumn([
                'application_code', 'screen_code', 'screen_name', 'path',
                'action', 'screen_type', 'sort_order',
            ]);
        });
        Schema::table('roles', function (Blueprint $table) {
            $table->dropColumn('allow_historical_date_actions');
        });
    }
};
