<?php

namespace App\Services;

use App\Models\BookingRoomGuest;
use App\Models\Guest;

/**
 * Keeps the profile-level guest_status derived from all room assignments.
 * The room assignment status remains the source of truth for one stay.
 */
class GuestStatusSyncService
{
    public function syncForGuest(?string $guestId): ?int
    {
        if (!$guestId) return null;

        $statuses = BookingRoomGuest::query()
            ->where('guest_id', $guestId)
            ->pluck('status')
            ->map(fn ($status) => (int) $status)
            ->unique();

        if ($statuses->isEmpty()) return null;

        $nextStatus = match (true) {
            $statuses->contains(BookingRoomGuest::STATUS_CHECKED_IN) => 1,
            $statuses->contains(BookingRoomGuest::STATUS_ACTIVE) => 0,
            $statuses->contains(BookingRoomGuest::STATUS_NOSHOW) => 4,
            $statuses->contains(BookingRoomGuest::STATUS_CHECKED_OUT),
            $statuses->contains(100) => 2,
            default => 3,
        };

        $guest = Guest::find($guestId);
        if ($guest && (int) $guest->guest_status !== $nextStatus) {
            $guest->update(['guest_status' => $nextStatus]);
        }

        return $nextStatus;
    }

    public function syncForGuestIds(iterable $guestIds): void
    {
        foreach (collect($guestIds)->filter()->unique() as $guestId) {
            $this->syncForGuest((string) $guestId);
        }
    }
}
