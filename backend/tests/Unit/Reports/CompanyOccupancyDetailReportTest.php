<?php

namespace Tests\Unit\Reports;

use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CompanyOccupancyDetailReportTest extends TestCase
{
    private function migration(): string
    {
        return file_get_contents(database_path('migrations/2026_10_30_190000_update_company_occupancy_detail_report.php'));
    }

    public function test_migration_contains_all_21_contract_fields_and_legacy_parameters(): void
    {
        $migration = $this->migration();

        $this->assertStringContainsString('CREATE PROCEDURE rpt_company_occupancy_detail', $migration);
        $this->assertStringContainsString('RPT_COMPANY_OCCUPANCY_DETAIL', $migration);
        $this->assertStringContainsString('COMPANY_OCCUPANCY_DETAIL', $migration);

        // 7 tham số chuẩn legacy
        foreach ([
            'p_from_date',
            'p_to_date',
            'p_area',
            'p_company',
            'p_segment',
            'p_user_sale',
            'p_source_code',
        ] as $param) {
            $this->assertStringContainsString("'name' => '{$param}'", $migration);
        }

        // 21 field contract chuẩn
        $expectedFields = [
            'BookingCode', 'ReferenceCode', 'CompanyName', 'GuestName',
            'MarketSegment', 'SourceCode', 'BookingDate', 'ArrivalDate',
            'DepartureDate', 'NoOfNight', 'NoOfRoom', 'RoomNight',
            'GuestNight', 'AverageRate', 'AverageRateOriginal', 'RoomRevenue',
            'FbRevenue', 'OtherRevenue', 'TotalRevenue', 'RoomType', 'Nationality',
        ];

        foreach ($expectedFields as $field) {
            $this->assertStringContainsString("'name' => '{$field}'", $migration);
        }

        // Khắc phục lỗi nhân đêm khách và hỗ trợ phân tách quá khứ - tương lai
        $this->assertStringContainsString('GREATEST(COALESCE(br.adults + br.children_qty, 1), 1)', $migration);
        $this->assertStringContainsString('COALESCE(g.nationality_code, \'VNM\')', $migration);
        $this->assertStringContainsString('HAVING (SUM(RoomNight) > 0 OR SUM(RoomRevenue + FbRevenue + OtherRevenue) <> 0)', $migration);
    }

    public function test_runtime_data_cross_validation_with_summary_report(): void
    {
        try {
            if (config('database.connections.mysql.database') === ':memory:') {
                config(['database.connections.mysql.database' => 'pms_hkt1']);
                DB::purge('mysql');
            }

            $db = DB::connection('mysql');
            if ($db->getDriverName() !== 'mysql') {
                $this->markTestSkipped('MySQL connection not available for stored procedure execution.');
            }

            // Gọi Báo cáo chi tiết công ty (166)
            $detailRows = $db->select('CALL rpt_company_occupancy_detail(?, ?, ?, ?, ?, ?, ?)', [
                '2026-08-20', '2026-08-20', null, null, null, null, null
            ]);

            // Gọi Báo cáo tổng hợp công suất công ty (167)
            $summaryRows = $db->select('CALL rpt_company_occupancy(?, ?, ?, ?, ?, ?, ?, ?)', [
                '2026-08-20', '2026-08-20', null, null, null, null, 'company', 1
            ]);

            $this->assertNotEmpty($detailRows, 'Báo cáo chi tiết phải có dữ liệu ngày 20/08/2026');
            $this->assertNotEmpty($summaryRows, 'Báo cáo tổng hợp phải có dữ liệu ngày 20/08/2026');

            $sumDetailRoomNight = array_sum(array_column($detailRows, 'RoomNight'));
            $sumDetailGuestNight = array_sum(array_column($detailRows, 'GuestNight'));
            $sumDetailRoomRev = (float) array_sum(array_column($detailRows, 'RoomRevenue'));
            $sumDetailFbRev = (float) array_sum(array_column($detailRows, 'FbRevenue'));
            $sumDetailOtherRev = (float) array_sum(array_column($detailRows, 'OtherRevenue'));
            $sumDetailTotalRev = (float) array_sum(array_column($detailRows, 'TotalRevenue'));

            $sumSummaryRoomNight = array_sum(array_column($summaryRows, 'RoomNight'));
            $sumSummaryRoomRev = (float) array_sum(array_column($summaryRows, 'RoomRevenue'));
            $sumSummaryFbRev = (float) array_sum(array_column($summaryRows, 'FbRevenue'));
            $sumSummaryOtherRev = (float) array_sum(array_column($summaryRows, 'OtherRevenue'));
            $sumSummaryTotalRev = (float) array_sum(array_column($summaryRows, 'TotalRevenue'));

            // Đối chiếu chéo 100% từng đồng và từng đêm phòng
            $this->assertSame($sumSummaryRoomNight, $sumDetailRoomNight, 'Tổng đêm phòng phải khớp 100% giữa 166 và 167');
            $this->assertEquals($sumSummaryRoomRev, $sumDetailRoomRev, 'Tổng doanh thu phòng phải khớp 100% giữa 166 và 167');
            $this->assertEquals($sumSummaryFbRev, $sumDetailFbRev, 'Tổng doanh thu F&B phải khớp 100% giữa 166 và 167');
            $this->assertEquals($sumSummaryOtherRev, $sumDetailOtherRev, 'Tổng doanh thu khác phải khớp 100% giữa 166 và 167');
            $this->assertEquals($sumSummaryTotalRev, $sumDetailTotalRev, 'Tổng doanh thu phải khớp 100% giữa 166 và 167');

            // Kiểm tra không bị lỗi nhân số lượng khách (221 khách cho 97 phòng, thay vì 3083)
            $this->assertLessThan(300, $sumDetailGuestNight);
            $this->assertGreaterThan(200, $sumDetailGuestNight);

            // Kiểm tra từng dòng chi tiết có đầy đủ 21 cột và không có chuỗi rỗng bất thường
            foreach ($detailRows as $row) {
                $row = (array) $row;
                $this->assertArrayHasKey('BookingCode', $row);
                $this->assertArrayHasKey('CompanyName', $row);
                $this->assertArrayHasKey('RoomType', $row);
                $this->assertArrayHasKey('Nationality', $row);
                $this->assertNotEmpty($row['BookingCode']);
                $this->assertNotEmpty($row['CompanyName']);
                $this->assertNotEmpty($row['RoomType']);
                $this->assertNotEmpty($row['Nationality']);
            }
        } catch (\Throwable $e) {
            if (str_contains($e->getMessage(), 'Connection refused') || str_contains($e->getMessage(), 'Driver not found')) {
                $this->markTestSkipped('Skipping runtime database test due to environment connection: ' . $e->getMessage());
            } else {
                throw $e;
            }
        }
    }
}
