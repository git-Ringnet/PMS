<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;

class MigrateMultiDbCommand extends Command
{
    protected $signature = 'db:migrate-all {target=all : all | system | hkt1 | hkt2 | hkt3 | hkt4} {--force}';

    protected $description = 'Chạy đúng migration domain cho System DB và từng Branch DB mà không reset dữ liệu';

    protected array $databases = [
        'system' => ['connection' => 'mysql_system', 'type' => 'system'],
        'hkt1' => ['connection' => 'mysql_hkt1', 'type' => 'branch'],
        'hkt2' => ['connection' => 'mysql_hkt2', 'type' => 'branch'],
        'hkt3' => ['connection' => 'mysql_hkt3', 'type' => 'branch'],
        'hkt4' => ['connection' => 'mysql_hkt4', 'type' => 'branch'],
    ];

    public function handle(): int
    {
        $target = strtolower((string) $this->argument('target'));
        $targets = $target === 'all'
            ? $this->databases
            : (isset($this->databases[$target]) ? [$target => $this->databases[$target]] : []);

        if (!$targets) {
            $this->error("Mục tiêu '{$target}' không hợp lệ.");
            return self::FAILURE;
        }

        foreach ($targets as $name => $database) {
            $this->line("Migrating {$name} ({$database['connection']})...");
            $exitCode = Artisan::call('migrate', [
                '--database' => $database['connection'],
                '--path' => $this->migrationPaths($database['type']),
                '--force' => (bool) $this->option('force'),
            ]);

            $this->output->write(Artisan::output());

            if ($exitCode !== self::SUCCESS) {
                return self::FAILURE;
            }
        }

        return self::SUCCESS;
    }

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
