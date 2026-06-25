<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

$template = App\Models\Template::find(16);
$html = $template->content_html;
$css = $template->css;

echo (str_contains($html, 'data:image/png') ? "base64 icons: yes\n" : "base64 icons: no\n");
echo (str_contains($html, 'receipt-field-line') ? "field lines: yes\n" : "field lines: no\n");
echo (str_contains($html, 'receipt-contact-text') ? "brown text class: yes\n" : "brown text class: no\n");
echo (str_contains($css, '#8B5A4A') ? "brown css: yes\n" : "brown css: no\n");

$renderer = app(App\Services\TemplateRendererService::class);
$preview = $renderer->render($html, $css, $renderer->getMockData($template->group, $template->name), [
    'page_size' => $template->page_size,
    'page_orientation' => $template->page_orientation,
    'margin_top' => $template->margin_top,
    'margin_bottom' => $template->margin_bottom,
    'margin_left' => $template->margin_left,
    'margin_right' => $template->margin_right,
]);

echo (str_contains($preview, 'receipt-field-line') ? "preview field lines: yes\n" : "preview field lines: no\n");
echo (str_contains($preview, 'data:image/png') ? "preview icons: yes\n" : "preview icons: no\n");
echo (str_contains($preview, '#8B5A4A') ? "preview brown: yes\n" : "preview brown: no\n");
