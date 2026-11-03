<?php

namespace Tests\Unit\Reports;

use Tests\TestCase;

class CompanyOccupancyLegacyEligibilityTest extends TestCase
{
    public function test_finalizer_keeps_only_legacy_eligible_physical_rooms(): void
    {
        $migration = file_get_contents(database_path('migrations/2026_11_02_100000_finalize_company_occupancy_legacy_pdf.php'));

        $this->assertStringContainsString("COALESCE(rs.is_availability, 1) = 1", $migration);
        $this->assertStringContainsString("COALESCE(r.is_internal, 0) = 0 AND br.room_number NOT LIKE '0%'", $migration);
        $this->assertStringContainsString("COALESCE(r0.is_internal, 0) = 0 AND br0.room_number NOT LIKE '0%'", $migration);
        $this->assertStringContainsString("room_number NOT LIKE '0%'", $migration);
    }

    public function test_summary_group_metadata_preserves_legacy_combinations(): void
    {
        $migration = file_get_contents(database_path('migrations/2026_11_02_100000_finalize_company_occupancy_legacy_pdf.php'));
        $reportsPage = file_get_contents(base_path('../frontend/src/pages/reports/ReportsPage.vue'));

        $this->assertStringContainsString("'value' => 'daysegment'", $migration);
        $this->assertStringContainsString("'value' => 'daysourcecode'", $migration);
        $this->assertStringContainsString("'exclusive_with' => ['p_group_by_source']", $migration);
        $this->assertStringContainsString('parameter.maps_to.combinations', $reportsPage);
        $this->assertStringContainsString('parameter.maps_to?.exclusive_with', $reportsPage);
    }
}
