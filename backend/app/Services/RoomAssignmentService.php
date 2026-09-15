<?php

namespace App\Services;

use App\Models\Room;
use Illuminate\Support\Collection;

/**
 * Orders physical rooms for automatic assignment.
 *
 * The rooms.floor column is intentionally a string because the hotel can use
 * symbolic floors (for example, B1 or G). Numeric floors therefore need an
 * application-level comparison; a database string ORDER BY would sort 10
 * before 2. Symbolic floors remain deterministic without pretending that
 * their business order is known here.
 */
class RoomAssignmentService
{
    /**
     * Return physical rooms in assignment order.
     *
     * Numeric floors are ordered by their numeric value first. Symbolic floors
     * are ordered after numeric floors using a natural, case-insensitive text
     * comparison. Within a floor, room numbers use natural ordering, followed
     * by stable model values so equal display values do not produce a random
     * assignment order.
     *
     * @param  Collection<int, Room>  $rooms
     * @return Collection<int, Room>
     */
    public function sortCandidates(Collection $rooms): Collection
    {
        return $rooms
            ->sort(function (Room $left, Room $right): int {
                $floorComparison = $this->compareFloors($left->floor, $right->floor);
                if ($floorComparison !== 0) {
                    return $floorComparison;
                }

                $roomNumberComparison = $this->compareNaturalText(
                    $left->room_number,
                    $right->room_number,
                );
                if ($roomNumberComparison !== 0) {
                    return $roomNumberComparison;
                }

                return $this->compareNaturalText($left->getKey(), $right->getKey());
            })
            ->values();
    }

    /**
     * Query and lock physical rooms that can be considered by auto-assign.
     * The caller owns the surrounding transaction.
     *
     * @return Collection<int, Room>
     */
    public function lockedCandidates(int|string $roomClassId): Collection
    {
        $rooms = Room::query()
            ->where('room_class_id', $roomClassId)
            ->where('is_internal', false)
            ->lockForUpdate()
            ->get();

        return $this->sortCandidates($rooms);
    }

    private function compareFloors(mixed $left, mixed $right): int
    {
        $leftFloor = trim((string) ($left ?? ''));
        $rightFloor = trim((string) ($right ?? ''));
        $leftNumeric = $this->numericFloor($leftFloor);
        $rightNumeric = $this->numericFloor($rightFloor);

        if ($leftNumeric !== null && $rightNumeric !== null) {
            $comparison = $leftNumeric <=> $rightNumeric;
            if ($comparison !== 0) {
                return $comparison;
            }

            // Keep equal numeric values (for example, 1 and 01) deterministic.
            return $this->compareNaturalText($leftFloor, $rightFloor);
        }

        if ($leftNumeric !== null) {
            return -1;
        }

        if ($rightNumeric !== null) {
            return 1;
        }

        // No symbolic-floor business mapping is available in this service.
        // Natural text ordering gives a stable result without mapping every
        // unknown label to floor zero.
        return $this->compareNaturalText($leftFloor, $rightFloor);
    }

    private function numericFloor(string $floor): ?float
    {
        if ($floor === '' || ! preg_match('/^[+-]?(?:\d+(?:\.\d+)?|\.\d+)$/', $floor)) {
            return null;
        }

        return (float) $floor;
    }

    private function compareNaturalText(mixed $left, mixed $right): int
    {
        $leftText = trim((string) ($left ?? ''));
        $rightText = trim((string) ($right ?? ''));
        $comparison = strnatcasecmp($leftText, $rightText);

        if ($comparison !== 0) {
            return $comparison;
        }

        return strcmp($leftText, $rightText);
    }
}
