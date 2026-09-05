<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::connection()->getDriverName() !== 'mysql') {
            return;
        }

        $template = DB::table('templates')->where('report', 'CHECKED_OUT_GUESTS_STANDARD')->first();
        if (! $template) {
            return;
        }

        $css = (string) $template->css;
        $replacements = [
            '.hotel-header{display:grid;grid-template-columns:38% 62%;min-height:105px;align-items:center}' => '.hotel-header{display:grid;grid-template-columns:38% 62%;align-items:center}',
            '.hotel-logo{display:flex;align-items:center;padding-left:16px;min-height:82px}' => '.hotel-logo{display:flex;align-items:center}',
            '.hotel-logo img{max-width:120px;max-height:82px;object-fit:contain}' => '.hotel-logo img,.hotel-logo-image{display:block;max-width:120px;max-height:70px;object-fit:contain}',
            'hr{border:0;border-top:1px solid #111;margin:8px 0 48px}' => 'hr,.header-divider{border:0;border-top:1px solid #111;margin:0}',
            'h1{margin:0 0 34px;text-align:center;font-size:18px;font-weight:700}' => 'h1{margin:0;text-align:center;font-size:18px;font-weight:700}',
            '.period{margin:0 0 25px;text-align:center;font-weight:700}' => '.period{margin:0;text-align:center;font-weight:700}',
        ];

        DB::table('templates')->where('id', $template->id)->update([
            'css' => str_replace(array_keys($replacements), array_values($replacements), $css),
            'version' => '1.5',
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() !== 'mysql') {
            return;
        }

        DB::table('templates')->where('report', 'CHECKED_OUT_GUESTS_STANDARD')->update([
            'version' => '1.4',
            'updated_at' => now(),
        ]);
    }
};
