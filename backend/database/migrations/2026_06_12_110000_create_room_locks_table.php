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
        Schema::create('room_locks', function (Blueprint $table) {
            $table->id();
            $table->string('room_number');
            $table->foreign('room_number')->references('room_number')->on('rooms')->onDelete('cascade');
            $table->dateTime('start_date')->nullable();
            $table->dateTime('end_date')->nullable();
            $table->string('reason')->nullable();
            $table->integer('maintenance_percent')->default(0);
            $table->string('status')->default('New');
            $table->string('username')->default('NB0016');
            $table->string('lock_type')->nullable(); // OOO, OOS
            $table->unsignedTinyInteger('is_active')->default(1); // 1 = active, 2 = unlocked
            $table->string('unlock_username')->nullable();
            $table->dateTime('unlocked_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('room_locks');
    }
};
