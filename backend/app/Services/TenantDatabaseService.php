<?php

namespace App\Services;

use App\Models\SystemBranch;
use App\Models\User;
use App\Models\UserBranch;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use PDO;

class TenantDatabaseService
{
    /**
     * Sinh tên Database theo mã chi nhánh.
     * Ví dụ: HKT5 -> pms_hkt5, CANTHO -> pms_cantho
     */
    public static function getDatabaseName(string $branchCode): string
    {
        $clean = strtolower(preg_replace('/[^a-zA-Z0-9_]/', '', $branchCode));
        return 'pms_' . $clean;
    }

    /**
     * Sinh tên Connection theo mã chi nhánh.
     * Ví dụ: HKT5 -> mysql_hkt5
     */
    public static function getConnectionName(string $branchCode): string
    {
        $clean = strtolower(preg_replace('/[^a-zA-Z0-9_]/', '', $branchCode));
        return 'mysql_' . $clean;
    }

    /**
     * Đăng ký Dynamic Connection vào Runtime Configuration của Laravel.
     */
    public static function registerDynamicConnection(string $connName, string $dbName): array
    {
        $base = Config::get('database.connections.mysql') 
            ?? Config::get('database.connections.mysql_system') 
            ?? [
                'driver' => 'mysql',
                'host' => env('DB_HOST', '127.0.0.1'),
                'port' => env('DB_PORT', '3306'),
                'username' => env('DB_USERNAME', 'root'),
                'password' => env('DB_PASSWORD', ''),
                'charset' => 'utf8mb4',
                'collation' => 'utf8mb4_unicode_ci',
                'prefix' => '',
                'strict' => true,
                'engine' => null,
            ];

        $dynamicConfig = array_merge($base, [
            'database' => $dbName,
        ]);

        Config::set("database.connections.{$connName}", $dynamicConfig);

        return $dynamicConfig;
    }

    /**
     * Tự động tạo Database vật lý trên MySQL nếu chưa tồn tại.
     */
    public static function createDatabaseIfNotExists(string $dbName): bool
    {
        try {
            $host = env('DB_HOST', '127.0.0.1');
            $port = env('DB_PORT', '3306');
            $username = env('DB_USERNAME', 'root');
            $password = env('DB_PASSWORD', '');
            $socket = env('DB_SOCKET', '');

            $dsn = $socket
                ? "mysql:unix_socket={$socket}"
                : "mysql:host={$host};port={$port}";

            $pdo = new PDO($dsn, $username, $password, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            ]);

            $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$dbName}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;");
            return true;
        } catch (\Throwable $e) {
            Log::warning("TenantDatabaseService: Không thể tự tạo database {$dbName} qua PDO: " . $e->getMessage());
            // Fallback qua DB connection hiện tại
            try {
                DB::connection('mysql_system')->statement("CREATE DATABASE IF NOT EXISTS `{$dbName}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;");
                return true;
            } catch (\Throwable $e2) {
                Log::error("TenantDatabaseService fallback error: " . $e2->getMessage());
                return false;
            }
        }
    }

    /**
     * Chạy toàn bộ Migration khởi tạo bảng cho Database chi nhánh mới.
     */
    public static function migrateBranchDatabase(string $connName): bool
    {
        try {
            DB::purge($connName);
            Artisan::call('migrate', [
                '--database' => $connName,
                '--path' => 'database/migrations',
                '--force' => true,
            ]);
            return true;
        } catch (\Throwable $e) {
            Log::error("TenantDatabaseService: Lỗi migrate cho {$connName}: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Nạp dữ liệu mẫu ban đầu cho Database chi nhánh mới.
     */
    public static function seedBranchDatabase(string $connName): bool
    {
        try {
            DB::purge($connName);
            Artisan::call('db:seed', [
                '--database' => $connName,
                '--class' => 'DatabaseSeeder',
                '--force' => true,
            ]);
            return true;
        } catch (\Throwable $e) {
            Log::error("TenantDatabaseService: Lỗi seeder cho {$connName}: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Khởi tạo toàn diện (Provisioning) 1 chi nhánh mới:
     * 1. Tạo MySQL Database
     * 2. Đăng ký connection
     * 3. Migrate bảng
     * 4. Seed dữ liệu mẫu
     * 5. Gán quyền cho Super Admins
     */
    public static function provisionBranch(SystemBranch $branch, bool $withSeed = true): array
    {
        $dbName = self::getDatabaseName($branch->code);
        $connName = self::getConnectionName($branch->code);

        // 1. Tạo Database
        $dbCreated = self::createDatabaseIfNotExists($dbName);

        // 2. Đăng ký connection vào Runtime
        self::registerDynamicConnection($connName, $dbName);

        // 3. Migrate bảng
        $migrated = self::migrateBranchDatabase($connName);

        // 4. Seed dữ liệu mặc định
        $seeded = false;
        if ($withSeed && $migrated) {
            $seeded = self::seedBranchDatabase($connName);
        }

        // 5. Cập nhật connection vào bản ghi chi nhánh
        $branch->update([
            'db_connection' => $connName,
            'organization_type' => $branch->organization_type ?: 'PMS',
        ]);

        // 6. Gán quyền chi nhánh này cho các tài khoản Super Admin
        try {
            $superAdmins = User::whereHas('roles', fn($q) => $q->where('code', 'super_admin'))->get();
            foreach ($superAdmins as $admin) {
                UserBranch::firstOrCreate(
                    ['user_id' => $admin->id, 'system_branch_id' => $branch->id],
                    ['is_primary' => false]
                );
            }
        } catch (\Throwable $e) {
            Log::warning("TenantDatabaseService: Gán quyền super admin cho chi nhánh {$branch->code} thất bại: " . $e->getMessage());
        }

        return [
            'success' => $migrated,
            'database' => $dbName,
            'connection' => $connName,
            'db_created' => $dbCreated,
            'migrated' => $migrated,
            'seeded' => $seeded,
        ];
    }
}
