<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;

class ResetMultiDbCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'db:reset-all 
                            {target=all : Mục tiêu reset (all | system | hkt1 | hkt2 | hkt3 | hkt4)} 
                            {--seed : Tự động seed dữ liệu mẫu sau khi migrate}
                            {--seed-all : Seed dữ liệu mẫu cho tất cả các chi nhánh}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Reset (migrate:fresh) toàn bộ hoặc riêng lẻ từng Database trong hệ thống Multi-DB PMS';

    /**
     * Danh sách database connections
     */
    protected array $databases = [
        'system' => ['conn' => 'mysql_system', 'name' => 'System DB (pms_system)', 'type' => 'system'],
        'hkt1'   => ['conn' => 'mysql_hkt1',   'name' => 'Chi nhánh HKT 1 - Nha Trang (pms_hkt1)', 'type' => 'branch'],
        'hkt2'   => ['conn' => 'mysql_hkt2',   'name' => 'Chi nhánh HKT 2 - TP.HCM (pms_hkt2)',   'type' => 'branch'],
        'hkt3'   => ['conn' => 'mysql_hkt3',   'name' => 'Chi nhánh HKT 3 - Đà Nẵng (pms_hkt3)',  'type' => 'branch'],
        'hkt4'   => ['conn' => 'mysql_hkt4',   'name' => 'Chi nhánh HKT 4 - Hà Nội (pms_hkt4)',   'type' => 'branch'],
    ];

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $target = strtolower($this->argument('target'));
        $seed = $this->option('seed');
        $seedAll = $this->option('seed-all');

        $this->info('======================================================');
        $this->info('  🚀 PMS MULTI-DATABASE RESET TOOL');
        $this->info('======================================================');

        if ($target === 'all') {
            $this->warn('Đang thực hiện Reset TOÀN BỘ 5 Database...');
            $this->newLine();

            foreach ($this->databases as $key => $db) {
                $shouldSeed = ($key === 'hkt1' && $seed) || $seedAll;
                $this->resetSingleDb($db['conn'], $db['name'], $shouldSeed);
            }

            $this->newLine();
            $this->info('✅ ĐÃ RESET TOÀN BỘ CÁC DATABASE THÀNH CÔNG!');
            return Command::SUCCESS;
        }

        if (array_key_exists($target, $this->databases)) {
            $db = $this->databases[$target];
            $this->warn("Đang thực hiện Reset riêng: {$db['name']}...");
            $this->newLine();

            $this->resetSingleDb($db['conn'], $db['name'], $seed || $seedAll);

            $this->newLine();
            $this->info("✅ ĐÃ RESET THÀNH CÔNG: {$db['name']}!");
            return Command::SUCCESS;
        }

        $this->error("❌ Mục tiêu '{$target}' không hợp lệ. Các lựa chọn hợp lệ: all, system, hkt1, hkt2, hkt3, hkt4");
        return Command::FAILURE;
    }

    /**
     * Thực hiện migrate:fresh và seed cho 1 connection
     */
    protected function resetSingleDb(string $conn, string $name, bool $shouldSeed): void
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
            $seedCode = Artisan::call('db:seed', [
                '--database' => $conn,
                '--force'    => true,
            ]);

            if ($seedCode === 0) {
                $this->info("  ✓ Seeded data thành công: {$name}");
            } else {
                $this->warn("  ! Thông báo seed {$name}: " . Artisan::output());
            }
        }
    }
}
