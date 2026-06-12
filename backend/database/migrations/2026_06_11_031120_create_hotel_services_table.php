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
        Schema::create('hotel_services', function (Blueprint $table) {
            $table->id();
            $table->string('code')->nullable();
            $table->string('name')->nullable();
            $table->decimal('service_charge', 5, 2)->default(5.00);
            $table->decimal('tax', 5, 2)->default(8.00);
            $table->decimal('special_tax', 5, 2)->default(0.00);
            $table->boolean('include_service_charge')->default(true);
            $table->boolean('include_tax')->default(true);
            $table->boolean('include_special_tax')->default(true);
            $table->integer('folio')->default(1);
            $table->string('short_name')->nullable();
            $table->string('unit')->nullable();
            $table->decimal('price', 15, 2)->default(0.00);
            $table->string('department')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('hotel_services');
    }
};
