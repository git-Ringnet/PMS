<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fb_party_payments', function (Blueprint $table) {
            if (!Schema::hasColumn('fb_party_payments', 'status')) {
                $table->string('status')->default('active')->after('note');
            }
        });
    }

    public function down(): void
    {
        Schema::table('fb_party_payments', function (Blueprint $table) {
            if (Schema::hasColumn('fb_party_payments', 'status')) {
                $table->dropColumn('status');
            }
        });
    }
};
