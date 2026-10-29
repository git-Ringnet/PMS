<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\CustomerSource;
use App\Models\HotelConfig;
use App\Models\Market;
use App\Models\SystemBranch;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ChainCatalogSyncTest extends TestCase
{
    use RefreshDatabase;

    private const TARGET = 'mysql_hkt2';

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->withRole('branch_admin')->create(['username' => 'sync_user']));

        SystemBranch::create(['code' => 'HKT1', 'name' => 'Chi nhánh 1', 'is_active' => true]);
        SystemBranch::create(['code' => 'HKT2', 'name' => 'Chi nhánh 2', 'is_active' => true]);

        // Chi nhánh HKT2 dùng một DB SQLite riêng trong bộ nhớ
        config(['database.connections.' . self::TARGET => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']]);
        DB::purge(self::TARGET);
        $schema = Schema::connection(self::TARGET);
        foreach (['markets', 'customer_sources'] as $table) {
            $schema->create($table, function (Blueprint $t) {
                $t->id();
                $t->string('code')->unique();
                $t->string('name');
                $t->timestamps();
            });
        }
        $schema->create('companies', function (Blueprint $t) {
            $t->id();
            $t->string('code')->unique();
            $t->string('name');
            $t->string('trading_name')->nullable();
            $t->text('address')->nullable();
            $t->string('tax_code')->nullable();
            $t->string('phone')->nullable();
            $t->string('email')->nullable();
            $t->unsignedBigInteger('customer_source_id')->nullable();
            $t->unsignedBigInteger('market_id')->nullable();
            $t->boolean('sync_acc')->default(false);
            $t->decimal('max_debt', 15, 2)->default(0);
            $t->string('bank_account')->nullable();
            $t->unsignedBigInteger('booker_id')->nullable();
            $t->unsignedBigInteger('sales_person_id')->nullable();
            $t->string('rate_code')->nullable();
            $t->unsignedBigInteger('branch_id')->nullable();
            $t->boolean('is_active')->default(true);
            $t->timestamps();
        });
        $schema->create('bookings', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('company_id')->nullable();
            $t->unsignedBigInteger('market_id')->nullable();
            $t->unsignedBigInteger('customer_source_id')->nullable();
        });
    }

    private function enableSync(bool $on = true): void
    {
        HotelConfig::updateOrCreate(['name' => 'SyncCompanyAllBranch'], ['value' => $on ? '1' : '0']);
    }

    private function target(string $table)
    {
        return DB::connection(self::TARGET)->table($table);
    }

    public function test_sync_off_keeps_data_only_in_current_branch(): void
    {
        $this->enableSync(false);

        $this->postJson('/api/companies', ['name' => 'Công ty A'])->assertStatus(201);
        $this->postJson('/api/markets', ['code' => 'OTA', 'name' => 'OTA'])->assertStatus(201);

        $this->assertSame(0, $this->target('companies')->count());
        $this->assertSame(0, $this->target('markets')->count());
    }

    public function test_create_company_syncs_with_shared_code_and_maps_catalogs_by_code(): void
    {
        $this->enableSync();
        // HKT2 đã có công ty mã cao hơn => mã mới phải tính trên cả chuỗi
        $this->target('companies')->insert(['code' => 'CTY0007', 'name' => 'Công ty cũ HKT2']);
        $this->target('markets')->insert(['code' => 'GIT', 'name' => 'Đoàn']);
        $hkt2GitId = $this->target('markets')->where('code', 'GIT')->value('id');

        $market = Market::create(['code' => 'GIT', 'name' => 'Đoàn']);
        $source = CustomerSource::create(['code' => 'WALKIN', 'name' => 'Khách vãng lai']);
        $sales = User::factory()->create(['username' => 'sale_hkt1']);

        $res = $this->postJson('/api/companies', [
            'name' => 'Công ty Du lịch HKT', 'tax_code' => '0312345678', 'phone' => '0909',
            'market_id' => $market->id, 'customer_source_id' => $source->id,
            'sales_person_id' => $sales->id, 'max_debt' => 5000000,
        ])->assertStatus(201)->assertJsonPath('sync_warnings', []);

        $this->assertSame('CTY0008', Company::first()->code);

        $copy = $this->target('companies')->where('code', 'CTY0008')->first();
        $this->assertNotNull($copy);
        $this->assertSame('Công ty Du lịch HKT', $copy->name);
        $this->assertSame('0312345678', $copy->tax_code);
        $this->assertEquals(5000000, $copy->max_debt);
        $this->assertEquals($hkt2GitId, $copy->market_id);                       // khớp theo mã, không theo id
        $this->assertNotNull($copy->customer_source_id);                          // HKT2 chưa có nguồn khách => tự tạo
        $this->assertSame('WALKIN', $this->target('customer_sources')->where('id', $copy->customer_source_id)->value('code'));
        $this->assertNull($copy->sales_person_id);                                // không đồng bộ người bán
        $this->assertNotNull($res->json('data.id'));
    }

    public function test_create_warns_when_code_already_used_by_another_company(): void
    {
        $this->enableSync();
        Company::create(['code' => 'CTY0001', 'name' => 'Công ty X']);
        // Dữ liệu cũ HKT2 trùng mã với một công ty khác
        $this->target('companies')->insert(['code' => 'CTY0002', 'name' => 'Công ty Y của HKT2']);

        $service = app(\App\Services\ChainCatalogSyncService::class);
        $company = Company::create(['code' => 'CTY0002', 'name' => 'Công ty Z']);
        $warnings = $service->companyCreated($company);

        $this->assertCount(1, $warnings);
        $this->assertStringContainsString('đã có công ty mã CTY0002', $warnings[0]);
        $this->assertSame('Công ty Y của HKT2', $this->target('companies')->where('code', 'CTY0002')->value('name'));
    }

    public function test_update_company_syncs_but_keeps_other_branch_sales_person(): void
    {
        $this->enableSync();
        $created = $this->postJson('/api/companies', ['name' => 'Công ty A', 'phone' => '111'])->assertStatus(201);
        $id = $created->json('data.id');
        $code = Company::find($id)->code;
        $this->target('companies')->where('code', $code)->update(['sales_person_id' => 99]);

        $this->putJson("/api/companies/{$id}", ['name' => 'Công ty A (mới)', 'phone' => '222', 'is_active' => false])
            ->assertOk()->assertJsonPath('sync_warnings', []);

        $copy = $this->target('companies')->where('code', $code)->first();
        $this->assertSame('Công ty A (mới)', $copy->name);
        $this->assertSame('222', $copy->phone);
        $this->assertEquals(0, $copy->is_active);
        $this->assertEquals(99, $copy->sales_person_id);
    }

    public function test_update_does_not_overwrite_a_different_company_with_same_code(): void
    {
        $this->enableSync(false);
        $company = Company::create(['code' => 'CTY0001', 'name' => 'Công ty HKT1']);
        $this->target('companies')->insert(['code' => 'CTY0001', 'name' => 'Công ty khác của HKT2']);
        $this->enableSync();

        $this->putJson("/api/companies/{$company->id}", ['name' => 'Công ty HKT1 sửa'])
            ->assertOk()->assertJsonCount(1, 'sync_warnings');

        $this->assertSame('Công ty khác của HKT2', $this->target('companies')->where('code', 'CTY0001')->value('name'));
    }

    public function test_update_creates_missing_copy_in_other_branch(): void
    {
        $this->enableSync(false);
        $company = Company::create(['code' => 'CTY0003', 'name' => 'Công ty tạo trước khi bật']);
        $this->enableSync();

        $this->putJson("/api/companies/{$company->id}", ['name' => 'Công ty tạo trước khi bật'])->assertOk();

        $this->assertSame(1, $this->target('companies')->where('code', 'CTY0003')->count());
    }

    public function test_delete_company_syncs_unless_used_in_other_branch(): void
    {
        $this->enableSync();
        $a = $this->postJson('/api/companies', ['name' => 'Công ty A'])->json('data.id');
        $b = $this->postJson('/api/companies', ['name' => 'Công ty B'])->json('data.id');
        $codeA = Company::find($a)->code;
        $codeB = Company::find($b)->code;

        // Công ty B đã có booking ở HKT2
        $this->target('bookings')->insert(['company_id' => $this->target('companies')->where('code', $codeB)->value('id')]);

        $this->deleteJson("/api/companies/{$a}")->assertOk()->assertJsonPath('sync_warnings', []);
        $this->assertSame(0, $this->target('companies')->where('code', $codeA)->count());

        $this->deleteJson("/api/companies/{$b}")->assertOk()->assertJsonCount(1, 'sync_warnings');
        $this->assertSame(1, $this->target('companies')->where('code', $codeB)->count());
        $this->assertNull(Company::find($b));
    }

    public function test_market_and_customer_source_create_update_delete_sync_by_code(): void
    {
        $this->enableSync();

        $marketId = $this->postJson('/api/markets', ['code' => 'OTA', 'name' => 'Online'])->assertStatus(201)->json('data.id');
        $this->assertSame('Online', $this->target('markets')->where('code', 'OTA')->value('name'));

        $this->putJson("/api/markets/{$marketId}", ['code' => 'OTA2', 'name' => 'Online 2'])->assertOk();
        $this->assertSame(0, $this->target('markets')->where('code', 'OTA')->count());
        $this->assertSame('Online 2', $this->target('markets')->where('code', 'OTA2')->value('name'));

        $this->deleteJson("/api/markets/{$marketId}")->assertOk();
        $this->assertSame(0, $this->target('markets')->count());

        $sourceId = $this->postJson('/api/customer-sources', ['code' => 'FIT', 'name' => 'Khách lẻ'])->assertStatus(201)->json('data.id');
        $this->assertSame(1, $this->target('customer_sources')->where('code', 'FIT')->count());

        // Đang được dùng ở HKT2 thì không xóa bên đó
        $this->target('bookings')->insert(['customer_source_id' => $this->target('customer_sources')->where('code', 'FIT')->value('id')]);
        $this->deleteJson("/api/customer-sources/{$sourceId}")->assertOk()->assertJsonCount(1, 'sync_warnings');
        $this->assertSame(1, $this->target('customer_sources')->where('code', 'FIT')->count());
    }

    public function test_unreachable_branch_returns_warning_without_blocking(): void
    {
        $this->enableSync();
        SystemBranch::create(['code' => 'HKT9', 'name' => 'Chi nhánh lỗi', 'is_active' => true]);
        config(['database.connections.mysql_hkt9' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']]); // DB rỗng, không có bảng
        config(['database_domains.branch_connections.HKT9' => 'mysql_hkt9']);

        $res = $this->postJson('/api/markets', ['code' => 'NEW', 'name' => 'Mới'])->assertStatus(201);

        $this->assertSame(1, Market::where('code', 'NEW')->count());
        $this->assertSame(1, $this->target('markets')->where('code', 'NEW')->count());
        $this->assertCount(1, $res->json('sync_warnings'));
        $this->assertStringContainsString('HKT9', $res->json('sync_warnings.0'));
    }

    public function test_changing_setting_in_one_branch_applies_to_whole_chain(): void
    {
        DB::connection(self::TARGET)->getSchemaBuilder()->create('hotel_configs', function (Blueprint $t) {
            $t->id();
            $t->string('name')->unique();
            $t->string('value')->nullable();
            $t->text('description')->nullable();
            $t->boolean('is_visible')->default(true);
            $t->timestamps();
        });
        $this->target('hotel_configs')->insert(['name' => 'SyncCompanyAllBranch', 'value' => '0']);
        $config = HotelConfig::updateOrCreate(['name' => 'SyncCompanyAllBranch'], ['value' => '0']);

        $this->putJson("/api/hotel-configs/{$config->id}", ['name' => 'SyncCompanyAllBranch', 'value' => '1'])
            ->assertOk()->assertJsonPath('sync_warnings', []);
        $this->assertSame('1', $this->target('hotel_configs')->where('name', 'SyncCompanyAllBranch')->value('value'));

        $this->putJson("/api/hotel-configs/{$config->id}", ['name' => 'SyncCompanyAllBranch', 'value' => '0'])->assertOk();
        $this->assertSame('0', $this->target('hotel_configs')->where('name', 'SyncCompanyAllBranch')->value('value'));

        // Thông số khác không lan sang chi nhánh khác
        $other = HotelConfig::create(['name' => 'AllowInputOverAV', 'value' => '0']);
        $this->putJson("/api/hotel-configs/{$other->id}", ['name' => 'AllowInputOverAV', 'value' => '1'])->assertOk();
        $this->assertSame(0, $this->target('hotel_configs')->where('name', 'AllowInputOverAV')->count());
    }
}
