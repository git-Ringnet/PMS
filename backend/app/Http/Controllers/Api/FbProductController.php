<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\FbProduct;
use App\Models\Outlet;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class FbProductController extends Controller
{
    public function index()
    {
        $products = FbProduct::with(['category', 'outletPrices.outlet', 'comboItems.child'])->get();
        // Append outlet_code vào mỗi outletPrice để frontend dùng
        $products->each(function ($product) {
            $product->outletPrices->each(function ($op) {
                $op->outlet_code = $op->outlet?->code;
            });
        });
        return response()->json($products);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'fb_product_category_id' => 'required|exists:fb_product_categories,id',
            'name'                   => 'required|string|max:255',
            'product_code'           => 'nullable|string|max:100',
            'name_en'                => 'nullable|string|max:255',
            'short_name'             => 'nullable|string|max:255',
            'service_group'          => 'nullable|string|max:100',
            'vat_billing_name'       => 'nullable|string|max:255',
            'unit_id'                => 'required|exists:units_of_measure,id',
            'barcode'                => 'nullable|string|max:100',
            'note'                   => 'nullable|string',
            'price'                  => 'numeric',
            'original_amount'        => 'nullable|numeric',
            'service_charge_percent' => 'nullable|numeric',
            'service_charge_amount'  => 'nullable|numeric',
            'tax_percent'            => 'nullable|numeric',
            'tax_amount'             => 'nullable|numeric',
            'special_tax_percent'    => 'nullable|numeric',
            'special_tax_amount'     => 'nullable|numeric',
            'is_print'               => 'boolean',
            'is_gate_ticket'         => 'boolean',
            'is_dish_exchange'       => 'boolean',
            'is_pre_printed'         => 'boolean',
            'no_reinvest'            => 'boolean',
            'is_contra'              => 'boolean',
            'flexible_price'         => 'boolean',
            'change_table'           => 'boolean',
            'open_key'               => 'boolean',
            'is_alcohol'             => 'boolean',
            'track_stock'            => 'boolean',
            'processing_time'        => 'integer',
            'serving_time'           => 'integer',
            'is_combo'               => 'boolean',
            'is_active'              => 'boolean',
        ]);

        if ($request->hasFile('image')) {
            $path = $request->file('image')->store('products', 'public');
            $validated['image'] = $path;
        }

        $product = FbProduct::create($validated);

        // Sync Outlet prices (frontend gửi outlet_code, ta resolve sang outlet_id)
        if ($request->has('outlet_prices')) {
            $prices = is_string($request->outlet_prices) ? json_decode($request->outlet_prices, true) : $request->outlet_prices;
            if (is_array($prices)) {
                // Build lookup map outlet_code => outlet_id
                $codes = array_filter(array_column($prices, 'outlet_code'));
                $outletMap = Outlet::whereIn('code', $codes)->pluck('id', 'code');

                foreach ($prices as $priceItem) {
                    $code = $priceItem['outlet_code'] ?? null;
                    $outletId = $outletMap[$code] ?? ($priceItem['outlet_id'] ?? null);
                    if (!$outletId) continue;

                    $product->outletPrices()->create([
                        'outlet_id'              => $outletId,
                        'is_active'              => filter_var($priceItem['is_active'] ?? true, FILTER_VALIDATE_BOOLEAN),
                        'price'                  => $priceItem['price'] ?? 0,
                        'original_amount'        => $priceItem['original_amount'] ?? 0,
                        'service_charge_percent' => $priceItem['service_charge_percent'] ?? 0,
                        'service_charge_amount'  => $priceItem['service_charge_amount'] ?? 0,
                        'tax_percent'            => $priceItem['tax_percent'] ?? 0,
                        'tax_amount'             => $priceItem['tax_amount'] ?? 0,
                        'special_tax_percent'    => $priceItem['special_tax_percent'] ?? 0,
                        'special_tax_amount'     => $priceItem['special_tax_amount'] ?? 0,
                        'combo_original'         => $priceItem['combo_original'] ?? 0,
                        'combo_service'          => $priceItem['combo_service'] ?? 0,
                        'combo_special'          => $priceItem['combo_special'] ?? 0,
                        'combo_tax'              => $priceItem['combo_tax'] ?? 0,
                        'combo_price'            => $priceItem['combo_price'] ?? 0,
                        'update_price'           => filter_var($priceItem['update_price'] ?? false, FILTER_VALIDATE_BOOLEAN),
                        'update_combo_price'     => filter_var($priceItem['update_combo_price'] ?? false, FILTER_VALIDATE_BOOLEAN),
                        'is_expanded'            => filter_var($priceItem['is_expanded'] ?? false, FILTER_VALIDATE_BOOLEAN),
                        'selectedCounterOutlets' => $priceItem['selectedCounterOutlets'] ?? [],
                    ]);
                }
            }
        }

        // Sync Combo items
        if ($request->has('combo_items')) {
            $combos = is_string($request->combo_items) ? json_decode($request->combo_items, true) : $request->combo_items;
            if (is_array($combos)) {
                foreach ($combos as $comboItem) {
                    $product->comboItems()->create([
                        'child_id' => $comboItem['child_id'],
                        'quantity' => $comboItem['quantity'] ?? 1,
                        'price'    => $comboItem['price'] ?? 0,
                    ]);
                }
            }
        }

        $product->load(['outletPrices.outlet', 'comboItems.child']);
        $product->outletPrices->each(fn($op) => $op->outlet_code = $op->outlet?->code);
        return response()->json($product, 201);
    }

    public function update(Request $request, $id)
    {
        $product = FbProduct::findOrFail($id);

        $validated = $request->validate([
            'fb_product_category_id' => 'required|exists:fb_product_categories,id',
            'name'                   => 'required|string|max:255',
            'product_code'           => 'nullable|string|max:100',
            'name_en'                => 'nullable|string|max:255',
            'short_name'             => 'nullable|string|max:255',
            'service_group'          => 'nullable|string|max:100',
            'vat_billing_name'       => 'nullable|string|max:255',
            'unit_id'                => 'required|exists:units_of_measure,id',
            'barcode'                => 'nullable|string|max:100',
            'note'                   => 'nullable|string',
            'price'                  => 'numeric',
            'original_amount'        => 'nullable|numeric',
            'service_charge_percent' => 'nullable|numeric',
            'service_charge_amount'  => 'nullable|numeric',
            'tax_percent'            => 'nullable|numeric',
            'tax_amount'             => 'nullable|numeric',
            'special_tax_percent'    => 'nullable|numeric',
            'special_tax_amount'     => 'nullable|numeric',
            'is_print'               => 'boolean',
            'is_gate_ticket'         => 'boolean',
            'is_dish_exchange'       => 'boolean',
            'is_pre_printed'         => 'boolean',
            'no_reinvest'            => 'boolean',
            'is_contra'              => 'boolean',
            'flexible_price'         => 'boolean',
            'change_table'           => 'boolean',
            'open_key'               => 'boolean',
            'is_alcohol'             => 'boolean',
            'track_stock'            => 'boolean',
            'processing_time'        => 'integer',
            'serving_time'           => 'integer',
            'is_combo'               => 'boolean',
            'is_active'              => 'boolean',
        ]);

        if ($request->hasFile('image')) {
            if ($product->image) {
                Storage::disk('public')->delete($product->image);
            }
            $path = $request->file('image')->store('products', 'public');
            $validated['image'] = $path;
        } elseif ($request->input('remove_image') == '1') {
            if ($product->image) {
                Storage::disk('public')->delete($product->image);
            }
            $validated['image'] = null;
        }

        $product->update($validated);

        // Sync Outlet prices (resolve outlet_code => outlet_id)
        if ($request->has('outlet_prices')) {
            $prices = is_string($request->outlet_prices) ? json_decode($request->outlet_prices, true) : $request->outlet_prices;
            if (is_array($prices)) {
                $product->outletPrices()->delete();

                // Build lookup map outlet_code => outlet_id
                $codes = array_filter(array_column($prices, 'outlet_code'));
                $outletMap = Outlet::whereIn('code', $codes)->pluck('id', 'code');

                foreach ($prices as $priceItem) {
                    $code = $priceItem['outlet_code'] ?? null;
                    $outletId = $outletMap[$code] ?? ($priceItem['outlet_id'] ?? null);
                    if (!$outletId) continue;

                    $product->outletPrices()->create([
                        'outlet_id'              => $outletId,
                        'is_active'              => filter_var($priceItem['is_active'] ?? true, FILTER_VALIDATE_BOOLEAN),
                        'price'                  => $priceItem['price'] ?? 0,
                        'original_amount'        => $priceItem['original_amount'] ?? 0,
                        'service_charge_percent' => $priceItem['service_charge_percent'] ?? 0,
                        'service_charge_amount'  => $priceItem['service_charge_amount'] ?? 0,
                        'tax_percent'            => $priceItem['tax_percent'] ?? 0,
                        'tax_amount'             => $priceItem['tax_amount'] ?? 0,
                        'special_tax_percent'    => $priceItem['special_tax_percent'] ?? 0,
                        'special_tax_amount'     => $priceItem['special_tax_amount'] ?? 0,
                        'combo_original'         => $priceItem['combo_original'] ?? 0,
                        'combo_service'          => $priceItem['combo_service'] ?? 0,
                        'combo_special'          => $priceItem['combo_special'] ?? 0,
                        'combo_tax'              => $priceItem['combo_tax'] ?? 0,
                        'combo_price'            => $priceItem['combo_price'] ?? 0,
                        'update_price'           => filter_var($priceItem['update_price'] ?? false, FILTER_VALIDATE_BOOLEAN),
                        'update_combo_price'     => filter_var($priceItem['update_combo_price'] ?? false, FILTER_VALIDATE_BOOLEAN),
                        'is_expanded'            => filter_var($priceItem['is_expanded'] ?? false, FILTER_VALIDATE_BOOLEAN),
                        'selectedCounterOutlets' => $priceItem['selectedCounterOutlets'] ?? [],
                    ]);
                }
            }
        }

        // Sync Combo items
        if ($request->has('combo_items')) {
            $combos = is_string($request->combo_items) ? json_decode($request->combo_items, true) : $request->combo_items;
            if (is_array($combos)) {
                $product->comboItems()->delete();
                foreach ($combos as $comboItem) {
                    $product->comboItems()->create([
                        'child_id' => $comboItem['child_id'],
                        'quantity' => $comboItem['quantity'] ?? 1,
                        'price'    => $comboItem['price'] ?? 0,
                    ]);
                }
            }
        }

        $product->load(['outletPrices.outlet', 'comboItems.child']);
        $product->outletPrices->each(fn($op) => $op->outlet_code = $op->outlet?->code);
        return response()->json($product);
    }

    public function destroy($id)
    {
        $product = FbProduct::findOrFail($id);

        if ($product->image) {
            Storage::disk('public')->delete($product->image);
        }
        $product->delete();
        return response()->json(['message' => 'Deleted successfully']);
    }

    public function bulkToggleActive(Request $request)
    {
        $validated = $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'integer'
        ]);

        $products = FbProduct::whereIn('id', $validated['ids'])->get();
        foreach ($products as $product) {
            $product->update(['is_active' => !$product->is_active]);
        }

        return response()->json(['message' => 'Toggled successfully']);
    }
}
