<?php

namespace App\Services\Reports;

/**
 * Builds the "Bảng kê chi tiết theo loại phòng" from the rows returned by
 * rpt_arriving_rooms. It deliberately happens after the Store runs because
 * the Store owns the report data, while this is a presentational aggregate.
 */
class ArrivingRoomsSummaryService
{
    public function summarize(array $rows): array
    {
        $rooms = [];

        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }

            $roomId = $this->value($row, 'RentalRoomId');
            $roomType = $this->value($row, 'RoomTypeCode') ?: $this->value($row, 'RoomType');
            if ($roomId === null || $roomId === '' || $roomType === null || $roomType === '') {
                continue;
            }

            // A room can have several guest rows. Keep its primary guest row
            // (or the first one), so quantity/nights/guests are counted once.
            if (! isset($rooms[$roomId]) || $this->isPrimaryGuest($row)) {
                $rooms[$roomId] = $row;
            }
        }

        $byType = [];
        $total = ['qty' => 0, 'nights' => 0, 'adults' => 0, 'children' => 0];

        foreach ($rooms as $row) {
            $type = (string) ($this->value($row, 'RoomTypeCode') ?: $this->value($row, 'RoomType'));
            $byType[$type] ??= ['room_type_code' => $type, 'qty' => 0, 'nights' => 0, 'adults' => 0, 'children' => 0];

            $nights = $this->integer($this->value($row, 'RoomNight'));
            $adults = $this->integer($this->value($row, 'Adult'));
            $children = $this->integer($this->value($row, 'Child'));

            $byType[$type]['qty']++;
            $byType[$type]['nights'] += $nights;
            $byType[$type]['adults'] += $adults;
            $byType[$type]['children'] += $children;
            $total['qty']++;
            $total['nights'] += $nights;
            $total['adults'] += $adults;
            $total['children'] += $children;
        }

        ksort($byType, SORT_NATURAL | SORT_FLAG_CASE);
        $summaryRows = array_map(function (array $item) use ($total) {
            return [
                'room_type_code' => $item['room_type_code'],
                'qty' => $item['qty'],
                'nights' => $item['nights'],
                'guests' => $item['adults'].' / '.$item['children'],
                'percentage' => $total['qty'] === 0
                    ? '0%'
                    : $this->formatPercent(($item['qty'] / $total['qty']) * 100),
            ];
        }, array_values($byType));

        return [
            'rows' => $summaryRows,
            'total' => [
                'qty' => $total['qty'],
                'nights' => $total['nights'],
                'guests' => $total['adults'].' / '.$total['children'],
                'percentage' => $total['qty'] === 0 ? '0%' : '100%',
            ],
        ];
    }

    private function value(array $row, string $key): mixed
    {
        if (array_key_exists($key, $row)) {
            return $row[$key];
        }

        foreach ($row as $name => $value) {
            if (strcasecmp((string) $name, $key) === 0) {
                return $value;
            }
        }

        return null;
    }

    private function isPrimaryGuest(array $row): bool
    {
        return $this->integer($this->value($row, 'IsMainGuest')) === 1;
    }

    private function integer(mixed $value): int
    {
        return is_numeric($value) ? (int) $value : 0;
    }

    private function formatPercent(float $value): string
    {
        $formatted = rtrim(rtrim(number_format($value, 2, '.', ''), '0'), '.');

        return $formatted.'%';
    }
}
