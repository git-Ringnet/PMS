<?php

namespace App\Console\Commands;

use App\Models\SystemBranch;
use App\Services\TenantDatabaseService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use PDO;
use Exception;

class ResetMultiDbCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'db:reset-all 
                            {target=all : Mục tiêu reset (all | system | tên/mã chi nhánh bất kỳ như hkt1, hkt5, dai_luc...)} 
                            {--branch= : Chỉ định chi nhánh (tùy chọn thay cho target)} 
                            {--seed : Tự động seed dữ liệu mẫu sau khi migrate}
                            {--seed-all : Seed dữ liệu mẫu cho tất cả các chi nhánh}
                            {--drop-extra : Xóa bỏ các database rác không có trong bảng system_branches}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Tự động quét và Reset (migrate:fresh) TOÀN BỘ Database pms_* trong hệ thống Multi-DB PMS';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $target = strtolower($this->option('branch') ?: $this->argument('target'));
        $seed = $this->option('seed');
        $seedAll = $this->option('seed-all');
        $dropExtra = $this->option('drop-extra');

        $this->info('======================================================');
        $this->info('  🚀 PMS MULTI-DATABASE DYNAMIC RESET TOOL');
        $this->info('======================================================');

        // 1. Lưu tạm danh sách chi nhánh hiện có trước khi reset system
        $existingBranches = [];
        try {
            if (Config::has('database.connections.mysql_system')) {
                $existingBranches = SystemBranch::all()->toArray();
            }
        } catch (\Throwable $e) {
            $existingBranches = [];
        }

        // 2. Reset Database System trước (để đảm bảo bảng system_branches và seed chuẩn)
        if ($target === 'all' || $target === 'system') {
            $this->ensureDatabaseExists('pms_system');
            $this->resetSingleDb('mysql_system', 'pms_system', 'System DB (pms_system)', true, 'system');

            // Khôi phục lại toàn bộ chi nhánh đã tạo trước đó
            if (!empty($existingBranches)) {
                foreach ($existingBranches as $b) {
                    SystemBranch::updateOrCreate(
                        ['code' => $b['code']],
                        [
                            'name' => $b['name'],
                            'tax_code' => $b['tax_code'] ?? null,
                            'email' => $b['email'] ?? null,
                            'phone' => $b['phone'] ?? null,
                            'address' => $b['address'] ?? null,
                            'accounting_month' => $b['accounting_month'] ?? 11,
                            'accounting_year' => $b['accounting_year'] ?? 2024,
                            'is_active' => $b['is_active'] ?? true,
                            'db_connection' => $b['db_connection'] ?? null,
                            'organization_type' => $b['organization_type'] ?? 'PMS',
                        ]
                    );
                }
            }

            // Tự động đồng bộ các database pms_* hiện có trên MySQL vào system_branches nếu chưa có
            if (!$dropExtra) {
                $this->syncDatabasesToSystemBranches();
            }

            // Gán quyền truy cập cho tất cả Super Admin
            $this->assignSuperAdminsToAllBranches();

            if ($target === 'system') {
                $this->info('✅ ĐÃ RESET SYSTEM DATABASE THÀNH CÔNG!');
                return Command::SUCCESS;
            }
        }

        // 3. Quét toàn bộ database pms_* thực tế trên máy chủ MySQL
        $allDatabases = $this->discoverAllPmsDatabases();

        $this->info('  🔍 Tìm thấy ' . count($allDatabases) . ' database PMS trên MySQL:');
        foreach ($allDatabases as $dbInfo) {
            $this->line("    • {$dbInfo['db_name']} ({$dbInfo['conn']})");
        }
        $this->newLine();

        if ($target === 'all') {
            $this->warn('Đang thực hiện Reset TOÀN BỘ các Database chi nhánh...');
            $this->newLine();

            // Nếu bật cờ --drop-extra, xóa những DB rác không có trong system_branches
            if ($dropExtra) {
                $this->cleanExtraDatabases($allDatabases);
                $allDatabases = $this->discoverAllPmsDatabases();
            }

            foreach ($allDatabases as $key => $db) {
                if ($db['type'] === 'system') {
                    continue; // Đã reset ở bước 1
                }

                $shouldSeed = ($key === 'hkt1' && $seed) || $seedAll;
                $this->resetSingleDb($db['conn'], $db['db_name'], $db['name'], $shouldSeed, 'branch');
            }

            $this->newLine();
            $this->info('✅ ĐÃ RESET TOÀN BỘ CÁC DATABASE TRÊN HỆ THỐNG THÀNH CÔNG!');
            return Command::SUCCESS;
        }

        // Reset riêng 1 chi nhánh bất kỳ
        $matched = null;
        foreach ($allDatabases as $key => $db) {
            if ($key === $target || $db['db_name'] === $target || $db['db_name'] === 'pms_' . $target || $db['conn'] === $target) {
                $matched = $db;
                break;
            }
        }

        if ($matched) {
            $this->warn("Đang thực hiện Reset riêng: {$matched['name']}...");
            $this->newLine();

            $shouldSeed = ($matched['type'] === 'system') || $seed || $seedAll;
            $this->resetSingleDb($matched['conn'], $matched['db_name'], $matched['name'], $shouldSeed, $matched['type']);

            $this->newLine();
            $this->info("✅ ĐÃ RESET THÀNH CÔNG: {$matched['name']}!");
            return Command::SUCCESS;
        }

        $this->error("❌ Mục tiêu '{$target}' không tìm thấy trong hệ thống MySQL.");
        return Command::FAILURE;
    }

    /**
     * Quét và đăng ký dynamic connections cho tất cả database `pms_%` trên MySQL + bảng system_branches
     */
    protected function discoverAllPmsDatabases(): array
    {
        $databases = [];

        // 1. Luôn thêm system DB
        $databases['system'] = [
            'conn' => 'mysql_system',
            'db_name' => 'pms_system',
            'name' => 'System DB (pms_system)',
            'type' => 'system',
        ];

        // 2. Đọc từ MySQL SHOW DATABASES LIKE 'pms_%'
        try {
            $dbRows = DB::connection('mysql_system')->select("SHOW DATABASES LIKE 'pms_%'");
            foreach ($dbRows as $row) {
                $val = array_values((array) $row);
                $dbName = $val[0] ?? '';
                if (!$dbName || $dbName === 'pms_system') continue;

                $slug = preg_replace('/^pms_/', '', $dbName);
                $connName = 'mysql_' . $slug;

                // Đăng ký connection động vào Laravel runtime
                TenantDatabaseService::registerDynamicConnection($connName, $dbName);

                $databases[$slug] = [
                    'conn' => $connName,
                    'db_name' => $dbName,
                    'name' => "Chi nhánh DB ({$dbName})",
                    'type' => 'branch',
                ];
            }
        } catch (Exception $e) {
            $this->warn("  ⚠️  Không thể quét SHOW DATABASES qua mysql_system ({$e->getMessage()})");
        }

        // 3. Đọc từ bảng system_branches (nếu có chi nhánh mới chưa được tạo DB)
        try {
            $branches = SystemBranch::all();
            foreach ($branches as $branch) {
                $dbName = TenantDatabaseService::getDatabaseName($branch->code);
                $connName = TenantDatabaseService::getConnectionName($branch->code);
                $slug = preg_replace('/^pms_/', '', $dbName);

                // Tự động tạo database nếu chưa có trên MySQL
                $this->ensureDatabaseExists($dbName);

                // Đăng ký connection động
                TenantDatabaseService::registerDynamicConnection($connName, $dbName);

                $databases[$slug] = [
                    'conn' => $connName,
                    'db_name' => $dbName,
                    'name' => "Chi nhánh {$branch->name} ({$dbName})",
                    'type' => 'branch',
                ];
            }
        } catch (Exception $e) {
            // Trường hợp system chưa có bảng system_branches
        }

        return $databases;
    }

    /**
     * Tự động kết nối MySQL và tạo Database nếu chưa tồn tại
     */
    protected function ensureDatabaseExists(string $dbName): void
    {
        try {
            DB::connection('mysql_system')->statement("CREATE DATABASE IF NOT EXISTS `{$dbName}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;");
        } catch (Exception $e) {
            try {
                $host = config('database.connections.mysql_system.host', '127.0.0.1');
                $port = config('database.connections.mysql_system.port', '3306');
                $user = config('database.connections.mysql_system.username', 'root');
                $pass = config('database.connections.mysql_system.password', '');

                $pdo = new PDO("mysql:host={$host};port={$port}", $user, $pass, [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                ]);
                $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$dbName}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;");
            } catch (Exception $e2) {
                // Ignore
            }
        }
    }

    /**
     * Tự động đồng bộ các database pms_* hiện có trên MySQL vào system_branches
     */
    protected function syncDatabasesToSystemBranches(): void
    {
        try {
            $dbRows = DB::connection('mysql_system')->select("SHOW DATABASES LIKE 'pms_%'");
            $knownBranches = SystemBranch::all();
            $knownDbNames = $knownBranches->map(fn($b) => TenantDatabaseService::getDatabaseName($b->code))->toArray();

            foreach ($dbRows as $row) {
                $val = array_values((array) $row);
                $dbName = $val[0] ?? '';
                if (!$dbName || $dbName === 'pms_system') continue;

                if (!in_array($dbName, $knownDbNames, true)) {
                    $slug = preg_replace('/^pms_/', '', $dbName);
                    $name = 'Chi nhánh ' . ucwords(str_replace('_', ' ', $slug));
                    $code = strtoupper($slug);

                    SystemBranch::firstOrCreate(
                        ['code' => $code],
                        [
                            'name' => $name,
                            'db_connection' => 'mysql_' . $slug,
                            'accounting_month' => 11,
                            'accounting_year' => 2024,
                            'is_active' => true,
                            'organization_type' => 'PMS',
                        ]
                    );
                    $this->info("  ✓ Đã đồng bộ chi nhánh {$name} ({$dbName}) vào danh sách quản trị System.");
                }
            }
        } catch (\Throwable $e) {
            $this->warn("  ⚠️ Lỗi khi đồng bộ danh sách chi nhánh: " . $e->getMessage());
        }
    }

    /**
     * Gán quyền truy cập tất cả chi nhánh cho Super Admin
     */
    protected function assignSuperAdminsToAllBranches(): void
    {
        try {
            $superAdmins = \App\Models\User::whereHas('roles', fn($q) => $q->where('code', 'super_admin'))->get();
            $allBranches = SystemBranch::all();

            foreach ($superAdmins as $admin) {
                foreach ($allBranches as $branch) {
                    \App\Models\UserBranch::firstOrCreate(
                        ['user_id' => $admin->id, 'system_branch_id' => $branch->id],
                        ['is_primary' => ($branch->code === 'HKT1')]
                    );
                }
            }
        } catch (\Throwable $e) {
            // Ignore
        }
    }

    /**
     * Xóa các database rác không nằm trong bảng system_branches
     */
    protected function cleanExtraDatabases(array $discoveredDatabases): void
    {
        try {
            $validDbNames = collect(['pms_system']);
            $branches = SystemBranch::all();
            foreach ($branches as $b) {
                $validDbNames->push(TenantDatabaseService::getDatabaseName($b->code));
            }

            foreach ($discoveredDatabases as $db) {
                if (!$validDbNames->contains($db['db_name'])) {
                    $this->warn("  🗑️ Đang xóa database rác: {$db['db_name']}...");
                    DB::connection('mysql_system')->statement("DROP DATABASE IF EXISTS `{$db['db_name']}`;");
                }
            }
        } catch (Exception $e) {
            $this->warn("  ⚠️ Lỗi khi xóa database rác: " . $e->getMessage());
        }
    }

    /**
     * Thực hiện migrate:fresh và seed cho 1 connection
     */
    protected function resetSingleDb(string $conn, string $dbName, string $name, bool $shouldSeed, string $type = 'branch'): void
    {
        $this->line("⏳ [{$conn}] Bắt đầu migrate:fresh trên {$name}...");

        $migrationPaths = $this->migrationPaths($type);

        $exitCode = Artisan::call('migrate:fresh', [
            '--database' => $conn,
            '--path'     => $migrationPaths,
            '--force'    => true,
        ]);

        if ($exitCode === 0) {
            $this->info("  ✓ Schema migrated thành công: {$name}");
        } else {
            $this->error("  ✗ Lỗi khi migrate {$name}: " . Artisan::output());
            return;
        }

        if ($shouldSeed) {
            $this->line("  🌱 Đang chạy seeder cho {$name}...");

            $seedCode = Artisan::call('db:seed', [
                '--database' => $conn,
                '--class'    => \Database\Seeders\DatabaseSeeder::class,
                '--force'    => true,
            ]);

            if ($seedCode === 0) {
                $this->info("  ✓ Seeded đúng domain {$type}: {$name}");
            } else {
                $this->warn("  ! Thông báo seed {$name}: " . Artisan::output());
            }
        }
    }

    /**
     * Keep existing migration filenames stable while selecting only the domain
     * that belongs to the target database.
     */
    protected function migrationPaths(string $type): array
    {
        $systemMigrations = config('database_domains.system_migrations', []);
        $allMigrations = collect(File::files(database_path('migrations')))
            ->map(fn ($file) => $file->getFilename())
            ->filter(fn ($file) => str_ends_with($file, '.php'));

        $selected = $type === 'system'
            ? $allMigrations->filter(fn ($file) => in_array($file, $systemMigrations, true))
            : $allMigrations->reject(fn ($file) => in_array($file, $systemMigrations, true));

        return $selected
            ->sort()
            ->map(fn ($file) => 'database/migrations/' . $file)
            ->values()
            ->all();
    }
}
