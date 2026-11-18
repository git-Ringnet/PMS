<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Dòng 469: xóa bộ phận phải "nhớ" đã xóa, nếu không lần đồng bộ từ bảng departments cũ sẽ tạo lại.
return new class extends Migration
{
    public function up(): void
    {
        $system = config('database_domains.system_connection', 'mysql_system');
        if (!Schema::connection($system)->hasTable('organization_departments')
            || Schema::connection($system)->hasColumn('organization_departments', 'deleted_at')) {
            return;
        }
        Schema::connection($system)->table('organization_departments', function (Blueprint $table) {
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        $system = config('database_domains.system_connection', 'mysql_system');
        if (Schema::connection($system)->hasColumn('organization_departments', 'deleted_at')) {
            Schema::connection($system)->table('organization_departments', function (Blueprint $table) {
                $table->dropSoftDeletes();
            });
        }
    }
};
