<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\CustomerSource;
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

        $this->adminUser = User::create([
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

    public function test_export_companies_csv()
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
        $response->assertHeader('Content-Type', 'text/csv; charset=UTF-8');

        $content = $response->streamedContent();
        
        // Assert UTF-8 BOM exists at the start
        $this->assertEquals(0xEF, ord($content[0]));
        $this->assertEquals(0xBB, ord($content[1]));
        $this->assertEquals(0xBF, ord($content[2]));

        // Assert content contains expected company data
        $this->assertStringContainsString('Công ty HKT', $content);
        $this->assertStringContainsString('HKT Travel', $content);
        $this->assertStringContainsString('Agoda', $content);
        $this->assertStringContainsString('Online Travel Agent', $content);
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

    public function test_download_companies_template_csv()
    {
        \Laravel\Sanctum\Sanctum::actingAs($this->adminUser);

        $response = $this->get('/api/companies/template');
        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'text/csv; charset=UTF-8');

        $content = $response->streamedContent();

        // Assert UTF-8 BOM exists at the start
        $this->assertEquals(0xEF, ord($content[0]));
        $this->assertEquals(0xBB, ord($content[1]));
        $this->assertEquals(0xBF, ord($content[2]));

        // Assert headers are present
        $this->assertStringContainsString('Mã', $content);
        $this->assertStringContainsString('Tên', $content);
        $this->assertStringContainsString('Tên giao dịch', $content);
        $this->assertStringContainsString('Địa chỉ', $content);
        $this->assertStringContainsString('Tax', $content);
        $this->assertStringContainsString('Nguồn khách', $content);
        $this->assertStringContainsString('Thị trường', $content);
        // Assert example row is present
        $this->assertStringContainsString('Công ty TNHH Du lịch Việt', $content);
    }
}
