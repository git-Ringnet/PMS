<?php
require __DIR__ . '/../../backend/vendor/autoload.php';
$app = require_once __DIR__ . '/../../backend/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$templates = \App\Models\Template::where('name', 'like', '%sales%')->orWhere('report', 'SALES_INVOICES')->get();
foreach ($templates as $t) {
    echo "ID: {$t->id} | Name: {$t->name} | Report: {$t->report} | top: {$t->margin_top} | bot: {$t->margin_bottom} | left: {$t->margin_left} | right: {$t->margin_right}\n";
    echo "page_size: {$t->page_size} | orientation: {$t->page_orientation}\n";
    // Check if css contains body or margins
    echo "CSS preview: " . substr($t->css ?? '', 0, 150) . "\n";
}
