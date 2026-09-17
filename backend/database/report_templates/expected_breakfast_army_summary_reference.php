<?php

/**
 * Designer seed for the Army/Gallery summary form.
 * Totals bind to the renderer aggregate so no controller-side layout is needed.
 */
$baseProvider = require database_path('report_templates/expected_breakfast_summary_reference.php');

return new class($baseProvider)
{
    public function __construct(private readonly object $baseProvider) {}

    public function definition(): array
    {
        $definition = $this->baseProvider->definition();
        $definition['report'] = 'EXPECTED_BREAKFAST_ARMY_SUMMARY';
        $definition['name'] = 'Báo cáo dự kiến khách ăn sáng - Tổng hợp Army';
        $definition['content_html'] = $this->replaceTotals($definition['content_html']);
        $definition['content_json'] = $this->replaceTotals($definition['content_json']);

        return $definition;
    }

    private function replaceTotals(mixed $value): mixed
    {
        if (is_array($value)) {
            return array_map(fn (mixed $item): mixed => $this->replaceTotals($item), $value);
        }
        if (! is_string($value)) {
            return $value;
        }

        return str_replace(
            [
                'totals.Adults', 'totals.Children', 'totals.ChildrenNK', 'totals.TotalPax',
                'totals.CountryTotalPax',
            ],
            [
                'aggregate.rows.sum.Adults', 'aggregate.rows.sum.Children', 'aggregate.rows.sum.ChildrenNK', 'aggregate.rows.sum.TotalPax',
                'aggregate.country_summary.sum.Quantity',
            ],
            $value
        );
    }

    public function __call(string $method, array $arguments): mixed
    {
        return $this->baseProvider->{$method}(...$arguments);
    }
};
