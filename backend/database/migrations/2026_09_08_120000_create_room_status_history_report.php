<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const TABLE = 'room_status_change_logs';
    private const SOURCE = 'ROOM_STATUS_HISTORY';
    private const REPORT = 'ROOM_STATUS_HISTORY';
    private const TEMPLATE = 'ROOM_STATUS_HISTORY_STANDARD';

    public function up(): void
    {
        if (DB::connection()->getDriverName() !== 'mysql') {
            return;
        }

        Schema::create(self::TABLE, function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('legacy_id')->nullable()->unique();
            $table->dateTime('changed_at')->nullable();
            $table->string('username', 50)->nullable();
            $table->string('room', 50)->nullable();
            $table->integer('status_from_code')->nullable();
            $table->integer('status_to_code')->nullable();
            $table->string('status_from_english', 100)->nullable();
            $table->string('status_from_vietnamese', 100)->nullable();
            $table->string('status_to_english', 100)->nullable();
            $table->string('status_to_vietnamese', 100)->nullable();
            $table->string('source', 30)->default('legacy');
            $table->timestamps();
            $table->index(['changed_at', 'room']);
            $table->index(['changed_at', 'username']);
        });

        $this->createProcedure();
        $this->seedReportConfiguration();
        (require database_path('report_templates/room_status_history_reference.php'))->apply();
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() !== 'mysql') {
            return;
        }

        $reportId = DB::table('report_definitions')->where('code', self::REPORT)->value('id');
        $templateId = DB::table('templates')->where('report', self::TEMPLATE)->value('id');

        if ($reportId) {
            DB::table('report_definition_template')->where('report_definition_id', $reportId)->delete();
            DB::table('report_definitions')->where('id', $reportId)->delete();
        }
        if ($templateId) {
            DB::table('templates')->where('id', $templateId)->delete();
        }

        DB::table('report_data_sources')->where('code', self::SOURCE)->delete();
        DB::unprepared('DROP PROCEDURE IF EXISTS rpt_room_status_history');
        Schema::dropIfExists(self::TABLE);
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

    private function seedReportConfiguration(): void
    {
        $now = now();
        $database = DB::connection()->getDatabaseName();
        $parameters = [
            $this->parameter('p_from_date', 'date', 1),
            $this->parameter('p_to_date', 'date', 2),
            $this->parameter('p_username', 'varchar', 3, 50),
            $this->parameter('p_room', 'varchar', 4, 50),
        ];
        $fields = collect([
            'Id', 'Date', 'Username', 'Room', 'StatusFrom', 'StatusTo',
            'StatusFromEnglish', 'StatusFromVietnamese', 'StatusToEnglish', 'StatusToVietnamese',
        ])->map(fn (string $name) => [
            'name' => $name,
            'type' => in_array($name, ['Id', 'StatusFrom', 'StatusTo'], true) ? 'number' : 'string',
            'nullable' => true,
        ])->all();
        $defaults = [
            'p_from_date' => now()->toDateString(),
            'p_to_date' => now()->toDateString(),
            'p_username' => '',
            'p_room' => '',
        ];

        DB::table('report_data_sources')->updateOrInsert(['code' => self::SOURCE], [
            'name' => 'Dữ liệu thay đổi tình trạng phòng',
            'description' => 'Nguồn cô lập tương thích sp_288 từ SP8065 và SP1313.',
            'source_type' => 'procedure',
            'schema_name' => $database,
            'object_name' => 'rpt_room_status_history',
            'parameter_schema' => json_encode($parameters, JSON_UNESCAPED_UNICODE),
            'field_schema' => json_encode($fields, JSON_UNESCAPED_UNICODE),
            'sample_parameters' => json_encode($defaults, JSON_UNESCAPED_UNICODE),
            'max_rows' => 5000,
            'is_active' => true,
            'last_discovered_at' => $now,
            'updated_at' => $now,
            'created_at' => $now,
        ]);
        $sourceId = DB::table('report_data_sources')->where('code', self::SOURCE)->value('id');

        DB::table('templates')->updateOrInsert(['report' => self::TEMPLATE], [
            'group' => 'Báo cáo phòng',
            'name' => 'Báo cáo thay đổi tình trạng phòng',
            'report_data_source_id' => $sourceId,
            'parameter_defaults' => json_encode($defaults, JSON_UNESCAPED_UNICODE),
            'page_size' => 'A4',
            'page_orientation' => 'landscape',
            'margin_top' => 6,
            'margin_bottom' => 6,
            'margin_left' => 5,
            'margin_right' => 5,
            'content_json' => '{}',
            'content_html' => '',
            'css' => '',
            'is_default' => false,
            'version' => '1.0',
            'updated_at' => $now,
            'created_at' => $now,
        ]);
        $templateId = DB::table('templates')->where('report', self::TEMPLATE)->value('id');

        DB::table('report_definitions')->updateOrInsert(['code' => self::REPORT], [
            'name' => 'Báo cáo thay đổi tình trạng phòng',
            'group' => 'Báo cáo phòng',
            'description' => 'Lịch sử thao tác thay đổi tình trạng phòng theo sp_288.',
            'report_data_source_id' => $sourceId,
            'parameter_ui_schema' => json_encode([
                ['name' => 'p_from_date', 'label' => 'Từ ngày', 'control' => 'date-range', 'range_end_parameter' => 'p_to_date', 'default' => '$today', 'required' => true],
                ['name' => 'p_to_date', 'label' => 'Đến ngày', 'control' => 'hidden', 'default' => '$today', 'required' => true],
                ['name' => 'p_username', 'label' => 'Người dùng', 'control' => 'select', 'default' => '', 'required' => false, 'options_source' => 'users'],
                ['name' => 'p_room', 'label' => 'Số phòng', 'control' => 'select', 'default' => '', 'required' => false, 'options_source' => 'rooms'],
            ], JSON_UNESCAPED_UNICODE),
            'sort_order' => 26,
            'is_active' => true,
            'show_in_menu' => true,
            'menu_locations' => json_encode(['reservation', 'frontdesk']),
            'menu_top_order' => 20,
            'menu_group_order' => 10,
            'menu_item_order' => 26,
            'updated_at' => $now,
            'created_at' => $now,
        ]);
        $reportId = DB::table('report_definitions')->where('code', self::REPORT)->value('id');
        DB::table('report_definition_template')->updateOrInsert(
            ['report_definition_id' => $reportId, 'template_id' => $templateId],
            ['is_default' => true, 'sort_order' => 0, 'updated_at' => $now, 'created_at' => $now]
        );
    }

    private function parameter(string $name, string $type, int $position, ?int $maxLength = null): array
    {
        return [
            'name' => $name,
            'mode' => 'IN',
            'data_type' => $type,
            'database_type' => $type === 'varchar' ? "varchar({$maxLength})" : 'date',
            'max_length' => $maxLength,
            'numeric_precision' => null,
            'numeric_scale' => null,
            'position' => $position,
            'required' => true,
        ];
    }
};
