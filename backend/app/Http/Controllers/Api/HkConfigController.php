<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\HkPrintCol;
use App\Models\HkSymbol;
use Illuminate\Http\Request;

class HkConfigController extends Controller
{
    // GET /api/hk-config
    public function index()
    {
        $symbols = HkSymbol::orderBy('group')->orderBy('sort_order')->get();
        $printCols = HkPrintCol::orderBy('template')->orderBy('sort_order')->get();

        return response()->json([
            'symbols'   => $symbols,
            'printCols' => $printCols,
        ]);
    }

    // PUT /api/hk-config/symbols
    public function updateSymbols(Request $request)
    {
        $items = $request->input('symbols', []);
        foreach ($items as $item) {
            HkSymbol::updateOrCreate(
                ['group' => $item['group'], 'status_key' => $item['status_key']],
                [
                    'code'       => $item['code'] ?? '',
                    'label'      => $item['label'] ?? '',
                    'color'      => $item['color'] ?? null,
                    'sort_order' => $item['sort_order'] ?? 0,
                ]
            );
        }
        return response()->json(['message' => 'Luu ky hieu thanh cong']);
    }

    // PUT /api/hk-config/print-cols
    public function updatePrintCols(Request $request)
    {
        $template = $request->input('template'); // worksheet | supervisor
        $cols = $request->input('cols', []);

        HkPrintCol::where('template', $template)->where('is_fixed', false)->delete();

        foreach ($cols as $i => $col) {
            if (!empty($col['is_fixed'])) {
                HkPrintCol::where('template', $template)
                    ->where('label', $col['label'])
                    ->update(['sort_order' => $i + 1]);
            } else {
                HkPrintCol::create([
                    'template'   => $template,
                    'label'      => $col['label'],
                    'width'      => $col['width'] ?? '',
                    'is_fixed'   => false,
                    'sort_order' => $i + 1,
                ]);
            }
        }

        return response()->json(['message' => 'Luu mau in thanh cong']);
    }

    // POST /api/hk-config/reset
    public function reset()
    {
        $seeder = new \Database\Seeders\HkConfigSeeder();
        $seeder->run();
        return response()->json(['message' => 'Khoi phuc cau hinh mac dinh thanh cong']);
    }
}
