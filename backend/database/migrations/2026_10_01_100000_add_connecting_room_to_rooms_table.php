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
        if (Schema::hasTable('rooms') && !Schema::hasColumn('rooms', 'connecting_room')) {
            Schema::table('rooms', function (Blueprint $table) {
                $table->string('connecting_room', 50)->nullable()->after('linked_room');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('rooms') && Schema::hasColumn('rooms', 'connecting_room')) {
            Schema::table('rooms', function (Blueprint $table) {
                $table->dropColumn('connecting_room');
            });
        }
    }
};
