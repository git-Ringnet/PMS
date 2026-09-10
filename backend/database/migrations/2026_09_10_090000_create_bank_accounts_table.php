<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bank_accounts', function (Blueprint $table) {
            $table->id();
            $table->string('code', 50);
            // Keep this as text: bank account numbers may contain leading zeroes.
            $table->string('bank_account_number', 255);
            $table->string('accounting_account', 100)->nullable();
            $table->string('currency_code', 20)->nullable();
            $table->string('bank_name', 255);
            $table->date('opened_on')->nullable();
            $table->date('closed_on')->nullable();
            $table->text('description')->nullable();
            $table->boolean('is_intermediary')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->unique('code');
            $table->index(['is_intermediary', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bank_accounts');
    }
};
