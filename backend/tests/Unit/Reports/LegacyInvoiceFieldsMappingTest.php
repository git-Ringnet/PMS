<?php

namespace Tests\Unit\Reports;

use Tests\TestCase;

class LegacyInvoiceFieldsMappingTest extends TestCase
{
    public function test_legacy_invoice_field_migration_and_models_keep_verified_columns(): void
    {
        $migration = file_get_contents(database_path('migrations/2026_09_10_130000_add_legacy_invoice_fields.php'));
        $serviceBill = file_get_contents(app_path('Models/ServiceBill.php'));
        $serviceBillDetail = file_get_contents(app_path('Models/ServiceBillDetail.php'));
        $housekeepingBill = file_get_contents(app_path('Models/HousekeepingServiceBill.php'));

        foreach ([
            'RefId', 'VATNumber', 'BillExchangeRate', 'ParentBillId', 'UpdatedUser',
            'TotalAmount0', 'ConvertAmount2', 'IsSyncT',
        ] as $field) {
            $this->assertStringContainsString("'{$field}'", $migration);
            $this->assertStringContainsString("'{$field}'", $serviceBill);
        }

        foreach ([
            'ServiceChargeAmount', 'SpecialTaxAmount', 'TaxAmount', 'BillExchangeRate',
            'BillExchangeAmount', 'DetailBillTotalAmount', 'DetailBillServiceChargeAmount',
            'DetailBillSpecialTaxAmount', 'DetailBillTaxAmount', 'OriginalAmount',
        ] as $field) {
            $this->assertStringContainsString("'{$field}'", $migration);
            $this->assertStringContainsString("'{$field}'", $serviceBillDetail);
        }

        foreach ([
            'NumOfRoom', 'BillServicesChargeAmount', 'BillSpecialTaxAmount', 'BillTaxAmount',
            'BillTotalAmount', 'IncludeServicesCharge', 'IncludeSpecialTax',
            'IncludeTax', 'FOCType', 'BillTime', 'BillShift', 'CaptainOrder', 'IsExport',
        ] as $field) {
            $this->assertStringContainsString("'{$field}'", $migration);
            $this->assertStringContainsString("'{$field}'", $housekeepingBill);
        }

        foreach ([
            "float('BillExchangeRate')",
            "float('BillExchangeAmount')",
            "float('ConvertRate')",
            "float('ConvertRate2')",
            "decimal('BillExchangeAmount', 20, 6)",
            "decimal('DetailBillTotalAmount', 20, 6)",
        ] as $definition) {
            $this->assertStringContainsString($definition, $migration);
        }

        $this->assertGreaterThan(20, substr_count($migration, "->nullable()"));
    }
}
