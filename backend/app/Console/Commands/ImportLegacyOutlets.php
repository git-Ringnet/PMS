<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use App\Models\Department;
use App\Models\Outlet;
use App\Models\FbLocation;

class ImportLegacyOutlets extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:import-legacy-outlets';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Import legacy Department (SP1304) and Outlet (SP5409) data from CSV files';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Starting legacy database import process...');

        $this->importDepartments();
        $this->importOutlets();
        $this->importFbLocations();

        $this->info('Import process completed successfully!');
        return Command::SUCCESS;
    }

    /**
     * Import departments from SP1304.csv
     */
    private function importDepartments()
    {
        $filePath = 'imports/SP1304.csv';

        if (!Storage::exists($filePath)) {
            $this->warn("File {$filePath} not found in storage. Skipping Department import.");
            return;
        }

        $this->info('Importing Departments (SP1304)...');
        $realPath = Storage::path($filePath);
        $file = fopen($realPath, 'r');

        $count = 0;
        $isHeader = true;

        while (($row = fgetcsv($file, 0, ',')) !== false) {
            // Skip empty rows
            if (empty($row) || !isset($row[0])) {
                continue;
            }

            // Skip header if matches "Ma" or similar
            if ($isHeader) {
                $isHeader = false;
                if (strtolower(trim($row[0])) === 'ma' || strtolower(trim($row[0])) === 'column name') {
                    continue;
                }
            }

            $code = trim($row[0]);
            $name = trim($row[1] ?? '');

            if (empty($code) || empty($name)) {
                continue;
            }

            Department::updateOrCreate(
                ['code' => $code],
                [
                    'name' => $name,
                    'phone' => trim($row[2] ?? '') ?: null,
                    'show' => isset($row[3]) ? intval(trim($row[3])) : 1,
                ]
            );
            $count++;
        }

        fclose($file);
        $this->info("Imported/Updated {$count} departments.");
    }

    /**
     * Import outlets from SP5409.csv
     */
    private function importOutlets()
    {
        $filePath = 'imports/SP5409.csv';

        if (!Storage::exists($filePath)) {
            $this->warn("File {$filePath} not found in storage. Skipping Outlet import.");
            return;
        }

        $this->info('Importing Outlets (SP5409)...');
        $realPath = Storage::path($filePath);
        $file = fopen($realPath, 'r');

        $count = 0;
        $isHeader = true;

        while (($row = fgetcsv($file, 0, ',')) !== false) {
            // Skip empty rows
            if (empty($row) || !isset($row[0])) {
                continue;
            }

            // Skip header
            if ($isHeader) {
                $isHeader = false;
                if (strtolower(trim($row[0])) === 'outletid' || strtolower(trim($row[0])) === 'column name') {
                    continue;
                }
            }

            $code = trim($row[0]);
            $name = trim($row[1] ?? '');

            if (empty($code) || empty($name)) {
                continue;
            }

            // Parse status (OL_IsEnable)
            $isActive = true;
            if (isset($row[4])) {
                $statusVal = strtolower(trim($row[4]));
                if ($statusVal === '0' || $statusVal === 'false' || $statusVal === 'f') {
                    $isActive = false;
                }
            }

            // Parse BankInfo: accountNumber|accountName|bankName|paymentContent
            $bankInfo = trim($row[6] ?? '');
            $accNo = null;
            $accName = null;
            $bankName = null;
            $paymentContent = 'Thanh toan don hang [BillCode]';

            if (!empty($bankInfo)) {
                $parts = explode('|', $bankInfo);
                if (count($parts) >= 1) $accNo = trim($parts[0]);
                if (count($parts) >= 2) $accName = trim($parts[1]);
                if (count($parts) >= 3) $bankName = trim($parts[2]);
                if (count($parts) >= 4) $paymentContent = trim($parts[3]);
            }

            // Default fallback if bank information was not pipe-separated but contains normal text
            if (!empty($bankInfo) && empty($accNo)) {
                $accNo = $bankInfo;
            }

            Outlet::updateOrCreate(
                ['code' => $code],
                [
                    'name' => $name,
                    'department_code' => trim($row[2] ?? '') ?: null,
                    'service_code' => trim($row[3] ?? '') ?: null,
                    'is_active' => $isActive,
                    'order_index' => isset($row[5]) ? intval(trim($row[5])) : null,
                    'account_number' => $accNo,
                    'account_name' => $accName,
                    'bank_name' => $bankName,
                    'payment_content' => $paymentContent,
                    'connector' => trim($row[7] ?? '') ?: null,
                    'vat_config_id' => isset($row[8]) ? intval(trim($row[8])) : null,
                    'check_voucher' => false, // Default flags for new systems
                    'check_combo' => false,
                ]
            );
            $count++;
        }

        fclose($file);
        $this->info("Imported/Updated {$count} outlets.");
    }

    /**
     * Import FB locations from SP5405.csv
     */
    private function importFbLocations()
    {
        $filePath = 'imports/SP5405.csv';

        if (!Storage::exists($filePath)) {
            $this->warn("File {$filePath} not found in storage. Skipping FB Location import.");
            return;
        }

        $this->info('Importing FB Locations (SP5405)...');
        $realPath = Storage::path($filePath);
        $file = fopen($realPath, 'r');

        $count = 0;
        $isHeader = true;

        while (($row = fgetcsv($file, 0, ',')) !== false) {
            if ($isHeader) {
                $isHeader = false;
                continue;
            }

            if (empty($row) || count($row) < 5) {
                continue;
            }

            // Map columns: Id, Name, Note, Active, OutletId, Color, Letter, DayUse, Provide1, Image
            $id = trim($row[0]);
            $name = trim($row[1]);
            $note = isset($row[2]) ? trim($row[2]) : null;
            $isActive = isset($row[3]) ? (int)trim($row[3]) : 1;
            $outletCode = trim($row[4]);
            $color = isset($row[5]) ? trim($row[5]) : null;
            $letter = isset($row[6]) ? trim($row[6]) : null;
            $dayUse = isset($row[7]) ? trim($row[7]) : null;
            $provide1 = isset($row[8]) ? trim($row[8]) : null;
            $image = isset($row[9]) ? trim($row[9]) : null;

            if (empty($id) || empty($outletCode)) {
                continue;
            }

            // Check if outlet exists first to satisfy foreign key constraint
            if (!Outlet::where('code', $outletCode)->exists()) {
                $this->warn("Skipping location [{$id}] as linked outlet [{$outletCode}] does not exist.");
                continue;
            }

            FbLocation::updateOrCreate(
                ['id' => $id],
                [
                    'name' => $name,
                    'note' => $note,
                    'is_active' => $isActive,
                    'outlet_code' => $outletCode,
                    'color' => $color,
                    'letter' => $letter,
                    'day_use' => $dayUse,
                    'provide1' => $provide1,
                    'image' => $image,
                ]
            );

            $count++;
        }

        fclose($file);
        $this->info("Imported {$count} FB Locations.");
    }
}
