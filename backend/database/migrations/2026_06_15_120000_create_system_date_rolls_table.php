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
        Schema::create('system_date_rolls', function (Blueprint $table) {
            $table->id();
            $table->dateTime('system_date');
            $table->dateTime('actual_date');
            $table->string('shift'); // Ca 0, Ca 1, Ca 2
            $table->string('username');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('system_date_rolls');
    }
};
