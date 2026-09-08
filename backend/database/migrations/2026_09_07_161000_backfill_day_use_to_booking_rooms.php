<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::connection()->getDriverName() !== 'mysql') {
            return;
        }

        DB::statement(<<<'SQL'
UPDATE booking_rooms br
INNER JOIN bookings b ON b.id = br.booking_id
SET br.is_day_use = b.is_day_use,
    br.updated_at = CURRENT_TIMESTAMP
WHERE br.deleted_at IS NULL
  AND b.deleted_at IS NULL
  AND br.is_day_use <> b.is_day_use
SQL);
    }

    public function down(): void
    {
        // Intentionally no-op: the previous room-level values are not recoverable
        // after applying the booking-level source of truth.
    }
};
