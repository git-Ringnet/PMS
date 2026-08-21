<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private array $systemUserForeignKeys = [
        'user_branches' => 'user_id',
        'user_roles' => 'user_id',
        'user_settings' => 'user_id',
    ];

    public function up(): void
    {
        $connection = Schema::getConnection();

        if ($connection->getName() !== 'mysql_system' || $connection->getDriverName() !== 'mysql') {
            return;
        }

        foreach ($this->systemUserForeignKeys as $table => $column) {
            if (!Schema::hasTable($table) || !Schema::hasColumn($table, $column)) {
                continue;
            }

            $database = $connection->getDatabaseName();
            $hasForeignKey = $connection->selectOne(
                'SELECT CONSTRAINT_NAME FROM information_schema.KEY_COLUMN_USAGE '
                . 'WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ? '
                . "AND REFERENCED_TABLE_NAME = 'users' LIMIT 1",
                [$database, $table, $column]
            );

            if (!$hasForeignKey) {
                Schema::table($table, function (Blueprint $blueprint) use ($column) {
                    $blueprint->foreign($column)->references('id')->on('users')->cascadeOnDelete();
                });
            }
        }
    }

    public function down(): void
    {
        // System user foreign keys are part of the shared account integrity model.
    }
};
