<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * TC_BC_BP_02 (BP dòng 163 / Lễ tân dòng 352), file DANH MỤC BÁO CÁO.xlsx tab "BC dự đoán bán phòng":
 *  - cột Doanh thu theo thông số Revenue; cột "DT phòng khác" theo RevenueER;
 *  - mẫu chi tiết: DT phòng (RM không dayuse), P.Theo giờ (RM dayuse), DT phòng khác, Doanh thu;
 *  - giá TB theo AverageRoomRateIncludedOthersRoomRevenue (0: chỉ RM, 1: theo Revenue).
 */
class RoomForecastRevenueConfigTest extends TestCase
{
    private const DATE = '2031-01-15';

    private function template(): object
    {
        return require database_path('report_templates/room_forecast_reference.php');
    }

    public function test_detail_template_uses_rm_dayuse_er_and_revenue_columns(): void
    {
        $definition = $this->template()->definition();
        $detail = $definition['content_json']['detail'][1];

        $this->assertSame('truthy', $detail['visibleWhenMode']);
        $fields = array_map(static fn (array $c): string => (string) $c['field'], $detail['columns']);
        $this->assertSame([
            'Date', 'DepRooms', 'DepAdult', 'ArrRooms', 'ArrAdult', 'OccRooms', 'OccAdult',
            'HouseUse', 'FOCAll', 'RoomSales', 'RM', 'RMDay', 'ER', 'Revenue',
            'AvgRate', 'AvgRate2', 'RoomAvible', 'PercentOccupancy', 'PercentOccupancy1', 'RevPAR',
        ], $fields);

        $headers = array_map(static fn (array $c): string => (string) $c['header'], $detail['columns']);
        $this->assertStringContainsString('DT phòng', $headers[10]);
        $this->assertStringContainsString('P.Theo giờ', $headers[11]);
        $this->assertStringContainsString('DT phòng khác', $headers[12]);
        $this->assertStringContainsString('Doanh Thu', $headers[13]);
        $this->assertArrayHasKey('RMDay', $definition['data_contract']['rows']);
    }

    public function test_procedure_revenue_follows_revenue_and_revenue_er_configuration(): void
    {
        $this->requireMysql();

        DB::beginTransaction();
        try {
            $this->config('Revenue', 'RM,KC');
            $this->config('RevenueER', 'KC');

            $nightRoom = $this->bookingRoom(0);
            $dayRoom = $this->bookingRoom(1, $nightRoom);
            $this->bill('RM', 1000000, $nightRoom);
            $this->bill('RM', 300000, $dayRoom);
            $this->bill('KC', 200000, null);
            $this->bill('EB', 50000, $nightRoom);   // không thuộc Revenue
            $this->bill('LO', 70000, null);         // không thuộc Revenue/RevenueER

            $row = $this->callForecast(self::DATE);

            $this->assertEqualsWithDelta(1000000, (float) $row->RM, 0.01, 'RM không gồm phòng dayuse');
            $this->assertEqualsWithDelta(300000, (float) $row->RMDay, 0.01, 'RM của phòng dayuse');
            $this->assertEqualsWithDelta(200000, (float) $row->ER, 0.01, 'DT phòng khác theo RevenueER');
            $this->assertEqualsWithDelta(1500000, (float) $row->Revenue, 0.01, 'Doanh thu theo Revenue');
            $this->assertEqualsWithDelta((float) $row->Revenue, (float) $row->RM + (float) $row->RMDay + (float) $row->ER, 0.01);
        } finally {
            DB::rollBack();
        }
    }

    public function test_other_room_revenue_includes_extra_bed_when_extra_bed_is_in_revenue(): void
    {
        $this->requireMysql();

        DB::beginTransaction();
        try {
            $this->config('Revenue', 'RM,EB,KC');
            $this->config('RevenueER', 'KC');

            $nightRoom = $this->bookingRoom(0);
            $this->bill('RM', 1000000, $nightRoom);
            $this->bill('EB', 50000, $nightRoom);
            $this->bill('KC', 200000, null);

            $row = $this->callForecast(self::DATE);

            // Giống legacy sp_023: cột DT phòng khác = RevenueER + EB, để RM + dayuse + khác = Doanh thu.
            $this->assertEqualsWithDelta(250000, (float) $row->ER, 0.01);
            $this->assertEqualsWithDelta(1250000, (float) $row->Revenue, 0.01);
            $this->assertEqualsWithDelta((float) $row->Revenue, (float) $row->RM + (float) $row->RMDay + (float) $row->ER, 0.01);
        } finally {
            DB::rollBack();
        }
    }

    public function test_procedure_average_rate_follows_average_room_rate_included_others_room_revenue(): void
    {
        $this->requireMysql();

        $date = '2026-08-31';
        DB::beginTransaction();
        try {
            $this->config('Revenue', 'RM,EB,ER');
            $this->config('RevenueER', 'ER');

            $this->config('AverageRoomRateIncludedOthersRoomRevenue', '0');
            $off = $this->callForecast($date);
            $this->config('AverageRoomRateIncludedOthersRoomRevenue', '1');
            $on = $this->callForecast($date);

            $this->assertGreaterThan(0, (int) $off->RoomSales, 'Cần ngày có dữ liệu phòng bán');
            $this->assertEqualsWithDelta(
                round(((float) $off->RM + (float) $off->RMDay) / (int) $off->RoomSales),
                (float) $off->AvgRate,
                1.0,
                '=0: giá TB chỉ lấy dịch vụ RM'
            );
            $this->assertEqualsWithDelta(
                round((float) $on->Revenue / (int) $on->RoomSales),
                (float) $on->AvgRate,
                1.0,
                '=1: giá TB lấy theo tổng Revenue'
            );
        } finally {
            DB::rollBack();
        }
    }

    private function requireMysql(): void
    {
        if (DB::connection()->getDriverName() !== 'mysql') {
            $this->markTestSkipped('MySQL driver required for stored procedure test.');
        }
    }

    private function callForecast(string $date): object
    {
        $rows = DB::select('CALL rpt_room_forecast(?,?,?,?,?,?,?,?)', [$date, $date, '', '__current__', '', 0, 1, '']);
        $this->assertCount(1, $rows);

        return $rows[0];
    }

    private function config(string $name, string $value): void
    {
        $exists = DB::table('hotel_configs')->whereRaw('LOWER(name) = ?', [strtolower($name)])->exists();
        if ($exists) {
            DB::table('hotel_configs')->whereRaw('LOWER(name) = ?', [strtolower($name)])->update(['value' => $value]);

            return;
        }
        DB::table('hotel_configs')->insert([
            'name' => $name, 'value' => $value, 'description' => 'test', 'is_visible' => 1,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    /** Lấy 1 booking_room hợp lệ (booking chưa hủy) và đặt cờ dayuse trong transaction. */
    private function bookingRoom(int $dayUse, ?string $exceptId = null): string
    {
        $query = DB::table('booking_rooms as br')
            ->join('bookings as b', 'b.id', '=', 'br.booking_id')
            ->whereNull('br.deleted_at')->whereNull('b.deleted_at')
            ->whereNotIn('b.status', [3, 4]);
        if ($exceptId) {
            $query->where('br.id', '<>', $exceptId);
        }
        $id = $query->orderBy('br.id')->value('br.id');
        if (! $id) {
            $this->markTestSkipped('Không có booking_room để dựng dữ liệu.');
        }
        DB::table('booking_rooms')->where('id', $id)->update(['is_day_use' => $dayUse]);

        return (string) $id;
    }

    private function bill(string $serviceId, int $amount, ?string $bookingRoomId): void
    {
        DB::table('service_bills')->insert([
            'Date' => self::DATE.' 10:00:00',
            'OpenTime' => '10:00',
            'Guest' => 'Test',
            'DepartmentId' => 'FO',
            'ServiceId' => $serviceId,
            'Username' => 'test',
            'Amount' => $amount,
            'Edit' => 0,
            'Status' => 1,
            'RentalRoomId2' => $bookingRoomId,
        ]);
    }
}
