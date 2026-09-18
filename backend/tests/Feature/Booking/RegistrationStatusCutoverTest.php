<?php

namespace Tests\Feature\Booking;

use App\Services\RegistrationStatusSql;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Tests\TestCase;

class RegistrationStatusCutoverTest extends TestCase
{
    use RefreshDatabase;

    public function test_cutover_handles_colliding_ids_rerun_and_rollback_after_new_writes(): void
    {
        $migration = require database_path('migrations/2026_09_14_120000_use_booking_registration_status_codes.php');
        $migration->down();
        DB::table('booking_statuses')->insert(['id' => 0, 'name' => 'Reservation']);
        DB::table('registration_statuses')->insert([
            ['id' => 2, 'booking_status_id' => 20, 'name' => 'None Guaranteed'],
            ['id' => 20, 'booking_status_id' => 29, 'name' => 'Waiting'],
        ]);
        $base = ['booking_name' => 'Cutover', 'arrival_date' => '2026-09-09', 'departure_date' => '2026-09-11', 'booking_date' => '2026-09-09', 'created_by' => 'test'];
        $first = DB::table('bookings')->insertGetId($base + ['registration_status_id' => 2]);
        $second = DB::table('bookings')->insertGetId($base + ['registration_status_id' => 20]);
        $migration->up();
        $this->assertEquals(20, DB::table('bookings')->where('id', $first)->value('registration_status_id'));
        $this->assertEquals(29, DB::table('bookings')->where('id', $second)->value('registration_status_id'));
        $migration->up();
        $this->assertEquals(20, DB::table('bookings')->where('id', $first)->value('registration_status_id'));
        $third = DB::table('bookings')->insertGetId($base + ['registration_status_id' => 29]);
        DB::table('bookings')->where('id', $first)->update(['registration_status_id' => 29]);
        $migration->down();
        foreach ([$first, $second, $third] as $id) {
            $this->assertEquals(20, DB::table('bookings')->where('id', $id)->value('registration_status_id'));
        }
        $migration->up();
    }

    public function test_sql_conversion_preserves_other_joins_and_is_idempotent(): void
    {
        $sql = 'SELECT b.id FROM bookings b LEFT JOIN registration_statuses AS rs ON rs.id = b.registration_status_id LEFT JOIN rooms r ON r.id = b.id';
        $converted = RegistrationStatusSql::useBusinessCodes($sql);
        $this->assertStringContainsString('rs.booking_status_id = b.registration_status_id', $converted);
        $this->assertStringContainsString('r.id = b.id', $converted);
        $this->assertSame($converted, RegistrationStatusSql::useBusinessCodes($converted));
        $reverse = 'SELECT b.id FROM bookings b JOIN `registration_statuses` `s` ON `b`.`registration_status_id` = `s`.`id`';
        $this->assertStringContainsString('`s`.`booking_status_id` =', RegistrationStatusSql::useBusinessCodes($reverse));
    }

    public function test_sql_conversion_supports_unaliased_and_parenthesized_joins(): void
    {
        $sql = <<<'SQL'
            SELECT b.id
            FROM bookings AS b
            LEFT JOIN registration_statuses
                ON (registration_statuses.id) = b.registration_status_id
        SQL;

        $converted = RegistrationStatusSql::useBusinessCodes($sql);

        $this->assertStringContainsString('(registration_statuses.booking_status_id) = b.registration_status_id', $converted);
        $this->assertSame([], RegistrationStatusSql::findLegacyBookingStatusReferences($converted));
    }

    public function test_sql_conversion_rejects_unsupported_legacy_comparisons(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Unsupported legacy registration status reference');

        RegistrationStatusSql::useBusinessCodes(
            'SELECT b.id FROM bookings b JOIN registration_statuses rs ON rs.id > b.registration_status_id',
        );
    }

    public function test_cutover_does_not_leave_the_legacy_fk_on_the_backup_column(): void
    {
        $migration = require database_path('migrations/2026_09_14_120000_use_booking_registration_status_codes.php');
        $migration->down();
        DB::table('booking_statuses')->insert(['id' => 0, 'name' => 'Reservation']);
        DB::table('registration_statuses')->insert([
            ['id' => 302, 'booking_status_id' => 3020, 'name' => 'Cutover FK'],
        ]);

        $base = [
            'booking_name' => 'Cutover FK',
            'arrival_date' => '2026-09-09',
            'departure_date' => '2026-09-11',
            'booking_date' => '2026-09-09',
            'created_by' => 'test',
            'registration_status_id' => 302,
        ];
        DB::table('bookings')->insert($base);
        $migration->up();

        $foreignKeys = Schema::getForeignKeys('bookings');
        $backupForeignKeys = array_filter($foreignKeys, static fn (array $foreignKey): bool => in_array(
            'registration_status_pk_before_codes',
            $foreignKey['columns'],
            true,
        ));
        $businessForeignKey = array_values(array_filter($foreignKeys, static fn (array $foreignKey): bool => in_array(
            'registration_status_id',
            $foreignKey['columns'],
            true,
        ) && $foreignKey['foreign_table'] === 'registration_statuses'));

        $this->assertSame([], array_values($backupForeignKeys));
        $this->assertCount(1, $businessForeignKey);
        $this->assertSame(['booking_status_id'], $businessForeignKey[0]['foreign_columns']);

        $migration->down();
        $restoredForeignKeys = Schema::getForeignKeys('bookings');
        $legacyForeignKey = array_values(array_filter($restoredForeignKeys, static fn (array $foreignKey): bool => in_array(
            'registration_status_id',
            $foreignKey['columns'],
            true,
        ) && $foreignKey['foreign_table'] === 'registration_statuses'));

        $this->assertCount(1, $legacyForeignKey);
        $this->assertSame(['id'], $legacyForeignKey[0]['foreign_columns']);
    }

    public function test_rollback_rejects_a_lost_snapshot_before_mutating_schema(): void
    {
        $migration = require database_path('migrations/2026_09_14_120000_use_booking_registration_status_codes.php');
        $migration->down();
        DB::table('booking_statuses')->insert(['id' => 0, 'name' => 'Reservation']);
        DB::table('registration_statuses')->insert([
            ['id' => 303, 'booking_status_id' => 3030, 'name' => 'Cutover lost snapshot'],
        ]);
        DB::table('bookings')->insert([
            'booking_name' => 'Cutover lost snapshot',
            'arrival_date' => '2026-09-09',
            'departure_date' => '2026-09-11',
            'booking_date' => '2026-09-09',
            'created_by' => 'test',
            'registration_status_id' => 303,
        ]);
        $migration->up();
        DB::table('bookings')->update(['registration_status_id' => null]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('lost its business status');
        $migration->down();
    }

    public function test_cutover_maps_booking_cancel_config_and_restores_its_snapshot(): void
    {
        $migration = require database_path('migrations/2026_09_14_120000_use_booking_registration_status_codes.php');
        $migration->down();
        DB::table('booking_statuses')->insert(['id' => 0, 'name' => 'Reservation']);
        DB::table('registration_statuses')->insert([
            ['id' => 402, 'booking_status_id' => 4020, 'name' => 'Cancel config'],
        ]);
        DB::table('hotel_configs')->updateOrInsert(
            ['name' => 'RegistrationStatusId_BookingCancel'],
            ['value' => '402', 'description' => 'Cancel status', 'is_visible' => true],
        );

        $migration->up();
        $this->assertSame('4020', DB::table('hotel_configs')->where('name', 'RegistrationStatusId_BookingCancel')->value('value'));

        $migration->down();
        $this->assertSame('402', DB::table('hotel_configs')->where('name', 'RegistrationStatusId_BookingCancel')->value('value'));
    }

    public function test_cutover_rejects_an_ambiguous_booking_cancel_config_value(): void
    {
        $migration = require database_path('migrations/2026_09_14_120000_use_booking_registration_status_codes.php');
        $migration->down();
        DB::table('booking_statuses')->insert(['id' => 0, 'name' => 'Reservation']);
        DB::table('registration_statuses')->insert([
            ['id' => 410, 'booking_status_id' => 420, 'name' => 'Ambiguous source'],
            ['id' => 420, 'booking_status_id' => 430, 'name' => 'Ambiguous code'],
        ]);
        DB::table('hotel_configs')->updateOrInsert(
            ['name' => 'RegistrationStatusId_BookingCancel'],
            ['value' => '420', 'description' => 'Ambiguous cancel status', 'is_visible' => true],
        );

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Ambiguous registration status value');
        $migration->up();
    }

    public function test_cutover_maps_report_parameter_defaults_and_restores_them(): void
    {
        $migration = require database_path('migrations/2026_09_14_120000_use_booking_registration_status_codes.php');
        $migration->down();
        DB::table('booking_statuses')->insert(['id' => 0, 'name' => 'Reservation']);
        DB::table('registration_statuses')->insert([
            ['id' => 412, 'booking_status_id' => 4220, 'name' => 'Report default'],
        ]);
        DB::table('report_data_sources')->insert([
            'code' => 'CUTOVER_DEFAULTS',
            'name' => 'Cutover defaults',
            'schema_name' => 'pms',
            'object_name' => 'rpt_cutover_defaults',
            'parameter_schema' => json_encode([]),
            'sample_parameters' => json_encode(['p_registration_status_id' => 412]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $migration->up();
        $this->assertSame(
            4220,
            json_decode((string) DB::table('report_data_sources')->where('code', 'CUTOVER_DEFAULTS')->value('sample_parameters'), true)['p_registration_status_id'],
        );

        $migration->down();
        $this->assertSame(
            412,
            json_decode((string) DB::table('report_data_sources')->where('code', 'CUTOVER_DEFAULTS')->value('sample_parameters'), true)['p_registration_status_id'],
        );
    }

    public function test_sql_conversion_covers_all_repository_report_joins(): void
    {
        foreach (glob(database_path('migrations/*.php')) as $file) {
            $sql = file_get_contents($file);
            if (! str_contains($sql, 'rs.id = b.registration_status_id')) {
                continue;
            }
            $this->assertStringNotContainsString('rs.id = b.registration_status_id', RegistrationStatusSql::useBusinessCodes($sql), basename($file));
        }
    }
}
