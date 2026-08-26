<?php

return [
    'procedure_prefix' => env('REPORT_PROCEDURE_PREFIX', 'rpt_'),
    'default_max_rows' => (int) env('REPORT_MAX_ROWS', 1000),
    'maximum_max_rows' => (int) env('REPORT_MAX_ROWS_LIMIT', 5000),
];
