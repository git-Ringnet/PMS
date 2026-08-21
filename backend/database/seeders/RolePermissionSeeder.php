<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use App\Models\SystemBranch;
use App\Models\User;
use App\Models\UserBranch;
use App\Models\UserRole;
use Illuminate\Database\Seeder;

class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        // ── 1. Seed Roles ─────────────────────────────────────────
        $rolesData = [
            ['code' => 'super_admin',   'name' => 'Super Administrator',    'level' => 1, 'department_scope' => null,   'description' => 'Toàn quyền xuyên suốt hệ thống'],
            ['code' => 'branch_admin',  'name' => 'Quản Trị Chi Nhánh',     'level' => 2, 'department_scope' => null,   'description' => 'Quản trị toàn bộ 1 chi nhánh'],
            ['code' => 'mgmt',          'name' => 'Quản Lý',                'level' => 2, 'department_scope' => 'MGMT', 'description' => 'Quản lý chung, xem báo cáo'],
            ['code' => 'fo_manager',    'name' => 'Trưởng Lễ Tân',          'level' => 3, 'department_scope' => 'FO',   'description' => 'Quản lý bộ phận lễ tân'],
            ['code' => 'fo_staff',      'name' => 'Nhân Viên Lễ Tân',       'level' => 3, 'department_scope' => 'FO',   'description' => 'Nhân viên lễ tân'],
            ['code' => 'hk_manager',    'name' => 'Trưởng Buồng Phòng',     'level' => 3, 'department_scope' => 'HK',   'description' => 'Quản lý bộ phận buồng phòng'],
            ['code' => 'hk_staff',      'name' => 'Nhân Viên Buồng Phòng',  'level' => 3, 'department_scope' => 'HK',   'description' => 'Nhân viên buồng phòng'],
            ['code' => 'fb_manager',    'name' => 'Trưởng Nhà Hàng',        'level' => 3, 'department_scope' => 'FB',   'description' => 'Quản lý bộ phận nhà hàng'],
            ['code' => 'fb_staff',      'name' => 'Nhân Viên Nhà Hàng',     'level' => 3, 'department_scope' => 'FB',   'description' => 'Nhân viên nhà hàng / order'],
        ];

        $roles = [];
        foreach ($rolesData as $r) {
            $roles[$r['code']] = Role::updateOrCreate(['code' => $r['code']], $r + ['is_active' => true]);
        }

        // ── 2. Seed Permissions ───────────────────────────────────
        $permissionsData = [
            // FO — Lễ tân
            ['code' => 'fo.booking.view',       'name' => 'Xem danh sách đặt phòng',      'module' => 'FO'],
            ['code' => 'fo.booking.create',      'name' => 'Tạo đặt phòng mới',             'module' => 'FO'],
            ['code' => 'fo.booking.edit',        'name' => 'Sửa thông tin đặt phòng',       'module' => 'FO'],
            ['code' => 'fo.booking.cancel',      'name' => 'Hủy đặt phòng',                 'module' => 'FO'],
            ['code' => 'fo.booking.noshow',      'name' => 'Đánh dấu No-show',              'module' => 'FO'],
            ['code' => 'fo.checkin',             'name' => 'Check-in / Giao phòng',         'module' => 'FO'],
            ['code' => 'fo.checkout',            'name' => 'Check-out / Trả phòng',         'module' => 'FO'],
            ['code' => 'fo.room.move',           'name' => 'Chuyển phòng',                  'module' => 'FO'],
            ['code' => 'fo.payment.view',        'name' => 'Xem thanh toán',                'module' => 'FO'],
            ['code' => 'fo.payment.create',      'name' => 'Thu tiền / Tạo thanh toán',     'module' => 'FO'],
            ['code' => 'fo.service.add',         'name' => 'Thêm dịch vụ phòng',            'module' => 'FO'],
            ['code' => 'fo.frontdesk.view',      'name' => 'Xem FrontDesk / Sơ đồ phòng',  'module' => 'FO'],
            ['code' => 'fo.guest.view',          'name' => 'Xem thông tin khách',           'module' => 'FO'],
            ['code' => 'fo.guest.edit',          'name' => 'Sửa thông tin khách',           'module' => 'FO'],

            // HK — Buồng phòng
            ['code' => 'hk.view',               'name' => 'Xem module Buồng Phòng',        'module' => 'HK'],
            ['code' => 'hk.assign',             'name' => 'Phân công nhân viên buồng',     'module' => 'HK'],
            ['code' => 'hk.report.view',        'name' => 'Xem báo cáo buồng phòng',       'module' => 'HK'],
            ['code' => 'hk.lost_found.manage',  'name' => 'Quản lý đồ thất lạc',          'module' => 'HK'],
            ['code' => 'hk.room.status',        'name' => 'Cập nhật trạng thái phòng',     'module' => 'HK'],
            ['code' => 'hk.service.bill',       'name' => 'Lập hóa đơn dịch vụ HK',       'module' => 'HK'],
            ['code' => 'hk.warehouse.view',     'name' => 'Xem kho HK',                    'module' => 'HK'],
            ['code' => 'hk.warehouse.manage',   'name' => 'Quản lý nhập xuất kho HK',      'module' => 'HK'],

            // FB — Nhà hàng
            ['code' => 'fb.view',               'name' => 'Xem module Nhà Hàng',           'module' => 'FB'],
            ['code' => 'fb.order.view',         'name' => 'Xem danh sách order',           'module' => 'FB'],
            ['code' => 'fb.order.create',       'name' => 'Tạo order mới',                 'module' => 'FB'],
            ['code' => 'fb.order.edit',         'name' => 'Sửa order',                     'module' => 'FB'],
            ['code' => 'fb.order.cancel',       'name' => 'Hủy order',                     'module' => 'FB'],
            ['code' => 'fb.payment',            'name' => 'Thanh toán F&B',               'module' => 'FB'],
            ['code' => 'fb.menu.manage',        'name' => 'Quản lý menu / sản phẩm',      'module' => 'FB'],
            ['code' => 'fb.party.manage',       'name' => 'Quản lý tiệc / sự kiện',       'module' => 'FB'],

            // MGMT — Quản lý
            ['code' => 'mgmt.report.view',      'name' => 'Xem báo cáo tổng hợp',         'module' => 'MGMT'],
            ['code' => 'mgmt.revenue.view',     'name' => 'Xem báo cáo doanh thu',        'module' => 'MGMT'],
            ['code' => 'mgmt.occupancy.view',   'name' => 'Xem công suất phòng',          'module' => 'MGMT'],
            ['code' => 'mgmt.activity_log',     'name' => 'Xem lịch sử thao tác',         'module' => 'MGMT'],

            // SYSTEM — Hệ thống
            ['code' => 'system.user.view',      'name' => 'Xem danh sách nhân viên',       'module' => 'SYSTEM'],
            ['code' => 'system.user.manage',    'name' => 'Quản lý nhân viên',             'module' => 'SYSTEM'],
            ['code' => 'system.role.manage',    'name' => 'Quản lý vai trò & phân quyền',  'module' => 'SYSTEM'],
            ['code' => 'system.branch.manage',  'name' => 'Quản lý chi nhánh',             'module' => 'SYSTEM'],
            ['code' => 'system.setting',        'name' => 'Cài đặt hệ thống',             'module' => 'SYSTEM'],
        ];

        $permMap = [];
        foreach ($permissionsData as $p) {
            $permMap[$p['code']] = Permission::updateOrCreate(['code' => $p['code']], $p);
        }

        // ── 3. Gán Permissions cho Roles ─────────────────────────
        $mapping = [
            'super_admin' => array_keys($permMap), // tất cả

            'branch_admin' => [
                'fo.booking.view','fo.booking.create','fo.booking.edit','fo.booking.cancel',
                'fo.booking.noshow','fo.checkin','fo.checkout','fo.room.move',
                'fo.payment.view','fo.payment.create','fo.service.add','fo.frontdesk.view',
                'fo.guest.view','fo.guest.edit',
                'hk.view','hk.assign','hk.report.view','hk.lost_found.manage','hk.room.status',
                'hk.service.bill','hk.warehouse.view','hk.warehouse.manage',
                'fb.view','fb.order.view','fb.order.create','fb.order.edit','fb.order.cancel',
                'fb.payment','fb.menu.manage','fb.party.manage',
                'mgmt.report.view','mgmt.revenue.view','mgmt.occupancy.view','mgmt.activity_log',
                'system.user.view','system.user.manage',
            ],

            'mgmt' => [
                'fo.booking.view','fo.frontdesk.view','fo.guest.view','fo.payment.view',
                'hk.view','hk.report.view','hk.warehouse.view',
                'fb.view','fb.order.view',
                'mgmt.report.view','mgmt.revenue.view','mgmt.occupancy.view','mgmt.activity_log',
                'system.user.view',
            ],

            'fo_manager' => [
                'fo.booking.view','fo.booking.create','fo.booking.edit','fo.booking.cancel',
                'fo.booking.noshow','fo.checkin','fo.checkout','fo.room.move',
                'fo.payment.view','fo.payment.create','fo.service.add','fo.frontdesk.view',
                'fo.guest.view','fo.guest.edit',
                'mgmt.activity_log',
            ],

            'fo_staff' => [
                'fo.booking.view','fo.booking.create','fo.booking.edit',
                'fo.checkin','fo.checkout','fo.payment.view','fo.payment.create',
                'fo.service.add','fo.frontdesk.view','fo.guest.view','fo.guest.edit',
            ],

            'hk_manager' => [
                'hk.view','hk.assign','hk.report.view','hk.lost_found.manage',
                'hk.room.status','hk.service.bill','hk.warehouse.view','hk.warehouse.manage',
                'fo.frontdesk.view','mgmt.activity_log',
            ],

            'hk_staff' => [
                'hk.view','hk.room.status','hk.lost_found.manage','hk.service.bill',
                'fo.frontdesk.view',
            ],

            'fb_manager' => [
                'fb.view','fb.order.view','fb.order.create','fb.order.edit','fb.order.cancel',
                'fb.payment','fb.menu.manage','fb.party.manage',
                'mgmt.activity_log',
            ],

            'fb_staff' => [
                'fb.view','fb.order.view','fb.order.create','fb.order.edit',
                'fb.payment',
            ],
        ];

        foreach ($mapping as $roleCode => $permCodes) {
            if (!isset($roles[$roleCode])) continue;
            $permIds = collect($permCodes)
                ->filter(fn($c) => isset($permMap[$c]))
                ->map(fn($c) => $permMap[$c]->id)
                ->toArray();
            $roles[$roleCode]->permissions()->sync($permIds);
        }

        // ── 4. Cập nhật system_branches db_connection ─────────────
        $branchConnections = [
            'HKT1' => ['db_connection' => 'mysql_hkt1', 'organization_type' => 'PMS'],
            'HKT2' => ['db_connection' => 'mysql_hkt2', 'organization_type' => 'PMS'],
            'HKT3' => ['db_connection' => 'mysql_hkt3', 'organization_type' => 'PMS'],
            'HKT4' => ['db_connection' => 'mysql_hkt4', 'organization_type' => 'PMS'],
        ];
        foreach ($branchConnections as $code => $data) {
            \App\Models\SystemBranch::where('code', $code)->update($data);
        }

        // ── 5. Gán Super Admin cho user đầu tiên ─────────────────
        $adminUser = User::updateOrCreate(
            ['email' => 'test@example.com'],
            [
                'name' => 'Test User',
                'username' => 'testuser',
                'password' => \Illuminate\Support\Facades\Hash::make('PmsPass@123'),
                'email_verified_at' => now(),
                'is_active_user' => true,
            ]
        );

        if ($adminUser && isset($roles['super_admin'])) {
            UserRole::updateOrCreate(
                ['user_id' => $adminUser->id, 'role_id' => $roles['super_admin']->id, 'system_branch_id' => null],
                []
            );
            // Gán tất cả branches cho super admin
            $branches = \App\Models\SystemBranch::all();
            foreach ($branches as $idx => $branch) {
                UserBranch::updateOrCreate(
                    ['user_id' => $adminUser->id, 'system_branch_id' => $branch->id],
                    ['is_primary' => $idx === 0]
                );
            }
            $adminUser->update(['primary_branch_id' => $branches->first()?->id]);
        }

        $this->command->info('✅ RolePermissionSeeder: Đã seed ' . count($rolesData) . ' roles, ' . count($permissionsData) . ' permissions.');
    }
}
