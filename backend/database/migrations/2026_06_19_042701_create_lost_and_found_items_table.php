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
            $table->date('date_found')->nullable();
            $table->string('who_found', 200)->nullable();
            $table->string('received', 200)->nullable();
            $table->date('date_handling')->nullable();
            $table->string('method_handling', 200)->nullable();
            $table->string('delieved_handling', 200)->nullable();
            $table->string('received_handling', 200)->nullable();
            $table->string('remarks', 500)->nullable();
            $table->string('where_found', 200)->nullable();
            $table->string('guest_info', 200)->nullable();
            $table->string('storage_location', 200)->nullable();
            $table->date('date_reported')->nullable();
            // Dòng nghiệp vụ chỉ có hai trạng thái: Lost và Found.
            $table->string('status', 5)->default('lost');

            $table->longText('image')->nullable();
            $table->timestamps();

            $table->index(['status', 'date_found']);
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
