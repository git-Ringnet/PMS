<?php

namespace App\Services\Reports;

final class CompanyOccupancyDataAdapter
{
    public function adapt(array $result): array
    {
        $rows = array_map(static function (array $row): array {
            // The legacy grouped-detail layout exposes RM and EB separately,
            // while its default column "Doanh thu phòng" is RM + EB + ER.
            // Keep the procedure's stable fields and add presentation aliases
            // only for COMPANY_OCCUPANCY.
            $rmRevenue = (float) ($row['RmRevenue'] ?? $row['RoomRevenue'] ?? 0);
            $extraBedRevenue = (float) ($row['ExtraBedRevenue'] ?? 0);
            $extraRollawayRevenue = (float) ($row['ExtraRollawayRevenue'] ?? 0);

            $row['RmRevenue'] = $rmRevenue;
            $row['Revenue'] = (float) ($row['Revenue'] ?? ($rmRevenue + $extraBedRevenue + $extraRollawayRevenue));

            return $row;
        }, $result['rows'] ?? []);
        $result['rows'] = $rows;
        $params = $result['parameters'] ?? [];
        $groupBy = strtoupper((string) ($params['p_group_by'] ?? 'COMPANY'));
        $isGrouped = ! in_array($groupBy, ['COMPANY', 'NONE', ''], true);

        $result['parameters']['p_is_grouped'] = $isGrouped ? '1' : '0';

        if (empty($rows)) {
            $result['aggregate']['rows']['summary_occupancy'] = 0;
            $result['aggregate']['rows']['summary_actual_adr'] = 0;
            $result['aggregate']['rows']['summary_rack_adr'] = 0;
            $result['aggregate']['rows']['summary_average_revenue'] = 0;

            return $result;
        }

        $totalRoomNight = array_sum(array_map(static fn (array $row): float => (float) ($row['RoomNight'] ?? 0), $rows));
        $totalRevenue = array_sum(array_map(static fn (array $row): float => (float) ($row['TotalRevenue'] ?? ($row['Revenue'] ?? $row['RoomRevenue'] ?? 0) + ($row['FbRevenue'] ?? 0) + ($row['OtherRevenue'] ?? 0)), $rows));
        // legacy func_store148: Actual ADR uses TotalByDate (RM after the BF
        // toggle), while Rack ADR uses OriginalTotal. EB/ER and a master bill
        // therefore remain visible in revenue but do not distort Actual ADR.
        $totalByDate = array_sum(array_map(static fn (array $row): float => (float) ($row['TotalByDate'] ?? $row['Revenue'] ?? $row['RoomRevenue'] ?? 0), $rows));
        $originalTotal = array_sum(array_map(static fn (array $row): float => (float) ($row['OriginalTotal'] ?? $row['RackRevenue'] ?? $row['TotalByDate'] ?? $row['Revenue'] ?? $row['RoomRevenue'] ?? 0), $rows));
        $totalFoc = array_sum(array_map(static fn (array $row): float => (float) ($row['FocRoomNights'] ?? $row['Foc'] ?? 0), $rows));
        $totalHu = array_sum(array_map(static fn (array $row): float => (float) ($row['HuRoomNights'] ?? $row['Hu'] ?? 0), $rows));
        // TotalRoomAvailable là tổng công suất của toàn kỳ. RoomAvailable có thể là
        // công suất của từng group ngày nên không được lấy làm tổng báo cáo.
        $totalRoomAvailable = (int) ($rows[0]['TotalRoomAvailable'] ?? $rows[0]['RoomAvailable'] ?? 0);

        if ($totalRoomAvailable <= 0) {
            $totalRoomAvailable = 1;
        }

        $summaryOccupancy = round(($totalRoomNight * 100.0) / $totalRoomAvailable, 2);
        $summaryActualAdr = $totalRoomNight > 0 ? round($totalByDate / $totalRoomNight, 0) : 0;
        $paidNights = $totalRoomNight - $totalFoc - $totalHu;
        $summaryRackAdr = $paidNights > 0 ? round($originalTotal / $paidNights, 0) : 0;
        $summaryAverageRevenue = ($totalRoomNight - $totalHu) > 0
            ? round($totalRevenue / ($totalRoomNight - $totalHu), 0)
            : 0;

        $result['aggregate']['rows']['summary_occupancy'] = $summaryOccupancy;
        $result['aggregate']['rows']['summary_actual_adr'] = $summaryActualAdr;
        $result['aggregate']['rows']['summary_rack_adr'] = $summaryRackAdr;
        $result['aggregate']['rows']['summary_average_revenue'] = $summaryAverageRevenue;

        $fieldNames = array_column($result['fields'] ?? [], 'name');
        foreach ([
            ['name' => 'RmRevenue', 'type' => 'number', 'nullable' => true],
            ['name' => 'Revenue', 'type' => 'number', 'nullable' => true],
        ] as $field) {
            if (! in_array($field['name'], $fieldNames, true)) {
                $result['fields'][] = $field;
            }
        }

        return $result;
    }
}

