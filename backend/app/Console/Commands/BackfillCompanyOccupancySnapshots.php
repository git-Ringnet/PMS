<?php

namespace App\Console\Commands;

use App\Models\NightAuditRun;
use App\Models\SystemBranch;
use App\Services\ActivityLogService;
use App\Services\NightAuditSnapshotService;
use App\Services\TenantDatabaseService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class BackfillCompanyOccupancySnapshots extends Command
{
    protected $signature = 'reports:backfill-company-occupancy-snapshots
                            {--from= : Snapshot start date (YYYY-MM-DD)}
                            {--to= : Snapshot end date (YYYY-MM-DD)}
                            {--branch= : Branch code or system branch ID; defaults to the current connection}
                            {--force : Rebuild dates that already have snapshots after interactive confirmation}';

    protected $description = 'Backfill missing immutable company-occupancy snapshots; existing dates are skipped unless --force is confirmed.';

    public function handle(NightAuditSnapshotService $snapshotService): int
    {
        $from = $this->parseDate((string) $this->option('from'), '--from');
        $to = $this->parseDate((string) $this->option('to'), '--to');
        if (! $from || ! $to) {
            return self::INVALID;
        }

        if ($from->gt($to)) {
            $this->error('--from must not be later than --to.');

            return self::INVALID;
        }

        $originalConnection = DB::getDefaultConnection();
        $originalDefault = Config::get('database.default');

        try {
            $connection = $this->selectBranchConnection((string) $this->option('branch'));
            if (! Schema::connection($connection)->hasTable('night_audit_company_occupancy_daily_snapshots')) {
                $this->error("Connection [{$connection}] does not have company occupancy snapshot tables.");

                return self::FAILURE;
            }

            $existingCount = DB::table('night_audit_company_occupancy_daily_snapshots')
                ->whereBetween('snapshot_date', [$from->toDateString(), $to->toDateString()])
                ->count();

            $force = (bool) $this->option('force');
            if ($force && ! $this->confirm("Rebuild {$existingCount} existing snapshot date(s) from {$from->toDateString()} to {$to->toDateString()} on [{$connection}]?")) {
                $this->warn('Cancelled.');

                return self::FAILURE;
            }

            $created = 0;
            $skipped = 0;
            $affectedRows = 0;

            for ($date = $from->copy(); $date->lte($to); $date->addDay()) {
                $snapshotDate = $date->toDateString();
                $exists = DB::table('night_audit_company_occupancy_daily_snapshots')
                    ->whereDate('snapshot_date', $snapshotDate)
                    ->exists();

                if ($exists && ! $force) {
                    $skipped++;
                    continue;
                }

                $run = NightAuditRun::query()->create([
                    'source_system_date' => $snapshotDate,
                    'target_system_date' => $snapshotDate,
                    'actual_started_at' => now(),
                    'shift' => 'backfill',
                    'username' => substr((string) (getenv('USERNAME') ?: 'console'), 0, 50),
                    'status' => 'running',
                    'idempotency_key' => 'company-occ-backfill-'.Str::uuid(),
                    'metadata' => [
                        'source' => 'reports:backfill-company-occupancy-snapshots',
                        'force' => $force,
                        'connection' => $connection,
                    ],
                ]);

                try {
                    $count = DB::transaction(fn (): int => $snapshotService->captureCompanyOccupancyReport($date->copy(), $run));
                    $run->update([
                        'status' => 'succeeded',
                        'actual_finished_at' => now(),
                        'metadata' => array_merge($run->metadata ?? [], ['affected_rows' => $count]),
                    ]);
                } catch (\Throwable $exception) {
                    $run->update([
                        'status' => 'failed',
                        'actual_finished_at' => now(),
                        'error_code' => 'COMPANY_OCCUPANCY_BACKFILL_FAILED',
                        'error_message' => $exception->getMessage(),
                    ]);
                    $this->error("{$snapshotDate}: {$exception->getMessage()}");

                    return self::FAILURE;
                }

                $created++;
                $affectedRows += $count;
            }

            if ($force) {
                ActivityLogService::record(
                    'backfill_company_occupancy_snapshot',
                    "Cưỡng chế tính lại snapshot Báo cáo công suất công ty từ {$from->toDateString()} đến {$to->toDateString()}: {$created} ngày, {$affectedRows} dòng.",
                    [
                        'force_screen' => 'Báo cáo công suất công ty',
                        'module' => 'reports',
                        'target_type' => 'NightAuditCompanyOccupancySnapshot',
                        'target_label' => $connection,
                        'system_date' => $to->toDateString(),
                        'new_values' => [
                            'from_date' => $from->toDateString(),
                            'to_date' => $to->toDateString(),
                            'existing_dates_before' => $existingCount,
                            'rebuilt_dates' => $created,
                            'affected_rows' => $affectedRows,
                            'connection' => $connection,
                        ],
                    ],
                );
            }

            $this->info("Completed: {$created} snapshot date(s) generated, {$skipped} existing date(s) skipped, {$affectedRows} row(s) captured.");

            return self::SUCCESS;
        } catch (\Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        } finally {
            Config::set('database.default', $originalDefault);
            DB::setDefaultConnection($originalConnection);
        }
    }

    private function parseDate(string $value, string $option): ?Carbon
    {
        try {
            $date = Carbon::createFromFormat('Y-m-d', trim($value));
            if ($date->format('Y-m-d') !== trim($value)) {
                throw new \InvalidArgumentException();
            }

            return $date->startOfDay();
        } catch (\Throwable) {
            $this->error("{$option} must use YYYY-MM-DD.");

            return null;
        }
    }

    private function selectBranchConnection(string $branchOption): string
    {
        $branchOption = trim($branchOption);
        if ($branchOption === '') {
            return DB::getDefaultConnection();
        }

        $branch = SystemBranch::query()
            ->where('code', $branchOption)
            ->when(ctype_digit($branchOption), fn ($query) => $query->orWhereKey((int) $branchOption))
            ->first();
        if (! $branch) {
            throw new \InvalidArgumentException("Branch [{$branchOption}] was not found.");
        }

        $connection = $branch->db_connection ?: TenantDatabaseService::getConnectionName($branch->code);
        if (! config("database.connections.{$connection}")) {
            TenantDatabaseService::registerDynamicConnection($connection, TenantDatabaseService::getDatabaseName($branch->code));
        }

        Config::set('database.default', $connection);
        DB::setDefaultConnection($connection);
        DB::purge($connection);

        return $connection;
    }
}
