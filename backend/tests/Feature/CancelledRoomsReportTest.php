<?php

namespace Tests\Feature;

use App\Http\Controllers\Api\ReportDefinitionController;
use App\Models\ReportDataSource;
use App\Models\ReportDefinition;
use App\Models\SystemBranch;
use App\Models\User;
use App\Services\Reports\ReportDatasetEnricher;
use App\Services\Reports\ReportDataExecutorService;
use App\Services\Reports\ReportExportService;
use App\Services\RoomAvailabilityService;
use App\Services\TemplateRendererService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Mockery;
use ReflectionMethod;
use Tests\TestCase;

class CancelledRoomsReportTest extends TestCase
{
    use RefreshDatabase;

    public function test_migration_preserves_sp_261_room_cancellation_rules(): void
    {
        $migration = file_get_contents(database_path('migrations/2026_09_03_190000_fix_cancelled_rooms_report_accuracy.php'));
        $this->assertStringContainsString('l.cancel_type = \'room\'', $migration);
        $this->assertStringContainsString('COALESCE(p_view_type, \'CancelDate\') = \'ArrivalDate\'', $migration);
        $this->assertStringContainsString('br.status = 3', $migration);
        $this->assertStringContainsString('l.cancelled_at >= p_from_date', $migration);
        $this->assertStringContainsString('l.cancelled_at < DATE_ADD(p_to_date, INTERVAL 1 DAY)', $migration);
        $this->assertStringContainsString('DATEDIFF(br.arrival_date, DATE(l.cancelled_at))', $migration);
        $this->assertStringContainsString('p_division', $migration);
        $this->assertStringContainsString('p_group_by_reason', $migration);
        $this->assertStringContainsString('$parameter[\'control\'] = \'hidden\'', $migration);
        $this->assertStringNotContainsString('b.deleted_at IS NULL', $migration);
        $this->assertStringNotContainsString('br.deleted_at IS NULL', $migration);
        $schemaMigration = file_get_contents(database_path('migrations/2026_09_03_170000_fix_cancelled_rooms_parameter_schema.php'));
        $this->assertStringContainsString("'position' => 6", $schemaMigration);
        $this->assertStringNotContainsString('PREPARE ', $migration);
    }

    public function test_reference_template_renders_cancelled_room_columns(): void
    {
        $reference = require database_path('report_templates/cancelled_rooms_reference.php');
        $html = (new ReflectionMethod($reference, 'html'))->invoke($reference);
        $data = [
            'parameters' => ['p_from_date' => '2026-09-03', 'p_to_date' => '2026-09-03', 'p_group_by_reason' => false],
            'summary' => ['row_count' => 2],
            'rows' => [[
                'STT' => 1, 'BookingCode' => 'GAL100', 'BookingName' => 'Walkin', 'Company' => 'Test', 'DateDangKy' => '03/09/2026',
                'BookingStatus' => 'Definite', 'Room' => '101', 'ArrivalDate' => '03/09/2026', 'DepartureDate' => '04/09/2026',
                'CancelDate' => '03/09/2026', 'CancelTime' => '10:00', 'UserName' => 'tester', 'CancelReason' => 'Khách yêu cầu', 'CancelReasonGroup' => 'Khách yêu cầu', 'SoCancelDate' => 0,
            ], [
                'STT' => 2, 'BookingCode' => 'GAL101', 'BookingName' => 'Walkin 2', 'Company' => 'Test', 'DateDangKy' => '03/09/2026',
                'BookingStatus' => 'Definite', 'Room' => '102', 'ArrivalDate' => '03/09/2026', 'DepartureDate' => '04/09/2026',
                'CancelDate' => '03/09/2026', 'CancelTime' => '11:00', 'UserName' => 'tester', 'CancelReason' => 'Đổi ngày', 'CancelReasonGroup' => 'Đổi ngày', 'SoCancelDate' => 0,
            ]],
        ];
        $rendered = app(TemplateRendererService::class)->render($html, '', $data);
        $this->assertStringContainsString('BÁO CÁO PHÒNG HỦY', $rendered);
        $this->assertStringContainsString('Khách yêu cầu', $rendered);
        $this->assertStringContainsString('Hủy Trước', $rendered);
        $this->assertStringContainsString('GAL100', $rendered);
        $this->assertStringContainsString('data-group-by="CancelReasonGroup"', $html);
        $this->assertStringContainsString('data-group-enabled-by="parameters.p_group_by_reason"', $html);
        $this->assertStringNotContainsString('Lý do hủy:', $rendered);

        $data['parameters']['p_group_by_reason'] = true;
        $grouped = app(TemplateRendererService::class)->render($html, '', $data);
        $this->assertStringContainsString('Lý do hủy: Khách yêu cầu', $grouped);
        $this->assertStringContainsString('Lý do hủy: Đổi ngày', $grouped);
    }

    public function test_sp1100_short_name_maps_to_room_class_code(): void
    {
        $legacySchema = file_get_contents(base_path('../old_database_struct/db_schema/ProVistaDTXHotel/tables/SP1100.md'));
        $roomClassMigration = file_get_contents(database_path('migrations/2026_06_11_000002_create_room_classes_table.php'));
        $reportMigration = file_get_contents(database_path('migrations/2026_09_03_220000_fix_cancelled_rooms_all_branches.php'));

        $this->assertStringContainsString('**ShortName**', $legacySchema);
        $this->assertStringContainsString("\$table->string('code')->unique(); // Tên viết tắt", $roomClassMigration);
        $this->assertStringContainsString('rc.code AS RoomType', $reportMigration);
        $this->assertStringNotContainsString('rc.name AS RoomType', $reportMigration);
    }

    public function test_all_branches_aggregates_authorized_data_and_reindexes_rows(): void
    {
        SystemBranch::query()->create([
            'code' => 'HKT1', 'name' => 'Chi nhánh 1', 'db_connection' => 'mysql_hkt1', 'is_active' => true,
        ]);
        SystemBranch::query()->create([
            'code' => 'HKT2', 'name' => 'Chi nhánh 2', 'db_connection' => 'mysql_hkt2', 'is_active' => true,
        ]);
        SystemBranch::query()->create([
            'code' => 'HKT3', 'name' => 'Chi nhánh không hoạt động', 'db_connection' => 'mysql_hkt3', 'is_active' => false,
        ]);
        SystemBranch::query()->create([
            'code' => 'DATA', 'name' => 'Database ngoài cấu hình chi nhánh', 'db_connection' => 'mysql_data', 'is_active' => true,
        ]);

        $source = new ReportDataSource(['max_rows' => 1000]);
        $report = new ReportDefinition(['code' => 'CANCELLED_ROOMS']);
        $report->setRelation('reportDataSource', $source);

        $executor = Mockery::mock(ReportDataExecutorService::class);
        $executor->shouldReceive('executeSource')->once()->withArgs(
            fn ($actualSource, $parameters, $connection) => $actualSource === $source
                && $parameters['p_division'] === '__current__'
                && $connection === 'mysql_hkt1'
        )->andReturn([
            'rows' => [[
                'STT' => 99, 'BookingId' => 2, 'Room' => '202', 'CancelDate' => '02/09/2026',
                'CancelTime' => '10:00', 'Division' => '',
            ]],
            'fields' => [['name' => 'STT']],
            'summary' => ['row_count' => 1, 'truncated' => false],
        ]);
        $executor->shouldReceive('executeSource')->once()->withArgs(
            fn ($actualSource, $parameters, $connection) => $actualSource === $source
                && $parameters['p_division'] === '__current__'
                && $connection === 'mysql_hkt2'
        )->andReturn([
            'rows' => [[
                'STT' => 88, 'BookingId' => 1, 'Room' => '101', 'CancelDate' => '01/09/2026',
                'CancelTime' => '09:00', 'Division' => 'HKT2',
            ]],
            'fields' => [['name' => 'STT']],
            'summary' => ['row_count' => 1, 'truncated' => false],
        ]);

        $controller = new ReportDefinitionController(
            $executor,
            Mockery::mock(TemplateRendererService::class),
            Mockery::mock(ReportDatasetEnricher::class),
            Mockery::mock(ReportExportService::class),
            Mockery::mock(RoomAvailabilityService::class),
        );
        $user = Mockery::mock(User::class)->makePartial();
        $user->shouldReceive('isSuperAdmin')->once()->andReturnTrue();
        $request = Request::create('/api/report-definitions/1/execute', 'POST');
        $request->setUserResolver(fn () => $user);

        $method = new ReflectionMethod($controller, 'executeReportSource');
        $data = $method->invoke($controller, $report, [
            'p_division' => '__all__', 'p_group_by_reason' => false,
        ], $request);

        $this->assertSame(2, $data['summary']['row_count']);
        $this->assertFalse($data['summary']['truncated']);
        $this->assertSame([1, 2], array_column($data['rows'], 'STT'));
        $this->assertSame([1, 2], array_column($data['rows'], 'BookingId'));
        $this->assertSame(['HKT2', 'HKT1'], array_column($data['rows'], 'Division'));
        $this->assertSame([['name' => 'STT']], $data['fields']);
        $this->assertSame('__all__', $data['parameters']['p_division']);
    }
}
