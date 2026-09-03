<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::connection()->getDriverName() !== 'mysql') return;
        $sourceId = DB::table('report_data_sources')->where('code', 'CANCELLED_ROOMS')->value('id');
        if (! $sourceId) return;

        DB::table('report_data_sources')->where('id', $sourceId)->update([
            'parameter_schema' => json_encode([
                ['name' => 'p_from_date', 'mode' => 'IN', 'data_type' => 'date', 'database_type' => 'date', 'position' => 1, 'required' => true],
                ['name' => 'p_to_date', 'mode' => 'IN', 'data_type' => 'date', 'database_type' => 'date', 'position' => 2, 'required' => true],
                ['name' => 'p_view_type', 'mode' => 'IN', 'data_type' => 'varchar', 'database_type' => 'varchar(20)', 'position' => 3, 'required' => true],
                ['name' => 'p_booking_id', 'mode' => 'IN', 'data_type' => 'bigint', 'database_type' => 'bigint', 'position' => 4, 'required' => false],
                ['name' => 'p_division', 'mode' => 'IN', 'data_type' => 'varchar', 'database_type' => 'varchar(20)', 'position' => 5, 'required' => false],
                ['name' => 'p_group_by_reason', 'mode' => 'IN', 'data_type' => 'tinyint', 'database_type' => 'tinyint', 'position' => 6, 'required' => false],
            ], JSON_UNESCAPED_UNICODE),
            'sample_parameters' => json_encode(['p_from_date' => now()->toDateString(), 'p_to_date' => now()->toDateString(), 'p_view_type' => 'CancelDate', 'p_booking_id' => null, 'p_division' => '', 'p_group_by_reason' => 0]),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        // Data correction; the duplicated parameter schema is not restored.
    }
};
