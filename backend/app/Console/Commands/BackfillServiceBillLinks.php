<?php

namespace App\Console\Commands;

use App\Models\BookingRoomService;
use App\Models\ServiceBill;
use App\Models\ServiceBillDetail;
use Illuminate\Console\Command;

class BackfillServiceBillLinks extends Command
{
    protected $signature = 'services:backfill-bill-links
                            {--booking= : Booking code to scope the operation}
                            {--apply : Persist uniquely matched links}';

    protected $description = 'Backfill missing service-bill links only when exactly one active bill matches the posted room service.';

    public function handle(): int
    {
        $query = BookingRoomService::query()
            ->whereNotNull('is_posted')
            ->where('is_posted', 1)
            ->whereNull('service_bill_id')
            ->with('bookingRoom.booking');

        if ($bookingCode = trim((string) $this->option('booking'))) {
            if (!preg_match('/(\d+)$/', $bookingCode, $matches)) {
                $this->error('Booking code must end with its numeric ID, for example GAL2.');
                return self::INVALID;
            }

            $query->whereHas('bookingRoom.booking', fn ($booking) => $booking->whereKey((int) $matches[1]));
        }

        $matched = 0;
        $skipped = 0;

        $query->orderBy('id')->each(function (BookingRoomService $service) use (&$matched, &$skipped) {
            $bills = ServiceBill::query()
                ->where('RentalRoomId2', $service->booking_room_id)
                ->where('ServiceId', $service->service_code)
                ->whereDate('Date', $service->service_date)
                ->where('Amount', $service->total_amount)
                ->where('Status', 1)
                ->where('Edit', 0)
                ->get();

            if ($bills->count() !== 1) {
                $skipped++;
                return;
            }

            $bill = $bills->first();
            $details = ServiceBillDetail::query()
                ->where('BillServiceId', $bill->Ma)
                ->where('ServiceId', $service->service_code)
                ->where('Amount', $service->total_amount)
                ->get();

            if ($details->count() !== 1) {
                $skipped++;
                return;
            }

            $matched++;
            if ($this->option('apply')) {
                $service->update([
                    'service_bill_id' => $bill->Ma,
                    'service_bill_detail_no' => $details->first()->Ma,
                ]);
            }
        });

        $this->info(sprintf('%s: %d uniquely matched, %d skipped.', $this->option('apply') ? 'Applied' : 'Dry run', $matched, $skipped));

        return self::SUCCESS;
    }
}
