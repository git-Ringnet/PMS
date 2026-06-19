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
        Schema::create('lost_and_found_items', function (Blueprint $table) {
            $table->id();
            $table->integer('log_no')->nullable();
            $table->string('item_found', 200);
            $table->time('time_found');
            $table->date('date_found');
            $table->string('who_found', 200);
            $table->string('received', 200);
            $table->date('date_handling')->nullable();
            $table->time('time_handling')->nullable();
            $table->string('method_handling', 200)->nullable();
            $table->string('delieved_handling', 200)->nullable();
            $table->string('received_handling', 200)->nullable();
            $table->string('remarks', 500)->nullable();
            $table->string('where_found', 200);
            $table->boolean('status')->default(0);
            $table->string('image', 200)->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('lost_and_found_items');
    }
};
