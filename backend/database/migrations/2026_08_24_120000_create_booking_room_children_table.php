<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('booking_room_children', function (Blueprint $table) {
            $table->id();
            $table->string('booking_child_id', 50);
            $table->string('booking_room_id', 50);
            $table->unsignedTinyInteger('status')->default(1);
            $table->timestamps();

            $table->foreign('booking_child_id')->references('id')->on('booking_children')->cascadeOnDelete();
            $table->foreign('booking_room_id')->references('id')->on('booking_rooms')->cascadeOnDelete();
            $table->unique(['booking_child_id', 'booking_room_id']);
            $table->index(['booking_room_id', 'status']);
        });

        DB::table('booking_children')
            ->whereNotNull('booking_room_id')
            ->orderBy('id')
            ->get(['id', 'booking_room_id', 'child_status'])
            ->each(function ($child) {
                DB::table('booking_room_children')->insert([
                    'booking_child_id' => $child->id,
                    'booking_room_id' => $child->booking_room_id,
                    'status' => (int) $child->child_status === 0 ? 1 : (int) $child->child_status,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('booking_room_children');
    }
};