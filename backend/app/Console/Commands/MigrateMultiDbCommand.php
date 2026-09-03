<?php

namespace App\Console\Commands;

use App\Models\SystemBranch;
use App\Services\TenantDatabaseService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

class MigrateMultiDbCommand extends Command
{
    /**
     * The name and signature of the console command.
     * Hỗ trợ cả `php artisan migrate:all` và `php artisan db:migrate-all`.
     */
    protected $signature = 'migrate:all 
                            {target=all : Mục tiêu migrate (all | system | tên chi nhánh như hkt1, hkt2, hkt5...)} 
                            {--force : Bỏ qua xác nhận khi chạy ở production}';

    /**
     * Các bí danh tương đương của lệnh.
     */
    protected $aliases = ['db:migrate-all'];

    /**
     * Mô tả lệnh.
     */
    protected $description = 'Chạy đúng migration domain cho System DB và TẤT CẢ các Branch DB mà không xóa dữ liệu và không chạy seeder';

    public function handle(): int
    {
        $target = strtolower((string) $this->argument('target'));
        $force = (bool) $this->option('force');

        $this->info('======================================================');
        $this->info('  🚀 PMS MULTI-DATABASE DYNAMIC MIGRATE TOOL');
        $this->info('======================================================');

        // 1. Quét toàn bộ database pms_* thực tế trên MySQL + danh sách chi nhánh
        $allDatabases = $this->discoverAllPmsDatabases();

        $this->info('  🔍 Tìm thấy ' . count($allDatabases) . ' database PMS trên MySQL:');
        foreach ($allDatabases as $dbInfo) {
            $this->line("    • {$dbInfo['db_name']} ({$dbInfo['conn']})");
        }
        $this->newLine();

        // 2. Xác định danh sách database cần migrate
        if ($target === 'all') {
            $targets = $allDatabases;
        } else {
            $matched = null;
            foreach ($allDatabases as $key => $db) {
                if (
                    $key === $target ||
                    $db['db_name'] === $target ||
                    $db['db_name'] === 'pms_' . $target ||
                    $db['conn'] === $target ||
                    $db['conn'] === 'mysql_' . $target
                ) {
                    $matched = [$key => $db];
                    break;
                }
            }

            if (!$matched) {
                $this->error("❌ Mục tiêu '{$target}' không tìm thấy trong danh sách database hệ thống.");
                return self::FAILURE;
            }
            $targets = $matched;
        }

        // 3. Thực thi migrate tuần tự (ưu tiên System trước nếu có trong danh sách)
        $hasErrors = false;

        // Migrate System trước
        if (isset($targets['system'])) {
            $success = $this->migrateSingleDb($targets['system'], $force);
            if (!$success) $hasErrors = true;
            unset($targets['system']);
        }

        // Migrate các chi nhánh
        foreach ($targets as $dbInfo) {
            $success = $this->migrateSingleDb($dbInfo, $force);
            if (!$success) $hasErrors = true;
        }

        $this->newLine();
        if ($hasErrors) {
            $this->warn('⚠️ Đã hoàn thành quá trình migrate nhưng có một số database gặp cảnh báo hoặc lỗi.');
            return self::FAILURE;
        }

        $this->info('✅ ĐÃ CHẠY MIGRATE THÀNH CÔNG CHO TẤT CẢ DATABASE!');
        return self::SUCCESS;
    }

    /**
     * Chạy migrate cho 1 database với đúng path domain tương ứng.
     */
    protected function migrateSingleDb(array $dbInfo, bool $force): bool
    {
        $conn = $dbInfo['conn'];
        $name = $dbInfo['name'];
        $type = $dbInfo['type'];

        $this->line("⏳ [{$conn}] Migrating {$name}...");

        $exitCode = Artisan::call('migrate', [
            '--database' => $conn,
            '--path'     => $this->migrationPaths($type),
            '--force'    => $force,
        ]);

        $output = trim(Artisan::output());
        if ($output) {
            $this->line($output);
        }

        if ($exitCode !== self::SUCCESS) {
            $this->error("  ✗ Lỗi migrate {$name}");
            return false;
        }

        $this->info("  ✓ Hoàn tất migrate: {$name}");
        return true;
    }

    /**
     * Quét và đăng ký dynamic connections cho tất cả database `pms_%` trên MySQL + bảng system_branches.
     */
    protected function discoverAllPmsDatabases(): array
    {
        $databases = [];

        // 1. Luôn thêm System DB
        $databases['system'] = [
            'conn'    => 'mysql_system',
            'db_name' => 'pms_system',
            'name'    => 'System DB (pms_system)',
            'type'    => 'system',
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
                    'conn'    => $connName,
                    'db_name' => $dbName,
                    'name'    => "Chi nhánh DB ({$dbName})",
                    'type'    => 'branch',
                ];
            }
        } catch (\Throwable $e) {
            // Fallback sang cấu hình tĩnh nếu có lỗi kết nối
            $staticBranches = ['hkt1', 'hkt2', 'hkt3', 'hkt4'];
            foreach ($staticBranches as $code) {
                $dbName = 'pms_' . $code;
                $connName = 'mysql_' . $code;
                TenantDatabaseService::registerDynamicConnection($connName, $dbName);
                $databases[$code] = [
                    'conn'    => $connName,
                    'db_name' => $dbName,
                    'name'    => "Chi nhánh ({$dbName})",
                    'type'    => 'branch',
                ];
            }
        }

        // 3. Đọc thêm từ bảng system_branches
        try {
            $branches = SystemBranch::all();
            foreach ($branches as $branch) {
                $dbName = TenantDatabaseService::getDatabaseName($branch->code);
                $connName = TenantDatabaseService::getConnectionName($branch->code);
                $slug = preg_replace('/^pms_/', '', $dbName);

                TenantDatabaseService::registerDynamicConnection($connName, $dbName);

                $databases[$slug] = [
                    'conn'    => $connName,
                    'db_name' => $dbName,
                    'name'    => "Chi nhánh {$branch->name} ({$dbName})",
                    'type'    => 'branch',
                ];
            }
        } catch (\Throwable $e) {
            // Bỏ qua nếu bảng system_branches chưa sẵn sàng
        }

        return $databases;
    }

    /**
     * Phân tách migration theo domain: System hay Branch.
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
