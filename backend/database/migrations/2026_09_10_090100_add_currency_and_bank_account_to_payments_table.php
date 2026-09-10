<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            if (!Schema::hasColumn('payments', 'currency')) {
                $table->string('currency', 20)->nullable()->after('amount');
            }
            if (!Schema::hasColumn('payments', 'bank_account_id')) {
                $table->unsignedBigInteger('bank_account_id')->nullable()->after('debit_account');
                $table->index('bank_account_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            if (Schema::hasColumn('payments', 'bank_account_id')) {
                $table->dropIndex(['bank_account_id']);
                $table->dropColumn('bank_account_id');
            }
            if (Schema::hasColumn('payments', 'currency')) {
                $table->dropColumn('currency');
            }
        });
    }
};
