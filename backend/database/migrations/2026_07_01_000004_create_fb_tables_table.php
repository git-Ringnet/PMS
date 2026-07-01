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
        Schema::create('fb_tables', function (Blueprint $table) {
            $table->id();
            $table->string('table_code', 10)->nullable(); // TableId in legacy
            $table->string('name', 50);
            $table->string('location_id', 10); // TypeId in legacy (FB_Location)
            $table->integer('row_index')->default(1); // Y position / row index
            $table->integer('col_index')->default(1); // X position / col index
            $table->integer('max_seats')->default(4); // MaxSeats in legacy
            $table->string('status', 10)->default('Active'); // Status in legacy
            $table->string('image', 255)->nullable(); // Image in legacy
            $table->string('note', 100)->nullable(); // Note in legacy
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            // Foreign key to fb_locations
            $table->foreign('location_id')->references('id')->on('fb_locations')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('fb_tables');
    }
};
