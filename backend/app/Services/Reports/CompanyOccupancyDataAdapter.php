<?php

namespace App\Services\Reports;

final class CompanyOccupancyDataAdapter
{
    public function adapt(array $result): array
    {
        $rows = $result['rows'] ?? [];
        $params = $result['parameters'] ?? [];
        $groupBy = strtoupper((string) ($params['p_group_by'] ?? 'COMPANY'));
        $isGrouped = ! in_array($groupBy, ['COMPANY', 'NONE', ''], true);

        $result['parameters']['p_is_grouped'] = $isGrouped ? '1' : '0';

        if (empty($rows)) {
            $result['aggregate']['rows']['summary_occupancy'] = 0;
            $result['aggregate']['rows']['summary_actual_adr'] = 0;
            $result['aggregate']['rows']['summary_rack_adr'] = 0;

            return $result;
        }

        $totalRoomNight = array_sum(array_column($rows, 'RoomNight'));
        $totalRoomRevenue = array_sum(array_column($rows, 'RoomRevenue'));
        $totalFoc = array_sum(array_column($rows, 'Foc'));
        $totalHu = array_sum(array_column($rows, 'Hu'));
        $totalRoomAvailable = (int) ($rows[0]['TotalRoomAvailable'] ?? 0);

        if ($totalRoomAvailable <= 0) {
            $totalRoomAvailable = 1;
        }

        $summaryOccupancy = round(($totalRoomNight * 100.0) / $totalRoomAvailable, 2);
        $summaryActualAdr = $totalRoomNight > 0 ? round($totalRoomRevenue / $totalRoomNight, 0) : 0;
        $paidNights = $totalRoomNight - $totalFoc - $totalHu;
        $summaryRackAdr = $paidNights > 0 ? round($totalRoomRevenue / $paidNights, 0) : 0;

        $result['aggregate']['rows']['summary_occupancy'] = $summaryOccupancy;
        $result['aggregate']['rows']['summary_actual_adr'] = $summaryActualAdr;
        $result['aggregate']['rows']['summary_rack_adr'] = $summaryRackAdr;

        return $result;
    }
}

