<?php

namespace App\Services\Reports;

use Carbon\Carbon;

/**
 * Builds the legacy "Báo cáo tổng doanh thu" layout (sp_018 / sp_021 / sp_036
 * / sp_038 / sp_044) from the raw rows of rpt_total_revenue.
 *
 * The procedure returns one row per period/date/service code. Mapping service
 * codes to report columns lives in COLUMNS + belongsTo() only, so a hotel with
 * a different outlet model only needs this class (and the template headers)
 * adjusted. Each cell also carries the exact service codes it summed so a
 * double-click can open SERVICE_REVENUE_DETAIL with the same filter.
 */
final class TotalRevenueDataAdapter
{
    public const DETAIL_REPORT = 'SERVICE_REVENUE_DETAIL';

    /** Displayed columns in template order, grouped like the legacy header. */
    public const COLUMNS = [
        'Room' => ['label' => 'Tiền phòng', 'group' => 'FO'],
        'EB' => ['label' => 'Thêm giường', 'group' => 'FO'],
        'EP' => ['label' => 'Phụ thu thêm người', 'group' => 'FO'],
        'ER' => ['label' => 'Phụ thu tiền phòng', 'group' => 'FO'],
        'Tour' => ['label' => 'Vé tham quan', 'group' => 'FO'],
        'Transportation' => ['label' => 'DV đưa đón', 'group' => 'FO'],
        'Miscell' => ['label' => 'DT khác', 'group' => 'FO'],
        'Laundry' => ['label' => 'Giặt ủi', 'group' => 'HK'],
        'Minibar' => ['label' => 'Minibar', 'group' => 'HK'],
        'Broken' => ['label' => 'Hàng đền bù', 'group' => 'HK'],
        'Food' => ['label' => 'Thức ăn', 'group' => 'FB'],
        'Beverage' => ['label' => 'Đồ uống', 'group' => 'FB'],
        'MoneyBreakfastRoom' => ['label' => 'Ăn sáng (tách từ tiền phòng)', 'group' => 'FB'],
        'BF' => ['label' => 'Ăn sáng', 'group' => 'FB'],
        'Others' => ['label' => 'Khác', 'group' => 'FB'],
        'SPA' => ['label' => 'SPA', 'group' => null],
        'Shop' => ['label' => 'Showroom', 'group' => null],
    ];

    /** Pie chart categories, same order as sp_044 (Karaoke is chart-only). */
    private const CHART = [
        'Room', 'EB', 'EP', 'ER', 'Tour', 'Transportation', 'Miscell', 'Food', 'Beverage',
        'MoneyBreakfastRoom', 'BF', 'Others', 'Minibar', 'Laundry', 'Broken', 'SPA', 'Karaoke', 'Shop',
    ];

    private const CHART_COLORS = [
        '#3fa7c7', '#d9304f', '#43a047', '#f0a500', '#ef6c00', '#b23a48', '#5bc0de', '#e05a47',
        '#7cb342', '#e8b400', '#f4845f', '#c0392b', '#4fb3bf', '#ec7f8c', '#6ab04c', '#f6c344',
        '#f39c7a', '#cd6155',
    ];

    private const FO_EXCLUDED = [
        'RM', 'EB', 'TO', 'TS', 'EI', 'EP', 'ER', 'KC', 'LO', 'UP', 'MB', 'LA', 'BR', 'SP', 'S1', 'S2', 'S3',
        'S4', 'S5', 'S6', 'S7', 'TI', 'FB', 'BD', 'BF', 'RF', 'RB', 'PC',
    ];

    private const SPA_CODES = ['SP', 'S1', 'S2', 'S3', 'S4', 'S5', 'S6', 'S7', 'TI'];

    public function adapt(array $data): array
    {
        if (isset($data['revenue']['days'])) {
            return $data;
        }

        $parameters = is_array($data['parameters'] ?? null) ? $data['parameters'] : [];
        $context = $this->context($parameters);
        $byDay = [];
        $periods = ['MTD' => $this->emptyCells(), 'YTD' => $this->emptyCells(), 'CTM' => $this->emptyCells()];
        $sum = $this->emptyCells();

        foreach ($data['rows'] ?? [] as $sourceRow) {
            $row = (array) $sourceRow;
            $period = strtoupper((string) ($row['period_code'] ?? ''));
            $serviceId = strtoupper(trim((string) ($row['service_id'] ?? '')));
            $departmentId = strtoupper(trim((string) ($row['department_id'] ?? '')));
            $isRoomBill = (int) ($row['is_room_bill'] ?? 0) === 1;
            $value = (float) ($context['not_vat'] ? ($row['original_rate'] ?? 0) : ($row['amount'] ?? 0));
            $line = [
                'value' => $value,
                'service_charge' => (float) ($row['service_charge_amount'] ?? 0),
                'tax' => (float) ($row['tax_amount'] ?? 0),
                'columns' => $this->belongsTo($serviceId, $departmentId, $isRoomBill, $context['view_free']),
                'drill_code' => $serviceId === 'BF' && ! $isRoomBill ? 'BS' : $serviceId,
            ];

            if ($period === 'DAY') {
                $date = (string) ($row['business_date'] ?? '');
                $byDay[$date] ??= $this->emptyCells();
                $this->addLine($byDay[$date], $line);
                $this->addLine($sum, $line);
            } elseif (isset($periods[$period])) {
                $this->addLine($periods[$period], $line);
            }
        }

        ksort($byDay);
        $days = [];
        foreach ($byDay as $date => $cells) {
            $label = $this->formatDate($date, 'd-m-Y');
            $days[] = $this->presentRow($cells, $label, $this->drillParameters($context, $date, $date));
        }

        $presentedPeriods = [
            $this->presentRow($periods['MTD'], 'M.T.D', $this->drillParameters($context, $context['month_start'], $context['to_date'])),
            $this->presentRow($periods['YTD'], 'Y.T.D', $this->drillParameters($context, $context['year_start'], $context['to_date'])),
            $this->presentRow($periods['CTM'], 'CTM', $this->drillParameters($context, $context['month_start'], $context['to_date'], true)),
        ];

        $presentedSum = $this->presentRow($sum, 'Cộng', $this->drillParameters($context, $context['from_date'], $context['to_date']));
        $presentedSum['ServiceCharge'] = round($sum['service_charge']);
        $presentedSum['Tax'] = round($sum['tax']);
        [$chartSvg, $legend] = $this->chart($sum);

        $data['revenue'] = [
            'days' => $days,
            'sum' => $presentedSum,
            'periods' => $presentedPeriods,
            'chart_svg' => $chartSvg,
            'chart_legend' => $legend,
            'from_label' => $this->formatDate($context['from_date'], 'd-m-Y'),
            'to_label' => $this->formatDate($context['to_date'], 'd-m-Y'),
            'window_from' => $context['window_from'],
            'window_to' => $context['window_to'],
            'amount_type_label' => $context['not_vat'] ? 'Not VAT' : 'Tổng cộng',
        ];

        return $data;
    }

    /**
     * Escapes user-entered text of SERVICE_REVENUE_DETAIL rows (the renderer
     * inserts values raw) and adds the per-outlet summary table.
     */
    public function adaptDetail(array $data): array
    {
        if (isset($data['outlet_summary'])) {
            return $data;
        }

        $rows = [];
        $outlets = [];
        foreach ($data['rows'] ?? [] as $sourceRow) {
            $row = (array) $sourceRow;
            foreach ($row as $key => $value) {
                if (is_string($value) && ! is_numeric($value)) {
                    $row[$key] = $this->escape($value);
                }
            }
            $rows[] = $row;

            $outlet = (string) ($row['OutletName'] ?? '');
            $outlets[$outlet] ??= ['OutletName' => $outlet, 'OriginalRate' => 0.0, 'ServiceChargeAmount' => 0.0, 'TaxAmount' => 0.0, 'Amount' => 0.0];
            foreach (['OriginalRate', 'ServiceChargeAmount', 'TaxAmount', 'Amount'] as $field) {
                $outlets[$outlet][$field] += (float) ($row[$field] ?? 0);
            }
        }

        ksort($outlets);
        $data['rows'] = $rows;
        $data['outlet_summary'] = array_values($outlets);

        return $data;
    }

    /**
     * Column membership follows sp_018 literally; a line may count in more
     * than one column exactly as the legacy CASE expressions did. "Total" is
     * always the sum of every line, not the sum of the columns.
     *
     * @return list<string>
     */
    private function belongsTo(string $serviceId, string $departmentId, bool $isRoomBill, bool $viewFree): array
    {
        $roomCodes = $viewFree ? ['RM', 'CN'] : ['RM'];
        $foExcluded = $viewFree ? [...self::FO_EXCLUDED, 'CN'] : self::FO_EXCLUDED;
        $rules = [
            'Room' => in_array($serviceId, $roomCodes, true),
            'EB' => $serviceId === 'EB',
            'EP' => $serviceId === 'EP',
            'ER' => in_array($serviceId, ['EI', 'ER', 'KC', 'LO', 'UP'], true),
            'Tour' => $serviceId === 'TO',
            'Transportation' => $serviceId === 'TS',
            'Miscell' => $departmentId === 'FO' && ! in_array($serviceId, $foExcluded, true),
            'Food' => $serviceId === 'RF',
            'Beverage' => in_array($serviceId, ['RB', 'PC'], true),
            'MoneyBreakfastRoom' => $serviceId === 'BF' && $isRoomBill,
            'BF' => in_array($serviceId, ['BF', 'BD'], true) && ! $isRoomBill,
            'Others' => ($departmentId === 'FB' && ! in_array($serviceId, ['RF', 'RB', 'BF', 'PC', 'SR', 'SP'], true))
                || ($departmentId === 'FO' && $serviceId === 'FB'),
            'Minibar' => $serviceId === 'MB',
            'Laundry' => $serviceId === 'LA',
            'Broken' => in_array($departmentId, ['FO', 'HK'], true) && $serviceId === 'BR',
            'SPA' => in_array($serviceId, self::SPA_CODES, true),
            'Karaoke' => $departmentId === 'SK' && in_array($serviceId, ['MK', 'MS', 'KR'], true),
            'Shop' => $serviceId === 'SR',
        ];

        return array_keys(array_filter($rules));
    }

    private function context(array $parameters): array
    {
        $toDate = $this->isoDate($parameters['p_to_date'] ?? null) ?? $this->isoDate($parameters['p_from_date'] ?? null) ?? now()->toDateString();
        $fromDate = $this->isoDate($parameters['p_from_date'] ?? null) ?? $toDate;
        if ($fromDate > $toDate) {
            $fromDate = $toDate;
        }
        $byTime = filter_var($parameters['p_view_by_time'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $fromTime = $byTime ? $this->time($parameters['p_from_time'] ?? null, '00:00') : '00:00';
        $toTime = $byTime ? $this->time($parameters['p_to_time'] ?? null, '23:59') : '23:59';
        $windowStart = Carbon::parse($fromDate)->subDays($byTime ? 1 : 0);

        return [
            'from_date' => $fromDate,
            'to_date' => $toDate,
            'month_start' => Carbon::parse($toDate)->startOfMonth()->toDateString(),
            'year_start' => Carbon::parse($toDate)->startOfYear()->toDateString(),
            'by_time' => $byTime,
            'from_time' => $fromTime,
            'to_time' => $toTime,
            'window_from' => $fromTime.' '.$windowStart->format('d-m-Y'),
            'window_to' => $toTime.' '.Carbon::parse($toDate)->format('d-m-Y'),
            'not_vat' => in_array(strtoupper((string) ($parameters['p_amount_type'] ?? '')), ['NOT_VAT', '2', '0'], true),
            'view_free' => filter_var($parameters['p_view_free'] ?? false, FILTER_VALIDATE_BOOLEAN),
            'department' => (string) ($parameters['p_department'] ?? ''),
            'outlet' => (string) ($parameters['p_outlet'] ?? ''),
            'user' => (string) ($parameters['p_user'] ?? ''),
        ];
    }

    private function emptyCells(): array
    {
        $cells = ['Total' => 0.0, 'Karaoke' => 0.0, 'service_charge' => 0.0, 'tax' => 0.0, 'codes' => ['Total' => []]];
        foreach (array_keys(self::COLUMNS) as $column) {
            $cells[$column] = 0.0;
            $cells['codes'][$column] = [];
        }

        return $cells;
    }

    private function addLine(array &$cells, array $line): void
    {
        $cells['Total'] += $line['value'];
        $cells['service_charge'] += $line['service_charge'];
        $cells['tax'] += $line['tax'];
        $cells['codes']['Total'][$line['drill_code']] = true;
        foreach ($line['columns'] as $column) {
            $cells[$column] += $line['value'];
            if (isset($cells['codes'][$column])) {
                $cells['codes'][$column][$line['drill_code']] = true;
            }
        }
    }

    private function presentRow(array $cells, string $label, array $drill): array
    {
        $row = ['Label' => $label, 'FO' => 0.0, 'HK' => 0.0, 'FB' => 0.0];
        foreach (self::COLUMNS as $column => $definition) {
            $row[$column] = round($cells[$column]);
            $row[$column.'_drill'] = $this->drillAttribute($drill, array_keys($cells['codes'][$column]));
            if ($definition['group'] !== null) {
                $row[$definition['group']] += $cells[$column];
            }
        }
        foreach (['FO', 'HK', 'FB'] as $group) {
            $row[$group] = round($row[$group]);
        }
        $row['Total'] = round($cells['Total']);
        // Total drills into every service of the period, not only the listed codes.
        $row['Total_drill'] = $cells['codes']['Total'] === [] ? '' : $this->drillAttribute($drill, ['*']);

        return $row;
    }

    private function drillParameters(array $context, string $from, string $to, bool $unpaidOnly = false): array
    {
        return [
            'p_from_date' => $from,
            'p_to_date' => $to,
            'p_view_by_time' => $context['by_time'] ? 1 : 0,
            'p_from_time' => $context['from_time'],
            'p_to_time' => $context['to_time'],
            'p_department' => $context['department'],
            'p_outlet' => $context['outlet'],
            'p_user' => $context['user'],
            'p_view_free' => $context['view_free'] ? 1 : 0,
            'p_unpaid_only' => $unpaidOnly ? 1 : 0,
        ];
    }

    /** HTML-attribute-safe JSON consumed by ReportViewer double-click. */
    private function drillAttribute(array $parameters, array $codes): string
    {
        $codes = array_values(array_filter($codes, static fn ($code): bool => $code !== ''));
        if ($codes === []) {
            return '';
        }
        sort($codes);
        $parameters['p_services'] = $codes === ['*'] ? '' : implode(',', $codes);

        return htmlspecialchars(
            json_encode(['report' => self::DETAIL_REPORT, 'parameters' => $parameters], JSON_UNESCAPED_UNICODE),
            ENT_QUOTES,
            'UTF-8'
        );
    }

    /** @return array{0: string, 1: list<array<string, string>>} */
    private function chart(array $cells): array
    {
        $values = [];
        foreach (self::CHART as $index => $key) {
            $values[] = [
                'label' => self::COLUMNS[$key]['label'] ?? 'Karaoke',
                'value' => max(0.0, (float) ($cells[$key] ?? 0)),
                'color' => self::CHART_COLORS[$index % count(self::CHART_COLORS)],
            ];
        }
        $total = array_sum(array_column($values, 'value'));
        $legend = [];
        foreach ($values as $item) {
            $legend[] = [
                'Color' => $item['color'],
                'Label' => $item['label'],
                'Percent' => number_format($total > 0 ? $item['value'] / $total * 100 : 0, 2, ',', '.').'%',
            ];
        }

        return [$this->pieSvg($values, $total), $legend];
    }

    private function pieSvg(array $values, float $total): string
    {
        $radius = 80;
        $center = 90;
        $svg = '<svg class="total-revenue-pie" viewBox="0 0 180 180" width="180" height="180" xmlns="http://www.w3.org/2000/svg">';
        if ($total <= 0) {
            return $svg.'<circle cx="90" cy="90" r="80" fill="#e5e7eb"/></svg>';
        }

        $angle = -M_PI / 2;
        foreach ($values as $item) {
            if ($item['value'] <= 0) {
                continue;
            }
            $sweep = $item['value'] / $total * 2 * M_PI;
            if ($sweep >= 2 * M_PI - 0.0001) {
                $svg .= '<circle cx="'.$center.'" cy="'.$center.'" r="'.$radius.'" fill="'.$item['color'].'"/>';
                break;
            }
            $x1 = $center + $radius * cos($angle);
            $y1 = $center + $radius * sin($angle);
            $angle += $sweep;
            $x2 = $center + $radius * cos($angle);
            $y2 = $center + $radius * sin($angle);
            $svg .= sprintf(
                '<path d="M%d %d L%.2f %.2f A%d %d 0 %d 1 %.2f %.2f Z" fill="%s" stroke="#fff" stroke-width="0.6"/>',
                $center, $center, $x1, $y1, $radius, $radius, $sweep > M_PI ? 1 : 0, $x2, $y2, $item['color']
            );
        }

        return $svg.'</svg>';
    }

    private function isoDate(mixed $value): ?string
    {
        $value = trim((string) $value);
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return $value;
        }
        if (preg_match('/^(\d{2})\/(\d{2})\/(\d{4})$/', $value, $match)) {
            return "{$match[3]}-{$match[2]}-{$match[1]}";
        }

        return null;
    }

    private function time(mixed $value, string $fallback): string
    {
        $value = trim((string) $value);

        return preg_match('/^([01]\d|2[0-3]):[0-5]\d/', $value) ? substr($value, 0, 5) : $fallback;
    }

    private function formatDate(string $date, string $format): string
    {
        return $this->isoDate($date) ? Carbon::parse($this->isoDate($date))->format($format) : $date;
    }

    private function escape(string $value): string
    {
        return str_replace(['{', '}'], ['&#123;', '&#125;'], htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'));
    }
}
