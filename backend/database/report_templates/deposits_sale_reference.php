<?php

return new class
{
    public function definition(): array
    {
        return [
            'page_size' => 'A4', 'page_orientation' => 'portrait',
            'margin_top' => 8, 'margin_right' => 6, 'margin_bottom' => 8, 'margin_left' => 6,
            'content_html' => $this->html(), 'content_json' => $this->blocks(), 'css' => $this->css(),
        ];
    }

    private function html(): string
    {
        return $this->compileDesignerBlocks($this->blocks());
    }

    private function blocks(): array
    {
        $blocks = [
            'header' => [
                ['id'=>'deposits_sale_hotel_header','type'=>'text','content'=>'<div class="hotel-header"><div class="hotel-logo">{{hotel.logo}}</div><div class="hotel-information"><div><b>Địa chỉ:</b> {{hotel.address}}</div><div><b>Nhân viên:</b> {{report.generated_by}} <b class="generated-date">Ngày:</b> {{report.generated_at}}</div></div></div>','style'=>['fontSize'=>'10px','paddingTop'=>'0px','paddingBottom'=>'0px','paddingLeft'=>'8px','paddingRight'=>'8px','marginTop'=>'0px','marginBottom'=>'2px','fontWeight'=>'normal']],
                ['id'=>'deposits_sale_divider','type'=>'divider','content'=>'<hr class="header-divider">','style'=>['paddingTop'=>'0px','paddingBottom'=>'0px','paddingLeft'=>'8px','paddingRight'=>'8px','marginTop'=>'0px','marginBottom'=>'9px']],
                ['id'=>'deposits_sale_title','type'=>'text','content'=>'<h1>BÁO CÁO TIỀN ĐẶT CỌC</h1>','style'=>['textAlign'=>'center','fontSize'=>'18px','paddingTop'=>'0px','paddingBottom'=>'0px','paddingLeft'=>'0px','paddingRight'=>'0px','marginTop'=>'0px','marginBottom'=>'0px','fontWeight'=>'bold']],
                ['id'=>'deposits_sale_period','type'=>'text','content'=>'<p class="period"><b>Ngày:</b> {{parameters.p_from_date}} &nbsp; ~ &nbsp; {{parameters.p_to_date}}</p>','style'=>['textAlign'=>'center','fontSize'=>'10px','paddingTop'=>'0px','paddingBottom'=>'0px','paddingLeft'=>'0px','paddingRight'=>'0px','marginTop'=>'30px','marginBottom'=>'13px','fontWeight'=>'normal']],
            ],
            'detail' => [[
                'id'=>'deposits_sale_table','type'=>'table','dataSource'=>'rows','tableType'=>'dynamic','tableStyle'=>'grid','style'=>['fontSize'=>'10px','paddingTop'=>'0px','paddingBottom'=>'0px','paddingLeft'=>'0px','paddingRight'=>'0px','marginTop'=>'0px','marginBottom'=>'0px','fontWeight'=>'normal','width'=>'100%'],
                'groups'=>[['id'=>'deposits_sale_type_group','field'=>'GroupHeader','label'=>'{{row.GroupHeader}}','className'=>'deposit-type-group','enabledBy'=>'','sort'=>'ASC']],
                'columns'=>$this->columns(),
                'customRows'=>[
                    ['id'=>'deposits_sale_company_total','enabledBy'=>'','scope'=>'group','level'=>0,'className'=>'pms-group-footer','cells'=>[['id'=>'company_total_label','type'=>'text','content'=>'Tổng Theo C.ty','colspan'=>8,'align'=>'right'],['id'=>'company_total_value','type'=>'binding','binding'=>'row.CompanyTotal','colspan'=>1,'align'=>'right','format'=>'number'],['id'=>'company_total_spacer','type'=>'text','content'=>'','colspan'=>2,'align'=>'left']]],
                    ['id'=>'deposits_sale_deposit_total','enabledBy'=>'','scope'=>'table','level'=>0,'className'=>'deposit-total-row','cells'=>[['id'=>'deposit_total_label','type'=>'text','content'=>'Tổng Tiền Đặt Cọc','colspan'=>8,'align'=>'right'],['id'=>'deposit_total_value','type'=>'binding','binding'=>'aggregate.rows.sum.DepositAmount','colspan'=>1,'align'=>'right','format'=>'number'],['id'=>'deposit_total_spacer','type'=>'text','content'=>'','colspan'=>2,'align'=>'left']]],
                    ['id'=>'deposits_sale_total','enabledBy'=>'','scope'=>'table','level'=>0,'className'=>'report-total-row','cells'=>[['id'=>'report_total_label','type'=>'text','content'=>'Tổng','colspan'=>8,'align'=>'right'],['id'=>'report_total_value','type'=>'binding','binding'=>'aggregate.rows.sum.Amount','colspan'=>1,'align'=>'right','format'=>'number'],['id'=>'report_total_spacer','type'=>'text','content'=>'','colspan'=>2,'align'=>'left']]],
                ],
            ],['id'=>'deposits_sale_allocation_title','type'=>'text','content'=>'<h2>Bảng Phân Bổ Tiền Tệ</h2>','style'=>['textAlign'=>'center','fontSize'=>'18px','paddingTop'=>'0px','paddingBottom'=>'0px','paddingLeft'=>'0px','paddingRight'=>'0px','marginTop'=>'28px','marginBottom'=>'14px','fontWeight'=>'bold']],['id'=>'deposits_sale_allocation','type'=>'static-table','tableStyle'=>'grid','style'=>['fontSize'=>'10px','paddingTop'=>'0px','paddingBottom'=>'0px','paddingLeft'=>'0px','paddingRight'=>'0px','marginTop'=>'0px','marginBottom'=>'0px','marginLeft'=>'auto','marginRight'=>'auto','fontWeight'=>'normal','width'=>'84%'],'columns'=>[['width'=>'20%'],['width'=>'16%'],['width'=>'16%'],['width'=>'20%'],['width'=>'14%'],['width'=>'14%']],'rows'=>[['cells'=>[['content'=>'<b>HTTT</b>'],['content'=>'<b>Thu Ngân</b>'],['content'=>'<b>Đặt Cọc</b>'],['content'=>'<b>Thu Ngân + Đặt Cọc</b>'],['content'=>'<b>Hoàn Tiền</b>'],['content'=>'<b>Tổng</b>']] ],['cells'=>[['content'=>'{{parameters.p_payment_method}}'],['content'=>'{{aggregate.rows.sum.CashAmount|number}}'],['content'=>'{{aggregate.rows.sum.DepositAmount|number}}'],['content'=>'{{aggregate.rows.sum.PositiveAmount|number}}'],['content'=>'{{aggregate.rows.sum.RefundAmount|number}}'],['content'=>'{{aggregate.rows.sum.Amount|number}}']] ],['cells'=>[['content'=>'Tổng'],['content'=>''],['content'=>''],['content'=>''],['content'=>''],['content'=>'{{aggregate.rows.sum.Amount|number}}']] ]]],
            ],
            'footer'=>[['id'=>'deposits_sale_signatures','type'=>'text','content'=>'<div class="signatures">Nhân viên <span>Trưởng phòng</span> Bộ phận kế toán</div>','style'=>['textAlign'=>'center','fontSize'=>'10px','paddingTop'=>'0px','paddingBottom'=>'0px','paddingLeft'=>'0px','paddingRight'=>'0px','marginTop'=>'18px','marginBottom'=>'0px','marginLeft'=>'auto','marginRight'=>'auto','fontWeight'=>'bold','width'=>'84%']]],
        ];

        foreach ($blocks['detail'] as &$block) {
            if (($block['id'] ?? '') !== 'deposits_sale_allocation') {
                continue;
            }

            $block['rows'][0]['style']['fontWeight'] = 'normal';
            foreach ($block['rows'][0]['cells'] as &$cell) {
                $cell['content'] = strip_tags($cell['content'], '<br>');
            }
            unset($cell);
        }
        unset($block);

        return $blocks;
    }

    private function columns(): array
    {
        return [
            ['header'=>'Mã ĐK','value'=>'row.BookingCode','width'=>'7%','align'=>'center'],['header'=>'Phòng','value'=>'row.Room','width'=>'5%','align'=>'center'],['header'=>'Tên Khách','value'=>'row.GuestInfo','width'=>'12%','align'=>'left'],['header'=>'Ngày Đến','value'=>'row.ArrivalDate','width'=>'8%','align'=>'center'],['header'=>'Ngày Đi','value'=>'row.DepartureDate','width'=>'8%','align'=>'center'],['header'=>'Giờ','value'=>'row.OpenTime','width'=>'5%','align'=>'center'],['header'=>'Mã TT','value'=>'row.PaymentMethod','width'=>'5%','align'=>'center'],['header'=>'Mã HĐ','value'=>'row.BillID','width'=>'5%','align'=>'center'],['header'=>'Tổng','value'=>'row.Amount','width'=>'8%','align'=>'right','format'=>'number'],['header'=>'Người Dùng','value'=>'row.Username','width'=>'9%','align'=>'center'],['header'=>'Mô tả','value'=>'row.Description','width'=>'28%','align'=>'left'],
        ];
    }

    /**
     * Runtime HTML is a compiled artifact of the Designer blocks above.
     * Do not add report layout markup outside content_json.
     */
    private function compileDesignerBlocks(array $bands): string
    {
        $html = '';
        foreach (['header', 'detail', 'footer'] as $band) {
            $html .= '<div class="report-'.($band === 'detail' ? 'detail' : $band).'-band">';
            foreach ($bands[$band] ?? [] as $block) {
                $html .= $this->compileDesignerBlock($block);
            }
            $html .= '</div>';
        }

        return $html;
    }

    private function compileDesignerBlock(array $block): string
    {
        $id = htmlspecialchars((string) ($block['id'] ?? ''), ENT_QUOTES, 'UTF-8');
        $style = $this->compileStyle($block['style'] ?? []);
        $safeId = preg_replace('/[^a-zA-Z0-9_-]/', '-', $id);
        $class = 'pms-template-block-'.$safeId;
        $fontSize = !empty($block['style']['fontSize'])
            ? '<style>.'.$class.', .'.$class.' * { font-size: '.$block['style']['fontSize'].' !important; }</style>'
            : '';
        $open = $fontSize.'<div id="'.$id.'" class="'.$class.'" style="'.$style.'">';

        if (in_array($block['type'] ?? '', ['text', 'divider'], true)) {
            return $open."\n  ".($block['content'] ?? '')."\n</div>\n";
        }

        if (($block['type'] ?? '') === 'static-table') {
            $html = $open."\n  <table style=\"width: 100%; border-collapse: collapse; border: none;\">\n    <tbody>\n";
            foreach ($block['rows'] ?? [] as $row) {
                $rowStyle = $this->compileStyle($row['style'] ?? []);
                $html .= '      <tr style="'.$rowStyle.'">'."\n";
                foreach ($row['cells'] ?? [] as $index => $cell) {
                    $column = $block['columns'][$index] ?? [];
                    $cellStyle = $this->compileStyle(array_merge($row['style'] ?? [], $cell['style'] ?? []));
                    $html .= '        <td style="padding: 6px 8px; border: 1px solid #cbd5e1; width:'.($column['width'] ?? 'auto').';'.$cellStyle.'">'.($cell['content'] ?? '')."</td>\n";
                }
                $html .= "      </tr>\n";
            }

            return $html."    </tbody>\n  </table>\n</div>\n";
        }

        if (($block['type'] ?? '') !== 'table') {
            return $open.'</div>';
        }

        $columns = $block['columns'] ?? [];
        $html = $open."\n  <table style=\"width: 100%; border-collapse: collapse; border: none;\">\n    <thead>\n      <tr>\n";
        $tableStyle = $block['tableStyle'] ?? 'grid';
        $thStyle = 'padding: 6px 8px; font-weight: bold;';
        $tdStyle = 'padding: 6px 8px;';
        if ($tableStyle === 'grid') {
            $thStyle .= ' border-bottom: 2px solid #cbd5e1; border-right: 1px solid #cbd5e1;';
            $tdStyle .= ' border-bottom: 1px solid #e2e8f0; border-right: 1px solid #e2e8f0;';
        } elseif ($tableStyle === 'horizontal') {
            $thStyle .= ' border-bottom: 2px solid #cbd5e1;';
            $tdStyle .= ' border-bottom: 1px solid #e2e8f0;';
        } else {
            $thStyle .= ' border: none;';
            $tdStyle .= ' border: none;';
        }
        foreach ($columns as $column) {
            $html .= '        <th style="'.$thStyle.' width: '.($column['width'] ?? 'auto').'; text-align: '.($column['align'] ?? 'left').';">'.($column['header'] ?? '')."</th>\n";
        }
        $html .= "      </tr>\n    </thead>\n";
        $groups = $block['groups'] ?? [];
        $source = htmlspecialchars((string) ($block['dataSource'] ?? 'rows'), ENT_QUOTES, 'UTF-8');
        $html .= '<tbody'.($groups ? ' class="pms-grouped-rows" data-source="'.$source.'" data-group-by="'.htmlspecialchars((string) ($groups[0]['field'] ?? ''), ENT_QUOTES, 'UTF-8').'"' : '').'>';
        foreach ($groups as $group) {
            $html .= '<tr class="pms-group-header" data-group-level="0" data-group-field="'.htmlspecialchars((string) ($group['field'] ?? ''), ENT_QUOTES, 'UTF-8').'" data-group-sort="'.($group['sort'] ?? 'ASC').'">';
            $html .= '<td colspan="'.max(1, count($columns)).'" style="'.$tdStyle.' text-align: left; font-weight: bold;">'.($group['label'] ?? '').'</td></tr>';
        }
        $html .= '      <tr class="pms-detail-row"'.($groups ? '' : ' data-source="'.$source.'"').">\n";
        foreach ($columns as $column) {
            $format = ($column['format'] ?? '') === 'number' ? '|number' : '';
            $html .= '        <td style="'.$tdStyle.' text-align: '.($column['align'] ?? 'left').';">{{'.($column['value'] ?? '').$format."}}</td>\n";
        }
        $html .= "      </tr>\n";
        foreach (($block['customRows'] ?? []) as $row) {
            if (($row['scope'] ?? 'table') === 'table') {
                continue;
            }
            $html .= $this->compileCustomRow($row);
        }
        $html .= '</tbody>';
        $tableRows = array_filter($block['customRows'] ?? [], fn (array $row) => ($row['scope'] ?? 'table') === 'table');
        if ($tableRows) {
            $html .= '<tfoot>';
            foreach ($tableRows as $row) {
                $html .= $this->compileCustomRow($row);
            }
            $html .= '</tfoot>';
        }

        return $html.'</table></div>';
    }

    private function compileCustomRow(array $row): string
    {
        $html = '<tr class="pms-custom-row '.htmlspecialchars((string) ($row['className'] ?? ''), ENT_QUOTES, 'UTF-8').'">';
        foreach ($row['cells'] ?? [] as $cell) {
            $content = $cell['content'] ?? '';
            if (($cell['type'] ?? '') === 'binding') {
                $content = '{{'.($cell['binding'] ?? '').(($cell['format'] ?? '') === 'number' ? '|number' : '').'}}';
            }
            $html .= '<td colspan="'.max(1, (int) ($cell['colspan'] ?? 1)).'" style="padding: 6px 8px; text-align: '.($cell['align'] ?? 'left').'; font-weight: bold;">'.$content.'</td>';
        }

        return $html.'</tr>';
    }

    private function compileStyle(array $style): string
    {
        $parts = [];
        foreach ($style as $name => $value) {
            if ($value !== null && $value !== '') {
                $parts[] = strtolower((string) preg_replace('/([A-Z])/', '-$1', (string) $name)).': '.$value;
            }
        }

        return implode('; ', $parts);
    }

    private function css(): string
    {
        return <<<'CSS'
body{color:#111;font-family:Arial,Helvetica,sans-serif;font-size:10px}.hotel-header{display:grid;grid-template-columns:300px 1fr;align-items:center;min-height:65px}.hotel-logo{display:flex;align-items:center;min-height:55px}.hotel-logo img{max-width:120px;max-height:55px;object-fit:contain}.hotel-information{line-height:1.9;text-align:right}.generated-date{margin-left:140px}.header-divider{margin:0;border:0;border-top:1px solid #333}h1{margin:0;text-align:center;font-size:18px;font-weight:bold}.period{margin:0;text-align:center}.deposit-table,.allocation-table,#deposits_sale_table table,#deposits_sale_allocation table{width:100%;border-collapse:collapse;table-layout:fixed}.deposit-table th,.deposit-table td,.allocation-table th,.deposit-table td,#deposits_sale_table th,#deposits_sale_table td,#deposits_sale_allocation th,#deposits_sale_allocation td{border:1px solid #aeb5c0;padding:4px 3px;line-height:1.1;vertical-align:middle;overflow-wrap:anywhere}.deposit-table th,.allocation-table th,#deposits_sale_table th,#deposits_sale_allocation th,#deposits_sale_allocation tr:first-child td{background:#d9deea;text-align:center;font-weight:bold}.pms-group-header td,.deposit-type-group{background:#fff;border-bottom:0!important;font-weight:bold;text-align:left!important}.pms-group-footer td,.deposit-total-row td,.report-total-row td,.allocation-total td,#deposits_sale_allocation tr:last-child td{background:#d9deea;font-weight:bold}.money,.deposit-table td:nth-child(9),#deposits_sale_table td:nth-child(9){text-align:right;white-space:nowrap}.deposit-table td:nth-child(1),.deposit-table td:nth-child(2),.deposit-table td:nth-child(4),.deposit-table td:nth-child(5),.deposit-table td:nth-child(6),.deposit-table td:nth-child(7),.deposit-table td:nth-child(8),.deposit-table td:nth-child(10),#deposits_sale_table td:nth-child(1),#deposits_sale_table td:nth-child(2),#deposits_sale_table td:nth-child(4),#deposits_sale_table td:nth-child(5),#deposits_sale_table td:nth-child(6),#deposits_sale_table td:nth-child(7),#deposits_sale_table td:nth-child(8),#deposits_sale_table td:nth-child(10){text-align:center}.allocation-table,#deposits_sale_allocation table{width:84%;margin:0 auto}.allocation-table td:not(:first-child),#deposits_sale_allocation td:not(:first-child){text-align:right;white-space:nowrap}#deposits_sale_hotel_header{margin:0 0 2px;padding:0 8px}#deposits_sale_divider{margin:0 8px 9px;padding:0}#deposits_sale_title{margin:0;padding:0}#deposits_sale_period{margin:30px 0 13px;padding:0}#deposits_sale_table{margin:0;padding:0}#deposits_sale_table th,#deposits_sale_table td{padding:4px 3px!important;font-size:10px}#deposits_sale_allocation_title{margin:28px 0 14px;padding:0}#deposits_sale_allocation{margin:0 auto;padding:0}#deposits_sale_allocation td{padding:4px 3px!important;line-height:1.1}#deposits_sale_allocation tr:first-child td{white-space:normal!important;overflow-wrap:anywhere;word-break:break-word}#deposits_sale_allocation tr:not(:first-child) td{font-weight:normal!important}#deposits_sale_allocation tr:last-child td{font-weight:bold!important}#deposits_sale_signatures{margin:18px auto 0;padding:0}.signatures{display:grid;grid-template-columns:repeat(3,1fr);margin:0 auto;width:84%;text-align:center;font-size:10px;font-weight:bold}.signatures span{display:block}h2{margin:0;text-align:center;font-size:18px;font-weight:bold}@media print{thead{display:table-header-group}tr{break-inside:avoid}}
CSS;
    }
};
