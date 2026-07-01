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
        Schema::create('fb_locations', function (Blueprint $table) {
            $table->string('id', 10)->primary(); // char(10) in legacy
            $table->string('name', 50);
            $table->string('note', 100)->nullable();
            $table->boolean('is_active')->default(true);
            $table->string('outlet_code', 10);
            $table->string('color', 50)->nullable();
            $table->string('letter', 10)->nullable();
            $table->string('day_use', 100)->nullable();
            $table->string('provide1', 100)->nullable();
            $table->text('image')->nullable();
            $table->timestamps();

            // Foreign key to outlets
            $table->foreign('outlet_code')->references('code')->on('outlets')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('fb_locations');
    }
};
