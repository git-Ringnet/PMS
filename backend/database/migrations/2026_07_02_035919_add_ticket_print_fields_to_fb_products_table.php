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
        Schema::table('fb_products', function (Blueprint $table) {
            $table->boolean('is_print_one_ticket')->default(false)->after('is_pre_printed');
            $table->string('ticket_type')->nullable()->after('is_print_one_ticket');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('fb_products', function (Blueprint $table) {
            $table->dropColumn([
                'is_print_one_ticket',
                'ticket_type',
            ]);
        });
    }
};
