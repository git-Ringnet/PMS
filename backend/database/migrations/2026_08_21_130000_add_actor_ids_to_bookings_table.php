<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            // User is shared in mysql_system, therefore these are scalar references without FK.
            $table->unsignedBigInteger('created_by_user_id')->nullable()->after('created_by')->index();
            $table->unsignedBigInteger('updated_by_user_id')->nullable()->after('updated_by')->index();
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropColumn(['created_by_user_id', 'updated_by_user_id']);
        });
    }
};
