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
        Schema::create('room_forms', function (Blueprint $table) {
            $table->id();
            $table->string('name'); // Dạng phòng (e.g. Double, Twin...)
            $table->integer('max_adults')->default(2); // Người lớn (số khách tối đa)
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('room_forms');
    }
};
