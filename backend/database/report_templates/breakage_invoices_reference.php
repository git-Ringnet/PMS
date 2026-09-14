<?php

use App\Services\TemplateRendererService;

/** Presentation only, based on image 2 of the supplied legacy report document. */
return new class
{
    private object $layout;

    public function __construct()
    {
        // Read-only reuse: no changes to the LA report or its supplied dataset.
        $this->layout = require __DIR__.'/laundry_invoices_reference.php';
    }

    public function definition(): array
    {
        return array_replace($this->layout->definition(), [
            'report' => 'BREAKAGE_INVOICES_REFERENCE',
            'name' => 'Báo cáo hóa đơn hàng bể vỡ - Mẫu tham chiếu',
            'content_html' => $this->html(),
            'content_json' => $this->blocks(),
        ]);
    }

    public function html(): string
    {
        return str_replace('BÁO CÁO HÓA ĐƠN GIẶT ỦI', 'BÁO CÁO HÓA ĐƠN HÀNG BỂ VỠ', $this->layout->html());
    }

    public function blocks(): array
    {
        $blocks = $this->layout->blocks();
        foreach ($blocks['header'] as &$block) {
            if (($block['id'] ?? '') === 'laundry_invoices_title') {
                $block['content'] = '<h1>BÁO CÁO HÓA ĐƠN HÀNG BỂ VỠ</h1>';
            }
        }
        unset($block);

        return $blocks;
    }

    public function css(): string
    {
        return $this->layout->css();
    }

    public function prepareData(array $data): array
    {
        return $this->layout->prepareData($data);
    }

    public function render(array $data, ?TemplateRendererService $renderer = null): string
    {
        $renderer ??= new TemplateRendererService();
        $pageOptions = array_intersect_key($this->definition(), array_flip([
            'page_size', 'page_orientation', 'margin_top', 'margin_bottom', 'margin_left', 'margin_right',
        ]));

        return $renderer->render($this->html(), $this->css(), $this->prepareData($data), $pageOptions);
    }
};
