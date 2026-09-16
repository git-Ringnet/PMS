<?php

namespace App\Services;

use App\Models\RegistrationStatus;

final class RegistrationStatusMapper
{
    /**
     * Resolve an existing SP1311 booking-status code without converting it to a primary key.
     *
     * The returned value is safe to store in bookings.registration_status_id.
     */
    public static function codeFromLegacyCode(int|string|null $legacyCode): ?int
    {
        if ($legacyCode === null || $legacyCode === '' || !is_numeric($legacyCode)) {
            return null;
        }

        $statusId = RegistrationStatus::query()
            ->where('booking_status_id', (int) $legacyCode)
            ->value('booking_status_id');

        return $statusId === null ? null : (int) $statusId;
    }
}
