<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $schema = json_decode(DB::table('report_definitions')->where('code', 'DEPOSITS_SALE')->value('parameter_ui_schema') ?? '[]', true);
        $byName = collect($schema)->keyBy('name');
        $order = ['p_from_date', 'p_to_date', 'p_shift', 'p_from_time', 'p_to_time', 'p_department', 'p_company', 'p_user', 'p_payment_method', 'p_show_deposit', 'p_show_amount_zero'];
        $ordered = array_values(array_filter(array_map(fn (string $name) => $byName->get($name), $order)));

        DB::table('report_definitions')->where('code', 'DEPOSITS_SALE')->update([
            'parameter_ui_schema' => json_encode($ordered, JSON_UNESCAPED_UNICODE),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        // The filter order is presentation-only.
    }
};
