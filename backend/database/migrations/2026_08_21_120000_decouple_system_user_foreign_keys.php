<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * These tables may live in a branch database while the referenced users
     * live in mysql_system. MySQL cannot enforce a foreign key across the
     * configured database connections, so user IDs remain scalar references.
     */
    private array $userForeignKeys = [
        'activity_logs' => 'user_id',
        'booking_room_services' => 'user_id',
        'companies' => 'sales_person_id',
        'housekeeping_service_bills' => 'user_id',
        'payments' => 'user_id',
        'payment_debt_settlements' => 'user_id',
        'service_bills' => 'user_id',
        'template_versions' => 'updated_by',
        'user_branches' => 'user_id',
        'user_roles' => 'user_id',
        'user_settings' => 'user_id',
    ];

    public function up(): void
    {
        $connection = Schema::getConnection();

        if ($connection->getDriverName() !== 'mysql') {
            return;
        }

        $database = $connection->getDatabaseName();

        foreach ($this->userForeignKeys as $table => $column) {
            if (!Schema::hasTable($table) || !Schema::hasColumn($table, $column)) {
                continue;
            }

            $constraint = $connection->selectOne(
                'SELECT CONSTRAINT_NAME FROM information_schema.KEY_COLUMN_USAGE '
                . 'WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ? '
                . "AND REFERENCED_TABLE_NAME = 'users' LIMIT 1",
                [$database, $table, $column]
            );

            if ($constraint?->CONSTRAINT_NAME) {
                Schema::table($table, function (Blueprint $blueprint) use ($constraint) {
                    $blueprint->dropForeign($constraint->CONSTRAINT_NAME);
                });
            }
        }
    }

    public function down(): void
    {
        // Cross-connection User foreign keys must not be recreated here.
    }
};
