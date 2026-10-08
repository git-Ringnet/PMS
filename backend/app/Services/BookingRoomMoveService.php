<?php

namespace App\Services;

use App\Models\BookingRoom;
use App\Models\BookingRoomService;
use App\Models\RoomNightBill;
use App\Models\ServiceBill;
use Carbon\Carbon;

/**
 * Shared rules for keeping billing provenance intact across room moves.
 */
class BookingRoomMoveService
{
    /**
     * Return this stay segment and its full-move ancestors, newest first.
     * The move_room link is only written when every guest leaves the source;
     * partial moves intentionally do not join this dedupe chain.
     *
     * @return list<string>
     */
    public function previousRoomIds(BookingRoom $room): array
    {
        $ids = [];
        $visited = [];
        $cursor = $room;

        while ($cursor && !isset($visited[(string) $cursor->id])) {
            $visited[(string) $cursor->id] = true;
            $ids[] = (string) $cursor->id;
            $previousRoom = $cursor->movedFromRoom()->first();
            $cursor = $previousRoom && (string) $previousRoom->booking_id === (string) $room->booking_id
                ? $previousRoom
                : null;
        }

        return $ids;
    }

    /**
     * Whether this booking already has a live RM bill for a moved stay segment.
     * RentalRoomId1 is the original owner, including when the bill is on Master.
     */
    public function hasPostedRoomNight(BookingRoom $room, Carbon|string $date, ?bool $expectedRoomNight = null): bool
    {
        $dateString = $date instanceof Carbon ? $date->toDateString() : Carbon::parse($date)->toDateString();
        $billIds = ServiceBill::query()
            ->where('RegisterId1', $room->booking_id)
            ->whereIn('RentalRoomId1', $this->previousRoomIds($room))
            ->where('ServiceId', BookingRoomService::CODE_ROOM)
            ->whereDate('Date', $dateString)
            ->whereIn('Status', [1, 2])
            ->where('Edit', 0)
            ->pluck('Ma');

        if ($billIds->isEmpty()) {
            return false;
        }

        $roomNightBills = RoomNightBill::query()->whereIn('bill_id', $billIds);
        if ($expectedRoomNight !== null) {
            $roomNightBills->where('is_room_night', $expectedRoomNight);
        }

        return $roomNightBills->exists();
    }

    /**
     * Check for a posted setup-service bill on an earlier full-move segment.
     * This prevents lifecycle synchronization from recreating a charge that
     * was already posted before the guest moved.
     *
     * @param list<string> $serviceCodes
     */
    public function hasPostedService(BookingRoom $room, Carbon|string $date, array $serviceCodes): bool
    {
        $sourceRoomIds = array_values(array_filter(
            $this->previousRoomIds($room),
            fn (string $id): bool => $id !== (string) $room->id,
        ));
        if (!$sourceRoomIds || !$serviceCodes) {
            return false;
        }

        $dateString = $date instanceof Carbon ? $date->toDateString() : Carbon::parse($date)->toDateString();

        return ServiceBill::query()
            ->where('RegisterId1', $room->booking_id)
            ->whereIn('RentalRoomId1', $sourceRoomIds)
            ->whereIn('ServiceId', $serviceCodes)
            ->whereDate('Date', $dateString)
            ->whereIn('Status', [1, 2])
            ->where('Edit', 0)
            ->exists();
    }

    /**
     * Read-only bill references from prior full-move segments. These remain
     * owned by the source room/Master and are never converted into new service
     * rows or revenue on the target.
     *
     * @return list<array<string, mixed>>
     */
    public function postedHistoryBills(BookingRoom $room): array
    {
        $sourceRoomIds = array_values(array_filter(
            $this->previousRoomIds($room),
            fn (string $id): bool => $id !== (string) $room->id,
        ));
        if (!$sourceRoomIds) {
            return [];
        }

        return ServiceBill::query()
            ->where('RegisterId1', $room->booking_id)
            ->whereIn('RentalRoomId1', $sourceRoomIds)
            ->whereDate('Date', '>=', $room->arrival_date->toDateString())
            ->whereDate('Date', '<', $room->departure_date->toDateString())
            ->whereIn('Status', [1, 2])
            ->where('Edit', 0)
            ->orderBy('Date')
            ->orderBy('Ma')
            ->get(['Ma', 'Date', 'ServiceId', 'DescriptionServive', 'Quantity', 'Amount', 'RentalRoomId1', 'RentalRoomId2', 'Folio'])
            ->map(fn (ServiceBill $bill): array => [
                'bill_id' => $bill->Ma,
                'date' => $bill->Date,
                'service_code' => $bill->ServiceId,
                'description' => $bill->DescriptionServive,
                'quantity' => $bill->Quantity,
                'amount' => $bill->Amount,
                'source_room_id' => $bill->RentalRoomId1,
                'folio_room_id' => $bill->RentalRoomId2,
                'folio' => $bill->Folio,
                'is_posted_reference' => true,
            ])
            ->unique('bill_id')
            ->values()
            ->all();
    }

    /**
     * Move future, unposted setup to the next full-move segment.
     * Posted rows and their source ownership are immutable history.
     */
    public function transferUnpostedServices(BookingRoom $source, BookingRoom $target, string $fromDate, string $untilDate): void
    {
        BookingRoomService::query()
            ->where('booking_room_id', $source->id)
            ->where('is_posted', 0)
            ->whereDate('service_date', '>=', $fromDate)
            ->whereDate('service_date', '<', $untilDate)
            ->update(['booking_room_id' => $target->id]);
    }

    /**
     * Copy future, unposted setup for a partial move while leaving the source
     * rows and their independent setup intact.
     */
    public function copyUnpostedServices(
        BookingRoom $source,
        BookingRoom $target,
        string $fromDate,
        string $untilDate,
        array $movedGuestIds = [],
    ): void
    {
        $services = BookingRoomService::query()
            ->where('booking_room_id', $source->id)
            ->where('is_posted', 0)
            ->whereDate('service_date', '>=', $fromDate)
            ->whereDate('service_date', '<', $untilDate)
            ->where(function ($query) use ($movedGuestIds): void {
                $query->whereNull('guest_id');
                if ($movedGuestIds) {
                    $query->orWhereIn('guest_id', $movedGuestIds);
                }
            })
            ->get();

        foreach ($services as $service) {
            $copy = $service->replicate();
            $copy->booking_room_id = $target->id;
            $copy->service_bill_id = null;
            $copy->service_bill_detail_no = null;
            $copy->is_posted = 0;
            $copy->posted_at = null;
            $copy->save();
        }
    }
}
