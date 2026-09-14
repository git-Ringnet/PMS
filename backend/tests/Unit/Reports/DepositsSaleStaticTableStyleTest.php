<?php

namespace Tests\Unit\Reports;

use PHPUnit\Framework\TestCase;

class DepositsSaleStaticTableStyleTest extends TestCase
{
    public function test_allocation_header_uses_configurable_normal_row_style(): void
    {
        $provider = require dirname(__DIR__, 3).'/database/report_templates/deposits_sale_reference.php';
        $definition = $provider->definition();
        $allocation = collect($definition['content_json']['detail'])
            ->firstWhere('id', 'deposits_sale_allocation');

        $this->assertSame('normal', $allocation['rows'][0]['style']['fontWeight']);
        $this->assertSame('HTTT', $allocation['rows'][0]['cells'][0]['content']);
        $this->assertStringContainsString('font-weight: normal', $definition['content_html']);
        $this->assertStringNotContainsString('<b>HTTT</b>', $definition['content_html']);
    }
}
