<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\BookingRoom;

/**
 * Keeps the booking lifecycle header consistent with room lifecycle status.
 *
 * The invariant introduced for partial check-in is deliberately narrow:
 * whenever at least one booking room is checked in, the booking header is
 * CHECKIN. The caller may provide a fallback for flows that already had an
 * established rule for the no-inhouse case (such as undo check-in).
 */
class BookingStatusSyncService
{
    /**
     * Synchronize the booking header from current booking-room statuses.
     *
     * If an inhouse room exists, CHECKIN always wins. If no inhouse room
     * exists, the current header is preserved unless a fallback is supplied.
     * This keeps unspecified combinations unchanged while allowing existing
     * undo/revert flows to return to reservation.
     */
    public function sync(Booking|int|string $booking, ?int $statusWhenNoInhouse = null): Booking
    {
        $bookingId = $booking instanceof Booking ? $booking->getKey() : $booking;
        $lockedBooking = Booking::query()
            ->whereKey($bookingId)
            ->lockForUpdate()
            ->firstOrFail();

        $hasInhouseRoom = BookingRoom::query()
            ->where('booking_id', $lockedBooking->getKey())
            ->where('status', BookingRoom::STATUS_CHECKED_IN)
            ->exists();

        $targetStatus = $hasInhouseRoom
            ? Booking::STATUS_CHECKIN
            : $statusWhenNoInhouse;

        if ($targetStatus !== null && (int) $lockedBooking->status !== $targetStatus) {
            $lockedBooking->update(['status' => $targetStatus]);
        }

        return $lockedBooking->fresh();
    }

    /**
     * Expose the invariant check for callers that need to branch without
     * changing a header.
     */
    public function hasInhouseRoom(Booking|int|string $booking): bool
    {
        $bookingId = $booking instanceof Booking ? $booking->getKey() : $booking;

        return BookingRoom::query()
            ->where('booking_id', $bookingId)
            ->where('status', BookingRoom::STATUS_CHECKED_IN)
            ->exists();
    }
}
