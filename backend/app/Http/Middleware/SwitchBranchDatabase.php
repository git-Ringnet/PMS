<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
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

        $connections = config('database_domains.branch_connections', []);
        $defaultCode = strtoupper((string) config('database_domains.default_branch_code', 'HKT1'));

        // Existing feature tests use the default SQLite connection and do not
        // send branch headers. Keep them isolated from real branch databases.
        if (app()->environment('testing') && !$branchCode && !$branchId) {
            $request->attributes->set('_branch_code', $defaultCode);
            $request->attributes->set('_branch_connection', DB::getDefaultConnection());

            return $next($request);
        }

        $resolvedCode = $branchCode ? strtoupper(trim((string) $branchCode)) : null;

        if (!$resolvedCode && $branchId) {
            $resolvedCode = 'HKT' . (int) $branchId;
        }

        $resolvedCode ??= $defaultCode;
        $targetConnection = $connections[$resolvedCode] ?? null;

        if (!$targetConnection) {
            return response()->json([
                'success' => false,
                'message' => 'Chi nhánh không hợp lệ.',
            ], 422);
        }

        // Do not purge the in-memory SQLite connection used by feature tests
        // when the requested branch intentionally resolves to that connection.
        if (app()->environment('testing') && $targetConnection === DB::getDefaultConnection()) {
            $request->attributes->set('_branch_code', $resolvedCode);
            $request->attributes->set('_branch_connection', $targetConnection);

            return $next($request);
        }

        // 3. Thực hiện chuyển đổi connection cho toàn bộ vòng đời Request
        if ($targetConnection) {
            config(['database.default' => $targetConnection]);
            DB::setDefaultConnection($targetConnection);
            DB::purge($targetConnection);

            $targetDb = config("database.connections.{$targetConnection}.database");
            if ($targetDb) {
                config(['database.connections.mysql.database' => $targetDb]);
            }

            $request->attributes->set('_branch_code', $resolvedCode);
            $request->attributes->set('_branch_connection', $targetConnection);
        }

        return $next($request);
    }
}
