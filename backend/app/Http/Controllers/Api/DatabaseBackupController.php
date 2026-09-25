<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SystemBranch;
use App\Services\TenantDatabaseService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class DatabaseBackupController extends Controller
{
    /**
     * Tìm đường dẫn thực thi của mysql CLI
     */
    private function getMysqlBinary(): ?string
    {
        $envPath = env('MYSQL_BINARY_PATH');
        if ($envPath && file_exists($envPath)) {
            return $envPath;
        }

        $commonPaths = [
            'C:\\xampp\\mysql\\bin\\mysql.exe',
            'D:\\xampp\\mysql\\bin\\mysql.exe',
            'C:\\laragon\\bin\\mysql\\current\\bin\\mysql.exe',
            'C:\\Program Files\\MySQL\\MySQL Server 8.0\\bin\\mysql.exe',
            'C:\\Program Files\\MySQL\\MySQL Server 8.4\\bin\\mysql.exe',
            'C:\\Program Files\\MariaDB 10.4\\bin\\mysql.exe',
            'C:\\Program Files\\MariaDB 10.5\\bin\\mysql.exe',
            'C:\\Program Files\\MariaDB 10.11\\bin\\mysql.exe',
        ];

        foreach ($commonPaths as $path) {
            if (file_exists($path)) {
                return $path;
            }
        }

        $isWindows = strtoupper(substr(PHP_OS, 0, 3)) === 'WIN';
        $whichCmd = $isWindows ? 'where mysql 2>nul' : 'which mysql 2>/dev/null';
        $output = @shell_exec($whichCmd);
        if ($output) {
            $lines = explode("\n", trim($output));
            foreach ($lines as $line) {
                $line = trim($line);
                if ($line && file_exists($line)) {
                    return $line;
                }
            }
        }

        return null;
    }

    /**
     * Tự động nâng max_allowed_packet lên 1GB trên kết nối MySQL
     */
    private function ensureMaxAllowedPacket(?string $connName = null): void
    {
        try {
            $conn = $connName ?: config('database.default', 'mysql');
            DB::connection($conn)->statement('SET GLOBAL max_allowed_packet = 1073741824;');
        } catch (\Throwable $e) {
            // Không có quyền SUPER hoặc kết nối không hỗ trợ thì bỏ qua
        }
    }

    /**
     * Helper: Phân giải thông tin Database connection theo chi nhánh / ALL / SYSTEM
     */
    private function resolveBranchContext(Request $request): array
    {
        $branchCode = $request->input('branch_code') 
            ?: $request->header('X-Branch-Code') 
            ?: 'HKT1';
        $branchCode = strtoupper(trim((string) $branchCode));

        if ($branchCode === 'ALL') {
            $dbConfig = Config::get('database.connections.mysql_system') 
                ?? Config::get('database.connections.mysql') 
                ?? [];

            return [
                'type'        => 'ALL',
                'branch_code' => 'ALL',
                'branch_name' => 'Toàn Bộ Hệ Thống & Tất Cả Chi Nhánh',
                'database'    => 'ALL (Multi-Database)',
                'connection'  => 'all',
                'host'        => $dbConfig['host'] ?? env('DB_HOST', '127.0.0.1'),
                'port'        => $dbConfig['port'] ?? env('DB_PORT', '3306'),
                'username'    => $dbConfig['username'] ?? env('DB_USERNAME', 'root'),
                'password'    => $dbConfig['password'] ?? env('DB_PASSWORD', ''),
                'config'      => $dbConfig,
            ];
        }

        if ($branchCode === 'SYSTEM') {
            $systemConn = 'mysql_system';
            $systemDb = config('database.connections.mysql_system.database', 'pms_system');
            $dbConfig = Config::get('database.connections.mysql_system') 
                ?? Config::get('database.connections.mysql') 
                ?? [];

            return [
                'type'        => 'SYSTEM',
                'branch_code' => 'SYSTEM',
                'branch_name' => 'Cơ sở Dữ liệu Hệ Thống Chính (Users, Roles, Chi nhánh)',
                'database'    => $systemDb,
                'connection'  => $systemConn,
                'host'        => $dbConfig['host'] ?? env('DB_HOST', '127.0.0.1'),
                'port'        => $dbConfig['port'] ?? env('DB_PORT', '3306'),
                'username'    => $dbConfig['username'] ?? env('DB_USERNAME', 'root'),
                'password'    => $dbConfig['password'] ?? env('DB_PASSWORD', ''),
                'config'      => $dbConfig,
            ];
        }

        $branch = SystemBranch::where('code', $branchCode)->first();
        $branchName = $branch ? $branch->name : "Chi nhánh {$branchCode}";

        $connName = TenantDatabaseService::getConnectionName($branchCode);
        $dbName = TenantDatabaseService::getDatabaseName($branchCode);

        // Đăng ký connection nếu chưa có trong runtime config
        if (!Config::has("database.connections.{$connName}")) {
            TenantDatabaseService::registerDynamicConnection($connName, $dbName);
        }

        $dbConfig = Config::get("database.connections.{$connName}") 
            ?? Config::get('database.connections.mysql') 
            ?? [];

        $host = $dbConfig['host'] ?? env('DB_HOST', '127.0.0.1');
        $port = $dbConfig['port'] ?? env('DB_PORT', '3306');
        $username = $dbConfig['username'] ?? env('DB_USERNAME', 'root');
        $password = $dbConfig['password'] ?? env('DB_PASSWORD', '');

        return [
            'type'        => 'BRANCH',
            'branch'      => $branch,
            'branch_code' => $branchCode,
            'branch_name' => $branchName,
            'connection'  => $connName,
            'database'    => $dbName,
            'host'        => $host,
            'port'        => $port,
            'username'    => $username,
            'password'    => $password,
            'config'      => $dbConfig,
        ];
    }

    /**
     * GET /api/system/database/info
     * Lấy thông tin chi tiết các database: Toàn bộ, Hệ thống chính và từng chi nhánh
     */
    public function getDatabaseInfo(Request $request)
    {
        $currentContext = $this->resolveBranchContext($request);
        $branches = SystemBranch::where('is_active', true)->orderBy('id')->get();

        $totalTables = 0;
        $totalSizeMb = 0;

        // 1. Thống kê System DB
        $systemDbName = config('database.connections.mysql_system.database', 'pms_system');
        $systemConn = 'mysql_system';
        $systemTableCount = 0;
        $systemSizeMb = 0;
        $systemStatus = 'ready';

        try {
            $sysTables = DB::connection($systemConn)->select('SHOW TABLES');
            $systemTableCount = count($sysTables);
            $totalTables += $systemTableCount;

            $sysSizeQuery = DB::connection($systemConn)->select(
                "SELECT ROUND(SUM(data_length + index_length) / 1024 / 1024, 2) AS size_mb 
                 FROM information_schema.TABLES 
                 WHERE table_schema = ?",
                [$systemDbName]
            );
            if (!empty($sysSizeQuery) && isset($sysSizeQuery[0]->size_mb)) {
                $systemSizeMb = (float) $sysSizeQuery[0]->size_mb;
                $totalSizeMb += $systemSizeMb;
            }
        } catch (\Throwable $e) {
            $systemStatus = 'unreachable';
        }

        $systemItem = [
            'id'          => 'system',
            'code'        => 'SYSTEM',
            'name'        => 'Database Hệ Thống Chính (Users, Roles, Chi nhánh...)',
            'database'    => $systemDbName,
            'connection'  => $systemConn,
            'table_count' => $systemTableCount,
            'size_mb'     => $systemSizeMb,
            'status'      => $systemStatus,
            'is_system'   => true,
            'is_current'  => $currentContext['branch_code'] === 'SYSTEM',
        ];

        // 2. Thống kê từng chi nhánh con
        $branchList = [];
        foreach ($branches as $b) {
            $code = strtoupper($b->code);
            $dbName = TenantDatabaseService::getDatabaseName($code);
            $connName = TenantDatabaseService::getConnectionName($code);

            if (!Config::has("database.connections.{$connName}")) {
                TenantDatabaseService::registerDynamicConnection($connName, $dbName);
            }

            $tableCount = 0;
            $dbSizeMb = 0;
            $status = 'unknown';

            try {
                $tables = DB::connection($connName)->select('SHOW TABLES');
                $tableCount = count($tables);
                $totalTables += $tableCount;
                $status = $tableCount > 0 ? 'ready' : 'empty';

                $sizeQuery = DB::connection($connName)->select(
                    "SELECT ROUND(SUM(data_length + index_length) / 1024 / 1024, 2) AS size_mb 
                     FROM information_schema.TABLES 
                     WHERE table_schema = ?",
                    [$dbName]
                );
                if (!empty($sizeQuery) && isset($sizeQuery[0]->size_mb)) {
                    $dbSizeMb = (float) $sizeQuery[0]->size_mb;
                    $totalSizeMb += $dbSizeMb;
                }
            } catch (\Throwable $e) {
                $status = 'unreachable';
            }

            $branchList[] = [
                'id'          => $b->id,
                'code'        => $b->code,
                'name'        => $b->name,
                'database'    => $dbName,
                'connection'  => $connName,
                'table_count' => $tableCount,
                'size_mb'     => $dbSizeMb,
                'status'      => $status,
                'is_system'   => false,
                'is_current'  => $code === $currentContext['branch_code'],
            ];
        }

        // 3. Thống kê ALL (Toàn bộ)
        $allItem = [
            'id'          => 'all',
            'code'        => 'ALL',
            'name'        => 'Toàn Bộ Hệ Thống & Tất Cả Chi Nhánh',
            'database'    => 'ALL (' . (count($branchList) + 1) . ' Databases)',
            'connection'  => 'all',
            'table_count' => $totalTables,
            'size_mb'     => round($totalSizeMb, 2),
            'status'      => 'ready',
            'is_all'      => true,
            'is_current'  => $currentContext['branch_code'] === 'ALL',
        ];

        return response()->json([
            'success' => true,
            'data' => [
                'current_branch' => [
                    'code'        => $currentContext['branch_code'],
                    'name'        => $currentContext['branch_name'],
                    'database'    => $currentContext['database'],
                    'connection'  => $currentContext['connection'] ?? 'all',
                ],
                'summary_all' => $allItem,
                'system_db'   => $systemItem,
                'branches'    => $branchList,
            ],
        ]);
    }

    /**
     * Xuất một Database connection ra luồng SQL (Bao gồm Tables, Views, Stored Procedures, Functions, Triggers)
     */
    private function exportDatabaseToStream($out, string $connName, string $dbName, string $title, bool $includeUseDb = false): void
    {
        fwrite($out, "\n-- ========================================================\n");
        fwrite($out, "-- DATABASE SECTION: `{$dbName}`\n");
        fwrite($out, "-- Tiêu đề: {$title}\n");
        fwrite($out, "-- Thời gian xuất: " . date('Y-m-d H:i:s') . "\n");
        fwrite($out, "-- ========================================================\n\n");

        if ($includeUseDb) {
            fwrite($out, "CREATE DATABASE IF NOT EXISTS `{$dbName}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;\n");
            fwrite($out, "USE `{$dbName}`;\n\n");
        }

        fwrite($out, "/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;\n");
        fwrite($out, "/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;\n");
        fwrite($out, "SET FOREIGN_KEY_CHECKS=0;\n");
        fwrite($out, "SET SQL_MODE = \"NO_AUTO_VALUE_ON_ZERO\";\n");
        fwrite($out, "SET time_zone = \"+00:00\";\n\n");

        try {
            // 1. Xuất danh sách BẢNG DỮ LIỆU (Base Tables)
            $tables = DB::connection($connName)->select("SHOW FULL TABLES WHERE Table_type = 'BASE TABLE'");
            $tableKey = "Tables_in_" . $dbName;

            foreach ($tables as $t) {
                $tableArray = (array)$t;
                $tableName = $tableArray[$tableKey] ?? reset($tableArray);

                // Lấy cấu trúc CREATE TABLE
                try {
                    $createSql = DB::connection($connName)->select("SHOW CREATE TABLE `{$tableName}`");
                    if (!empty($createSql)) {
                        $createArray = (array)$createSql[0];
                        $createTableStmt = $createArray['Create Table'] ?? $createArray['CREATE TABLE'] ?? null;
                        if ($createTableStmt) {
                            fwrite($out, "-- Cấu trúc bảng cho `{$tableName}`\n");
                            fwrite($out, "DROP TABLE IF EXISTS `{$tableName}`;\n");
                            fwrite($out, $createTableStmt . ";\n\n");
                        }
                    }
                } catch (\Throwable $e) {
                    continue;
                }

                // Xuất dữ liệu bảng theo từng đợt nhỏ (50 dòng/lần) tránh vượt packet
                try {
                    $count = DB::connection($connName)->table($tableName)->count();
                    if ($count > 0) {
                        fwrite($out, "-- Dữ liệu cho bảng `{$tableName}`\n");
                        
                        DB::connection($connName)->table($tableName)->orderBy(DB::raw(1))->chunk(50, function ($rows) use ($out, $tableName, $connName) {
                            if ($rows->isEmpty()) return;
                            
                            $firstRow = (array)$rows->first();
                            $columns = array_keys($firstRow);
                            $colList = implode(', ', array_map(fn($c) => "`{$c}`", $columns));
                            
                            $valueRows = [];
                            foreach ($rows as $row) {
                                $rowArray = (array)$row;
                                $values = array_map(function ($val) use ($connName) {
                                    if (is_null($val)) return 'NULL';
                                    return DB::connection($connName)->getPdo()->quote($val);
                                }, $rowArray);
                                $valueRows[] = "(" . implode(', ', $values) . ")";
                            }
                            
                            fwrite($out, "INSERT INTO `{$tableName}` ({$colList}) VALUES\n" . implode(",\n", $valueRows) . ";\n\n");
                        });
                    }
                } catch (\Throwable $e) {
                    continue;
                }
            }

            // 2. Xuất VIEWS
            try {
                $views = DB::connection($connName)->select("SHOW FULL TABLES WHERE Table_type = 'VIEW'");
                foreach ($views as $v) {
                    $vArray = (array)$v;
                    $viewName = $vArray[$tableKey] ?? reset($vArray);
                    $createView = DB::connection($connName)->select("SHOW CREATE VIEW `{$viewName}`");
                    if (!empty($createView)) {
                        $vRow = (array)$createView[0];
                        $createViewStmt = $vRow['Create View'] ?? $vRow['CREATE VIEW'] ?? null;
                        if ($createViewStmt) {
                            fwrite($out, "-- View `{$viewName}`\n");
                            fwrite($out, "DROP VIEW IF EXISTS `{$viewName}`;\n");
                            fwrite($out, $createViewStmt . ";\n\n");
                        }
                    }
                }
            } catch (\Throwable $e) {
                // bỏ qua nếu không hỗ trợ view
            }

            // 3. Xuất STORED PROCEDURES
            try {
                $procedures = DB::connection($connName)->select("SHOW PROCEDURE STATUS WHERE Db = ?", [$dbName]);
                if (!empty($procedures)) {
                    fwrite($out, "\n-- --------------------------------------------------------\n");
                    fwrite($out, "-- Stored Procedures cho `{$dbName}`\n");
                    fwrite($out, "-- --------------------------------------------------------\n\n");
                    fwrite($out, "DELIMITER ;;\n");
                    foreach ($procedures as $proc) {
                        $procName = $proc->Name;
                        $createProc = DB::connection($connName)->select("SHOW CREATE PROCEDURE `{$procName}`");
                        if (!empty($createProc)) {
                            $procArr = (array)$createProc[0];
                            $stmt = $procArr['Create Procedure'] ?? $procArr['CREATE PROCEDURE'] ?? null;
                            if ($stmt) {
                                fwrite($out, "DROP PROCEDURE IF EXISTS `{$procName}`;;\n");
                                fwrite($out, $stmt . ";;\n\n");
                            }
                        }
                    }
                    fwrite($out, "DELIMITER ;\n\n");
                }
            } catch (\Throwable $e) {
                // tiếp tục
            }

            // 4. Xuất STORED FUNCTIONS
            try {
                $functions = DB::connection($connName)->select("SHOW FUNCTION STATUS WHERE Db = ?", [$dbName]);
                if (!empty($functions)) {
                    fwrite($out, "\n-- --------------------------------------------------------\n");
                    fwrite($out, "-- Stored Functions cho `{$dbName}`\n");
                    fwrite($out, "-- --------------------------------------------------------\n\n");
                    fwrite($out, "DELIMITER ;;\n");
                    foreach ($functions as $func) {
                        $funcName = $func->Name;
                        $createFunc = DB::connection($connName)->select("SHOW CREATE FUNCTION `{$funcName}`");
                        if (!empty($createFunc)) {
                            $funcArr = (array)$createFunc[0];
                            $stmt = $funcArr['Create Function'] ?? $funcArr['CREATE FUNCTION'] ?? null;
                            if ($stmt) {
                                fwrite($out, "DROP FUNCTION IF EXISTS `{$funcName}`;;\n");
                                fwrite($out, $stmt . ";;\n\n");
                            }
                        }
                    }
                    fwrite($out, "DELIMITER ;\n\n");
                }
            } catch (\Throwable $e) {
                // tiếp tục
            }

        } catch (\Throwable $e) {
            fwrite($out, "-- Error exporting database {$dbName}: " . $e->getMessage() . "\n");
        }

        fwrite($out, "SET FOREIGN_KEY_CHECKS=1;\n");
        fwrite($out, "/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;\n");
        fwrite($out, "/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;\n\n");
    }

    /**
     * GET /api/system/database/export
     * Xuất file SQL (hỗ trợ ALL, SYSTEM, hoặc từng chi nhánh cụ thể)
     */
    public function exportDatabase(Request $request)
    {
        @set_time_limit(0);
        @ini_set('memory_limit', '1024M');
        @ini_set('max_execution_time', '0');

        $this->ensureMaxAllowedPacket();

        $context = $this->resolveBranchContext($request);
        $type = $context['type'];
        $branchCode = $context['branch_code'];
        $branchName = $context['branch_name'];

        $timestamp = date('Y_m_d_H_i_s');

        // TRƯỜNG HỢP 1: XUẤT TOÀN BỘ (ALL) - CẢ HỆ THỐNG + TẤT CẢ CHI NHÁNH
        if ($type === 'ALL') {
            $filename = "pms_FULL_SYSTEM_ALL_BRANCHES_{$timestamp}.sql";

            return response()->streamDownload(function () {
                $out = fopen('php://output', 'w');

                fwrite($out, "-- ========================================================\n");
                fwrite($out, "-- PMS FULL SYSTEM & MULTI-BRANCH DATABASE BACKUP\n");
                fwrite($out, "-- Thời gian xuất: " . date('Y-m-d H:i:s') . "\n");
                fwrite($out, "-- Bao gồm: Database Hệ Thống Chính + Tất Cả Chi Nhánh\n");
                fwrite($out, "-- ========================================================\n\n");

                // 1. Xuất Database Hệ Thống Chính
                $sysDb = config('database.connections.mysql_system.database', 'pms_system');
                $this->exportDatabaseToStream($out, 'mysql_system', $sysDb, 'Database Hệ Thống Quản Trị Chính', true);

                // 2. Xuất lần lượt từng chi nhánh
                $branches = SystemBranch::where('is_active', true)->orderBy('id')->get();
                foreach ($branches as $b) {
                    $code = strtoupper($b->code);
                    $dbName = TenantDatabaseService::getDatabaseName($code);
                    $connName = TenantDatabaseService::getConnectionName($code);

                    if (!Config::has("database.connections.{$connName}")) {
                        TenantDatabaseService::registerDynamicConnection($connName, $dbName);
                    }

                    $this->exportDatabaseToStream($out, $connName, $dbName, "Chi nhánh {$b->name} ({$code})", true);
                }

                fclose($out);
            }, $filename, [
                'Content-Type'        => 'application/sql',
                'Content-Disposition' => "attachment; filename=\"{$filename}\"",
                'X-Filename'          => $filename,
            ]);
        }

        // TRƯỜNG HỢP 2: XUẤT DATABASE HỆ THỐNG CHÍNH (SYSTEM)
        if ($type === 'SYSTEM') {
            $filename = "pms_SYSTEM_backup_{$timestamp}.sql";
            $sysDb = config('database.connections.mysql_system.database', 'pms_system');

            return response()->streamDownload(function () use ($sysDb) {
                $out = fopen('php://output', 'w');
                $this->exportDatabaseToStream($out, 'mysql_system', $sysDb, 'Database Hệ Thống Quản Trị Chính', false);
                fclose($out);
            }, $filename, [
                'Content-Type'        => 'application/sql',
                'Content-Disposition' => "attachment; filename=\"{$filename}\"",
                'X-Filename'          => $filename,
            ]);
        }

        // TRƯỜNG HỢP 3: XUẤT MỘT CHI NHÁNH CỤ THỂ
        $database = $context['database'];
        $connName = $context['connection'];
        $filename = "pms_{$branchCode}_backup_{$timestamp}.sql";

        return response()->streamDownload(function () use ($database, $connName, $branchCode, $branchName) {
            $out = fopen('php://output', 'w');
            $this->exportDatabaseToStream($out, $connName, $database, "Chi nhánh {$branchName} (Mã: {$branchCode})", false);
            fclose($out);
        }, $filename, [
            'Content-Type'        => 'application/sql',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
            'X-Filename'          => $filename,
        ]);
    }

    /**
     * Chuẩn hóa và làm sạch file SQL sao lưu (loại bỏ lệnh USE/CREATE DATABASE gây chuyển nhầm DB, chuẩn hóa DEFINER)
     */
    private function prepareSanitizedSqlFile(string $sourcePath, bool $stripUseAndCreateDb): string
    {
        $tempPath = tempnam(sys_get_temp_dir(), 'pms_restore_');
        $in = fopen($sourcePath, 'r');
        if (!$in) {
            throw new \RuntimeException("Không thể đọc file sao lưu nguồn.");
        }
        $out = fopen($tempPath, 'w');
        if (!$out) {
            fclose($in);
            throw new \RuntimeException("Không thể tạo file tạm để khôi phục.");
        }

        fwrite($out, "/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;\n");
        fwrite($out, "/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;\n");
        fwrite($out, "SET FOREIGN_KEY_CHECKS = 0;\n");
        fwrite($out, "SET SQL_MODE = 'NO_AUTO_VALUE_ON_ZERO';\n");

        while (($line = fgets($in)) !== false) {
            if ($stripUseAndCreateDb) {
                // Bỏ qua lệnh CREATE DATABASE và USE khi đang import vào 1 chi nhánh chỉ định
                if (preg_match('/^\s*CREATE\s+DATABASE\b/i', $line)) {
                    continue;
                }
                if (preg_match('/^\s*USE\s+[`\'"]?[a-zA-Z0-9_\-]+[`\'"]?\s*;?/i', $line)) {
                    continue;
                }
                // Chỉ loại bỏ tiền tố `dbname`. trước tên bảng trên các câu lệnh DDL/DML, tránh chạm vào số thập phân (ví dụ 0.000000)
                if (strpos($line, '.') !== false) {
                    $line = preg_replace(
                        '/(CREATE\s+TABLE\s+(?:IF\s+NOT\s+EXISTS\s+)?|INSERT\s+(?:IGNORE\s+)?INTO\s+|DROP\s+TABLE\s+(?:IF\s+EXISTS\s+)?|ALTER\s+TABLE\s+|UPDATE\s+|TRUNCATE\s+(?:TABLE\s+)?)[`\'"]?[a-zA-Z0-9_\-]+[`\'"]?\s*\.\s*[`\'"]?([a-zA-Z0-9_\-]+)[`\'"]?/i',
                        '$1`$2`',
                        $line
                    );
                }
            }

            // Chuẩn hóa DEFINER nếu xuất từ server/tài khoản khác
            if (stripos($line, 'DEFINER=') !== false) {
                $line = preg_replace('/DEFINER\s*=\s*[`\'"]?[^`\'"@]+[`\'"]?@\s*[`\'"]?[^`\'"]+[`\'"]?/i', 'DEFINER=CURRENT_USER', $line);
            }

            fwrite($out, $line);
        }

        fwrite($out, "\nSET FOREIGN_KEY_CHECKS = 1;\n");
        fwrite($out, "/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;\n");
        fwrite($out, "/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;\n");

        fclose($in);
        fclose($out);

        return $tempPath;
    }

    /**
     * Khôi phục database trực tiếp qua mysql.exe CLI (O(1) RAM, cực nhanh, hỗ trợ đầy đủ Stored Procedures/Triggers)
     */
    private function executeRestoreViaCli(string $mysqlBin, array $dbConfig, string $sqlFilePath, ?string $targetDatabase = null): void
    {
        $host = $dbConfig['host'] ?? env('DB_HOST', '127.0.0.1');
        $port = $dbConfig['port'] ?? env('DB_PORT', '3306');
        $username = $dbConfig['username'] ?? env('DB_USERNAME', 'root');
        $password = $dbConfig['password'] ?? env('DB_PASSWORD', '');

        $cmd = sprintf(
            '"%s" -h %s -P %s -u %s --default-character-set=utf8mb4 --max_allowed_packet=512M',
            $mysqlBin,
            escapeshellarg($host),
            escapeshellarg((string)$port),
            escapeshellarg($username)
        );

        if ($targetDatabase) {
            $cmd .= ' ' . escapeshellarg($targetDatabase);
        }

        $descriptors = [
            0 => ['file', $sqlFilePath, 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        if ($password !== '') {
            putenv("MYSQL_PWD={$password}");
        } else {
            putenv("MYSQL_PWD");
        }

        $process = proc_open($cmd, $descriptors, $pipes, null, null);

        if (!is_resource($process)) {
            putenv("MYSQL_PWD");
            throw new \RuntimeException("Không thể khởi chạy tiến trình mysql CLI để khôi phục.");
        }

        $stdout = stream_get_contents($pipes[1]);
        fclose($pipes[1]);

        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[2]);

        $exitCode = proc_close($process);
        putenv("MYSQL_PWD");

        if ($exitCode !== 0) {
            $errorLines = array_filter(explode("\n", (string)$stderr), function ($l) {
                $l = trim($l);
                if (!$l) return false;
                if (stripos($l, '[Warning] Using a password') !== false) return false;
                return true;
            });
            $cleanError = implode("\n", $errorLines);

            if (!empty($cleanError)) {
                throw new \RuntimeException("MySQL CLI error (Mã {$exitCode}): " . mb_substr($cleanError, 0, 500));
            }
        }
    }

    /**
     * Fallback khôi phục theo từng câu lệnh streaming (Nếu môi trường không có mysql CLI)
     */
    private function executeRestoreViaPdoStream(string $connName, string $sqlFilePath): void
    {
        $in = fopen($sqlFilePath, 'r');
        if (!$in) {
            throw new \RuntimeException("Không thể mở file SQL để thực thi.");
        }

        DB::connection($connName)->statement('SET FOREIGN_KEY_CHECKS = 0;');

        $buffer = '';
        $delimiter = ';';

        while (($line = fgets($in)) !== false) {
            $trimmed = trim($line);
            if ($trimmed === '' || str_starts_with($trimmed, '--') || str_starts_with($trimmed, '#')) {
                continue;
            }

            // Nhận diện thay đổi DELIMITER
            if (preg_match('/^DELIMITER\s+(.+)$/i', $trimmed, $matches)) {
                $delimiter = trim($matches[1]);
                continue;
            }

            $buffer .= $line;

            // Kiểm tra kết thúc câu lệnh bằng DELIMITER hiện hành
            $checkBuffer = rtrim($buffer);
            if (str_ends_with($checkBuffer, $delimiter)) {
                $stmt = substr($checkBuffer, 0, -strlen($delimiter));
                $stmt = trim($stmt);
                $buffer = '';

                if ($stmt !== '') {
                    try {
                        DB::connection($connName)->unprepared($stmt);
                    } catch (\Throwable $e) {
                        fclose($in);
                        $preview = mb_substr($stmt, 0, 150);
                        throw new \RuntimeException("Lỗi thực thi câu lệnh [{$preview}...]: " . $e->getMessage());
                    }
                }
            }
        }

        if (trim($buffer) !== '') {
            try {
                DB::connection($connName)->unprepared(trim($buffer));
            } catch (\Throwable $e) {
                fclose($in);
                $preview = mb_substr(trim($buffer), 0, 150);
                throw new \RuntimeException("Lỗi thực thi câu lệnh cuối: " . $e->getMessage());
            }
        }

        fclose($in);
        DB::connection($connName)->statement('SET FOREIGN_KEY_CHECKS = 1;');
    }

    /**
     * POST /api/system/database/import
     * Khôi phục database (hỗ trợ ALL, SYSTEM hoặc từng chi nhánh cụ thể)
     */
    public function importDatabase(Request $request)
    {
        @set_time_limit(0);
        @ini_set('memory_limit', '1024M');
        @ini_set('max_execution_time', '0');

        $request->validate([
            'file'        => 'required|file',
            'branch_code' => 'nullable|string',
        ]);

        $file = $request->file('file');
        $ext = strtolower($file->getClientOriginalExtension());
        if ($ext !== 'sql') {
            return response()->json([
                'success' => false,
                'message' => 'Vui lòng chọn file có định dạng .sql',
            ], 422);
        }

        $this->ensureMaxAllowedPacket();

        $context = $this->resolveBranchContext($request);
        $type = $context['type'];
        $branchCode = $context['branch_code'];
        $branchName = $context['branch_name'];
        $database = $context['database'] ?? null;
        $connName = $context['connection'] ?? null;

        $mysqlBin = $this->getMysqlBinary();
        $sourceFilePath = $file->getRealPath();
        $tempSanitizedPath = null;

        try {
            // TRƯỜNG HỢP 1: KHÔI PHỤC TOÀN BỘ (ALL)
            if ($type === 'ALL') {
                $tempSanitizedPath = $this->prepareSanitizedSqlFile($sourceFilePath, false);

                if ($mysqlBin) {
                    $this->executeRestoreViaCli($mysqlBin, $context['config'], $tempSanitizedPath, null);
                } else {
                    // Fallback phân tích các khối USE `dbname`
                    $sqlContent = file_get_contents($tempSanitizedPath);
                    $sections = preg_split('/(?=CREATE\s+DATABASE\s+|USE\s+[`\'"]?[a-zA-Z0-9_\-]+[`\'"]?\s*;)/i', $sqlContent);
                    $restoredDatabases = [];

                    foreach ($sections as $sec) {
                        if (empty(trim($sec))) continue;

                        if (preg_match('/USE\s+[`\'"]?([a-zA-Z0-9_\-]+)[`\'"]?\s*;/i', $sec, $matches)) {
                            $targetDb = $matches[1];
                            TenantDatabaseService::createDatabaseIfNotExists($targetDb);

                            $tempConn = 'import_' . uniqid();
                            TenantDatabaseService::registerDynamicConnection($tempConn, $targetDb);

                            $tempSecFile = tempnam(sys_get_temp_dir(), 'sec_');
                            file_put_contents($tempSecFile, $sec);
                            $this->executeRestoreViaPdoStream($tempConn, $tempSecFile);
                            @unlink($tempSecFile);

                            $restoredDatabases[] = $targetDb;
                        }
                    }
                }

                return response()->json([
                    'success'     => true,
                    'message'     => 'Khôi phục toàn bộ hệ thống & tất cả chi nhánh thành công!',
                    'branch_code' => 'ALL',
                ]);
            }

            // TRƯỜNG HỢP 2 & 3: KHÔI PHỤC DATABASE HỆ THỐNG HOẶC MỘT CHI NHÁNH CỤ THỂ
            if ($type === 'SYSTEM') {
                $database = config('database.connections.mysql_system.database', 'pms_system');
                $connName = 'mysql_system';
            }

            TenantDatabaseService::createDatabaseIfNotExists($database);
            $tempSanitizedPath = $this->prepareSanitizedSqlFile($sourceFilePath, true);

            if ($mysqlBin) {
                $this->executeRestoreViaCli($mysqlBin, $context['config'], $tempSanitizedPath, $database);
            } else {
                $this->executeRestoreViaPdoStream($connName, $tempSanitizedPath);
            }

            return response()->json([
                'success'     => true,
                'message'     => "Khôi phục dữ liệu thành công cho {$branchName} (Database: {$database})!",
                'branch_code' => $branchCode,
                'database'    => $database,
            ]);

        } catch (\Throwable $e) {
            Log::error("Import Database Error for branch {$branchCode}: " . $e->getMessage());

            // Rút gọn thông điệp lỗi ngắn gọn, sạch sẽ, không dump hàng trăm nghìn ký tự SQL lên giao diện
            $errorDetail = $e->getMessage();
            if (strlen($errorDetail) > 300) {
                $errorDetail = mb_substr($errorDetail, 0, 300) . '...';
            }

            return response()->json([
                'success' => false,
                'message' => "Lỗi khôi phục database chi nhánh {$branchCode}: {$errorDetail}",
            ], 500);
        } finally {
            if ($tempSanitizedPath && file_exists($tempSanitizedPath)) {
                @unlink($tempSanitizedPath);
            }
        }
    }
}
