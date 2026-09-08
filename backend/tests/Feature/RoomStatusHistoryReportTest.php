<?php

namespace Tests\Feature;

use App\Services\TemplateRendererService;
use App\Services\RoomStatusChangeLogService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use ReflectionMethod;
use Tests\TestCase;

class RoomStatusHistoryReportTest extends TestCase
{
    use RefreshDatabase;

    public function test_migration_keeps_sp_288_filters_and_status_labels(): void
    {
        $migration = file_get_contents(database_path('migrations/2026_09_08_120000_create_room_status_history_report.php'));
        $this->assertStringContainsString('DATE(changed_at) BETWEEN p_from_date AND p_to_date', $migration);
        $this->assertStringContainsString("username = p_username", $migration);
        $this->assertStringContainsString("room = p_room", $migration);
        $this->assertStringContainsString("REPLACE(status_from_english, 'Vacant ', '')", $migration);
        $this->assertStringContainsString('status_from_vietnamese AS StatusFromVietnamese', $migration);
        $this->assertStringContainsString('status_to_vietnamese AS StatusToVietnamese', $migration);
        $this->assertStringNotContainsString('RoomController', $migration);
        $this->assertStringNotContainsString('ActivityLogService', $migration);
    }

    public function test_runtime_status_logger_uses_room_status_ids_and_skips_unchanged_status(): void
    {
        if (!Schema::hasTable('room_status_change_logs')) {
            Schema::create('room_status_change_logs', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('legacy_id')->nullable();
                $table->dateTime('changed_at')->nullable();
                $table->date('business_date')->nullable();
                $table->string('username', 50)->nullable();
                $table->string('room', 50)->nullable();
                $table->unsignedBigInteger('status_from_id')->nullable();
                $table->unsignedBigInteger('status_to_id')->nullable();
                $table->integer('legacy_status_from_code')->nullable();
                $table->integer('legacy_status_to_code')->nullable();
                $table->string('source', 30);
                $table->timestamps();
            });
        }

        DB::table('system_date_rolls')->insert([
            'system_date' => '2026-08-09 00:00:00',
            'actual_date' => '2026-09-08 16:00:00',
            'shift' => '1',
            'username' => 'tester',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $service = app(RoomStatusChangeLogService::class);
        $service->log('201', 'vacant_clean', 'vacant_dirty');
        $service->log('201', 'vacant_dirty', 'vacant_dirty');

        $this->assertDatabaseCount('room_status_change_logs', 1);
        $this->assertDatabaseHas('room_status_change_logs', [
            'room' => '201',
            'status_from_id' => 3,
            'status_to_id' => 2,
            'source' => 'runtime',
            'business_date' => '2026-08-09',
        ]);
    }

    public function test_room_controller_writes_audit_for_single_and_bulk_status_updates(): void
    {
        $controller = file_get_contents(app_path('Http/Controllers/Api/RoomController.php'));
        $this->assertSame(2, substr_count($controller, 'RoomStatusChangeLogService::class'));
        $this->assertStringContainsString('->log($room->room_number, $oldCode, $newCode, $request)', $controller);
    }

    public function test_legacy_layout_uses_business_date_and_actual_action_time(): void
    {
        $migration = file_get_contents(database_path('migrations/2026_09_08_160000_fix_room_status_history_business_datetime.php'));
        $this->assertStringContainsString('TIMESTAMP(history.business_date, TIME(history.changed_at))', $migration);
        $this->assertStringContainsString("'%d-%m-%Y %H:%i:%s'", $migration);
    }

    public function test_reference_template_renders_status_history_columns(): void
    {
        $reference = require database_path('report_templates/room_status_history_reference.php');
        $html = (new ReflectionMethod($reference, 'html'))->invoke($reference);
        $rendered = app(TemplateRendererService::class)->render($html, '', [
            'parameters' => ['p_from_date' => '2026-09-08', 'p_to_date' => '2026-09-08'],
            'summary' => ['row_count' => 1],
            'rows' => [[
                'Id' => 1, 'Date' => '08/09/2026 10:30', 'Username' => 'admin', 'Room' => '201',
                'StatusFromVietnamese' => 'Phòng sạch', 'StatusToVietnamese' => 'Phòng bẩn',
            ]],
        ]);
        $this->assertStringContainsString('BÁO CÁO THAY ĐỔI TÌNH TRẠNG PHÒNG', $rendered);
        $this->assertStringContainsString('Phòng sạch', $rendered);
        $this->assertStringContainsString('Phòng bẩn', $rendered);
        $this->assertStringContainsString('Thời gian thao tác', $rendered);
        $this->assertStringContainsString('Từ trạng thái', $rendered);
        $this->assertStringContainsString('Sang trạng thái', $rendered);
        $this->assertStringNotContainsString('<th>STT</th>', $rendered);
        $this->assertStringNotContainsString('Tổng số lượt thay đổi:', $rendered);
    }
}
