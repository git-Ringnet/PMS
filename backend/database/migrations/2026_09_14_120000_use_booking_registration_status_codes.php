<?php

use App\Services\RegistrationStatusSql;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const STATE = 'booking_status_code_cutover';

    private const BACKUP = 'registration_status_pk_before_codes';

    private const LEGACY_FOREIGN_KEYS = 'legacy_foreign_keys';

    private const BUSINESS_INDEX_CREATED = 'business_index_created';

    private const BOOKING_CANCEL_CONFIG = 'booking_cancel_config';

    private const REPORT_PARAMETER_DEFAULTS = 'report_parameter_defaults';

    // MySQL DDL is not transactional. Run with booking writes paused, after a backup.
    // The original FK column and procedure definitions are retained for recovery.
    public function up(): void
    {
        if (! Schema::hasTable('bookings') || ! Schema::hasTable('registration_statuses')) {
            return;
        }
        $hasBackup = Schema::hasColumn('bookings', self::BACKUP);
        $hasCurrent = Schema::hasColumn('bookings', 'registration_status_id');
        if (! $hasBackup && ! $hasCurrent) {
            throw new RuntimeException('Cannot cut over booking registration status: neither source nor backup column exists.');
        }
        $duplicate = DB::table('registration_statuses')->whereNotNull('booking_status_id')
            ->groupBy('booking_status_id')->havingRaw('COUNT(*) > 1')->exists();
        if ($duplicate) {
            throw new RuntimeException('Duplicate registration booking_status_id values: resolve catalogue mapping before cutover.');
        }

        $sourceColumn = Schema::hasColumn('bookings', self::BACKUP) ? self::BACKUP : 'registration_status_id';
        $invalid = DB::table('bookings as b')->leftJoin('registration_statuses as rs', 'rs.id', '=', 'b.'.$sourceColumn)
            ->whereNotNull('b.'.$sourceColumn)->whereNull('rs.booking_status_id')->exists();
        if ($invalid) {
            throw new RuntimeException('Unmapped booking registration status: cutover expects primary keys, not mixed business codes.');
        }

        if (! Schema::hasTable(self::STATE)) {
            Schema::create(self::STATE, function (Blueprint $table) {
                $table->string('name')->primary();
                $table->longText('payload')->nullable();
            });
        }
        $this->snapshotLegacyForeignKeys();
        $this->snapshotProcedures();
        $this->migrateBookingCancelConfig();
        $this->migrateReportParameterDefaults();
        $indexCreated = false;
        if (! Schema::hasIndex('registration_statuses', 'registration_status_business_code_unique', 'unique')) {
            if (Schema::hasIndex('registration_statuses', 'registration_status_business_code_unique')) {
                throw new RuntimeException('Registration status business code index exists but is not unique.');
            }
            $indexCreated = true;
        }
        if (! DB::table(self::STATE)->where('name', self::BUSINESS_INDEX_CREATED)->exists()) {
            DB::table(self::STATE)->insert([
                'name' => self::BUSINESS_INDEX_CREATED,
                'payload' => $indexCreated ? '1' : '0',
            ]);
        }
        if ($indexCreated && ! Schema::hasIndex('registration_statuses', 'registration_status_business_code_unique', 'unique')) {
            Schema::table('registration_statuses', fn (Blueprint $table) => $table->unique('booking_status_id', 'registration_status_business_code_unique'));
        }
        if (! Schema::hasColumn('bookings', self::BACKUP)) {
            $this->dropForeignKeysForColumn('bookings', 'registration_status_id');
            Schema::table('bookings', fn (Blueprint $table) => $table->renameColumn('registration_status_id', self::BACKUP));
        }
        // A previous attempt may have renamed the column after dropping its
        // constraint. Remove any retained legacy FK before adding the new one.
        $this->dropForeignKeysForColumn('bookings', self::BACKUP);
        if (! Schema::hasColumn('bookings', 'registration_status_id')) {
            Schema::table('bookings', function (Blueprint $table) {
                $table->unsignedInteger('registration_status_id')->nullable();
                $table->foreign('registration_status_id', 'bookings_registration_business_code_foreign')
                    ->references('booking_status_id')->on('registration_statuses')->nullOnDelete()->cascadeOnUpdate();
            });
        }
        if (! DB::table(self::STATE)->where('name', 'data_complete')->exists()) {
            DB::transaction(function () {
                $mapping = DB::table('registration_statuses')->pluck('booking_status_id', 'id');
                // Match exclusively against the retained PK column, never against partially converted values.
                foreach ($mapping as $id => $code) {
                    DB::table('bookings')->where(self::BACKUP, $id)->update(['registration_status_id' => $code]);
                }
                DB::table(self::STATE)->insert(['name' => 'data_complete', 'payload' => '1']);
            });
        }
        $this->applyProcedures(false);
    }

    public function down(): void
    {
        $hasState = Schema::hasTable(self::STATE);
        $hasBackup = Schema::hasColumn('bookings', self::BACKUP);
        if (! $hasState && ! $hasBackup) {
            return;
        }
        if (! $hasState || ! $hasBackup || ! Schema::hasColumn('bookings', 'registration_status_id')) {
            throw new RuntimeException('Cannot roll back booking registration status cutover: schema is only partially cut over.');
        }
        // Resolve current business codes, so bookings created or edited after cutover also roll back correctly.
        $invalid = DB::table('bookings as b')->leftJoin('registration_statuses as rs', 'rs.booking_status_id', '=', 'b.registration_status_id')
            ->whereNotNull('b.registration_status_id')->whereNull('rs.id')->exists();
        if ($invalid) {
            throw new RuntimeException('Cannot roll back unmapped booking business codes.');
        }
        $lostSnapshot = DB::table('bookings')->whereNull('registration_status_id')->whereNotNull(self::BACKUP)->exists();
        if ($lostSnapshot) {
            throw new RuntimeException('Cannot roll back: a booking lost its business status after cutover.');
        }
        DB::transaction(function () {
            foreach (DB::table('registration_statuses')->pluck('id', 'booking_status_id') as $code => $id) {
                DB::table('bookings')->where('registration_status_id', $code)->update([self::BACKUP => $id]);
            }
            DB::table('bookings')->whereNull('registration_status_id')->update([self::BACKUP => null]);
        });
        $this->dropForeignKeysForColumn('bookings', 'registration_status_id', 'registration_statuses', ['booking_status_id']);
        Schema::table('bookings', fn (Blueprint $table) => $table->dropColumn('registration_status_id'));
        Schema::table('bookings', fn (Blueprint $table) => $table->renameColumn(self::BACKUP, 'registration_status_id'));
        $this->restoreLegacyForeignKeys();
        $this->applyProcedures(true);
        $this->restoreBookingCancelConfig();
        $this->restoreReportParameterDefaults();
        if (DB::table(self::STATE)->where('name', self::BUSINESS_INDEX_CREATED)->value('payload') === '1'
            && Schema::hasIndex('registration_statuses', 'registration_status_business_code_unique', 'unique')) {
            Schema::table('registration_statuses', fn (Blueprint $table) => $table->dropUnique('registration_status_business_code_unique'));
        }
        Schema::drop(self::STATE);
    }

    private function snapshotLegacyForeignKeys(): void
    {
        if (DB::table(self::STATE)->where('name', self::LEGACY_FOREIGN_KEYS)->exists()) {
            return;
        }

        $foreignKeys = array_values(array_filter(
            Schema::getForeignKeys('bookings'),
            static fn (array $foreignKey): bool => (in_array('registration_status_id', $foreignKey['columns'], true)
                || in_array(self::BACKUP, $foreignKey['columns'], true))
                && $foreignKey['foreign_table'] === 'registration_statuses'
                && $foreignKey['foreign_columns'] === ['id'],
        ));
        DB::table(self::STATE)->insert([
            'name' => self::LEGACY_FOREIGN_KEYS,
            'payload' => json_encode($foreignKeys, JSON_THROW_ON_ERROR),
        ]);
    }

    private function migrateBookingCancelConfig(): void
    {
        if (DB::table(self::STATE)->where('name', self::BOOKING_CANCEL_CONFIG)->exists()) {
            return;
        }

        $row = Schema::hasTable('hotel_configs')
            ? DB::table('hotel_configs')->where('name', 'RegistrationStatusId_BookingCancel')->first()
            : null;
        $before = $row ? (array) $row : null;
        $after = $before;
        if ($row && $this->hasStatusParameterValue($row->value ?? null)) {
            $after['value'] = $this->mapLegacyStatusValue($row->value, 'hotel_configs.RegistrationStatusId_BookingCancel');
        }

        DB::transaction(function () use ($before, $after): void {
            DB::table(self::STATE)->insert([
                'name' => self::BOOKING_CANCEL_CONFIG,
                'payload' => json_encode(['before' => $before, 'after' => $after], JSON_THROW_ON_ERROR),
            ]);
            if ($before !== null && $after !== null && ($before['value'] ?? null) !== ($after['value'] ?? null)) {
                DB::table('hotel_configs')->where('id', $before['id'])->update([
                    'value' => $after['value'],
                    'updated_at' => now(),
                ]);
            }
        });
    }

    private function migrateReportParameterDefaults(): void
    {
        if (DB::table(self::STATE)->where('name', self::REPORT_PARAMETER_DEFAULTS)->exists()) {
            return;
        }

        $targets = [
            ['table' => 'report_definitions', 'column' => 'parameter_ui_schema'],
            ['table' => 'report_data_sources', 'column' => 'sample_parameters'],
            ['table' => 'templates', 'column' => 'parameter_defaults'],
            ['table' => 'template_versions', 'column' => 'parameter_defaults'],
        ];
        $snapshots = [];

        foreach ($targets as $target) {
            if (! Schema::hasTable($target['table']) || ! Schema::hasColumn($target['table'], $target['column'])) {
                continue;
            }
            foreach (DB::table($target['table'])->whereNotNull($target['column'])->get(['id', $target['column']]) as $row) {
                $before = (string) $row->{$target['column']};
                [$after, $changed] = $this->convertReportParameterJson(
                    $before,
                    $target['table'].'#'.$row->id.'.'.$target['column'],
                );
                if (! $changed) {
                    continue;
                }
                $snapshots[] = [
                    'table' => $target['table'],
                    'id' => $row->id,
                    'column' => $target['column'],
                    'before' => $before,
                    'after' => $after,
                ];
            }
        }

        DB::transaction(function () use ($snapshots): void {
            DB::table(self::STATE)->insert([
                'name' => self::REPORT_PARAMETER_DEFAULTS,
                'payload' => json_encode($snapshots, JSON_THROW_ON_ERROR),
            ]);
            foreach ($snapshots as $snapshot) {
                DB::table($snapshot['table'])->where('id', $snapshot['id'])->update([
                    $snapshot['column'] => $snapshot['after'],
                ]);
            }
        });
    }

    private function restoreBookingCancelConfig(): void
    {
        $payload = DB::table(self::STATE)->where('name', self::BOOKING_CANCEL_CONFIG)->value('payload');
        if (! $payload || ! Schema::hasTable('hotel_configs')) {
            return;
        }
        $snapshot = json_decode($payload, true, 512, JSON_THROW_ON_ERROR);
        $before = $snapshot['before'] ?? null;
        if (! is_array($before) || empty($before['name'])) {
            return;
        }

        $existing = DB::table('hotel_configs')->where('name', $before['name'])->first();
        $attributes = $before;
        unset($attributes['id']);
        if ($existing) {
            DB::table('hotel_configs')->where('id', $existing->id)->update($attributes);
        } else {
            DB::table('hotel_configs')->insert($attributes);
        }
    }

    private function restoreReportParameterDefaults(): void
    {
        $payload = DB::table(self::STATE)->where('name', self::REPORT_PARAMETER_DEFAULTS)->value('payload');
        if (! $payload) {
            return;
        }
        $snapshots = json_decode($payload, true, 512, JSON_THROW_ON_ERROR);
        foreach ($snapshots as $snapshot) {
            if (! is_array($snapshot) || ! isset($snapshot['table'], $snapshot['id'], $snapshot['column'], $snapshot['before'])) {
                continue;
            }
            if (! Schema::hasTable($snapshot['table']) || ! Schema::hasColumn($snapshot['table'], $snapshot['column'])) {
                throw new RuntimeException('Cannot restore report parameter snapshot: table or column is missing.');
            }
            if (! DB::table($snapshot['table'])->where('id', $snapshot['id'])->exists()) {
                throw new RuntimeException('Cannot restore report parameter snapshot: row is missing.');
            }
            DB::table($snapshot['table'])->where('id', $snapshot['id'])->update([
                $snapshot['column'] => $snapshot['before'],
            ]);
        }
    }

    /**
     * @return array{0: string, 1: bool}
     */
    private function convertReportParameterJson(string $json, string $context): array
    {
        $decoded = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        $changed = false;
        $walk = function (&$value, string $path) use (&$walk, &$changed, $context): void {
            if (! is_array($value)) {
                return;
            }
            if (($value['name'] ?? null) === 'p_registration_status_id' && array_key_exists('default', $value)
                && $this->hasStatusParameterValue($value['default'])) {
                $mapped = $this->mapLegacyStatusValue($value['default'], $context.'.'.$path.'.default');
                if ($mapped !== $value['default']) {
                    $value['default'] = $mapped;
                    $changed = true;
                }
            }
            if (array_key_exists('p_registration_status_id', $value) && $this->hasStatusParameterValue($value['p_registration_status_id'])) {
                $mapped = $this->mapLegacyStatusValue($value['p_registration_status_id'], $context.'.'.$path.'.p_registration_status_id');
                if ($mapped !== $value['p_registration_status_id']) {
                    $value['p_registration_status_id'] = $mapped;
                    $changed = true;
                }
            }
            foreach ($value as $key => &$child) {
                if (is_array($child)) {
                    $walk($child, $path.'.'.$key);
                }
            }
            unset($child);
        };
        $walk($decoded, '$');

        return [json_encode($decoded, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR), $changed];
    }

    private function hasStatusParameterValue(mixed $value): bool
    {
        return $value !== null && $value !== '';
    }

    private function mapLegacyStatusValue(mixed $value, string $context): int|string
    {
        if (is_string($value)) {
            $candidate = trim($value);
            if ($candidate === '' || ! ctype_digit($candidate)) {
                throw new RuntimeException('Unmapped registration status value in '.$context.'.');
            }
            $id = (int) $candidate;
        } elseif (is_int($value) || (is_float($value) && floor($value) === $value)) {
            $id = (int) $value;
        } else {
            throw new RuntimeException('Unsupported registration status value in '.$context.'.');
        }

        $legacy = DB::table('registration_statuses')->where('id', $id)->first(['id', 'booking_status_id']);
        if (! $legacy || $legacy->booking_status_id === null) {
            throw new RuntimeException('Unmapped registration status value in '.$context.': '.$id.'.');
        }
        $codeMatch = DB::table('registration_statuses')->where('booking_status_id', $id)->get(['id', 'booking_status_id']);
        $sameIdentity = $codeMatch->count() === 1
            && (int) $codeMatch[0]->id === (int) $legacy->id
            && (int) $legacy->booking_status_id === $id;
        if ($codeMatch->isNotEmpty() && ! $sameIdentity) {
            throw new RuntimeException('Ambiguous registration status value in '.$context.': '.$id.'.');
        }

        return is_string($value) ? (string) $legacy->booking_status_id : (int) $legacy->booking_status_id;
    }

    private function dropForeignKeysForColumn(string $table, string $column, ?string $foreignTable = null, ?array $foreignColumns = null): void
    {
        foreach (Schema::getForeignKeys($table) as $foreignKey) {
            if (! in_array($column, $foreignKey['columns'], true)
                || ($foreignTable !== null && $foreignKey['foreign_table'] !== $foreignTable)
                || ($foreignColumns !== null && $foreignKey['foreign_columns'] !== $foreignColumns)) {
                continue;
            }

            $name = $foreignKey['name'];
            Schema::table($table, static function (Blueprint $blueprint) use ($name, $foreignKey): void {
                $blueprint->dropForeign($name ?: $foreignKey['columns']);
            });
        }
    }

    private function restoreLegacyForeignKeys(): void
    {
        $payload = DB::table(self::STATE)->where('name', self::LEGACY_FOREIGN_KEYS)->value('payload');
        $foreignKeys = $payload ? json_decode($payload, true, 512, JSON_THROW_ON_ERROR) : [];

        foreach ($foreignKeys as $foreignKey) {
            if (! is_array($foreignKey) || ! $this->foreignKeyExists($foreignKey)) {
                Schema::table('bookings', static function (Blueprint $blueprint) use ($foreignKey): void {
                    if (! is_array($foreignKey) || empty($foreignKey['columns']) || empty($foreignKey['foreign_table']) || empty($foreignKey['foreign_columns'])) {
                        return;
                    }

                    $definition = $blueprint->foreign($foreignKey['columns'], $foreignKey['name'] ?? null)
                        ->references($foreignKey['foreign_columns'])
                        ->on($foreignKey['foreign_table']);
                    if (! empty($foreignKey['on_delete'])) {
                        $definition->onDelete($foreignKey['on_delete']);
                    }
                    if (! empty($foreignKey['on_update'])) {
                        $definition->onUpdate($foreignKey['on_update']);
                    }
                });
            }
        }
    }

    private function foreignKeyExists(array $expected): bool
    {
        foreach (Schema::getForeignKeys('bookings') as $foreignKey) {
            if (($expected['name'] ?? null) !== null && $foreignKey['name'] === $expected['name']) {
                return true;
            }
            if ($foreignKey['columns'] === ($expected['columns'] ?? [])
                && $foreignKey['foreign_table'] === ($expected['foreign_table'] ?? '')
                && $foreignKey['foreign_columns'] === ($expected['foreign_columns'] ?? [])) {
                return true;
            }
        }

        return false;
    }

    private function snapshotProcedures(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }
        // Snapshot all existing affected procedures before the first schema mutation.
        foreach (DB::select('SELECT ROUTINE_NAME FROM information_schema.ROUTINES WHERE ROUTINE_SCHEMA = DATABASE() AND ROUTINE_TYPE = ?', ['PROCEDURE']) as $routine) {
            $routine = array_change_key_case((array) $routine, CASE_LOWER);
            $name = $routine['routine_name'] ?? null;
            if (! is_string($name) || $name === '') {
                throw new RuntimeException('Cannot read procedure name from information_schema.');
            }
            $key = 'procedure:'.$name;
            if (DB::table(self::STATE)->where('name', $key)->exists()) {
                continue;
            }
            $quoted = '`'.str_replace('`', '``', $name).'`';
            $row = array_change_key_case((array) DB::selectOne('SHOW CREATE PROCEDURE '.$quoted), CASE_LOWER);
            $original = isset($row['create procedure']) ? (string) $row['create procedure'] : null;
            if ($original === null || $original === '') {
                throw new RuntimeException('Cannot read procedure definition: '.$name);
            }
            $updated = RegistrationStatusSql::useBusinessCodes($original);
            if ($updated !== $original) {
                DB::table(self::STATE)->insert(['name' => $key, 'payload' => json_encode([
                    'before' => $original, 'after' => $updated,
                    'sql_mode' => $row['sql_mode'] ?? '',
                ], JSON_THROW_ON_ERROR)]);
            }
        }
    }

    private function applyProcedures(bool $rollback): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }
        $previousMode = DB::selectOne('SELECT @@SESSION.sql_mode AS mode')->mode;
        try {
            foreach (DB::table(self::STATE)->where('name', 'like', 'procedure:%')->get() as $row) {
                $snapshot = json_decode($row->payload, true, 512, JSON_THROW_ON_ERROR);
                $name = substr($row->name, strlen('procedure:'));
                DB::statement('SET SESSION sql_mode = ?', [$snapshot['sql_mode']]);
                DB::unprepared('DROP PROCEDURE IF EXISTS `'.str_replace('`', '``', $name).'`');
                DB::unprepared($snapshot[$rollback ? 'before' : 'after']);
            }
        } finally {
            DB::statement('SET SESSION sql_mode = ?', [$previousMode]);
        }
    }
};
