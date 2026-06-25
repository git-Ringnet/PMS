<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

$template = App\Models\Template::where('report', 'BookingConfirmationNavyDalat')->first();
$renderer = app(App\Services\TemplateRendererService::class);
$mock = $renderer->getMockData($template->group, $template->name);
$html = $renderer->render($template->content_html, $template->css, $mock, [
    'page_size' => $template->page_size,
    'page_orientation' => $template->page_orientation,
    'margin_top' => $template->margin_top,
    'margin_bottom' => $template->margin_bottom,
    'margin_left' => $template->margin_left,
    'margin_right' => $template->margin_right,
]);

echo 'Template: ' . $template->name . ' (ID ' . $template->id . ")\n";
echo 'Has title: ' . (str_contains($html, 'XÁC NHẬN ĐẶT PHÒNG') ? 'yes' : 'no') . "\n";
echo 'Has guest: ' . (str_contains($html, 'Thái Văn Hiền') ? 'yes' : 'no') . "\n";
echo 'Has room line: ' . (str_contains($html, 'Deluxe Twin') ? 'yes' : 'no') . "\n";
echo 'Has policy: ' . (str_contains($html, 'Chính sách hủy') ? 'yes' : 'no') . "\n";
echo 'HTML length: ' . strlen($html) . "\n";
