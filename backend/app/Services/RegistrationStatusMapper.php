<?php

namespace App\Services;

use App\Models\RegistrationStatus;

final class RegistrationStatusMapper
{
    /**
     * Resolve a legacy SP1311 booking-status code to the new primary key.
     *
     * The returned value is safe to store in bookings.registration_status_id.
     */
    public static function idFromLegacyCode(int|string|null $legacyCode): ?int
    {
        if ($legacyCode === null || $legacyCode === '' || !is_numeric($legacyCode)) {
            return null;
        }

        $statusId = RegistrationStatus::query()
            ->where('booking_status_id', (int) $legacyCode)
            ->value('id');

        return $statusId === null ? null : (int) $statusId;
    }
}
