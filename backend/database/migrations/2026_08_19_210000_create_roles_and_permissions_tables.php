<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Thêm cột vào system_branches
        Schema::table('system_branches', function (Blueprint $table) {
            $table->string('organization_type', 20)->default('PMS')->after('is_active')
                ->comment('PMS | FB | PROVISTA | ALL');
            $table->string('db_connection', 50)->nullable()->after('organization_type')
                ->comment('Tên connection trong config/database.php, vd: mysql_hkt1');
        });

        // 2. Thêm cột vào users
        Schema::table('users', function (Blueprint $table) {
            $table->unsignedBigInteger('primary_branch_id')->nullable()->after('is_active_user')
                ->comment('Chi nhánh chính của nhân viên');
            $table->foreign('primary_branch_id')->references('id')->on('system_branches')->nullOnDelete();
        });

        // 3. Bảng roles — vai trò
        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->string('code', 30)->unique()->comment('super_admin, branch_admin, fo_manager...');
            $table->string('name', 100)->comment('Tên hiển thị');
            $table->string('description', 255)->nullable();
            $table->tinyInteger('level')->default(3)
                ->comment('1=system, 2=branch, 3=department');
            $table->string('department_scope', 10)->nullable()
                ->comment('FO | HK | FB | MGMT | null=all');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // 4. Bảng permissions — quyền chi tiết
        Schema::create('permissions', function (Blueprint $table) {
            $table->id();
            $table->string('code', 60)->unique()->comment('fo.booking.create, hk.assign...');
            $table->string('name', 120)->comment('Tên hiển thị');
            $table->string('module', 20)->default('FO')
                ->comment('FO | HK | FB | MGMT | SYSTEM');
            $table->string('description', 255)->nullable();
            $table->timestamps();
        });

        // 5. Pivot: role ↔ permissions
        Schema::create('role_permissions', function (Blueprint $table) {
            $table->unsignedBigInteger('role_id');
            $table->unsignedBigInteger('permission_id');
            $table->primary(['role_id', 'permission_id']);
            $table->foreign('role_id')->references('id')->on('roles')->cascadeOnDelete();
            $table->foreign('permission_id')->references('id')->on('permissions')->cascadeOnDelete();
        });

        // 6. user_branches — user được gán vào chi nhánh nào
        Schema::create('user_branches', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('system_branch_id');
            $table->boolean('is_primary')->default(false);
            $table->timestamps();
            $table->unique(['user_id', 'system_branch_id']);
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('system_branch_id')->references('id')->on('system_branches')->cascadeOnDelete();
        });

        // 7. user_roles — user có vai trò gì, tại chi nhánh nào
        Schema::create('user_roles', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('role_id');
            $table->unsignedBigInteger('system_branch_id')->nullable()
                ->comment('null = áp dụng cho tất cả chi nhánh');
            $table->timestamps();
            $table->unique(['user_id', 'role_id', 'system_branch_id']);
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('role_id')->references('id')->on('roles')->cascadeOnDelete();
            $table->foreign('system_branch_id')->references('id')->on('system_branches')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['primary_branch_id']);
            $table->dropColumn('primary_branch_id');
        });
        Schema::table('system_branches', function (Blueprint $table) {
            $table->dropColumn(['organization_type', 'db_connection']);
        });
        Schema::dropIfExists('user_roles');
        Schema::dropIfExists('user_branches');
        Schema::dropIfExists('role_permissions');
        Schema::dropIfExists('permissions');
        Schema::dropIfExists('roles');
    }
};
