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
        Schema::create('fb_combos', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('parent_id');
            $table->unsignedBigInteger('child_id');
            $table->integer('quantity')->default(1);
            $table->decimal('price', 15, 2)->nullable();
            $table->timestamps();

            $table->foreign('parent_id')->references('id')->on('fb_products')->onDelete('cascade');
            $table->foreign('child_id')->references('id')->on('fb_products')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('fb_combos');
    }
};
