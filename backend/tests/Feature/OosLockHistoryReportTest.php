<?php

namespace Tests\Feature;

use App\Services\TemplateRendererService;
use ReflectionMethod;
use Tests\TestCase;

class OosLockHistoryReportTest extends TestCase
{
    public function test_migration_contains_oos_lock_history_rules(): void
    {
        $migration = file_get_contents(database_path('migrations/2026_08_28_170000_create_oos_lock_history_report.php'));

        $this->assertStringContainsString('rpt_oos_lock_history', $migration);
        $this->assertStringContainsString("UPPER(rl.lock_type) = 'OOS'", $migration);
        $this->assertStringContainsString('start_date', $migration);
        $this->assertStringContainsString('end_date', $migration);
        $this->assertStringContainsString('p_sort_by', $migration);
        $this->assertStringContainsString('p_order_by', $migration);
        $this->assertStringContainsString("'OOS_LOCK_HISTORY'", $migration);
    }

    public function test_reference_template_contains_legacy_columns(): void
    {
        $reference = require database_path('report_templates/oos_lock_history_reference.php');
        $html = (new ReflectionMethod($reference, 'html'))->invoke($reference);
        $rendered = app(TemplateRendererService::class)->render($html, '', [
            'parameters' => ['p_from_date' => '2026-08-28', 'p_to_date' => '2026-08-28'],
            'report' => ['generated_by' => 'Tester', 'generated_at' => '28/08/2026 12:00'],
            'rows' => [[
                'GroupName' => 'UnLock',
                'Room' => '502',
                'DateBeginTime' => '01/07/2026 13:12',
                'EndDateTime' => '02/07/2026 10:31',
                'UserUnlock' => 'NB0031',
                'LockDateTime' => '01/07/2026 13:12',
                'Username' => 'NB0031',
                'Note' => 'KHÁCH ĐẶT',
            ]],
        ]);

        $this->assertStringContainsString('BÁO CÁO LỊCH SỬ KHÓA PHÒNG OOS', $rendered);
        $this->assertStringContainsString('Ngày Bắt Đầu', $rendered);
        $this->assertStringContainsString('Người Mở Khóa', $rendered);
        $this->assertStringContainsString('502', $rendered);
    }
}
