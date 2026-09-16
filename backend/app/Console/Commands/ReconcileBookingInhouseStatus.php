<?php

namespace App\Console\Commands;

use App\Models\Booking;
use App\Models\BookingRoom;
use App\Services\BookingStatusSyncService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ReconcileBookingInhouseStatus extends Command
{
    protected $signature = 'bookings:reconcile-inhouse-status {--apply : Apply the reported header corrections}';

    protected $description = 'Report bookings with inhouse rooms but an incorrect header status; read-only unless --apply is supplied';

    public function handle(BookingStatusSyncService $sync): int
    {
        $query = Booking::query()->where('status', '!=', Booking::STATUS_CHECKIN)
            ->whereHas('bookingRooms', fn ($rooms) => $rooms->where('status', BookingRoom::STATUS_CHECKED_IN));
        $count = 0;
        $query->chunkById(200, function ($bookings) use ($sync, &$count) {
            foreach ($bookings as $booking) {
                $this->line("Booking {$booking->id}: {$booking->status} -> 1");
                if ($this->option('apply')) {
                    DB::transaction(fn () => $sync->sync($booking));
                }
                $count++;
            }
        });
        $this->info(($this->option('apply') ? 'Reconciled candidates: ' : 'Dry-run candidates: ') . $count);
        return self::SUCCESS;
    }
}
