<?php

use App\Services\TemplateRendererService;

/** Presentation-only reference provider for the legacy MB (minibar) report. */
return new class
{
    private object $layout;

    public function __construct()
    {
        // Read-only composition of the verified LA layout; no template-table or DB write.
        $this->layout = require __DIR__.'/laundry_invoices_reference.php';
    }

    private function title(): string
    {
        return 'BÁO CÁO HÓA ĐƠN MINIBAR';
    }

    public function definition(): array
    {
        return array_replace($this->layout->definition(), [
            'report' => 'MINIBAR_INVOICES_REFERENCE',
            'name' => 'Báo cáo hóa đơn minibar - Mẫu tham chiếu',
            'content_html' => $this->html(),
            'content_json' => $this->blocks(),
        ]);
    }

    public function html(): string
    {
        // Replace only in the static template source, before caller data reaches the renderer.
        return str_replace(
            'BÁO CÁO HÓA ĐƠN GIẶT ỦI',
            $this->title(),
            $this->layout->html()
        );
    }

    public function blocks(): array
    {
        $blocks = $this->layout->blocks();
        foreach ($blocks['header'] as &$block) {
            if (($block['id'] ?? '') === 'laundry_invoices_title') {
                $block['id'] = 'minibar_invoices_title';
                $block['content'] = '<h1>'.$this->title().'</h1>';
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
        $definition = $this->definition();
        $pageOptions = array_intersect_key($definition, array_flip([
            'page_size', 'page_orientation', 'margin_top', 'margin_bottom', 'margin_left', 'margin_right',
        ]));

        return $renderer->render($this->html(), $this->css(), $this->prepareData($data), $pageOptions);
    }
};
