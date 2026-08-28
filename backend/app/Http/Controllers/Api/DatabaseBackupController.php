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
     * Helper: Phân giải thông tin Database connection theo chi nhánh / ALL / SYSTEM
     */
    private function resolveBranchContext(Request $request): array
    {
        $branchCode = $request->input('branch_code') 
            ?: $request->header('X-Branch-Code') 
            ?: 'HKT1';
        $branchCode = strtoupper(trim((string) $branchCode));

        if ($branchCode === 'ALL') {
            return [
                'type'        => 'ALL',
                'branch_code' => 'ALL',
                'branch_name' => 'Toàn Bộ Hệ Thống & Tất Cả Chi Nhánh',
                'database'    => 'ALL (Multi-Database)',
                'connection'  => 'all',
            ];
        }

        if ($branchCode === 'SYSTEM') {
            $systemConn = 'mysql_system';
            $systemDb = config('database.connections.mysql_system.database', 'pms_system');
            return [
                'type'        => 'SYSTEM',
                'branch_code' => 'SYSTEM',
                'branch_name' => 'Cơ sở Dữ liệu Hệ Thống Chính (Users, Roles, Chi nhánh)',
                'database'    => $systemDb,
                'connection'  => $systemConn,
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
     * Xuất một Database connection ra luồng SQL
     */
    private function exportDatabaseToStream($out, string $connName, string $dbName, string $title, bool $includeUseDb = false)
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

        fwrite($out, "SET FOREIGN_KEY_CHECKS=0;\n");
        fwrite($out, "SET SQL_MODE = \"NO_AUTO_VALUE_ON_ZERO\";\n");
        fwrite($out, "SET time_zone = \"+00:00\";\n\n");

        try {
            $tables = DB::connection($connName)->select('SHOW TABLES');
            $tableKey = "Tables_in_" . $dbName;

            foreach ($tables as $t) {
                $tableArray = (array)$t;
                $tableName = $tableArray[$tableKey] ?? reset($tableArray);

                // Lấy câu lệnh CREATE TABLE
                try {
                    $createSql = DB::connection($connName)->select("SHOW CREATE TABLE `{$tableName}`");
                    if (!empty($createSql)) {
                        $createArray = (array)$createSql[0];
                        $createTableStmt = $createArray['Create Table'] ?? $createArray['CREATE TABLE'] ?? null;
                        if ($createTableStmt) {
                            fwrite($out, "-- Table structure for `{$tableName}`\n");
                            fwrite($out, "DROP TABLE IF EXISTS `{$tableName}`;\n");
                            fwrite($out, $createTableStmt . ";\n\n");
                        }
                    }
                } catch (\Throwable $e) {
                    continue;
                }

                // Lấy dữ liệu bảng theo từng đợt
                try {
                    $count = DB::connection($connName)->table($tableName)->count();
                    if ($count > 0) {
                        fwrite($out, "-- Dumping data for table `{$tableName}`\n");
                        
                        DB::connection($connName)->table($tableName)->orderBy(DB::raw(1))->chunk(200, function ($rows) use ($out, $tableName, $connName) {
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
        } catch (\Throwable $e) {
            fwrite($out, "-- Error exporting database {$dbName}: " . $e->getMessage() . "\n");
        }

        fwrite($out, "SET FOREIGN_KEY_CHECKS=1;\n\n");
    }

    /**
     * GET /api/system/database/export
     * Xuất file SQL (hỗ trợ ALL, SYSTEM, hoặc từng chi nhánh cụ thể)
     */
    public function exportDatabase(Request $request)
    {
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
     * POST /api/system/database/import
     * Khôi phục database (hỗ trợ ALL, SYSTEM hoặc từng chi nhánh cụ thể)
     */
    public function importDatabase(Request $request)
    {
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

        $context = $this->resolveBranchContext($request);
        $type = $context['type'];
        $branchCode = $context['branch_code'];
        $branchName = $context['branch_name'];

        try {
            $filePath = $file->getRealPath();
            $sqlContent = file_get_contents($filePath);

            if (empty(trim($sqlContent))) {
                return response()->json([
                    'success' => false,
                    'message' => 'File SQL tải lên trống!',
                ], 422);
            }

            // TRƯỜNG HỢP 1: KHÔI PHỤC TOÀN BỘ (ALL) - Tự động định tuyến nhiều Database
            if ($type === 'ALL') {
                // Kiểm tra xem file có các khối USE `dbname` hoặc DATABASE SECTION không
                $sections = preg_split('/(?=CREATE\s+DATABASE\s+|USE\s+[`\'"]?[a-zA-Z0-9_\-]+[`\'"]?\s*;)/i', $sqlContent);

                if (count($sections) > 1) {
                    $restoredDatabases = [];
                    foreach ($sections as $sec) {
                        if (empty(trim($sec))) continue;

                        // Tìm tên database trong lệnh USE `dbname`
                        if (preg_match('/USE\s+[`\'"]?([a-zA-Z0-9_\-]+)[`\'"]?\s*;/i', $sec, $matches)) {
                            $targetDb = $matches[1];
                            TenantDatabaseService::createDatabaseIfNotExists($targetDb);

                            $tempConn = 'import_' . uniqid();
                            TenantDatabaseService::registerDynamicConnection($tempConn, $targetDb);

                            $cleanSec = preg_replace('/^\s*CREATE\s+DATABASE\s+.*?;/mi', '', $sec);
                            $cleanSec = preg_replace('/^\s*USE\s+[`\'"]?[a-zA-Z0-9_\-]+[`\'"]?\s*;?/mi', '', $cleanSec);

                            DB::connection($tempConn)->statement('SET FOREIGN_KEY_CHECKS = 0;');
                            DB::connection($tempConn)->unprepared($cleanSec);
                            DB::connection($tempConn)->statement('SET FOREIGN_KEY_CHECKS = 1;');

                            $restoredDatabases[] = $targetDb;
                        }
                    }

                    return response()->json([
                        'success'     => true,
                        'message'     => 'Khôi phục toàn bộ hệ thống thành công (' . count($restoredDatabases) . ' Database)!',
                        'databases'   => $restoredDatabases,
                        'branch_code' => 'ALL',
                    ]);
                }
            }

            // TRƯỜNG HỢP 2: KHÔI PHỤC SYSTEM DB
            if ($type === 'SYSTEM') {
                $targetDb = config('database.connections.mysql_system.database', 'pms_system');
                TenantDatabaseService::createDatabaseIfNotExists($targetDb);

                $cleanSql = preg_replace('/^\s*CREATE\s+DATABASE\s+.*?;/mi', '-- [STRIPPED CREATE DATABASE]', $sqlContent);
                $cleanSql = preg_replace('/^\s*USE\s+[`\'"]?[a-zA-Z0-9_\-]+[`\'"]?\s*;?/mi', '-- [STRIPPED USE STATEMENT]', $cleanSql);

                DB::connection('mysql_system')->statement('SET FOREIGN_KEY_CHECKS = 0;');
                DB::connection('mysql_system')->unprepared($cleanSql);
                DB::connection('mysql_system')->statement('SET FOREIGN_KEY_CHECKS = 1;');

                return response()->json([
                    'success'     => true,
                    'message'     => "Khôi phục Database Hệ Thống Chính ({$targetDb}) thành công!",
                    'branch_code' => 'SYSTEM',
                    'database'    => $targetDb,
                ]);
            }

            // TRƯỜNG HỢP 3: KHÔI PHỤC MỘT CHI NHÁNH CỤ THỂ
            $database = $context['database'];
            $connName = $context['connection'];

            TenantDatabaseService::createDatabaseIfNotExists($database);

            $cleanSql = preg_replace('/^\s*CREATE\s+DATABASE\s+.*?;/mi', '-- [STRIPPED CREATE DATABASE]', $sqlContent);
            $cleanSql = preg_replace('/^\s*USE\s+[`\'"]?[a-zA-Z0-9_\-]+[`\'"]?\s*;?/mi', '-- [STRIPPED USE STATEMENT]', $cleanSql);
            
            // Xóa tiền tố `dbname`. trước tên bảng nếu có
            $cleanSql = preg_replace('/(CREATE\s+TABLE\s+(?:IF\s+NOT\s+EXISTS\s+)?)[`\'"]?[a-zA-Z0-9_\-]+[`\'"]?\s*\.\s*[`\'"]?([a-zA-Z0-9_\-]+)[`\'"]?/i', '$1`$2`', $cleanSql);
            $cleanSql = preg_replace('/(INSERT\s+INTO\s+)[`\'"]?[a-zA-Z0-9_\-]+[`\'"]?\s*\.\s*[`\'"]?([a-zA-Z0-9_\-]+)[`\'"]?/i', '$1`$2`', $cleanSql);
            $cleanSql = preg_replace('/(DROP\s+TABLE\s+(?:IF\s+EXISTS\s+)?)[`\'"]?[a-zA-Z0-9_\-]+[`\'"]?\s*\.\s*[`\'"]?([a-zA-Z0-9_\-]+)[`\'"]?/i', '$1`$2`', $cleanSql);

            DB::connection($connName)->statement('SET FOREIGN_KEY_CHECKS = 0;');
            DB::connection($connName)->unprepared($cleanSql);
            DB::connection($connName)->statement('SET FOREIGN_KEY_CHECKS = 1;');

            return response()->json([
                'success'     => true,
                'message'     => "Khôi phục dữ liệu thành công cho {$branchName} (Database: {$database})!",
                'branch_code' => $branchCode,
                'database'    => $database,
            ]);
        } catch (\Throwable $e) {
            Log::error("Import Database Error for branch {$branchCode}: " . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => "Lỗi khôi phục database chi nhánh {$branchCode}: " . $e->getMessage(),
            ], 500);
        }
    }
}
