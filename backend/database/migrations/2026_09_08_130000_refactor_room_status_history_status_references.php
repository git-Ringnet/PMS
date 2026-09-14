<?php

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
            $table->unsignedBigInteger('status_from_id')->nullable()->after('room')->index();
            $table->unsignedBigInteger('status_to_id')->nullable()->after('status_from_id')->index();
            $table->integer('legacy_status_from_code')->nullable()->after('status_to_id');
            $table->integer('legacy_status_to_code')->nullable()->after('legacy_status_from_code');
        });

        DB::statement(<<<'SQL'
UPDATE room_status_change_logs AS history
LEFT JOIN room_statuses AS status_from ON status_from.id = history.status_from_code
LEFT JOIN room_statuses AS status_to ON status_to.id = history.status_to_code
SET history.status_from_id = status_from.id,
    history.status_to_id = status_to.id,
    history.legacy_status_from_code = history.status_from_code,
    history.legacy_status_to_code = history.status_to_code
SQL);

        Schema::table('room_status_change_logs', function (Blueprint $table) {
            $table->dropColumn([
                'status_from_code',
                'status_to_code',
                'status_from_english',
                'status_from_vietnamese',
                'status_to_english',
                'status_to_vietnamese',
            ]);
        });

        $this->createProcedure();
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() !== 'mysql' || !Schema::hasTable('room_status_change_logs')) {
            return;
        }

        Schema::table('room_status_change_logs', function (Blueprint $table) {
            $table->integer('status_from_code')->nullable()->after('room');
            $table->integer('status_to_code')->nullable()->after('status_from_code');
            $table->string('status_from_english', 100)->nullable()->after('status_to_code');
            $table->string('status_from_vietnamese', 100)->nullable()->after('status_from_english');
            $table->string('status_to_english', 100)->nullable()->after('status_from_vietnamese');
            $table->string('status_to_vietnamese', 100)->nullable()->after('status_to_english');
        });

        DB::statement(<<<'SQL'
UPDATE room_status_change_logs AS history
LEFT JOIN room_statuses AS status_from ON status_from.id = history.status_from_id
LEFT JOIN room_statuses AS status_to ON status_to.id = history.status_to_id
SET history.status_from_code = COALESCE(history.legacy_status_from_code, history.status_from_id),
    history.status_to_code = COALESCE(history.legacy_status_to_code, history.status_to_id),
    history.status_from_english = status_from.name_en,
    history.status_from_vietnamese = status_from.name_vi,
    history.status_to_english = status_to.name_en,
    history.status_to_vietnamese = status_to.name_vi
SQL);

        Schema::table('room_status_change_logs', function (Blueprint $table) {
            $table->dropColumn([
                'status_from_id',
                'status_to_id',
                'legacy_status_from_code',
                'legacy_status_to_code',
            ]);
        });

        DB::unprepared('DROP PROCEDURE IF EXISTS rpt_room_status_history');
        DB::unprepared(<<<'SQL'
CREATE PROCEDURE rpt_room_status_history(
    IN p_from_date DATE,
    IN p_to_date DATE,
    IN p_username VARCHAR(50),
    IN p_room VARCHAR(50)
)
READS SQL DATA
BEGIN
    SELECT
        id AS Id,
        changed_at AS Date,
        username AS Username,
        room AS Room,
        status_from_code AS StatusFrom,
        status_to_code AS StatusTo,
        REPLACE(status_from_english, 'Vacant ', '') AS StatusFromEnglish,
        status_from_vietnamese AS StatusFromVietnamese,
        REPLACE(status_to_english, 'Vacant ', '') AS StatusToEnglish,
        status_to_vietnamese AS StatusToVietnamese
    FROM room_status_change_logs
    WHERE DATE(changed_at) BETWEEN p_from_date AND p_to_date
      AND (p_username IS NULL OR p_username = '' OR username = p_username)
      AND (p_room IS NULL OR p_room = '' OR room = p_room);
END
SQL);
    }

    private function createProcedure(): void
    {
        DB::unprepared('DROP PROCEDURE IF EXISTS rpt_room_status_history');
        DB::unprepared(<<<'SQL'
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
    WHERE DATE(history.changed_at) BETWEEN p_from_date AND p_to_date
      AND (p_username IS NULL OR p_username = '' OR history.username = p_username)
      AND (p_room IS NULL OR p_room = '' OR history.room = p_room);
END
SQL);
    }
};
