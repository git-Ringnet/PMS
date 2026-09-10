<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (!DB::table('hotel_configs')
            ->where('name', 'RuleUserCorrectOrPostBillPaymentOldDay')
            ->exists()) {
            DB::table('hotel_configs')->insert([
                'name' => 'RuleUserCorrectOrPostBillPaymentOldDay',
                'value' => '',
                'description' => 'Comma-separated usernames, job titles, or role codes allowed to operate on old dates. Example: admin,FOM,ACC.',
                'updated_at' => now(),
                'created_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        // Preserve an existing hotel configuration during rollback.
    }
};