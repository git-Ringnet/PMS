<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('booking_room_guests', function (Blueprint $table) {
            if (!Schema::hasColumn('booking_room_guests', 'actual_arrival_date')) {
                $table->date('actual_arrival_date')->nullable()->after('is_primary');
            }
            if (!Schema::hasColumn('booking_room_guests', 'actual_arrival_time')) {
                $table->time('actual_arrival_time')->nullable()->after('actual_arrival_date');
            }
            if (!Schema::hasColumn('booking_room_guests', 'actual_checkout_date')) {
                $table->date('actual_checkout_date')->nullable()->after('actual_arrival_time');
            }
            if (!Schema::hasColumn('booking_room_guests', 'actual_checkout_time')) {
                $table->time('actual_checkout_time')->nullable()->after('actual_checkout_date');
            }
            if (!Schema::hasColumn('booking_room_guests', 'checkin_by')) {
                $table->string('checkin_by', 100)->nullable()->after('actual_checkout_time');
            }
            if (!Schema::hasColumn('booking_room_guests', 'checkout_by')) {
                $table->string('checkout_by', 100)->nullable()->after('checkin_by');
            }
            if (!Schema::hasColumn('booking_room_guests', 'breakfast')) {
                $table->boolean('breakfast')->nullable()->after('checkout_by');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('booking_room_guests', function (Blueprint $table) {
            $table->dropColumn([
                'actual_arrival_date',
                'actual_arrival_time',
                'actual_checkout_date',
                'actual_checkout_time',
                'checkin_by',
                'checkout_by',
                'breakfast',
            ]);
        });
    }
};
