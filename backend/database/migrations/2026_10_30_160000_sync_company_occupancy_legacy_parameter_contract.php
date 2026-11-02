<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $connection = DB::getDefaultConnection();
        $db = DB::connection($connection);

        if ($db->getDriverName() !== 'mysql' || ! Schema::connection($connection)->hasTable('report_data_sources')) {
            return;
        }

        $db->table('report_data_sources')->where('code', 'RPT_COMPANY_OCCUPANCY')->update([
            'parameter_schema' => json_encode($this->parameterSchema(), JSON_UNESCAPED_UNICODE),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        // Không khôi phục p_source_code vì đây không phải tham số legacy.
    }

    private function parameterSchema(): array
    {
        return [
            ['name' => 'p_from_date', 'mode' => 'IN', 'data_type' => 'date', 'database_type' => 'date', 'position' => 1, 'required' => true],
            ['name' => 'p_to_date', 'mode' => 'IN', 'data_type' => 'date', 'database_type' => 'date', 'position' => 2, 'required' => true],
            ['name' => 'p_area', 'mode' => 'IN', 'data_type' => 'varchar', 'database_type' => 'varchar', 'position' => 3, 'required' => false],
            ['name' => 'p_company', 'mode' => 'IN', 'data_type' => 'varchar', 'database_type' => 'varchar', 'position' => 4, 'required' => false],
            ['name' => 'p_segment', 'mode' => 'IN', 'data_type' => 'varchar', 'database_type' => 'varchar', 'position' => 5, 'required' => false],
            ['name' => 'p_user_sale', 'mode' => 'IN', 'data_type' => 'varchar', 'database_type' => 'varchar', 'position' => 6, 'required' => false],
            ['name' => 'p_group_by', 'mode' => 'IN', 'data_type' => 'varchar', 'database_type' => 'varchar', 'position' => 7, 'required' => false],
            ['name' => 'p_include_breakfast', 'mode' => 'IN', 'data_type' => 'tinyint', 'database_type' => 'tinyint', 'position' => 8, 'required' => false],
        ];
    }
};
