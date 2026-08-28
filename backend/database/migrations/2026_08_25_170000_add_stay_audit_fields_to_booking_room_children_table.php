<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('booking_room_children', function (Blueprint $table) {
            $table->date('actual_arrival_date')->nullable()->after('status');
            $table->time('actual_arrival_time')->nullable()->after('actual_arrival_date');
            $table->date('actual_checkout_date')->nullable()->after('actual_arrival_time');
            $table->time('actual_checkout_time')->nullable()->after('actual_checkout_date');
            $table->string('checkin_by', 100)->nullable()->after('actual_checkout_time');
            $table->string('checkout_by', 100)->nullable()->after('checkin_by');
        });

        DB::table('booking_room_children')
            ->orderBy('id')
            ->each(function ($assignment): void {
                $room = DB::table('booking_rooms')->where('id', $assignment->booking_room_id)->first();
                if (!$room) {
                    return;
                }

                DB::table('booking_room_children')
                    ->where('id', $assignment->id)
                    ->update([
                        'actual_arrival_date' => $room->actual_arrival_date ?: $room->arrival_date,
                        'actual_arrival_time' => $room->arrival_time,
                        'actual_checkout_date' => $room->CheckoutDate ?: $room->departure_date,
                        'actual_checkout_time' => $room->CheckoutTime ?: '12:00:00',
                        'checkin_by' => $room->check_in_user,
                        'checkout_by' => in_array((int) $assignment->status, [2, 3, 4, 100], true)
                            ? $room->check_out_user
                            : null,
                    ]);
            });
    }

    public function down(): void
    {
        Schema::table('booking_room_children', function (Blueprint $table) {
            $table->dropColumn([
                'actual_arrival_date',
                'actual_arrival_time',
                'actual_checkout_date',
                'actual_checkout_time',
                'checkin_by',
                'checkout_by',
            ]);
        });
    }
};
