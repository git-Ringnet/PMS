<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('bookings')
            || !Schema::hasColumn('bookings', 'created_by_user_id')
            || !Schema::hasColumn('bookings', 'updated_by_user_id')) {
            return;
        }

        $systemConnection = config('database_domains.system_connection', 'mysql_system');
        $users = DB::connection($systemConnection)
            ->table('users')
            ->whereNotNull('username')
            ->pluck('id', 'username');

        foreach ($users as $username => $userId) {
            DB::table('bookings')
                ->whereNull('created_by_user_id')
                ->where('created_by', $username)
                ->update(['created_by_user_id' => $userId]);

            DB::table('bookings')
                ->whereNull('updated_by_user_id')
                ->where('updated_by', $username)
                ->update(['updated_by_user_id' => $userId]);
        }
    }

    public function down(): void
    {
        // Actor IDs are audit data and are intentionally not cleared on rollback.
    }
};
