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
        Schema::create('late_checkins', function (Blueprint $table) {
            $table->id();
            $table->string('booking_room_id', 50);
            $table->foreign('booking_room_id')->references('id')->on('booking_rooms')->cascadeOnDelete();
            $table->dateTime('late_checkin_date'); // Ngày thao tác
            $table->dateTime('actual_arrival_date'); // Ngày đến mới
            $table->string('late_checkin_time', 5); // HH:MM
            $table->string('reason', 200); // NightAudit No Show One Day + Charge Room / No Charge
            $table->integer('status');
            $table->string('username', 50);
            $table->string('shift', 10);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('late_checkins');
    }
};
