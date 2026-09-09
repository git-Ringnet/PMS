<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReturningGuestsReportTest extends TestCase
{
    use RefreshDatabase;

    public function test_report_migration_contains_dynamic_filter_and_business_conditions(): void
    {
        $migration = file_get_contents(database_path('migrations/2026_09_09_220000_create_returning_guests_report.php'));

        $this->assertStringContainsString('CREATE PROCEDURE rpt_returning_guests', $migration);
        $this->assertStringContainsString('IN p_filter_by_stay TINYINT', $migration);
        $this->assertStringContainsString('COUNT(DISTINCT booking_id) > 1', $migration);
        $this->assertStringContainsString('rs.is_availability = 1', $migration);
        $this->assertStringContainsString("br.room_number NOT LIKE '0%'", $migration);
        $this->assertStringContainsString('p_filter_by_stay = 0', $migration);
    }

    public function test_template_matches_legacy_columns_and_defaults_filter_off(): void
    {
        $template = require database_path('report_templates/returning_guests_reference.php');
        $reflection = new \ReflectionClass($template);
        $blocks = $reflection->getMethod('blocks');
        $blocks->setAccessible(true);
        $value = $blocks->invoke($template);
        $html = $reflection->getMethod('html');
        $html->setAccessible(true);

        $this->assertSame('Passport', $value['detail'][0]['groups'][0]['field']);
        $this->assertCount(8, $value['detail'][0]['columns']);
        $this->assertStringContainsString('data-group-by="Passport"', $html->invoke($template));

        $migration = file_get_contents(database_path('migrations/2026_09_09_220000_create_returning_guests_report.php'));
        $this->assertStringContainsString("'default' => false", $migration);
        $this->assertStringContainsString("'name' => 'p_filter_by_stay'", $migration);
    }
}
