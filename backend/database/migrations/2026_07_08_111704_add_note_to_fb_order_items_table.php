<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fb_order_items', function (Blueprint $table) {
            if (!Schema::hasColumn('fb_order_items', 'note')) {
                $table->string('note')->nullable()->after('base_surcharge');
            }
        });
    }

    public function down(): void
    {
        Schema::table('fb_order_items', function (Blueprint $table) {
            if (Schema::hasColumn('fb_order_items', 'note')) {
                $table->dropColumn('note');
            }
        });
    }
};
