<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\HousekeepingOutlet;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\UnitOfMeasure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Illuminate\Support\Facades\Response;

class ProductController extends Controller
{
    public function index()
    {
        $products = Product::with(['category'])->get();
        // Ensure all products have real product_code if missing
        foreach ($products as $p) {
            if (empty($p->product_code)) {
                $outletName = $p->category->outlet ?? '';
                $prefix = 'SP';
                if ($outletName) {
                    $outlet = \App\Models\HousekeepingOutlet::where('code', $outletName)->orWhere('name', $outletName)->first();
                    if ($outlet) {
                        $prefix = $outlet->code;
                    } elseif (mb_stripos($outletName, 'minibar') !== false) {
                        $prefix = 'MB';
                    } elseif (mb_stripos($outletName, 'giặt') !== false || mb_stripos($outletName, 'laundry') !== false) {
                        $prefix = 'LA';
                    } elseif (mb_stripos($outletName, 'đền bù') !== false) {
                        $prefix = 'BR';
                    } elseif (mb_stripos($outletName, 'amenity') !== false) {
                        $prefix = 'AM';
                    }
                }
                $p->product_code = $prefix . '-' . str_pad($p->id, 3, '0', STR_PAD_LEFT);
                $p->saveQuietly();
            }
        }
        return response()->json($products);
    }

    public function nextCode(Request $request)
    {
        $categoryId = $request->query('product_category_id');
        $category = $categoryId ? \App\Models\ProductCategory::find($categoryId) : null;
        $outletName = $category->outlet ?? $request->query('outlet', '');

        $prefix = 'SP';
        if ($outletName) {
            $outlet = \App\Models\HousekeepingOutlet::where('code', $outletName)
                ->orWhere('name', $outletName)
                ->first();
            if ($outlet) {
                $prefix = $outlet->code;
            } elseif (mb_stripos($outletName, 'minibar') !== false) {
                $prefix = 'MB';
            } elseif (mb_stripos($outletName, 'giặt') !== false || mb_stripos($outletName, 'laundry') !== false) {
                $prefix = 'LA';
            } elseif (mb_stripos($outletName, 'đền bù') !== false) {
                $prefix = 'BR';
            } elseif (mb_stripos($outletName, 'amenity') !== false) {
                $prefix = 'AM';
            }
        }

        $nextId = (Product::max('id') ?? 0) + 1;
        $code = $prefix . '-' . str_pad($nextId, 3, '0', STR_PAD_LEFT);

        return response()->json(['code' => $code]);
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

        // Auto generation of product code if not provided
        if (empty($validated['product_code'])) {
            $category = $product->category;
            $outletName = $category->outlet ?? '';
            $prefix = 'SP';
            if ($outletName) {
                $outlet = \App\Models\HousekeepingOutlet::where('code', $outletName)
                    ->orWhere('name', $outletName)
                    ->first();
                if ($outlet) {
                    $prefix = $outlet->code;
                } elseif (mb_stripos($outletName, 'minibar') !== false) {
                    $prefix = 'MB';
                } elseif (mb_stripos($outletName, 'giặt') !== false || mb_stripos($outletName, 'laundry') !== false) {
                    $prefix = 'LA';
                } elseif (mb_stripos($outletName, 'đền bù') !== false) {
                    $prefix = 'BR';
                } elseif (mb_stripos($outletName, 'amenity') !== false) {
                    $prefix = 'AM';
                }
            }

            $product->update(['product_code' => $prefix . '-' . str_pad($product->id, 3, '0', STR_PAD_LEFT)]);
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
        } elseif ($request->input('remove_image') == '1') {
            if ($product->image) {
                Storage::disk('public')->delete($product->image);
            }
            $validated['image'] = null;
        }

        $product->update($validated);

        return response()->json($product);
    }

    public function destroy(Product $product)
    {
        app(\App\Services\ConfigurationReferenceGuard::class)->assertUnused($product);
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
        $products = Product::with('category')->orderBy('id')->get();
        $units = UnitOfMeasure::query()->orderBy('code')->get(['code', 'name']);
        $categories = ProductCategory::query()->orderBy('id')->get(['id', 'name', 'outlet']);
        $outletValues = $categories->pluck('outlet')->filter()->unique();
        $outlets = HousekeepingOutlet::query()
            ->where(function ($query) use ($outletValues) {
                $query->whereIn('code', $outletValues)->orWhereIn('name', $outletValues);
            })
            ->get(['code', 'name'])
            ->flatMap(fn (HousekeepingOutlet $outlet) => [$outlet->code => $outlet, $outlet->name => $outlet]);

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Sản phẩm');

        $headers = [
            'ID', 'Tên Sản Phẩm', 'Mã Nhóm', 'Mã Sản Phẩm', 'Mã Đơn Vị Tính', 'Giá', 'Ghi Chú'
        ];
        $sheet->fromArray($headers, null, 'A1');
        $sheet->freezePane('A2');
        $sheet->setAutoFilter('A1:G1');

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

        $unitSheet = $spreadsheet->createSheet();
        $unitSheet->setTitle('Đơn vị tính');
        $unitSheet->fromArray(['Mã', 'Tên'], null, 'A1');
        foreach ($units as $index => $unit) {
            $unitSheet->fromArray([$unit->code, $unit->name], null, 'A' . ($index + 2));
        }
        $unitSheet->freezePane('A2');
        $unitSheet->setAutoFilter('A1:B1');

        $categorySheet = $spreadsheet->createSheet();
        $categorySheet->setTitle('Nhóm sản phẩm');
        $categorySheet->fromArray(['Mã Nhóm', 'Tên Nhóm', 'Mã Outlet', 'Tên Outlet'], null, 'A1');
        foreach ($categories as $index => $category) {
            $outlet = $outlets->get($category->outlet);
            $categorySheet->fromArray([
                $category->id,
                $category->name,
                $outlet?->code ?? $category->outlet,
                $outlet?->name ?? '',
            ], null, 'A' . ($index + 2));
        }
        $categorySheet->freezePane('A2');
        $categorySheet->setAutoFilter('A1:D1');

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
            $sheet = $spreadsheet->getSheetByName('Sản phẩm') ?? $spreadsheet->getActiveSheet();
            $rows = $sheet->toArray(null, true, true, false);

            $headers = array_shift($rows) ?? [];
            $isNewTemplate = trim((string) ($headers[4] ?? '')) === 'Mã Đơn Vị Tính';
            $records = [];
            $errors = [];

            foreach ($rows as $index => $row) {
                $rowNumber = $index + 2;
                $values = array_map(fn ($value) => trim((string) $value), $row);
                if (!array_filter($values, fn ($value) => $value !== '')) {
                    continue;
                }

                $productId = $this->excelId($row[0] ?? null);
                $name = trim((string) ($row[1] ?? ''));
                $categoryId = $this->excelId($row[2] ?? null);
                $productCode = trim((string) ($row[3] ?? '')) ?: null;
                $unitCode = trim((string) ($row[4] ?? ''));
                $price = ($row[5] ?? null) === null || trim((string) ($row[5] ?? '')) === '' ? 0 : $row[5];
                $note = trim((string) ($row[6] ?? '')) ?: null;

                if ($name === '') {
                    $errors[] = ['row' => $rowNumber, 'message' => 'Tên sản phẩm là bắt buộc.'];
                }
                if (!$categoryId) {
                    $errors[] = ['row' => $rowNumber, 'message' => 'Mã nhóm là bắt buộc và phải là số nguyên.'];
                }
                if ($isNewTemplate && $unitCode === '') {
                    $errors[] = ['row' => $rowNumber, 'message' => 'Mã đơn vị tính là bắt buộc.'];
                }
                if ($isNewTemplate && !is_numeric($price)) {
                    $errors[] = ['row' => $rowNumber, 'message' => 'Giá phải là số.'];
                }

                $records[] = compact('rowNumber', 'productId', 'name', 'categoryId', 'productCode', 'unitCode', 'price', 'note');
            }

            if ($isNewTemplate) {
                $categoryIds = collect($records)->pluck('categoryId')->filter()->unique();
                $unitCodes = collect($records)->pluck('unitCode')->filter()->unique();
                $knownCategoryIds = ProductCategory::query()->whereIn('id', $categoryIds)->pluck('id')->flip();
                $knownUnitCodes = UnitOfMeasure::query()->whereIn('code', $unitCodes)->pluck('code')->flip();

                foreach ($records as $record) {
                    if ($record['categoryId'] && !$knownCategoryIds->has($record['categoryId'])) {
                        $errors[] = ['row' => $record['rowNumber'], 'message' => "Mã nhóm {$record['categoryId']} không tồn tại."];
                    }
                    if ($record['unitCode'] !== '' && !$knownUnitCodes->has($record['unitCode'])) {
                        $errors[] = ['row' => $record['rowNumber'], 'message' => "Mã đơn vị tính {$record['unitCode']} không tồn tại."];
                    }
                }
            }

            if ($errors) {
                return response()->json([
                    'message' => 'Dữ liệu Excel không hợp lệ.',
                    'errors' => $errors,
                ], 422);
            }

            $existingProductIds = Product::query()
                ->whereIn('id', collect($records)->pluck('productId')->filter()->unique())
                ->pluck('id')
                ->flip();

            DB::transaction(function () use ($records) {
                foreach ($records as $record) {
                    $data = [
                        'name' => $record['name'],
                        'product_category_id' => $record['categoryId'],
                        'product_code' => $record['productCode'],
                        'currency' => $record['unitCode'] !== '' ? $record['unitCode'] : 'VND',
                        'price' => $record['price'],
                        'note' => $record['note'],
                        'change_table' => false,
                        'open_key' => false,
                        'is_alcohol' => false,
                        'track_stock' => false,
                        'is_active' => true,
                        'flexible_price' => false,
                    ];

                    if ($record['productId']) {
                        Product::updateOrCreate(['id' => $record['productId']], $data);
                    } else {
                        Product::create($data);
                    }
                }
            });

            return response()->json([
                'message' => 'Imported successfully',
                'created' => collect($records)->filter(fn ($record) => !$record['productId'] || !$existingProductIds->has($record['productId']))->count(),
                'updated' => collect($records)->filter(fn ($record) => $record['productId'] && $existingProductIds->has($record['productId']))->count(),
            ]);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Lỗi xử lý file: ' . $e->getMessage()], 500);
        }
    }

    private function excelId(mixed $value): ?int
    {
        $value = trim((string) $value);

        return ctype_digit($value) && (int) $value > 0 ? (int) $value : null;
    }
}
