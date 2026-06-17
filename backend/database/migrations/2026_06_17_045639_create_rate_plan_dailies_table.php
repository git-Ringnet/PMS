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
        Schema::create('rate_plan_dailies', function (Blueprint $table) {
            $table->id();
            $table->string('rate_code', 20);
            $table->date('date');
            $table->string('code', 10);
            $table->timestamps();
 
            $table->unique(['rate_code', 'date'], 'uq_rate_plan_dailies');
            $table->foreign('rate_code')
                  ->references('code')
                  ->on('rate_codes')
                  ->onDelete('cascade');
            $table->foreign(['rate_code', 'code'])
                  ->references(['rate_code', 'code'])
                  ->on('rate_plans')
                  ->onDelete('cascade')
                  ->onUpdate('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('rate_plan_dailies');
    }
};
