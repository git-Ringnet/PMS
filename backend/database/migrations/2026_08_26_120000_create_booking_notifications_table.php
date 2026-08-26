<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('booking_notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->constrained('bookings')->cascadeOnDelete();
            $table->enum('scope_type', ['booking', 'room'])->default('booking');
            $table->json('booking_room_ids')->nullable();
            $table->date('starts_on');
            $table->date('ends_on');
            $table->text('description');
            // User accounts live in mysql_system; tenant databases cannot enforce
            // foreign keys across connections, so retain scalar audited IDs only.
            $table->unsignedBigInteger('created_by_user_id')->nullable()->index();
            $table->unsignedBigInteger('updated_by_user_id')->nullable()->index();
            $table->timestamps();

            $table->index(['booking_id', 'starts_on', 'ends_on']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('booking_notifications');
    }
};
