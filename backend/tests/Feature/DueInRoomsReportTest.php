<?php

namespace Tests\Feature;

use Tests\TestCase;

class DueInRoomsReportTest extends TestCase
{
    public function test_due_in_report_migration_contains_sp_006_rules(): void
    {
        $migration = file_get_contents(database_path('migrations/2026_08_28_180000_create_due_in_rooms_report.php'));

        $this->assertStringContainsString('rpt_due_in_rooms', $migration);
        $this->assertStringContainsString('br.arrival_date BETWEEN p_from_date AND p_to_date', $migration);
        $this->assertStringContainsString('p_room_class_id', $migration);
        $this->assertStringContainsString('p_registration_status_id', $migration);
        $this->assertStringContainsString('DUE_IN_ROOMS_STANDARD', $migration);
    }
}
