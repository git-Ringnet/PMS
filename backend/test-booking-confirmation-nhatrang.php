<?php

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

$template = App\Models\Template::where('report', 'BookingConfirmationNavyNhatrang')->first();
$renderer = app(App\Services\TemplateRendererService::class);
$mock = $renderer->getMockData($template->group, $template->name);
$html = $renderer->render($template->content_html, $template->css, $mock, []);

$checks = [
    'BOOKING CONFIRMATION' => str_contains($html, 'BOOKING CONFIRMATION'),
    'guest' => str_contains($html, 'MR VIACHASLAU FILIPCHUK'),
    'company' => str_contains($html, 'AMEGA TRAVEL'),
    'line1' => str_contains($html, 'Deluxe Triple City View'),
    'total' => str_contains($html, '10,400,000'),
    'note' => str_contains($html, '900027592/Amega'),
    'signatures' => str_contains($html, 'Chữ ký lễ tân'),
];

echo 'Template: ' . $template->name . ' (ID ' . $template->id . ")\n";
foreach ($checks as $label => $ok) {
    echo $label . ': ' . ($ok ? 'yes' : 'NO') . "\n";
}
echo 'HTML length: ' . strlen($html) . "\n";
