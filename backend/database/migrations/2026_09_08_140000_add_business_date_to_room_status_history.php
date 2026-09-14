<?php

use Carbon\Carbon;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::connection()->getDriverName() !== 'mysql' || !Schema::hasTable('room_status_change_logs')) {
            return;
        }

        Schema::table('room_status_change_logs', function (Blueprint $table) {
            $table->date('business_date')->nullable()->after('changed_at');
            $table->index(['business_date', 'room']);
            $table->index(['business_date', 'username']);
        });

        DB::table('room_status_change_logs')
            ->where('source', '!=', 'runtime')
            ->update(['business_date' => DB::raw('DATE(changed_at)')]);

        $systemDate = DB::table('system_date_rolls')->latest('id')->value('system_date');
        DB::table('room_status_change_logs')
            ->where('source', 'runtime')
            ->update([
                'business_date' => $systemDate
                    ? Carbon::parse($systemDate)->toDateString()
                    : DB::raw('DATE(changed_at)'),
            ]);

        $this->createProcedure();
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() !== 'mysql' || !Schema::hasTable('room_status_change_logs')) {
            return;
        }

        Schema::table('room_status_change_logs', function (Blueprint $table) {
            $table->dropColumn('business_date');
        });

        DB::unprepared('DROP PROCEDURE IF EXISTS rpt_room_status_history');
        DB::unprepared($this->procedureSql('DATE(history.changed_at)'));
    }

    private function createProcedure(): void
    {
        DB::unprepared('DROP PROCEDURE IF EXISTS rpt_room_status_history');
        DB::unprepared($this->procedureSql('history.business_date'));
    }

    private function procedureSql(string $dateExpression): string
    {
        return <<<SQL
CREATE PROCEDURE rpt_room_status_history(
    IN p_from_date DATE,
    IN p_to_date DATE,
    IN p_username VARCHAR(50),
    IN p_room VARCHAR(50)
)
READS SQL DATA
BEGIN
    SELECT
        history.id AS Id,
        history.changed_at AS Date,
        history.username AS Username,
        history.room AS Room,
        COALESCE(history.legacy_status_from_code, history.status_from_id) AS StatusFrom,
        COALESCE(history.legacy_status_to_code, history.status_to_id) AS StatusTo,
        REPLACE(status_from.name_en, 'Vacant ', '') AS StatusFromEnglish,
        status_from.name_vi AS StatusFromVietnamese,
        REPLACE(status_to.name_en, 'Vacant ', '') AS StatusToEnglish,
        status_to.name_vi AS StatusToVietnamese
    FROM room_status_change_logs AS history
    LEFT JOIN room_statuses AS status_from ON status_from.id = history.status_from_id
    LEFT JOIN room_statuses AS status_to ON status_to.id = history.status_to_id
    WHERE {$dateExpression} BETWEEN p_from_date AND p_to_date
      AND (p_username IS NULL OR p_username = '' OR history.username = p_username)
      AND (p_room IS NULL OR p_room = '' OR history.room = p_room);
END
SQL;
    }
};
