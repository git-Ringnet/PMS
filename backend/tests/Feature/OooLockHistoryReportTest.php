<?php

namespace Tests\Feature;

use App\Services\TemplateRendererService;
use ReflectionMethod;
use Tests\TestCase;

class OooLockHistoryReportTest extends TestCase
{
    public function test_migration_contains_ooo_lock_history_rules(): void
    {
        $migration = file_get_contents(database_path('migrations/2026_08_27_160000_create_ooo_lock_history_report.php'));

        $this->assertStringContainsString('rpt_ooo_lock_history', $migration);
        $this->assertStringContainsString("COALESCE(rl.lock_type, 'OOO')) = 'OOO'", $migration);
        $this->assertStringContainsString('start_date', $migration);
        $this->assertStringContainsString('end_date', $migration);
        $this->assertStringContainsString('p_sort_by', $migration);
        $this->assertStringContainsString('p_order_by', $migration);
    }

    public function test_user_filter_has_a_system_database_lookup(): void
    {
        $controller = file_get_contents(app_path('Http/Controllers/Api/ReportLookupController.php'));

        $this->assertStringContainsString("'users' => \$this->users(\$search)", $controller);
        $this->assertStringContainsString('User::query()', $controller);
        $this->assertStringContainsString("'value' => \$user->username", $controller);
    }

    public function test_reference_template_contains_legacy_columns(): void
    {
        $reference = require database_path('report_templates/ooo_lock_history_reference.php');
        $html = (new ReflectionMethod($reference, 'html'))->invoke($reference);
        $rendered = app(TemplateRendererService::class)->render($html, '', [
            'parameters' => ['p_from_date'=>'2026-08-27','p_to_date'=>'2026-08-27'],
            'report' => ['generated_by'=>'Tester','generated_at'=>'27/08/2026 12:00'],
            'rows' => [[
                'GroupName'=>'Locking','Room'=>'712','DateBeginTime'=>'27/06/2026 10:22','EndDateTime'=>'31/12/2026 23:59',
                'UserUnlock'=>'','LockDateTime'=>'27/06/2026 10:22','Username'=>'FOM','Note'=>'GM ở',
            ]],
        ]);

        $this->assertStringContainsString('BÁO CÁO LỊCH SỬ KHÓA PHÒNG OOO', $rendered);
        $this->assertStringContainsString('Ngày Bắt Đầu', $rendered);
        $this->assertStringContainsString('Người Mở Khóa', $rendered);
        $this->assertStringContainsString('712', $rendered);
    }
}
