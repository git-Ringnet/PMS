<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Illuminate\Support\Facades\Response;

class ProductController extends Controller
{
    public function index()
    {
        $products = Product::with('category')->get();
        return response()->json($products);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'product_category_id' => 'required|exists:product_categories,id',
            'name' => 'required|string|max:255',
            'currency' => 'nullable|string|max:20',
            'price' => 'numeric',
            'note' => 'nullable|string',
            'change_table' => 'boolean',
            'open_key' => 'boolean',
            'is_alcohol' => 'boolean',
            'goods' => 'nullable|string|max:50',
            'is_in_stock' => 'nullable|integer',
            'service_charge_percent' => 'numeric',
            'tax_percent' => 'numeric',
            'special_tax_percent' => 'numeric',
            'original_amount' => 'nullable|numeric',
            'service_charge_amount' => 'nullable|numeric',
            'tax_amount' => 'nullable|numeric',
            'special_tax_amount' => 'nullable|numeric',
            'inventory_id' => 'nullable|string|max:50',
            'flexible_price' => 'boolean',
            'misa_id' => 'nullable|uuid',
            'product_code' => 'nullable|string',
            'debit_account' => 'nullable|string|max:20',
            'credit_account' => 'nullable|string|max:20',
            'is_active' => 'boolean',
            'track_stock' => 'boolean',
        ]);

        if ($request->hasFile('image')) {
            $path = $request->file('image')->store('products', 'public');
            $validated['image'] = $path;
        }

        $product = Product::create($validated);

        $outlet = $product->category->outlet ?? '';
        $prefix = '';
        if ($outlet === 'Minibar') $prefix = 'MB';
        elseif ($outlet === 'Giặt ủi') $prefix = 'LA';
        elseif ($outlet === 'Hàng đền bù') $prefix = 'BK';
        elseif ($outlet === 'Amenity') $prefix = 'AM';

        if ($prefix) {
            $product->update(['product_code' => $prefix . '-' . $product->id]);
        }

        return response()->json($product, 201);
    }

    public function update(Request $request, Product $product)
    {
        $validated = $request->validate([
            'product_category_id' => 'required|exists:product_categories,id',
            'name' => 'required|string|max:255',
            'currency' => 'nullable|string|max:20',
            'price' => 'numeric',
            'note' => 'nullable|string',
            'change_table' => 'boolean',
            'open_key' => 'boolean',
            'is_alcohol' => 'boolean',
            'goods' => 'nullable|string|max:50',
            'is_in_stock' => 'nullable|integer',
            'service_charge_percent' => 'numeric',
            'tax_percent' => 'numeric',
            'special_tax_percent' => 'numeric',
            'original_amount' => 'nullable|numeric',
            'service_charge_amount' => 'nullable|numeric',
            'tax_amount' => 'nullable|numeric',
            'special_tax_amount' => 'nullable|numeric',
            'inventory_id' => 'nullable|string|max:50',
            'flexible_price' => 'boolean',
            'misa_id' => 'nullable|uuid',
            'product_code' => 'nullable|string',
            'debit_account' => 'nullable|string|max:20',
            'credit_account' => 'nullable|string|max:20',
            'is_active' => 'boolean',
            'track_stock' => 'boolean',
        ]);

        if ($request->hasFile('image')) {
            if ($product->image) {
                Storage::disk('public')->delete($product->image);
            }
            $path = $request->file('image')->store('products', 'public');
            $validated['image'] = $path;
        }

        $product->update($validated);

        return response()->json($product);
    }

    public function destroy(Product $product)
    {
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

        $products = \App\Models\Product::whereIn('id', $validated['ids'])->get();
        foreach ($products as $product) {
            $product->update(['is_active' => !$product->is_active]);
        }

        return response()->json(['message' => 'Toggled successfully']);
    }

    public function exportExcel()
    {
        $products = Product::with('category')->get();

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        // Header
        $headers = [
            'ID', 'Tên sản phẩm', 'Danh mục ID', 'Mã sản phẩm', 'Tiền tệ', 'Giá', 'Ghi chú'
        ];
        $sheet->fromArray($headers, null, 'A1');

        // Data
        $row = 2;
        foreach ($products as $product) {
            $sheet->fromArray([
                $product->id,
                $product->name,
                $product->product_category_id,
                $product->product_code,
                $product->currency,
                $product->price,
                $product->note
            ], null, 'A' . $row);
            $row++;
        }

        $writer = new Xlsx($spreadsheet);
        $fileName = 'products.xlsx';
        $tempFile = tempnam(sys_get_temp_dir(), $fileName);
        $writer->save($tempFile);

        return response()->download($tempFile, $fileName, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ])->deleteFileAfterSend(true);
    }

    public function importExcel(Request $request)
    {
        $request->validate([
            'file' => 'required|mimes:xlsx,xls,csv'
        ]);

        $file = $request->file('file');
        
        try {
            $spreadsheet = IOFactory::load($file->getPathname());
            $sheet = $spreadsheet->getActiveSheet();
            $rows = $sheet->toArray();

            // Bỏ qua dòng header
            array_shift($rows);

            foreach ($rows as $row) {
                if (empty($row[0]) && empty($row[1])) continue;
                
                // Cấu trúc cột dựa theo export: ID, Tên, Danh mục ID, Mã SP, Tiền tệ, Giá, Ghi chú
                $productId = $row[0] ?? null;
                $name = $row[1] ?? '';
                $categoryId = $row[2] ?? null;
                $productCode = $row[3] ?? null;
                $currency = $row[4] ?? 'VND';
                $price = $row[5] ?? 0;
                $note = $row[6] ?? null;

                if ($name && $categoryId) {
                    $data = [
                        'name' => $name,
                        'product_category_id' => $categoryId,
                        'product_code' => $productCode,
                        'currency' => $currency,
                        'price' => $price,
                        'note' => $note,
                        'change_table' => false,
                        'open_key' => false,
                        'is_alcohol' => false,
                        'track_stock' => false,
                        'is_active' => true,
                        'flexible_price' => false,
                    ];

                    if ($productId) {
                        Product::updateOrCreate(['id' => $productId], $data);
                    } else {
                        Product::create($data);
                    }
                }
            }

            return response()->json(['message' => 'Imported successfully']);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Lỗi xử lý file: ' . $e->getMessage()], 500);
        }
    }
}
