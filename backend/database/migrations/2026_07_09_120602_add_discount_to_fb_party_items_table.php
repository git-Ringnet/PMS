<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fb_party_items', function (Blueprint $table) {
            if (!Schema::hasColumn('fb_party_items', 'discount')) {
                $table->decimal('discount', 15, 2)->default(0)->after('price');
            }
        });
    }

    public function down(): void
    {
        Schema::table('fb_party_items', function (Blueprint $table) {
            if (Schema::hasColumn('fb_party_items', 'discount')) {
                $table->dropColumn('discount');
            }
        });
    }
};
