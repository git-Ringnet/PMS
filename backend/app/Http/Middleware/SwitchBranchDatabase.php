<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class SwitchBranchDatabase
{
    /**
     * Handle an incoming request.
     * Tự động chuyển đổi Database connection theo chi nhánh được chọn từ Header request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $branchCode = $request->header('X-Branch-Code');
        $branchId = $request->header('X-Branch-Id');

        $targetConnection = null;

        // 1. Kiểm tra theo Branch Code (ví dụ: HKT1, HKT2, HKT3, HKT4, HKT5, CANTHO, ...)
        if ($branchCode) {
            $codeClean = strtolower(trim($branchCode));
            $candidateConn = 'mysql_' . $codeClean;
            if (!Config::has("database.connections.{$candidateConn}")) {
                $dbName = \App\Services\TenantDatabaseService::getDatabaseName($branchCode);
                \App\Services\TenantDatabaseService::registerDynamicConnection($candidateConn, $dbName);
            }
            $targetConnection = $candidateConn;
        }

        // 2. Kiểm tra theo Branch ID (1 -> mysql_hkt1, 2 -> mysql_hkt2, ...)
        if (!$targetConnection && $branchId) {
            $candidateConn = 'mysql_hkt' . (int)$branchId;
            if (!Config::has("database.connections.{$candidateConn}")) {
                $dbName = 'pms_hkt' . (int)$branchId;
                \App\Services\TenantDatabaseService::registerDynamicConnection($candidateConn, $dbName);
            }
            $targetConnection = $candidateConn;
        }

        // 3. Thực hiện chuyển đổi connection cho toàn bộ vòng đời Request
        if ($targetConnection) {
            Config::set('database.default', $targetConnection);
            DB::setDefaultConnection($targetConnection);
            DB::purge($targetConnection);

            $targetDb = Config::get("database.connections.{$targetConnection}.database");
            if ($targetDb) {
                Config::set('database.connections.mysql.database', $targetDb);
                DB::purge('mysql');
            }
        }

        return $next($request);
    }
}
