<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fb_orders', function (Blueprint $table) {
            if (!Schema::hasColumn('fb_orders', 'internal_note_discount')) {
                $table->text('internal_note_discount')->nullable()->after('internal_note');
            }
        });
    }

    public function down(): void
    {
        Schema::table('fb_orders', function (Blueprint $table) {
            if (Schema::hasColumn('fb_orders', 'internal_note_discount')) {
                $table->dropColumn('internal_note_discount');
            }
        });
    }
};
