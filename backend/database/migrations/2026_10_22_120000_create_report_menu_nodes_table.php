<?php

use App\Models\ReportDefinition;
use App\Services\Reports\ReportPermissionService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Dòng 327: cây menu báo cáo theo từng module (nhóm báo cáo + báo cáo con, sắp xếp kéo thả).
 * Dòng 328: mỗi báo cáo có một quyền xem riêng (report.<mã>.view) để phân quyền theo rule.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('report_menu_nodes')) {
            Schema::create('report_menu_nodes', function (Blueprint $table) {
                $table->id();
                $table->string('location', 30);
                $table->foreignId('parent_id')->nullable()->constrained('report_menu_nodes')->cascadeOnDelete();
                $table->string('type', 10); // group | report
                $table->string('name')->nullable();
                $table->foreignId('report_definition_id')->nullable()->constrained('report_definitions')->cascadeOnDelete();
                $table->unsignedInteger('sort_order')->default(0);
                $table->timestamps();

                $table->index(['location', 'parent_id', 'sort_order']);
            });
        }

        // Tạo quyền xem cho các báo cáo đang có; quyền mới được cấp cho các vai trò hiện có để không ai bị mất báo cáo sau khi cập nhật
        if (Schema::hasTable('report_definitions')) {
            app(ReportPermissionService::class)->ensurePermissions(ReportDefinition::all());
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('report_menu_nodes');
    }
};
