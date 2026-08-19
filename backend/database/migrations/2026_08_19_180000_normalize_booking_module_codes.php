<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('bookings')->where('module', 'reservation')->update(['module' => 'SALE']);
        DB::table('bookings')->whereIn('module', ['reception', 'frontdesk'])->update(['module' => 'FO']);
        DB::table('bookings')->where('module', 'housekeeping')->update(['module' => 'HK']);
    }

    public function down(): void
    {
        DB::table('bookings')->where('module', 'SALE')->update(['module' => 'reservation']);
        DB::table('bookings')->where('module', 'FO')->update(['module' => 'reception']);
        DB::table('bookings')->where('module', 'HK')->update(['module' => 'housekeeping']);
    }
};
