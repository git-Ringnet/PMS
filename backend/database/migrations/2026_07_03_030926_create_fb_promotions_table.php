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
        Schema::create('fb_promotions', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->unsignedBigInteger('outlet_id')->nullable();
            $table->decimal('discount_percent', 5, 2)->default(0);
            $table->decimal('increase_percent', 5, 2)->default(0);
            $table->decimal('discount_amount', 15, 2)->default(0);
            $table->decimal('increase_amount', 15, 2)->default(0);
            $table->boolean('is_auto_apply')->default(false);
            $table->boolean('is_active')->default(true);
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->boolean('apply_by_time')->default(false);
            $table->time('start_time')->nullable();
            $table->time('end_time')->nullable();
            $table->unsignedBigInteger('company_id')->nullable();
            $table->unsignedBigInteger('customer_source_id')->nullable();
            $table->boolean('is_all_product')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('fb_promotions');
    }
};
