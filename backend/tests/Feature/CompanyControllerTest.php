<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\CustomerSource;
use App\Models\HotelConfig;
use App\Models\Market;
use App\Models\Branch;
use App\Models\Booker;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class CompanyControllerTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;
    protected Market $marketOta;
    protected CustomerSource $sourceAgoda;
    protected Branch $branch1;
    protected Booker $bookerA;

    protected function setUp(): void
    {
        parent::setUp();

        $this->adminUser = User::factory()->withRole('branch_admin')->create([
            'name' => 'Admin User',
            'username' => 'admin',
            'email' => 'admin@pms.com',
            'password' => bcrypt('password'),
        ]);

        $this->marketOta = Market::create(['code' => 'OTA', 'name' => 'Online Travel Agent']);
        $this->sourceAgoda = CustomerSource::create(['code' => 'AGODA', 'name' => 'Agoda']);
        $this->branch1 = Branch::create(['code' => 'HKT1', 'name' => 'HKT 1', 'api_url' => 'http://test1', 'api_report_url' => 'http://test1/rep', 'is_master' => true]);
        $this->bookerA = Booker::create(['name' => 'Nguyễn Văn A', 'email' => 'nguyenvana@gmail.com']);
    }

    public function test_sync_companies_acc()
    {
        \Laravel\Sanctum\Sanctum::actingAs($this->adminUser);

        // Create a company that is not synced
        $company = Company::create([
            'name' => 'Công ty Test 1',
            'trading_name' => 'Test 1',
            'market_id' => $this->marketOta->id,
            'customer_source_id' => $this->sourceAgoda->id,
            'sync_acc' => false,
        ]);

        $this->assertFalse((bool)$company->sync_acc);

        $response = $this->postJson('/api/companies/sync');
        $response->assertStatus(200);
        $response->assertJsonFragment([
            'success' => true,
        ]);

        $this->assertTrue((bool)$company->fresh()->sync_acc);
    }

    public function test_company_name_and_trading_name_are_always_required(): void
    {
        \Laravel\Sanctum\Sanctum::actingAs($this->adminUser);

        $this->postJson('/api/companies', ['name' => 'Công ty thiếu tên giao dịch'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['trading_name']);

        $company = Company::create(['name' => 'Công ty cũ', 'trading_name' => 'Tên giao dịch cũ']);
        $this->putJson("/api/companies/{$company->id}", ['name' => 'Công ty mới'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['trading_name']);
    }

    public function test_configured_company_required_fields_are_exposed_and_validated(): void
    {
        \Laravel\Sanctum\Sanctum::actingAs($this->adminUser);
        HotelConfig::updateOrCreate(
            ['name' => 'CreateCompanyRequiredField'],
            ['value' => 'market_id,customer_source_id,unknown_field']
        );

        $this->getJson('/api/companies?per_page=10')
            ->assertOk()
            ->assertJsonPath('meta.company_required_fields', ['market_id', 'customer_source_id']);

        $this->postJson('/api/companies', [
            'name' => 'Công ty thiếu danh mục',
            'trading_name' => 'Thiếu danh mục',
        ])->assertStatus(422)->assertJsonValidationErrors(['market_id', 'customer_source_id']);

        $this->postJson('/api/companies', [
            'name' => 'Công ty đủ danh mục',
            'trading_name' => 'Đủ danh mục',
            'market_id' => $this->marketOta->id,
            'customer_source_id' => $this->sourceAgoda->id,
        ])->assertCreated();
    }

    public function test_export_companies_excel_with_each_field_in_its_own_column()
    {
        \Laravel\Sanctum\Sanctum::actingAs($this->adminUser);

        Company::create([
            'name' => 'Công ty HKT',
            'trading_name' => 'HKT Travel',
            'market_id' => $this->marketOta->id,
            'customer_source_id' => $this->sourceAgoda->id,
            'branch_id' => $this->branch1->id,
            'booker_id' => $this->bookerA->id,
            'max_debt' => 50000000,
        ]);

        $response = $this->get('/api/companies/export');
        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');

        $content = $response->streamedContent();
        $file = tempnam(sys_get_temp_dir(), 'companies-export');
        file_put_contents($file, $content);
        $sheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($file)->getActiveSheet();

        $this->assertSame('Mã', $sheet->getCell('A1')->getValue());
        $this->assertSame('Trạng Thái Sử Dụng', $sheet->getCell('O1')->getValue());
        $this->assertSame('Công ty HKT', $sheet->getCell('B2')->getValue());
        $this->assertSame('HKT Travel', $sheet->getCell('C2')->getValue());
        $this->assertSame('Agoda', $sheet->getCell('H2')->getValue());
        $this->assertSame('Online Travel Agent', $sheet->getCell('I2')->getValue());
    }

    public function test_import_companies_csv()
    {
        \Laravel\Sanctum\Sanctum::actingAs($this->adminUser);

        // Prepare CSV file content
        $csvHeader = "Mã,Tên,Tên giao dịch,Địa chỉ,Tax,Số điện thoại,Email,Nguồn khách,Thị trường,Công nợ tối đa,Tài khoản ngân hàng,Người đặt phòng,Mã giá phòng,Chi nhánh\n";
        // Create an existing company to test Update
        $existingCompany = Company::create([
            'name' => 'Công ty Cũ',
            'trading_name' => 'Trading Cũ',
        ]);
        $existingCode = $existingCompany->code;

        // Row 1 (Update existing company by code)
        $row1 = "{$existingCode},Công ty Cũ Đã Cập Nhật,Trading Mới,Quận 1,01020304,0987654321,cu@pms.com,AGODA,OTA,10000000,12345678,Nguyễn Văn A,GP01,HKT 1\n";
        // Row 2 (Create new company by name)
        $row2 = ",Công ty Mới Hoàn Toàn,Trading Mới 2,Quận 3,03040506,0123456789,moi@pms.com,AGODA,OTA,20000000,87654321,Nguyễn Văn A,GP02,HKT 1\n";

        $csvContent = chr(0xEF).chr(0xBB).chr(0xBF) . $csvHeader . $row1 . $row2;

        $tempFile = tempnam(sys_get_temp_dir(), 'csv');
        file_put_contents($tempFile, $csvContent);

        $uploadedFile = new UploadedFile(
            $tempFile,
            'companies.csv',
            'text/csv',
            null,
            true // test mode
        );

        $response = $this->postJson('/api/companies/import', [
            'file' => $uploadedFile
        ]);

        $response->assertStatus(200);
        $response->assertJsonFragment([
            'success' => true,
        ]);
        $response->assertJsonPath('summary.created', 1);
        $response->assertJsonPath('summary.updated', 1);
        $response->assertJsonCount(2, 'rows');

        // Check DB updates
        $this->assertEquals('Công ty Cũ Đã Cập Nhật', $existingCompany->fresh()->name);
        $this->assertEquals($this->marketOta->id, $existingCompany->fresh()->market_id);
        $this->assertEquals($this->sourceAgoda->id, $existingCompany->fresh()->customer_source_id);
        $this->assertEquals($this->branch1->id, $existingCompany->fresh()->branch_id);
        $this->assertEquals($this->bookerA->id, $existingCompany->fresh()->booker_id);

        $newCompany = Company::where('name', 'Công ty Mới Hoàn Toàn')->first();
        $this->assertNotNull($newCompany);
        $this->assertEquals('Trading Mới 2', $newCompany->trading_name);
        $this->assertEquals($this->marketOta->id, $newCompany->market_id);
        $this->assertEquals($this->sourceAgoda->id, $newCompany->customer_source_id);
        $this->assertEquals($this->branch1->id, $newCompany->branch_id);
        $this->assertEquals($this->bookerA->id, $newCompany->booker_id);
    }

    public function test_import_companies_excel_and_report_invalid_rows(): void
    {
        \Laravel\Sanctum\Sanctum::actingAs($this->adminUser);

        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->fromArray([
            ['Mã', 'Tên', 'Tên Giao Dịch', 'Nguồn Khách', 'Thị Trường', 'Công Nợ Tối Đa'],
            ['CTY-XLSX', 'Công ty Excel', 'Excel Travel', 'AGODA', 'OTA', '50.000.000'],
            ['CTY-ERR', 'Công ty lỗi', 'Lỗi Travel', 'KHONG_CO', 'OTA', '0'],
        ]);
        $file = tempnam(sys_get_temp_dir(), 'companies-import');
        (new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet))->save($file);

        $response = $this->postJson('/api/companies/import', [
            'file' => new UploadedFile($file, 'companies.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true),
        ]);

        $response->assertOk()
            ->assertJsonPath('success', false)
            ->assertJsonPath('summary.created', 1)
            ->assertJsonPath('summary.failed', 1)
            ->assertJsonPath('rows.0.status', 'created')
            ->assertJsonPath('rows.1.status', 'failed');
        $this->assertDatabaseHas('companies', ['code' => 'CTY-XLSX', 'name' => 'Công ty Excel']);
        $this->assertDatabaseMissing('companies', ['code' => 'CTY-ERR']);
    }

    public function test_download_companies_template_excel()
    {
        \Laravel\Sanctum\Sanctum::actingAs($this->adminUser);

        $response = $this->get('/api/companies/template');
        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');

        $content = $response->streamedContent();
        $file = tempnam(sys_get_temp_dir(), 'companies-template');
        file_put_contents($file, $content);
        $sheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($file)->getActiveSheet();

        $this->assertSame('Mã', $sheet->getCell('A1')->getValue());
        $this->assertSame('Tên Giao Dịch', $sheet->getCell('C1')->getValue());
        $this->assertSame('Công ty TNHH Du lịch Việt', $sheet->getCell('B2')->getValue());
    }
}
