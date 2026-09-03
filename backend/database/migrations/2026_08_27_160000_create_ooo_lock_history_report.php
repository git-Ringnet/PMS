<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::connection()->getDriverName() !== 'mysql') return;
        DB::unprepared('DROP PROCEDURE IF EXISTS rpt_ooo_lock_history');
        DB::unprepared(<<<'SQL'
CREATE PROCEDURE rpt_ooo_lock_history(
    IN p_from_date DATE, IN p_to_date DATE, IN p_user VARCHAR(50),
    IN p_sort_by VARCHAR(20), IN p_order_by VARCHAR(20)
)
READS SQL DATA
BEGIN
    SELECT
        CASE WHEN COALESCE(rl.is_active, 1) = 1 THEN 'Locking' ELSE 'Unlock' END AS GroupName,
        rl.room_number AS Room,
        DATE_FORMAT(rl.start_date, '%d/%m/%Y %H:%i') AS DateBeginTime,
        CASE WHEN rl.end_date IS NULL THEN NULL ELSE DATE_FORMAT(rl.end_date, '%d/%m/%Y %H:%i') END AS EndDateTime,
        rl.unlock_username AS UserUnlock,
        DATE_FORMAT(rl.created_at, '%d/%m/%Y %H:%i') AS LockDateTime,
        rl.username AS Username,
        rl.reason AS Note,
        rl.start_date AS DateOrder
    FROM room_locks rl
    WHERE UPPER(COALESCE(rl.lock_type, 'OOO')) = 'OOO'
      AND (rl.start_date IS NULL OR DATE(rl.start_date) <= p_to_date)
      AND (rl.end_date IS NULL OR DATE(rl.end_date) >= p_from_date)
      AND (p_user IS NULL OR p_user = '' OR rl.username LIKE CONCAT('%', p_user, '%'))
    ORDER BY
      CASE WHEN p_sort_by = 'Room' AND p_order_by = 'ASC' THEN LENGTH(rl.room_number) END ASC,
      CASE WHEN p_sort_by = 'Room' AND p_order_by = 'ASC' THEN rl.room_number END ASC,
      CASE WHEN p_sort_by = 'Room' AND p_order_by = 'DESC' THEN LENGTH(rl.room_number) END DESC,
      CASE WHEN p_sort_by = 'Room' AND p_order_by = 'DESC' THEN rl.room_number END DESC,
      CASE WHEN p_sort_by = 'Date' AND p_order_by = 'DESC' THEN rl.start_date END DESC,
      rl.start_date ASC;
END
SQL);

        $now = now(); $db = DB::connection()->getDatabaseName();
        $params = [
            ['name'=>'p_from_date','mode'=>'IN','data_type'=>'date','database_type'=>'date','position'=>1,'required'=>true],
            ['name'=>'p_to_date','mode'=>'IN','data_type'=>'date','database_type'=>'date','position'=>2,'required'=>true],
            ['name'=>'p_user','mode'=>'IN','data_type'=>'varchar','database_type'=>'varchar(50)','position'=>3,'required'=>false],
            ['name'=>'p_sort_by','mode'=>'IN','data_type'=>'varchar','database_type'=>'varchar(20)','position'=>4,'required'=>false],
            ['name'=>'p_order_by','mode'=>'IN','data_type'=>'varchar','database_type'=>'varchar(20)','position'=>5,'required'=>false],
        ];
        $fields = collect(['GroupName','Room','DateBeginTime','EndDateTime','UserUnlock','LockDateTime','Username','Note','DateOrder'])->map(fn($name)=>['name'=>$name,'type'=>'string','nullable'=>true])->all();
        DB::table('report_data_sources')->updateOrInsert(['code'=>'OOO_LOCK_HISTORY'],['name'=>'Dữ liệu lịch sử khóa phòng OOO','description'=>'MySQL chuyển đổi từ ProVista sp_057/SP4001.','source_type'=>'procedure','schema_name'=>$db,'object_name'=>'rpt_ooo_lock_history','parameter_schema'=>json_encode($params,JSON_UNESCAPED_UNICODE),'field_schema'=>json_encode($fields,JSON_UNESCAPED_UNICODE),'sample_parameters'=>json_encode(['p_from_date'=>now()->toDateString(),'p_to_date'=>now()->toDateString(),'p_user'=>'','p_sort_by'=>'Room','p_order_by'=>'ASC'],JSON_UNESCAPED_UNICODE),'max_rows'=>5000,'is_active'=>true,'last_discovered_at'=>$now,'created_at'=>$now,'updated_at'=>$now]);
        $sid=DB::table('report_data_sources')->where('code','OOO_LOCK_HISTORY')->value('id');
        DB::table('templates')->updateOrInsert(['report'=>'OOO_LOCK_HISTORY_STANDARD'],['group'=>'Báo cáo phòng khóa','name'=>'Báo cáo lịch sử khóa phòng OOO - Mẫu tham chiếu legacy','report_data_source_id'=>$sid,'page_size'=>'A4','page_orientation'=>'landscape','margin_top'=>6,'margin_bottom'=>6,'margin_left'=>5,'margin_right'=>5,'content_json'=>'{}','content_html'=>'','css'=>'','version'=>'1.0','created_at'=>$now,'updated_at'=>$now]);
        (require database_path('report_templates/ooo_lock_history_reference.php'))->apply();
        $tid=DB::table('templates')->where('report','OOO_LOCK_HISTORY_STANDARD')->value('id');
        DB::table('report_definitions')->updateOrInsert(['code'=>'OOO_LOCK_HISTORY'],['name'=>'Báo cáo lịch sử khóa phòng OOO','group'=>'Báo cáo phòng khóa','description'=>'Lịch sử khóa và mở khóa phòng OOO theo sp_057.','report_data_source_id'=>$sid,'parameter_ui_schema'=>json_encode([['name'=>'p_from_date','label'=>'Ngày','control'=>'date-range','range_end_parameter'=>'p_to_date','default'=>'$today','required'=>true],['name'=>'p_to_date','label'=>'Đến ngày','control'=>'hidden','default'=>'$today','required'=>true],['name'=>'p_user','label'=>'Người dùng','control'=>'select','default'=>'','required'=>false,'options_source'=>'users'],['name'=>'p_sort_by','label'=>'Sắp xếp theo','control'=>'select','default'=>'Room','required'=>true,'options'=>[['label'=>'Phòng','value'=>'Room'],['label'=>'Ngày','value'=>'Date']]],['name'=>'p_order_by','label'=>'Thứ tự','control'=>'select','default'=>'ASC','required'=>true,'options'=>[['label'=>'ASC','value'=>'ASC'],['label'=>'DESC','value'=>'DESC']]]],JSON_UNESCAPED_UNICODE),'sort_order'=>13,'is_active'=>true,'show_in_menu'=>true,'menu_locations'=>json_encode(['reservation','frontdesk']),'menu_top_order'=>20,'menu_group_order'=>20,'menu_item_order'=>10,'created_at'=>$now,'updated_at'=>$now]);
        $rid=DB::table('report_definitions')->where('code','OOO_LOCK_HISTORY')->value('id'); DB::table('report_definition_template')->updateOrInsert(['report_definition_id'=>$rid,'template_id'=>$tid],['is_default'=>true,'sort_order'=>0,'created_at'=>$now,'updated_at'=>$now]);
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() !== 'mysql') return;
        $rid=DB::table('report_definitions')->where('code','OOO_LOCK_HISTORY')->value('id'); $tid=DB::table('templates')->where('report','OOO_LOCK_HISTORY_STANDARD')->value('id'); $sid=DB::table('report_data_sources')->where('code','OOO_LOCK_HISTORY')->value('id');
        if($rid){DB::table('report_definition_template')->where('report_definition_id',$rid)->delete();DB::table('report_definitions')->where('id',$rid)->delete();} if($tid)DB::table('templates')->where('id',$tid)->delete(); if($sid)DB::table('report_data_sources')->where('id',$sid)->delete(); DB::unprepared('DROP PROCEDURE IF EXISTS rpt_ooo_lock_history');
    }
};
