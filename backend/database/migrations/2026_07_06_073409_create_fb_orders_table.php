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
        Schema::create('fb_orders', function (Blueprint $table) {
            $table->id();
            $table->string('outlet_code')->nullable();
            $table->foreignId('table_id')->constrained('fb_tables')->onDelete('cascade');
            $table->string('name')->default('Bill 1');
            $table->string('status')->default('serving'); // serving, waiting, paid, cancelled
            $table->string('customer_name')->nullable();
            $table->string('customer_phone')->nullable();
            $table->integer('guest_count')->default(1);
            $table->text('public_note')->nullable();
            $table->text('internal_note')->nullable();
            $table->unsignedBigInteger('promotion_id')->nullable();
            $table->decimal('total_amount', 15, 2)->default(0);
            $table->unsignedBigInteger('creator_id')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('fb_orders');
    }
};
