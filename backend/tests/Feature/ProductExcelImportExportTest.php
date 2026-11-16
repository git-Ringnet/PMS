<?php

namespace Tests\Feature;

use App\Http\Controllers\Api\ProductController;
use App\Models\HousekeepingOutlet;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\UnitOfMeasure;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class ProductExcelImportExportTest extends TestCase
{
    use RefreshDatabase;

    public function test_export_contains_product_and_reference_sheets(): void
    {
        $outlet = HousekeepingOutlet::create(['code' => 'HKX', 'name' => 'Outlet HK']);
        $category = ProductCategory::create(['name' => 'Đồ uống', 'outlet' => $outlet->code]);
        UnitOfMeasure::create(['code' => 'CHAI', 'name' => 'Chai']);
        Product::create([
            'name' => 'Nước suối',
            'product_category_id' => $category->id,
            'product_code' => 'HKX-001',
            'currency' => 'CHAI',
            'price' => 20000,
        ]);

        $response = app(ProductController::class)->exportExcel();
        $spreadsheet = IOFactory::load($response->getFile()->getPathname());

        $this->assertSame(['Sản phẩm', 'Đơn vị tính', 'Nhóm sản phẩm'], $spreadsheet->getSheetNames());
        $this->assertSame('Mã Đơn Vị Tính', $spreadsheet->getSheetByName('Sản phẩm')->getCell('E1')->getValue());
        $this->assertSame('CHAI', $spreadsheet->getSheetByName('Đơn vị tính')->getCell('A2')->getValue());
        $this->assertSame($category->id, $spreadsheet->getSheetByName('Nhóm sản phẩm')->getCell('A2')->getValue());
        $this->assertSame('HKX', $spreadsheet->getSheetByName('Nhóm sản phẩm')->getCell('C2')->getValue());
    }

    public function test_new_template_import_validates_references_before_writing(): void
    {
        $outlet = HousekeepingOutlet::create(['code' => 'HKX', 'name' => 'Outlet HK']);
        $category = ProductCategory::create(['name' => 'Đồ uống', 'outlet' => $outlet->code]);
        UnitOfMeasure::create(['code' => 'CHAI', 'name' => 'Chai']);
        $file = $this->newTemplateFile([
            [null, 'Nước suối', $category->id, 'HKX-001', 'CHAI', 20000, ''],
            [null, 'Nước ngọt', $category->id, 'HKX-002', 'SAI', 25000, ''],
        ]);

        $response = app(ProductController::class)->importExcel($this->importRequest($file));

        $this->assertSame(422, $response->getStatusCode());
        $this->assertSame(0, Product::count());
        $this->assertSame('Dữ liệu Excel không hợp lệ.', $response->getData(true)['message']);
    }

    public function test_new_template_import_uses_unit_code_and_category_id(): void
    {
        $outlet = HousekeepingOutlet::create(['code' => 'HKX', 'name' => 'Outlet HK']);
        $category = ProductCategory::create(['name' => 'Đồ uống', 'outlet' => $outlet->code]);
        UnitOfMeasure::create(['code' => 'CHAI', 'name' => 'Chai']);
        $file = $this->newTemplateFile([
            [null, 'Nước suối', $category->id, 'HKX-001', 'CHAI', 20000, 'Ghi chú'],
        ]);

        $response = app(ProductController::class)->importExcel($this->importRequest($file));

        $this->assertSame(200, $response->getStatusCode());
        $this->assertDatabaseHas('products', [
            'name' => 'Nước suối',
            'product_category_id' => $category->id,
            'currency' => 'CHAI',
            'price' => 20000,
        ]);
    }

    private function newTemplateFile(array $rows): string
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Sản phẩm');
        $sheet->fromArray(['ID', 'Tên Sản Phẩm', 'Mã Nhóm', 'Mã Sản Phẩm', 'Mã Đơn Vị Tính', 'Giá', 'Ghi Chú'], null, 'A1');
        foreach ($rows as $index => $row) {
            $sheet->fromArray($row, null, 'A' . ($index + 2));
        }

        $path = tempnam(sys_get_temp_dir(), 'pms-products-');
        (new Xlsx($spreadsheet))->save($path);

        return $path;
    }

    private function importRequest(string $path): Request
    {
        return Request::create('/', 'POST', [], [], [
            'file' => new UploadedFile($path, 'products.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true),
        ]);
    }
}
