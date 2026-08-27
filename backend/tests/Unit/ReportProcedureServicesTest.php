<?php

namespace Tests\Unit;

use App\Services\Reports\ReportDataExecutorService;
use App\Services\Reports\ReportProcedureCatalogService;
use InvalidArgumentException;
use Mockery;
use PHPUnit\Framework\TestCase;

class ReportProcedureServicesTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_catalog_rejects_unsafe_procedure_identifiers(): void
    {
        $catalog = new ReportProcedureCatalogService;

        $this->expectException(InvalidArgumentException::class);
        $catalog->assertIdentifier('rpt_guests; DROP TABLE guests');
    }

    public function test_executor_requires_every_declared_input_parameter(): void
    {
        $catalog = Mockery::mock(ReportProcedureCatalogService::class);
        $catalog->shouldReceive('assertIdentifier')->once()->with('rpt_guest_birthday');
        $executor = new ReportDataExecutorService($catalog);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Missing report parameter: p_to_date.');

        $executor->executeProcedure(
            'rpt_guest_birthday',
            [
                ['name' => 'p_from_date', 'mode' => 'IN', 'data_type' => 'date'],
                ['name' => 'p_to_date', 'mode' => 'IN', 'data_type' => 'date'],
            ],
            ['p_from_date' => '2026-08-01']
        );
    }

    public function test_executor_rejects_output_parameters_in_mvp(): void
    {
        $catalog = Mockery::mock(ReportProcedureCatalogService::class);
        $catalog->shouldReceive('assertIdentifier')->once()->with('rpt_totals');
        $executor = new ReportDataExecutorService($catalog);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Only named IN parameters are supported');

        $executor->executeProcedure(
            'rpt_totals',
            [['name' => 'p_total', 'mode' => 'OUT', 'data_type' => 'decimal']],
            ['p_total' => null]
        );
    }

    public function test_executor_casts_checkbox_boolean_to_integer(): void
    {
        $catalog = Mockery::mock(ReportProcedureCatalogService::class);
        $executor = new ReportDataExecutorService($catalog);
        $castValue = new \ReflectionMethod($executor, 'castValue');

        $this->assertSame(1, $castValue->invoke($executor, true, 'tinyint'));
        $this->assertSame(0, $castValue->invoke($executor, false, 'tinyint'));
    }
}
