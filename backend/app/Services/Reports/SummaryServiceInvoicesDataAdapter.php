<?php

namespace App\Services\Reports;

final class SummaryServiceInvoicesDataAdapter
{
    public function adapt(array $result): array
    {
        $totals = [
            'Doanh Thu Phòng' => 0.0,
            'Doanh Thu Nhà Hàng' => 0.0,
            'Doanh Thu Minibar' => 0.0,
            'Doanh Thu Giặt Là' => 0.0,
            'Doanh Thu Vận Chuyển' => 0.0,
            'Doanh Thu Dịch Vụ' => 0.0,
        ];

        foreach ($result['rows'] ?? [] as $row) {
            $group = (string) ($row['RevenueGroupName'] ?? 'Doanh Thu Dịch Vụ');
            $totals[$group] = ($totals[$group] ?? 0.0) + (float) ($row['Amount'] ?? 0);
        }

        $result['service_summary'] = [];
        foreach ($totals as $label => $amount) {
            if ($amount !== 0.0) {
                $result['service_summary'][] = ['Label' => $label, 'Amount' => $amount];
            }
        }
        $result['service_summary'][] = [
            'Label' => 'Tổng',
            'Amount' => array_sum($totals),
        ];

        return $result;
    }
}
