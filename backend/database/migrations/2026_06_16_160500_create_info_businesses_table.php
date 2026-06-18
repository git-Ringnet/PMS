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
        if (!Schema::hasTable('info_businesses')) {
            Schema::create('info_businesses', function (Blueprint $table) {
                $table->id();
                $table->string('company_name');
                $table->string('bank_name')->nullable();
                $table->string('chairman')->nullable();
                $table->string('phone')->nullable();
                $table->string('email')->nullable();
                $table->string('director')->nullable();
                $table->text('address')->nullable();
                $table->foreignId('system_branch_id')->nullable()->constrained('system_branches')->onDelete('set null');
                $table->string('chief_accountant')->nullable();
                $table->string('logo_url')->nullable();
                $table->timestamps();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('info_businesses');
    }
};
