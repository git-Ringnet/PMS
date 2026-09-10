<?php

namespace App\Console\Commands;

use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ImportLegacyRoomStatusChanges extends Command
{
    protected $signature = 'legacy:import-room-status-changes
        {--from= : Inclusive legacy date in Y-m-d format}
        {--to= : Inclusive legacy date in Y-m-d format}
        {--chunk=500 : Number of legacy rows per batch}';

    protected $description = 'Import ProVista SP8065 room status changes for ROOM_STATUS_HISTORY';

    public function handle(): int
    {
        if (!array_key_exists('sqlsrv', config('database.connections'))) {
            $this->error('SQL Server legacy connection is not configured.');
            return self::FAILURE;
        }

        $legacy = DB::connection('sqlsrv');
        $query = $legacy->table('SP8065 as st')
            ->select(['st.Id', 'st.Date', 'st.Username', 'st.Room', 'st.StatusFrom', 'st.StatusTo'])
            ->when($this->option('from'), fn ($q, $date) => $q->whereDate('st.Date', '>=', $date))
            ->when($this->option('to'), fn ($q, $date) => $q->whereDate('st.Date', '<=', $date))
            ->orderBy('st.Id');

        $knownStatusIds = DB::table('room_statuses')->pluck('id')->mapWithKeys(
            fn ($id) => [(int) $id => (int) $id]
        );
        $count = 0;
        $query->chunk((int) $this->option('chunk'), function ($rows) use (&$count, $knownStatusIds): void {
            foreach ($rows as $row) {
                DB::table('room_status_change_logs')->updateOrInsert(
                    ['legacy_id' => $row->Id],
                    [
                        'changed_at' => $row->Date,
                        'business_date' => $row->Date ? Carbon::parse($row->Date)->toDateString() : null,
                        'username' => $row->Username,
                        'room' => $row->Room,
                        'status_from_id' => $row->StatusFrom !== null ? $knownStatusIds->get((int) $row->StatusFrom) : null,
                        'status_to_id' => $row->StatusTo !== null ? $knownStatusIds->get((int) $row->StatusTo) : null,
                        'legacy_status_from_code' => $row->StatusFrom,
                        'legacy_status_to_code' => $row->StatusTo,
                        'source' => 'legacy',
                        'updated_at' => now(),
                        'created_at' => now(),
                    ]
                );
                $count++;
            }
        });

        $this->info("Imported {$count} SP8065 rows.");
        return self::SUCCESS;
    }
}
