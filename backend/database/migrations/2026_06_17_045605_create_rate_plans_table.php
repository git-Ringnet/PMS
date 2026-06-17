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
        Schema::create('rate_plans', function (Blueprint $table) {
            $table->id();
            $table->string('rate_code', 20);
            $table->string('code', 10);
            $table->string('description', 150)->nullable();
            $table->date('begin_date');
            $table->date('end_date');
            $table->json('period')->nullable();
            $table->timestamps();

            $table->unique(['rate_code', 'code'], 'uq_rate_plan');
            $table->foreign('rate_code')
                  ->references('code')
                  ->on('rate_codes')
                  ->onDelete('cascade')
                  ->onUpdate('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('rate_plans');
    }
};
