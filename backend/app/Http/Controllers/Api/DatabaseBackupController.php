<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class DatabaseBackupController extends Controller
{
    /**
     * Export database to a .sql dump file
     */
    public function exportDatabase()
    {
        $dbConfig = config('database.connections.mysql');
        $database = $dbConfig['database'] ?? env('DB_DATABASE', 'pms');
        $host = $dbConfig['host'] ?? env('DB_HOST', '127.0.0.1');
        $port = $dbConfig['port'] ?? env('DB_PORT', '3306');
        $username = $dbConfig['username'] ?? env('DB_USERNAME', 'root');
        $password = $dbConfig['password'] ?? env('DB_PASSWORD', '');

        $filename = "pms_backup_" . date('Y_m_d_H_i_s') . ".sql";

        // Try mysqldump first if exec exists and executable is found
        $mysqldumpPaths = [
            'C:\\xampp\\mysql\\bin\\mysqldump.exe',
            'mysqldump'
        ];

        $dumpPath = null;
        foreach ($mysqldumpPaths as $path) {
            if ($path === 'mysqldump' || file_exists($path)) {
                $dumpPath = $path;
                break;
            }
        }

        if ($dumpPath && function_exists('exec')) {
            $tempFile = storage_path('app/temp_' . uniqid() . '.sql');
            $passParam = !empty($password) ? "--password=" . escapeshellarg($password) : "";
            $cmd = sprintf(
                '"%s" --host=%s --port=%s --user=%s %s %s > "%s"',
                $dumpPath,
                escapeshellarg($host),
                escapeshellarg($port),
                escapeshellarg($username),
                $passParam,
                escapeshellarg($database),
                $tempFile
            );

            @exec($cmd, $output, $returnVar);

            if ($returnVar === 0 && file_exists($tempFile) && filesize($tempFile) > 0) {
                return response()->download($tempFile, $filename, [
                    'Content-Type' => 'application/sql',
                ])->deleteFileAfterSend(true);
            }
        }

        // Fallback: Pure PHP SQL Exporter
        return response()->streamDownload(function () use ($database) {
            $out = fopen('php://output', 'w');

            fwrite($out, "-- PMS Database Backup\n");
            fwrite($out, "-- Database: {$database}\n");
            fwrite($out, "-- Date: " . date('Y-m-d H:i:s') . "\n\n");
            fwrite($out, "SET FOREIGN_KEY_CHECKS=0;\n");
            fwrite($out, "SET SQL_MODE = \"NO_AUTO_VALUE_ON_ZERO\";\n");
            fwrite($out, "SET time_zone = \"+00:00\";\n\n");

            $tables = DB::select('SHOW TABLES');
            $tableKey = "Tables_in_" . $database;

            foreach ($tables as $t) {
                $tableArray = (array)$t;
                $tableName = $tableArray[$tableKey] ?? reset($tableArray);

                // Fetch CREATE TABLE statement
                try {
                    $createSql = DB::select("SHOW CREATE TABLE `{$tableName}`");
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

                // Fetch table data in chunks
                try {
                    $count = DB::table($tableName)->count();
                    if ($count > 0) {
                        fwrite($out, "-- Dumping data for table `{$tableName}`\n");
                        
                        DB::table($tableName)->orderBy(DB::raw(1))->chunk(200, function ($rows) use ($out, $tableName) {
                            if ($rows->isEmpty()) return;
                            
                            $firstRow = (array)$rows->first();
                            $columns = array_keys($firstRow);
                            $colList = implode(', ', array_map(fn($c) => "`{$c}`", $columns));
                            
                            $valueRows = [];
                            foreach ($rows as $row) {
                                $rowArray = (array)$row;
                                $values = array_map(function ($val) {
                                    if (is_null($val)) return 'NULL';
                                    return DB::getPdo()->quote($val);
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

            fwrite($out, "SET FOREIGN_KEY_CHECKS=1;\n");
            fclose($out);
        }, $filename, [
            'Content-Type' => 'application/sql',
        ]);
    }

    /**
     * Import database from uploaded .sql file
     */
    public function importDatabase(Request $request)
    {
        $request->validate([
            'file' => 'required|file',
        ]);

        $file = $request->file('file');
        $ext = strtolower($file->getClientOriginalExtension());
        if ($ext !== 'sql') {
            return response()->json([
                'success' => false,
                'message' => 'Vui lòng chọn file có định dạng .sql',
            ], 422);
        }

        try {
            $filePath = $file->getRealPath();
            $sqlContent = file_get_contents($filePath);

            if (empty(trim($sqlContent))) {
                return response()->json([
                    'success' => false,
                    'message' => 'File SQL tải lên trống!',
                ], 422);
            }

            // Disable FK checks and run SQL
            DB::statement('SET FOREIGN_KEY_CHECKS = 0;');
            DB::unprepared($sqlContent);
            DB::statement('SET FOREIGN_KEY_CHECKS = 1;');

            return response()->json([
                'success' => true,
                'message' => 'Khôi phục (Import) cơ sở dữ liệu thành công!',
            ]);
        } catch (\Exception $e) {
            DB::statement('SET FOREIGN_KEY_CHECKS = 1;');
            Log::error("Import Database Error: " . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Có lỗi xảy ra trong quá trình khôi phục: ' . $e->getMessage(),
            ], 500);
        }
    }
}
