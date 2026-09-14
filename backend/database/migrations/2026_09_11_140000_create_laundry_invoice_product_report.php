<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const CODE = 'LAUNDRY_INVOICES_BY_PRODUCT';
    private const PROCEDURE = 'rpt_laundry_invoices_by_product';
    private const TEMPLATE = 'LAUNDRY_INVOICES_BY_PRODUCT_STANDARD';

    public function up(): void
    {
        if (DB::connection()->getDriverName() !== 'mysql') return;
        DB::unprepared('DROP PROCEDURE IF EXISTS `'.self::PROCEDURE.'`');
        DB::unprepared($this->procedureSql());
        $now = now(); $db = DB::connection()->getDatabaseName();
        $params = $this->parameters(); $fields = $this->fields();
        $defaults = ['p_from_date'=>now()->toDateString(),'p_to_date'=>now()->toDateString(),'p_shift'=>'','p_department'=>'','p_user'=>'','p_freeitem'=>0,'p_group_by_date'=>false];
        DB::table('report_data_sources')->updateOrInsert(['code'=>self::CODE], ['name'=>'Dữ liệu hóa đơn giặt ủi theo sản phẩm','description'=>'Tổng hợp sản phẩm theo logic legacy sp_206 với freeitem=0.','source_type'=>'procedure','schema_name'=>$db,'object_name'=>self::PROCEDURE,'parameter_schema'=>json_encode($params, JSON_UNESCAPED_UNICODE),'field_schema'=>json_encode($fields, JSON_UNESCAPED_UNICODE),'sample_parameters'=>json_encode($defaults),'max_rows'=>5000,'is_active'=>true,'last_discovered_at'=>$now,'created_at'=>$now,'updated_at'=>$now]);
        $sourceId = DB::table('report_data_sources')->where('code',self::CODE)->value('id');
        $provider = require database_path('report_templates/laundry_invoices_by_product_reference.php'); $td = $provider->definition();
        DB::table('templates')->updateOrInsert(['report'=>self::TEMPLATE], ['group'=>'Báo cáo dịch vụ','name'=>$td['name'],'report_data_source_id'=>$sourceId,'parameter_defaults'=>json_encode($defaults),'page_size'=>$td['page_size'],'page_orientation'=>$td['page_orientation'],'margin_top'=>$td['margin_top'],'margin_bottom'=>$td['margin_bottom'],'margin_left'=>$td['margin_left'],'margin_right'=>$td['margin_right'],'content_json'=>json_encode($td['content_json'],JSON_UNESCAPED_UNICODE),'content_html'=>$td['content_html'],'css'=>$td['css'],'is_default'=>false,'version'=>$td['version'],'created_at'=>$now,'updated_at'=>$now]);
        $templateId=DB::table('templates')->where('report',self::TEMPLATE)->value('id');
        DB::table('report_definitions')->updateOrInsert(['code'=>self::CODE], ['name'=>'Báo cáo hóa đơn giặt ủi theo sản phẩm','group'=>'Báo cáo dịch vụ','description'=>'Tổng hợp sản phẩm giặt ủi theo sp_206 legacy.','report_data_source_id'=>$sourceId,'parameter_ui_schema'=>json_encode($this->ui(),JSON_UNESCAPED_UNICODE),'sort_order'=>41,'is_active'=>true,'show_in_menu'=>true,'menu_locations'=>json_encode(['reservation','frontdesk']),'menu_top_order'=>40,'menu_group_order'=>20,'menu_item_order'=>41,'created_at'=>$now,'updated_at'=>$now]);
        $reportId=DB::table('report_definitions')->where('code',self::CODE)->value('id');
        DB::table('report_definition_template')->updateOrInsert(['report_definition_id'=>$reportId,'template_id'=>$templateId],['is_default'=>true,'sort_order'=>0,'created_at'=>$now,'updated_at'=>$now]);
    }
    public function procedureSql(): string
    {
        return <<<'SQL'
CREATE PROCEDURE `rpt_laundry_invoices_by_product`(IN p_from_date DATE,IN p_to_date DATE,IN p_shift VARCHAR(20),IN p_department VARCHAR(50),IN p_user VARCHAR(50),IN p_freeitem TINYINT,IN p_group_by_date TINYINT)
READS SQL DATA
BEGIN
 SELECT CASE WHEN COALESCE(p_group_by_date, 0)=1 THEN DATE_FORMAT(MIN(h.Date), '%d/%m/%Y') ELSE NULL END AS DateGroup,
        MIN(d.Product) AS Product, MIN(d.MaProduct) AS MaProduct, MIN(d.ProductGroupId) AS ProductType, MIN(h.Currency) AS Currency,
        SUM(d.Quantity) AS Quantity, AVG(d.Rate) AS Rate, AVG(d.Rate + d.DiscountAmount / NULLIF(d.Quantity, 0)) AS StandardRate, SUM(d.TotalAmount) AS Total,
        SUM(d.DiscountAmount) AS DiscountAmount
 FROM housekeeping_service_bill_details d
 JOIN housekeeping_service_bills h ON h.Ma=d.BillId
 WHERE h.Outlet='LA' AND h.Date>=p_from_date AND h.Date<DATE_ADD(p_to_date,INTERVAL 1 DAY)
   AND h.Status=1
   AND ((COALESCE(p_freeitem,0)=0 AND COALESCE(h.FOCType,0)=0)
        OR (COALESCE(p_freeitem,0)=1 AND COALESCE(h.FOCType,0)<>0))
   AND (p_user IS NULL OR p_user='' OR h.BillUsername LIKE CONCAT('%',p_user,'%'))
   AND (p_shift IS NULL OR p_shift='' OR CAST(h.BillShift AS CHAR) LIKE CONCAT('%',p_shift,'%'))
   AND (p_department IS NULL OR p_department='' OR h.Department LIKE CONCAT('%',p_department,'%'))
   AND d.Deleted=0
 GROUP BY CASE WHEN COALESCE(p_group_by_date, 0)=1 THEN DATE(h.Date) ELSE NULL END,d.MaProduct,d.ProductGroupId,d.Product
 HAVING SUM(d.Quantity)>0 ORDER BY d.Product;
END
SQL;
    }
    private function parameters(): array { return [['name'=>'p_from_date','mode'=>'IN','data_type'=>'date','database_type'=>'date','position'=>1,'required'=>true],['name'=>'p_to_date','mode'=>'IN','data_type'=>'date','database_type'=>'date','position'=>2,'required'=>true],['name'=>'p_shift','mode'=>'IN','data_type'=>'varchar','database_type'=>'varchar(20)','position'=>3,'required'=>false],['name'=>'p_department','mode'=>'IN','data_type'=>'varchar','database_type'=>'varchar(50)','position'=>4,'required'=>false],['name'=>'p_user','mode'=>'IN','data_type'=>'varchar','database_type'=>'varchar(50)','position'=>5,'required'=>false],['name'=>'p_freeitem','mode'=>'IN','data_type'=>'tinyint','database_type'=>'tinyint','position'=>6,'required'=>false],['name'=>'p_group_by_date','mode'=>'IN','data_type'=>'tinyint','database_type'=>'tinyint','position'=>7,'required'=>false]]; }
    private function fields(): array { return array_map(fn($n)=>['name'=>$n,'type'=>in_array($n,['MaProduct','ProductType','Quantity','Rate','StandardRate','Total','DiscountAmount'],true)?'number':'string','nullable'=>true],['DateGroup','Product','MaProduct','ProductType','Currency','Quantity','Rate','StandardRate','Total','DiscountAmount']); }
    private function ui(): array { return [['name'=>'p_from_date','label'=>'Ngày','control'=>'date-range','range_end_parameter'=>'p_to_date','default'=>'$today','required'=>true],['name'=>'p_to_date','label'=>'Đến ngày','control'=>'hidden','default'=>'$today','required'=>true],['name'=>'p_shift','label'=>'Ca làm việc','control'=>'select','default'=>'','required'=>false,'placeholder'=>'Select Value','options_source'=>'report-shifts','options'=>[]],['name'=>'p_department','label'=>'Chọn bộ phận','control'=>'select','default'=>'','required'=>false,'placeholder'=>'Select Value','options_source'=>'service-departments','options'=>[]],['name'=>'p_user','label'=>'Chọn người dùng','control'=>'select','default'=>'','required'=>false,'placeholder'=>'Select Value','options_source'=>'users','options'=>[]],['name'=>'p_freeitem','label'=>'Hàng bán','control'=>'checkbox','default'=>0,'required'=>false],['name'=>'p_group_by_date','label'=>'Nhóm theo ngày','control'=>'checkbox','default'=>false,'required'=>false]]; }
    public function down(): void { if (DB::connection()->getDriverName() !== 'mysql') return; $id=DB::table('report_definitions')->where('code',self::CODE)->value('id'); $tid=DB::table('templates')->where('report',self::TEMPLATE)->value('id'); if($id) DB::table('report_definition_template')->where('report_definition_id',$id)->delete(); if($id) DB::table('report_definitions')->where('id',$id)->delete(); if($tid) DB::table('templates')->where('id',$tid)->delete(); DB::table('report_data_sources')->where('code',self::CODE)->delete(); DB::unprepared('DROP PROCEDURE IF EXISTS `'.self::PROCEDURE.'`'); }
};
