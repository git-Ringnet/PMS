<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
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
                            {target=all : Mục tiêu reset (all | system | hkt1 | hkt2 | hkt3 | hkt4)} 
                            {--branch= : Chỉ định chi nhánh (tùy chọn thay cho target)} 
                            {--seed : Tự động seed dữ liệu mẫu sau khi migrate}
                            {--seed-all : Seed dữ liệu mẫu cho tất cả các chi nhánh}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Tự động tạo database nếu chưa có và Reset (migrate:fresh) 5 Database trong hệ thống Multi-DB PMS';

    /**
     * Danh sách database connections
     */
    protected array $databases = [
        'system' => ['conn' => 'mysql_system', 'db_name' => 'pms_system', 'name' => 'System DB (pms_system)', 'type' => 'system'],
        'hkt1'   => ['conn' => 'mysql_hkt1',   'db_name' => 'pms_hkt1',   'name' => 'Chi nhánh HKT 1 - Nha Trang (pms_hkt1)', 'type' => 'branch'],
        'hkt2'   => ['conn' => 'mysql_hkt2',   'db_name' => 'pms_hkt2',   'name' => 'Chi nhánh HKT 2 - TP.HCM (pms_hkt2)',   'type' => 'branch'],
        'hkt3'   => ['conn' => 'mysql_hkt3',   'db_name' => 'pms_hkt3',   'name' => 'Chi nhánh HKT 3 - Đà Nẵng (pms_hkt3)',  'type' => 'branch'],
        'hkt4'   => ['conn' => 'mysql_hkt4',   'db_name' => 'pms_hkt4',   'name' => 'Chi nhánh HKT 4 - Hà Nội (pms_hkt4)',   'type' => 'branch'],
    ];

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $target = strtolower($this->option('branch') ?: $this->argument('target'));
        $seed = $this->option('seed');
        $seedAll = $this->option('seed-all');

        $this->info('======================================================');
        $this->info('  🚀 PMS MULTI-DATABASE RESET TOOL');
        $this->info('======================================================');

        // 1. Tự động kiểm tra và TẠO MỚI database trên MySQL nếu chưa tồn tại
        $this->ensureDatabasesExist();

        if ($target === 'all') {
            $this->warn('Đang thực hiện Reset TOÀN BỘ 5 Database...');
            $this->newLine();

            foreach ($this->databases as $key => $db) {
                $shouldSeed = ($key === 'system') || ($key === 'hkt1' && $seed) || $seedAll;
                $this->resetSingleDb($db['conn'], $db['name'], $shouldSeed, $db['type']);
            }

            $this->newLine();
            $this->info('✅ ĐÃ RESET TOÀN BỘ CÁC DATABASE THÀNH CÔNG!');
            return Command::SUCCESS;
        }

        if (array_key_exists($target, $this->databases)) {
            $db = $this->databases[$target];
            $this->warn("Đang thực hiện Reset riêng: {$db['name']}...");
            $this->newLine();

            $shouldSeed = ($db['type'] === 'system') || $seed || $seedAll;
            $this->resetSingleDb($db['conn'], $db['name'], $shouldSeed, $db['type']);

            $this->newLine();
            $this->info("✅ ĐÃ RESET THÀNH CÔNG: {$db['name']}!");
            return Command::SUCCESS;
        }

        $this->error("❌ Mục tiêu '{$target}' không hợp lệ. Các lựa chọn hợp lệ: all, system, hkt1, hkt2, hkt3, hkt4");
        return Command::FAILURE;
    }

    /**
     * Tự động kết nối MySQL và tạo Database nếu chưa tồn tại
     */
    protected function ensureDatabasesExist(): void
    {
        $host = config('database.connections.mysql.host', '127.0.0.1');
        $port = config('database.connections.mysql.port', '3306');
        $user = config('database.connections.mysql.username', 'root');
        $pass = config('database.connections.mysql.password', '');

        try {
            $pdo = new PDO("mysql:host={$host};port={$port}", $user, $pass, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            ]);

            foreach ($this->databases as $key => $db) {
                $dbConfigName = config("database.connections.{$db['conn']}.database") ?: $db['db_name'];
                $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$dbConfigName}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;");
            }
            $this->info('  ✓ Đã kiểm tra và đảm bảo 5 Database sẵn sàng trên MySQL.');
        } catch (Exception $e) {
            $this->warn("  ⚠️  Không thể tự tạo database qua PDO ({$e->getMessage()}). Vui lòng đảm bảo các database đã được tạo trên MySQL.");
        }
    }

    /**
     * Thực hiện migrate:fresh và seed cho 1 connection
     */
    protected function resetSingleDb(string $conn, string $name, bool $shouldSeed, string $type = 'branch'): void
    {
        $this->line("⏳ [{$conn}] Bắt đầu migrate:fresh trên {$name}...");

        $exitCode = Artisan::call('migrate:fresh', [
            '--database' => $conn,
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

            if ($type === 'system') {
                Artisan::call('db:seed', ['--database' => $conn, '--class' => 'SystemBranchSeeder', '--force' => true]);
                Artisan::call('db:seed', ['--database' => $conn, '--class' => 'InfoBusinessSeeder', '--force' => true]);
                Artisan::call('db:seed', ['--database' => $conn, '--class' => 'RolePermissionSeeder', '--force' => true]);
                Artisan::call('db:seed', ['--database' => $conn, '--class' => 'ModuleSeeder', '--force' => true]);
                Artisan::call('db:seed', ['--database' => $conn, '--class' => 'DepartmentSeeder', '--force' => true]);
                $this->info("  ✓ Seeded branches, info_business, roles, permissions, modules, departments thành công cho System!");
            } else {
                $seedCode = Artisan::call('db:seed', [
                    '--database' => $conn,
                    '--force'    => true,
                ]);

                if ($seedCode === 0) {
                    $this->info("  ✓ Seeded operational data thành công: {$name}");
                } else {
                    $this->warn("  ! Thông báo seed {$name}: " . Artisan::output());
                }
            }
        }
    }
}
