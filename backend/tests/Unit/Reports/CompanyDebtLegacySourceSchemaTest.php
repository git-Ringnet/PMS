<?php

namespace Tests\Unit\Reports;

use Illuminate\Support\Facades\Schema;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CompanyDebtLegacySourceSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_company_debt_legacy_source_columns_are_nullable_and_keep_raw_keys(): void
    {
        $this->assertTrue(Schema::hasTable('sales_invoices'));
        $this->assertTrue(Schema::hasColumns('sales_invoices', [
            'legacy_id', 'invoice_date', 'amount', 'legacy_rental_room_id',
            'legacy_booking_id', 'legacy_payment_id', 'outlet', 'username',
        ]));
        $this->assertTrue(Schema::hasColumns('payments', [
            'legacy_id', 'legacy_payment_total_amount0', 'legacy_payment_currency0', 'legacy_pack5',
        ]));
        $this->assertTrue(Schema::hasColumns('payment_debt_settlements', [
            'legacy_id', 'legacy_payment_table_id',
        ]));
        $this->assertTrue(Schema::hasColumns('companies', [
            'legacy_id', 'legacy_re_credit_limit',
        ]));

        $this->assertSame('integer', Schema::getColumnType('sales_invoices', 'legacy_id'));
        $this->assertContains(Schema::getColumnType('sales_invoices', 'amount'), ['decimal', 'numeric']);
    }
}
