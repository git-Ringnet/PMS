<?php

namespace Tests\Feature;

use App\Http\Controllers\Api\ReportDefinitionController;
use App\Models\ReportDataSource;
use App\Models\ReportDefinition;
use App\Models\SystemBranch;
use App\Models\User;
use App\Services\Reports\ReportDataExecutorService;
use App\Services\Reports\ReportDatasetEnricher;
use App\Services\Reports\ReportExportService;
use App\Services\RoomAvailabilityService;
use App\Services\TemplateRendererService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Mockery;
use ReflectionMethod;
use Tests\TestCase;

class NoShowReportTest extends TestCase
{
    use RefreshDatabase;

    public function test_migration_preserves_sp_054_fields_and_filters(): void
    {
        $migration = file_get_contents(database_path('migrations/2026_09_03_260000_create_no_show_report.php'));
        $finalProcedure = substr($migration, strpos($migration, 'private function createLegacyAccuracyProcedure'));
        $accuracyMigration = $finalProcedure;
        $unchargedMigration = $finalProcedure;
        $dateMatchingMigration = $finalProcedure;
        foreach (['noshow_logs', 'booking_rooms', 'service_bills', 'p_from_date', 'p_to_date', 'p_type', 'p_user', 'p_type_money', 'p_sort_type', 'p_division', 'br.status = 4', "ServiceId = 'RM'"] as $text) {
            $this->assertStringContainsString($text, $migration);
        }
        $this->assertStringContainsString("'value' => 2, 'label' => 'All'", $migration);
        $this->assertStringContainsString("'value' => 0, 'label' => 'Charge'", $migration);
        $this->assertStringContainsString("'value' => 1, 'label' => 'No Charge'", $migration);
        $this->assertStringContainsString("'options_source' => 'users'", $migration);
        $this->assertStringNotContainsString('ALTER TABLE bookings', $migration);
        $this->assertStringContainsString('INNER JOIN room_night_bills rnb', $accuracyMigration);
        $this->assertStringContainsString('room_night_bills rnb', $accuracyMigration);
        $this->assertStringContainsString('rnb.is_room_night = 1', $accuracyMigration);
        $this->assertStringContainsString("br.created_by LIKE CONCAT('%', p_user, '%')", $accuracyMigration);
        $this->assertStringNotContainsString('ns.username LIKE CONCAT', $accuracyMigration);
        $this->assertStringContainsString('FROM booking_room_services brs', $unchargedMigration);
        $this->assertStringContainsString("brs.service_code = 'RM'", $unchargedMigration);
        $this->assertStringContainsString('LEFT JOIN (', $unchargedMigration);
        $this->assertStringContainsString('INNER JOIN room_night_bills rnb', $unchargedMigration);
        $this->assertStringContainsString('CASE WHEN charged.RoomId IS NOT NULL', $unchargedMigration);
        $this->assertStringContainsString('COALESCE(billing.Total, charged.Total, br.rate, 0)', $unchargedMigration);
        $this->assertStringContainsString('charged.RoomId IS NULL', $unchargedMigration);
        $this->assertStringNotContainsString('INNER JOIN booking_room_services AS brs', $unchargedMigration);
        $this->assertStringContainsString("charged.ChargeDate = COALESCE(billing.BillingDate, DATE(ns.noshow_date))", $dateMatchingMigration);
    }

    public function test_reference_template_has_legacy_columns_and_charge_group(): void
    {
        $reference = require database_path('report_templates/no_show_reference.php');
        $html = (new ReflectionMethod($reference, 'html'))->invoke($reference);
        $this->assertStringContainsString('BÁO CÁO PHÒNG NO SHOW', $html);
        foreach (['Room', 'BookingId', 'BookingName', 'Company', 'BookingDate', 'ArrivalDate', 'NumOfDays', 'NoshowDate', 'NoshowTime', 'Total', 'Username', 'Ca', 'Reason'] as $field) {
            $this->assertStringContainsString('{{row.'.$field, $html);
        }
        $this->assertStringContainsString('data-group-by="RoomType"', $html);
        $this->assertStringContainsString('{{row.Total|number}}', $html);
        $this->assertStringContainsString('<tfoot>', $html);
        $this->assertStringContainsString('{{aggregate.rows.count}}', $html);
        $blocks = (new ReflectionMethod($reference, 'detail'))->invoke($reference);
        $this->assertSame('count', $blocks[0]['customRows'][0]['cells'][1]['type']);
        $this->assertSame(12, $blocks[0]['customRows'][0]['cells'][0]['colspan']);
        $editor = file_get_contents(base_path('../frontend/src/pages/config/components/hotel/TemplateEditorModal.vue'));
        $this->assertStringContainsString('customRows', $editor);
        $this->assertStringContainsString('Thêm hàng', $editor);
        $this->assertStringContainsString('Array.from({ length: columnCount }', $editor);
        $this->assertStringContainsString('backgroundColor', $editor);
        $this->assertStringContainsString('borderColor', $editor);
    }

    public function test_no_show_all_branches_requires_configured_authorized_connections(): void
    {
        config(['database_domains.branch_connections' => ['HKT1' => [], 'HKT2' => []]]);
        $source = new ReportDataSource(['max_rows' => 100]);
        $report = new ReportDefinition(['code' => 'NO_SHOW']);
        $report->setRelation('reportDataSource', $source);
        $executor = Mockery::mock(ReportDataExecutorService::class);
        $executor->shouldReceive('executeSource')->never();
        $controller = new ReportDefinitionController($executor, Mockery::mock(TemplateRendererService::class), Mockery::mock(ReportDatasetEnricher::class), Mockery::mock(ReportExportService::class), Mockery::mock(RoomAvailabilityService::class));
        $user = Mockery::mock(User::class)->makePartial();
        $user->shouldReceive('isSuperAdmin')->once()->andReturnTrue();
        $request = Request::create('/api/report-definitions/1/execute', 'POST');
        $request->setUserResolver(fn () => $user);
        $method = new ReflectionMethod($controller, 'executeReportSource');
        $this->expectException(\RuntimeException::class);
        $method->invoke($controller, $report, ['p_division' => '__all__'], $request);
    }

    public function test_no_show_all_branches_merges_authorized_rows_and_sorts_descending_by_real_date(): void
    {
        config(['database_domains.branch_connections' => ['HKT1' => 'mysql_hkt1', 'HKT2' => 'mysql_hkt2']]);
        SystemBranch::query()->create([
            'code' => 'HKT1', 'name' => 'Chi nhánh 1', 'db_connection' => 'mysql_hkt1', 'is_active' => true,
        ]);
        SystemBranch::query()->create([
            'code' => 'HKT2', 'name' => 'Chi nhánh 2', 'db_connection' => 'mysql_hkt2', 'is_active' => true,
        ]);

        $source = new ReportDataSource(['max_rows' => 100]);
        $report = new ReportDefinition(['code' => 'NO_SHOW']);
        $report->setRelation('reportDataSource', $source);
        $executor = Mockery::mock(ReportDataExecutorService::class);
        $executor->shouldReceive('executeSource')->once()->withArgs(
            fn ($actualSource, $parameters, $connection) => $actualSource === $source
                && $parameters['p_division'] === '__current__'
                && $connection === 'mysql_hkt1'
        )->andReturn([
            'rows' => [['STT' => 9, 'NoshowDate' => '31/12/2025', 'NoshowTime' => '09:00', 'Room' => '101', 'Division' => '']],
            'fields' => [['name' => 'STT']],
            'summary' => ['row_count' => 1, 'truncated' => false],
        ]);
        $executor->shouldReceive('executeSource')->once()->withArgs(
            fn ($actualSource, $parameters, $connection) => $actualSource === $source
                && $parameters['p_division'] === '__current__'
                && $connection === 'mysql_hkt2'
        )->andReturn([
            'rows' => [['STT' => 8, 'NoshowDate' => '01/01/2026', 'NoshowTime' => '08:00', 'Room' => '201', 'Division' => 'HKT2']],
            'fields' => [['name' => 'STT']],
            'summary' => ['row_count' => 1, 'truncated' => false],
        ]);

        $controller = $this->controller($executor);
        $user = Mockery::mock(User::class)->makePartial();
        $user->shouldReceive('isSuperAdmin')->once()->andReturnTrue();
        $request = Request::create('/api/report-definitions/1/execute', 'POST');
        $request->setUserResolver(fn () => $user);
        $method = new ReflectionMethod($controller, 'executeReportSource');
        $data = $method->invoke($controller, $report, [
            'p_division' => '__all__', 'p_sort_type' => 'DESC',
        ], $request);

        $this->assertSame(2, $data['summary']['row_count']);
        $this->assertSame(['01/01/2026', '31/12/2025'], array_column($data['rows'], 'NoshowDate'));
        $this->assertSame(['HKT2', 'HKT1'], array_column($data['rows'], 'Division'));
        $this->assertSame([1, 2], array_column($data['rows'], 'STT'));
        $this->assertSame('__all__', $data['parameters']['p_division']);
    }

    public function test_no_show_cross_branch_sort_supports_ascending_and_descending(): void
    {
        $controller = $this->controller(Mockery::mock(ReportDataExecutorService::class));
        $method = new ReflectionMethod($controller, 'sortNoShowRows');
        $rows = [
            ['NoshowDate' => '01/01/2026', 'NoshowTime' => '08:00', 'Room' => '201'],
            ['NoshowDate' => '31/12/2025', 'NoshowTime' => '09:00', 'Room' => '101'],
        ];

        $method->invokeArgs($controller, [&$rows, 'ASC']);
        $this->assertSame(['31/12/2025', '01/01/2026'], array_column($rows, 'NoshowDate'));

        $method->invokeArgs($controller, [&$rows, 'DESC']);
        $this->assertSame(['01/01/2026', '31/12/2025'], array_column($rows, 'NoshowDate'));
    }

    private function controller(ReportDataExecutorService $executor): ReportDefinitionController
    {
        return new ReportDefinitionController(
            $executor,
            Mockery::mock(TemplateRendererService::class),
            Mockery::mock(ReportDatasetEnricher::class),
            Mockery::mock(ReportExportService::class),
            Mockery::mock(RoomAvailabilityService::class),
        );
    }
}
