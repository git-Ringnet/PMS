<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table): void {
            $table->boolean('is_service_only')->default(false);
        });

        Schema::table('booking_rooms', function (Blueprint $table): void {
            $table->unsignedBigInteger('room_class_id')->nullable()->change();
        });

        // Backfill only bookings with at least one verified virtual room and
        // no physical or unassigned room rows. Include soft-deleted rows so an
        // old mixed stay is never reclassified after a physical row is deleted.
        $hasVirtualRoom = DB::table('booking_rooms as virtual_room')
            ->selectRaw('1')
            ->whereColumn('virtual_room.booking_id', 'bookings.id')
            ->where(function ($query): void {
                $query->where('virtual_room.room_number', 'like', '0%')
                    ->orWhereExists(function ($roomQuery): void {
                        $roomQuery->selectRaw('1')
                            ->from('rooms as virtual_room_record')
                            ->whereColumn('virtual_room_record.room_number', 'virtual_room.room_number')
                            ->where('virtual_room_record.is_internal', true);
                    });
            });

        $hasPhysicalOrUnassignedRoom = DB::table('booking_rooms as other_room')
            ->selectRaw('1')
            ->whereColumn('other_room.booking_id', 'bookings.id')
            ->where(function ($query): void {
                $query->whereNull('other_room.room_number')
                    ->orWhere('other_room.room_number', '')
                    ->orWhere(function ($assigned): void {
                        $assigned->where('other_room.room_number', 'not like', '0%')
                            ->whereNotExists(function ($roomQuery): void {
                                $roomQuery->selectRaw('1')
                                    ->from('rooms as other_room_record')
                                    ->whereColumn('other_room_record.room_number', 'other_room.room_number')
                                    ->where('other_room_record.is_internal', true);
                            });
                    });
            });

        DB::table('bookings')
            ->whereExists($hasVirtualRoom)
            ->whereNotExists($hasPhysicalOrUnassignedRoom)
            ->update(['is_service_only' => true]);
    }

    public function down(): void
    {
        if (DB::table('booking_rooms')->whereNull('room_class_id')->exists()) {
            throw new RuntimeException(
                'Cannot rollback virtual folio support while booking_rooms contains rows without room_class_id.'
            );
        }

        Schema::table('booking_rooms', function (Blueprint $table): void {
            $table->unsignedBigInteger('room_class_id')->nullable(false)->change();
        });

        Schema::table('bookings', function (Blueprint $table): void {
            $table->dropColumn('is_service_only');
        });
    }
};
