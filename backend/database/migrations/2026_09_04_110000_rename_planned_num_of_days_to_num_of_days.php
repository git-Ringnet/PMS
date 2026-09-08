<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Giữ đúng tên dữ liệu của Link Hotel: NumOfDays là số đêm đặt ban đầu.
        Schema::table('booking_rooms', function (Blueprint $table) {
            $table->renameColumn('planned_num_of_days', 'NumOfDays');
        });
    }

    public function down(): void
    {
        Schema::table('booking_rooms', function (Blueprint $table) {
            $table->renameColumn('NumOfDays', 'planned_num_of_days');
        });
    }
};
