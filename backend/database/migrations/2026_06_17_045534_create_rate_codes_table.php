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
        Schema::create('rate_codes', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique();
            $table->string('description', 150)->nullable();
            $table->date('begin_date');
            $table->date('end_date');
            $table->boolean('include_bf')->default(false);
            $table->string('currency', 5)->default('VND');
            $table->string('promotion_code', 20)->nullable();
            $table->integer('source_code')->nullable();
            $table->integer('market_segment')->nullable();
            $table->string('type', 10)->nullable();
            $table->json('value')->nullable();
            $table->boolean('disable')->default(false);
            $table->boolean('allow_change_rate')->default(false);
            $table->boolean('is_channel_manager')->default(false);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('rate_codes');
    }
};
