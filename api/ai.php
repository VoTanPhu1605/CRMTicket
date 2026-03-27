<?php
ob_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/ai_config.php';
require_once __DIR__ . '/../includes/functions.php';

if (session_status() === PHP_SESSION_NONE) session_start();

function aiJson($data) {
    ob_end_clean();
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit();
}

if (empty($_SESSION['user_id']))  aiJson(['success'=>false,'message'=>'Chưa đăng nhập.']);
if (!AI_ENABLED)                  aiJson(['success'=>false,'message'=>'Chưa cấu hình GROQ_API_KEY.']);

$input   = json_decode(file_get_contents('php://input'), true);
$action  = $input['action']  ?? 'crm_agent';
$content = trim($input['content'] ?? '');
$context = $input['context'] ?? [];
$history = $input['history'] ?? [];

// ── Groq HTTP ─────────────────────────────────────────────────────────────────
function callGroq($payload) {
    $ch = curl_init('https://api.groq.com/openai/v1/chat/completions');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => json_encode($payload),
        CURLOPT_HTTPHEADER     => ['Content-Type: application/json','Authorization: Bearer '.GROQ_API_KEY],
        CURLOPT_TIMEOUT        => 45,
        CURLOPT_SSL_VERIFYPEER => false,
    ]);
    $res  = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return [$res, $code];
}

// ── Tool definitions (short descriptions = fewer tokens) ─────────────────────
function getCRMTools() {
    $p0 = ['type'=>'object','properties'=>new stdClass,'required'=>[]];
    $pL = ['type'=>'object','properties'=>['limit'=>['type'=>'integer']],'required'=>[]];
    return [
        ['type'=>'function','function'=>['name'=>'get_ticket_detail',
            'description'=>'Chi tiết ticket: KH, trạng thái, ghi chú, lịch sử, thanh toán.',
            'parameters'=>['type'=>'object','properties'=>['ticket_id'=>['type'=>'integer']],'required'=>['ticket_id']]]],

        ['type'=>'function','function'=>['name'=>'get_ticket_stats',
            'description'=>'Thống kê tổng: ticket theo trạng thái, ưu tiên, danh mục, quá hạn.',
            'parameters'=>$p0]],

        ['type'=>'function','function'=>['name'=>'search_tickets',
            'description'=>'Tìm ticket theo tên KH, tiêu đề, SĐT, email. Lọc trạng thái/danh mục.',
            'parameters'=>['type'=>'object','properties'=>[
                'query'=>['type'=>'string'],'status'=>['type'=>'string'],
                'category'=>['type'=>'string'],'limit'=>['type'=>'integer']],'required'=>['query']]]],

        ['type'=>'function','function'=>['name'=>'get_customer_history',
            'description'=>'Tất cả ticket của một khách hàng (tên/email/SĐT).',
            'parameters'=>['type'=>'object','properties'=>['query'=>['type'=>'string']],'required'=>['query']]]],

        ['type'=>'function','function'=>['name'=>'get_priority_queue',
            'description'=>'Ticket cần xử lý gấp nhất: quá hạn + ưu tiên cao + chưa phân công.',
            'parameters'=>$pL]],

        ['type'=>'function','function'=>['name'=>'get_overdue_tickets',
            'description'=>'Ticket quá hạn chưa đóng.','parameters'=>$p0]],

        ['type'=>'function','function'=>['name'=>'get_recent_tickets',
            'description'=>'Ticket mới nhất.','parameters'=>$pL]],

        ['type'=>'function','function'=>['name'=>'get_unassigned_tickets',
            'description'=>'Ticket chưa phân công.','parameters'=>$p0]],

        ['type'=>'function','function'=>['name'=>'get_staff_workload',
            'description'=>'Số ticket đang xử lý từng nhân viên.','parameters'=>$p0]],

        ['type'=>'function','function'=>['name'=>'get_staff_expertise',
            'description'=>'Chuyên môn nhân viên: loại ticket giỏi, thời gian TB xử lý.','parameters'=>$p0]],

        ['type'=>'function','function'=>['name'=>'recommend_assignee',
            'description'=>'Đề xuất nhân viên tốt nhất cho ticket (chuyên môn + tải lượng).',
            'parameters'=>['type'=>'object','properties'=>['ticket_id'=>['type'=>'integer']],'required'=>['ticket_id']]]],

        ['type'=>'function','function'=>['name'=>'get_performance_report',
            'description'=>'Hiệu suất nhân viên: đã giải quyết bao nhiêu, thời gian TB, đúng hạn.',
            'parameters'=>$p0]],

        ['type'=>'function','function'=>['name'=>'find_similar_solutions',
            'description'=>'Tìm ticket đã đóng tương tự kèm ghi chú giải pháp.',
            'parameters'=>['type'=>'object','properties'=>['query'=>['type'=>'string']],'required'=>['query']]]],

        ['type'=>'function','function'=>['name'=>'get_warranty_expiring',
            'description'=>'Bảo hành sắp hết hạn trong N ngày.',
            'parameters'=>['type'=>'object','properties'=>['days'=>['type'=>'integer']],'required'=>[]]]],

        ['type'=>'function','function'=>['name'=>'get_revenue_summary',
            'description'=>'Doanh thu: đã thu, chờ thu, theo danh mục và tháng.','parameters'=>$p0]],
    ];
}

// ── Tool execution ────────────────────────────────────────────────────────────
function executeCRMTool($pdo, $name, $args) {
    switch ($name) {

        case 'get_ticket_detail':
            $id = (int)($args['ticket_id'] ?? 0);
            if (!$id) return ['loi'=>'Thiếu ticket_id'];
            $s = $pdo->prepare("
                SELECT t.*, c.name as danh_muc, p.name as uu_tien,
                       s.name as trang_thai,
                       ua.fullname as phan_cong_cho, uc.fullname as nguoi_tao,
                       b.price as gia, b.payment_status as thanh_toan, b.template_name as dich_vu
                FROM tickets t
                JOIN categories c ON c.id=t.category_id JOIN priorities p ON p.id=t.priority_id
                JOIN statuses s   ON s.id=t.status_id
                LEFT JOIN users ua ON ua.id=t.assigned_to
                LEFT JOIN users uc ON uc.id=t.created_by
                LEFT JOIN ticket_billing b ON b.ticket_id=t.id
                WHERE t.id=?");
            $s->execute([$id]);
            $ticket = $s->fetch(PDO::FETCH_ASSOC);
            if (!$ticket) return ['loi'=>"Ticket #$id không tồn tại"];
            $n = $pdo->prepare("SELECT n.note, n.created_at, u.fullname as nguoi_viet FROM ticket_notes n JOIN users u ON u.id=n.created_by WHERE n.ticket_id=? ORDER BY n.created_at ASC");
            $n->execute([$id]);
            $a = $pdo->prepare("SELECT al.action_type, al.description, al.created_at, u.fullname as nguoi FROM ticket_activity_log al LEFT JOIN users u ON u.id=al.user_id WHERE al.ticket_id=? ORDER BY al.created_at DESC LIMIT 8");
            $a->execute([$id]);
            return ['ticket'=>$ticket,'ghi_chu'=>$n->fetchAll(PDO::FETCH_ASSOC),'lich_su'=>$a->fetchAll(PDO::FETCH_ASSOC)];

        case 'get_ticket_stats':
            $r = $pdo->query("SELECT COUNT(*) as tong,
                SUM(CASE WHEN s.name='Mở' THEN 1 ELSE 0 END) as mo,
                SUM(CASE WHEN s.name='Đang chờ' THEN 1 ELSE 0 END) as cho,
                SUM(CASE WHEN s.name='Đang xử lý' THEN 1 ELSE 0 END) as xu_ly,
                SUM(CASE WHEN s.name='Đã đóng' THEN 1 ELSE 0 END) as dong,
                SUM(CASE WHEN t.due_date<CURDATE() AND s.name!='Đã đóng' THEN 1 ELSE 0 END) as qua_han,
                SUM(CASE WHEN t.assigned_to IS NULL AND s.name!='Đã đóng' THEN 1 ELSE 0 END) as chua_pc,
                (SELECT COUNT(*) FROM tickets WHERE DATE(created_at)=CURDATE()) as hom_nay
                FROM tickets t JOIN statuses s ON s.id=t.status_id")->fetch(PDO::FETCH_ASSOC);
            $p = $pdo->query("SELECT p.name as uu_tien,COUNT(*) as sl FROM tickets t JOIN priorities p ON p.id=t.priority_id JOIN statuses s ON s.id=t.status_id WHERE s.name!='Đã đóng' GROUP BY p.name ORDER BY sl DESC")->fetchAll(PDO::FETCH_ASSOC);
            $c = $pdo->query("SELECT c.name as danh_muc,COUNT(*) as tong,SUM(CASE WHEN s.name='Đã đóng' THEN 1 ELSE 0 END) as dong FROM tickets t JOIN categories c ON c.id=t.category_id JOIN statuses s ON s.id=t.status_id GROUP BY c.name ORDER BY tong DESC")->fetchAll(PDO::FETCH_ASSOC);
            return ['thong_ke'=>$r,'uu_tien'=>$p,'danh_muc'=>$c];

        case 'search_tickets':
            $q = '%'.($args['query']??'').'%'; $st=$args['status']??''; $ca=$args['category']??'';
            $lm = min((int)($args['limit']??5),15);
            $w = '(t.title LIKE ? OR t.customer_name LIKE ? OR t.customer_phone LIKE ? OR t.customer_email LIKE ?)';
            $pm = [$q,$q,$q,$q];
            if ($st){$w.=' AND s.name=?';$pm[]=$st;} if($ca){$w.=' AND c.name LIKE ?';$pm[]="%$ca%";}
            $s=$pdo->prepare("SELECT t.id,t.title,t.customer_name,t.customer_phone,s.name as trang_thai,p.name as uu_tien,t.due_date,u.fullname as phan_cong,c.name as danh_muc,t.created_at FROM tickets t JOIN statuses s ON s.id=t.status_id JOIN priorities p ON p.id=t.priority_id JOIN categories c ON c.id=t.category_id LEFT JOIN users u ON u.id=t.assigned_to WHERE $w ORDER BY t.created_at DESC LIMIT $lm");
            $s->execute($pm); return $s->fetchAll(PDO::FETCH_ASSOC);

        case 'get_customer_history':
            $q='%'.($args['query']??'').'%';
            $s=$pdo->prepare("SELECT t.id,t.title,t.created_at,t.customer_name,t.customer_phone,t.customer_email,t.customer_address,s.name as trang_thai,p.name as uu_tien,c.name as danh_muc,t.warranty_end_date,b.price as gia,b.payment_status as tt,u.fullname as phan_cong FROM tickets t JOIN statuses s ON s.id=t.status_id JOIN priorities p ON p.id=t.priority_id JOIN categories c ON c.id=t.category_id LEFT JOIN users u ON u.id=t.assigned_to LEFT JOIN ticket_billing b ON b.ticket_id=t.id WHERE t.customer_name LIKE ? OR t.customer_email LIKE ? OR t.customer_phone LIKE ? ORDER BY t.created_at DESC LIMIT 20");
            $s->execute([$q,$q,$q]); $rows=$s->fetchAll(PDO::FETCH_ASSOC);
            return ['tickets'=>$rows,'so_luong'=>count($rows),'tong_chi_phi'=>array_sum(array_column($rows,'gia'))];

        case 'get_priority_queue':
            $lm=min((int)($args['limit']??10),20);
            $s=$pdo->query("SELECT t.id,t.title,t.customer_name,t.due_date,s.name as trang_thai,p.name as uu_tien,u.fullname as phan_cong,
                CASE WHEN t.due_date<CURDATE() AND p.name='Khẩn cấp' THEN 1
                     WHEN t.due_date<CURDATE() AND p.name='Cao' THEN 2
                     WHEN t.due_date<CURDATE() THEN 3
                     WHEN p.name='Khẩn cấp' AND t.assigned_to IS NULL THEN 4
                     WHEN p.name='Khẩn cấp' THEN 5
                     WHEN t.assigned_to IS NULL THEN 6
                     WHEN p.name='Cao' THEN 7 ELSE 8 END as do_khan
                FROM tickets t JOIN statuses s ON s.id=t.status_id JOIN priorities p ON p.id=t.priority_id LEFT JOIN users u ON u.id=t.assigned_to
                WHERE s.name!='Đã đóng' ORDER BY do_khan ASC,t.due_date ASC LIMIT $lm");
            return $s->fetchAll(PDO::FETCH_ASSOC);

        case 'get_overdue_tickets':
            $s=$pdo->query("SELECT t.id,t.title,t.customer_name,t.due_date,s.name as trang_thai,p.name as uu_tien,u.fullname as phan_cong,DATEDIFF(CURDATE(),t.due_date) as so_ngay_tre FROM tickets t JOIN statuses s ON s.id=t.status_id JOIN priorities p ON p.id=t.priority_id LEFT JOIN users u ON u.id=t.assigned_to WHERE t.due_date<CURDATE() AND s.name!='Đã đóng' ORDER BY t.due_date ASC LIMIT 15");
            return $s->fetchAll(PDO::FETCH_ASSOC);

        case 'get_recent_tickets':
            $lm=min((int)($args['limit']??5),10);
            $s=$pdo->query("SELECT t.id,t.title,t.customer_name,s.name as trang_thai,p.name as uu_tien,c.name as danh_muc,t.created_at,u.fullname as phan_cong FROM tickets t JOIN statuses s ON s.id=t.status_id JOIN priorities p ON p.id=t.priority_id JOIN categories c ON c.id=t.category_id LEFT JOIN users u ON u.id=t.assigned_to ORDER BY t.created_at DESC LIMIT $lm");
            return $s->fetchAll(PDO::FETCH_ASSOC);

        case 'get_unassigned_tickets':
            $s=$pdo->query("SELECT t.id,t.title,t.customer_name,s.name as trang_thai,p.name as uu_tien,c.name as danh_muc,t.created_at FROM tickets t JOIN statuses s ON s.id=t.status_id JOIN priorities p ON p.id=t.priority_id JOIN categories c ON c.id=t.category_id WHERE t.assigned_to IS NULL AND s.name!='Đã đóng' ORDER BY p.id ASC,t.created_at ASC LIMIT 15");
            return $s->fetchAll(PDO::FETCH_ASSOC);

        case 'get_staff_workload':
            $s=$pdo->query("SELECT u.id,u.fullname,r.name as vai_tro,COUNT(t.id) as dang_xu_ly,SUM(CASE WHEN t.due_date<CURDATE() THEN 1 ELSE 0 END) as qua_han,SUM(CASE WHEN p.name='Khẩn cấp' THEN 1 ELSE 0 END) as khan_cap FROM users u JOIN roles r ON r.id=u.role_id LEFT JOIN tickets t ON t.assigned_to=u.id AND t.status_id NOT IN (SELECT id FROM statuses WHERE name='Đã đóng') LEFT JOIN priorities p ON p.id=t.priority_id WHERE u.status='active' AND r.name IN ('Admin','Manager','IT Helpdesk','IT Support','IT Intern') GROUP BY u.id ORDER BY dang_xu_ly ASC");
            return $s->fetchAll(PDO::FETCH_ASSOC);

        case 'get_staff_expertise':
            $cat=$pdo->query("SELECT u.fullname,r.name as vai_tro,c.name as danh_muc,COUNT(*) as da_giai_quyet,ROUND(AVG(DATEDIFF(t.updated_at,t.created_at)),1) as tb_ngay FROM tickets t JOIN statuses s ON s.id=t.status_id AND s.name='Đã đóng' JOIN users u ON u.id=t.assigned_to JOIN roles r ON r.id=u.role_id JOIN categories c ON c.id=t.category_id GROUP BY u.id,c.id ORDER BY u.fullname,da_giai_quyet DESC")->fetchAll(PDO::FETCH_ASSOC);
            $all=$pdo->query("SELECT u.fullname,r.name as vai_tro,COUNT(*) as tong_giai_quyet,ROUND(AVG(DATEDIFF(t.updated_at,t.created_at)),1) as tb_ngay FROM tickets t JOIN statuses s ON s.id=t.status_id AND s.name='Đã đóng' JOIN users u ON u.id=t.assigned_to JOIN roles r ON r.id=u.role_id GROUP BY u.id ORDER BY tong_giai_quyet DESC")->fetchAll(PDO::FETCH_ASSOC);
            return ['chuyen_mon'=>$cat,'tong_quan'=>$all];

        case 'recommend_assignee':
            $tid=(int)($args['ticket_id']??0);
            if(!$tid) return ['loi'=>'Thiếu ticket_id'];
            $ts=$pdo->prepare("SELECT t.title,c.name as danh_muc,p.name as uu_tien,c.id as cat_id FROM tickets t JOIN categories c ON c.id=t.category_id JOIN priorities p ON p.id=t.priority_id WHERE t.id=?");
            $ts->execute([$tid]); $tk=$ts->fetch(PDO::FETCH_ASSOC);
            if(!$tk) return ['loi'=>"Ticket #$tid không tồn tại"];
            $wl=$pdo->query("SELECT u.id,u.fullname,r.name as vai_tro,r.hierarchy_level,COUNT(t2.id) as hien_tai FROM users u JOIN roles r ON r.id=u.role_id LEFT JOIN tickets t2 ON t2.assigned_to=u.id AND t2.status_id NOT IN (SELECT id FROM statuses WHERE name='Đã đóng') WHERE u.status='active' AND r.name IN ('Manager','IT Helpdesk','IT Support','IT Intern') GROUP BY u.id ORDER BY hien_tai ASC")->fetchAll(PDO::FETCH_ASSOC);
            $ep=$pdo->prepare("SELECT t.assigned_to as uid,COUNT(*) as cnt,ROUND(AVG(DATEDIFF(t.updated_at,t.created_at)),1) as tb FROM tickets t JOIN statuses s ON s.id=t.status_id AND s.name='Đã đóng' WHERE t.category_id=? AND t.assigned_to IS NOT NULL GROUP BY t.assigned_to");
            $ep->execute([$tk['cat_id']]); $exp=[];
            foreach($ep->fetchAll(PDO::FETCH_ASSOC) as $e) $exp[$e['uid']]=$e;
            $scored=[];
            foreach($wl as $st){
                $uid=$st['id']; $ed=$exp[$uid]??null;
                $score=($ed?min($ed['cnt']*3,30):0)-($st['hien_tai']*2)+match($st['vai_tro']){'Manager'=>5,'IT Helpdesk'=>8,'IT Support'=>6,'IT Intern'=>2,default=>0};
                $scored[]=array_merge($st,['diem'=>$score,'chuyen_mon_danh_muc'=>$ed?$ed['cnt'].' ticket':'0','tb_ngay'=>$ed['tb']??null]);
            }
            usort($scored,fn($a,$b)=>$b['diem']-$a['diem']);
            return ['ticket'=>$tk,'de_xuat'=>array_slice($scored,0,5)];

        case 'get_performance_report':
            $s=$pdo->query("SELECT u.fullname,r.name as vai_tro,COUNT(t.id) as da_giai_quyet,ROUND(AVG(DATEDIFF(t.updated_at,t.created_at)),1) as tb_ngay,SUM(CASE WHEN t.due_date IS NULL OR t.updated_at<=t.due_date THEN 1 ELSE 0 END) as dung_han,(SELECT COUNT(*) FROM tickets t2 WHERE t2.assigned_to=u.id AND t2.status_id NOT IN (SELECT id FROM statuses WHERE name='Đã đóng')) as dang_xu_ly FROM tickets t JOIN statuses s ON s.id=t.status_id AND s.name='Đã đóng' JOIN users u ON u.id=t.assigned_to JOIN roles r ON r.id=u.role_id GROUP BY u.id ORDER BY da_giai_quyet DESC");
            $m=$pdo->query("SELECT u.fullname,COUNT(*) as thang_nay FROM tickets t JOIN statuses s ON s.id=t.status_id AND s.name='Đã đóng' JOIN users u ON u.id=t.assigned_to WHERE MONTH(t.updated_at)=MONTH(CURDATE()) AND YEAR(t.updated_at)=YEAR(CURDATE()) GROUP BY u.id ORDER BY thang_nay DESC");
            return ['hieu_suat'=>$s->fetchAll(PDO::FETCH_ASSOC),'thang_nay'=>$m->fetchAll(PDO::FETCH_ASSOC)];

        case 'find_similar_solutions':
            $q='%'.($args['query']??'').'%';
            $s=$pdo->prepare("SELECT t.id,t.title,c.name as danh_muc,u.fullname as nguoi_xu_ly,ROUND(DATEDIFF(t.updated_at,t.created_at),0) as ngay_xu_ly,GROUP_CONCAT(n.note ORDER BY n.created_at SEPARATOR ' | ') as giai_phap FROM tickets t JOIN statuses s ON s.id=t.status_id AND s.name='Đã đóng' JOIN categories c ON c.id=t.category_id LEFT JOIN users u ON u.id=t.assigned_to LEFT JOIN ticket_notes n ON n.ticket_id=t.id WHERE t.title LIKE ? OR t.description LIKE ? GROUP BY t.id ORDER BY t.updated_at DESC LIMIT 5");
            $s->execute([$q,$q]); return $s->fetchAll(PDO::FETCH_ASSOC);

        case 'get_warranty_expiring':
            $d=(int)($args['days']??30);
            $s=$pdo->prepare("SELECT t.id,t.title,t.customer_name,t.customer_phone,t.warranty_end_date,DATEDIFF(t.warranty_end_date,CURDATE()) as con_lai FROM tickets t WHERE t.warranty_end_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(),INTERVAL ? DAY) ORDER BY t.warranty_end_date ASC");
            $s->execute([$d]); return $s->fetchAll(PDO::FETCH_ASSOC);

        case 'get_revenue_summary':
            $t=$pdo->query("SELECT SUM(CASE WHEN payment_status='paid' THEN price ELSE 0 END) as da_thu,SUM(CASE WHEN payment_status IN ('unpaid','pending') THEN price ELSE 0 END) as cho_thu,SUM(CASE WHEN payment_status='waived' THEN price ELSE 0 END) as mien_phi,COUNT(*) as tong_hoa_don FROM ticket_billing")->fetch(PDO::FETCH_ASSOC);
            $c=$pdo->query("SELECT c.name as danh_muc,SUM(CASE WHEN b.payment_status='paid' THEN b.price ELSE 0 END) as doanh_thu FROM ticket_billing b JOIN tickets t ON t.id=b.ticket_id JOIN categories c ON c.id=t.category_id GROUP BY c.name ORDER BY doanh_thu DESC")->fetchAll(PDO::FETCH_ASSOC);
            $m=$pdo->query("SELECT DATE_FORMAT(updated_at,'%Y-%m') as thang,SUM(price) as doanh_thu FROM ticket_billing WHERE payment_status='paid' GROUP BY thang ORDER BY thang DESC LIMIT 6")->fetchAll(PDO::FETCH_ASSOC);
            return ['tong_hop'=>$t,'danh_muc'=>$c,'theo_thang'=>$m];
    }
    return ['loi'=>'Tool không tồn tại: '.$name];
}

// ── Truncate tool result to stay within TPM ───────────────────────────────────
function truncateToolResult($result, $maxChars = 1800) {
    $json = json_encode($result, JSON_UNESCAPED_UNICODE);
    if (strlen($json) <= $maxChars) return $json;
    // If array of rows, trim rows until fits
    if (is_array($result) && isset($result[0])) {
        while (count($result) > 1) {
            array_pop($result);
            $json = json_encode($result, JSON_UNESCAPED_UNICODE) . '...(bị rút gọn)';
            if (strlen($json) <= $maxChars) return $json;
        }
    }
    return substr($json, 0, $maxChars) . '...(bị rút gọn)';
}

// ── CRM Agent (tool calling loop) ─────────────────────────────────────────────
if ($action === 'crm_agent') {
    // Ultra-compact system prompt to save tokens
    $sysPrompt = "CRM AI Agent HelpDesk. Dùng tools lấy dữ liệu thực. Trả lời tiếng Việt, ngắn gọn. Ticket dùng #ID. Đề xuất phân công: ít việc + chuyên môn + vai trò phù hợp.";

    $messages = [['role'=>'system','content'=>$sysPrompt]];
    // Max 4 history turns to save tokens
    foreach (array_slice($history,-4) as $h) {
        if (!empty($h['role']) && !empty($h['content']))
            $messages[] = ['role'=>$h['role'],'content'=>substr($h['content'],0,300)];
    }
    $messages[] = ['role'=>'user','content'=>$content ?: 'Thống kê hệ thống'];

    for ($i=0; $i<4; $i++) {
        [$res,$code] = callGroq(['model'=>GROQ_MODEL,'messages'=>$messages,'tools'=>getCRMTools(),'tool_choice'=>'auto','max_tokens'=>700,'temperature'=>0.2]);
        if ($res===false) aiJson(['success'=>false,'message'=>'Không thể kết nối Groq.']);
        $d = json_decode($res,true);
        if ($code!==200) aiJson(['success'=>false,'message'=>'Groq lỗi: '.($d['error']['message']??'HTTP '.$code)]);
        $msg = $d['choices'][0]['message'];
        $messages[] = $msg;
        if (empty($msg['tool_calls'])) aiJson(['success'=>true,'reply'=>$msg['content']??'']);
        foreach ($msg['tool_calls'] as $tc) {
            $result = executeCRMTool($pdo,$tc['function']['name'],json_decode($tc['function']['arguments'],true)??[]);
            $messages[] = ['role'=>'tool','tool_call_id'=>$tc['id'],'content'=>truncateToolResult($result)];
        }
    }
    aiJson(['success'=>true,'reply'=>'Xin lỗi, không thể xử lý yêu cầu.']);
}

// ── Other actions (analyze_ticket, etc.) ──────────────────────────────────────
$sys=''; $um=$content;
switch ($action) {
    case 'analyze_ticket':
        $sys="Chuyên gia IT HelpDesk. Phân tích ticket: phân loại, ưu tiên, bước xử lý, thời gian ước tính. Tiếng Việt ngắn gọn.";
        $um="Ticket:\nTiêu đề: ".($context['title']??'')."\nMô tả: ".($context['description']??'')."\nDanh mục: ".($context['category']??'')."\nƯu tiên: ".($context['priority']??'');
        break;
    case 'suggest_solution':
        $sys="Chuyên gia IT. Đề xuất giải pháp chi tiết từng bước. Tiếng Việt.";
        $um="Vấn đề: $content\nGhi chú: ".($context['notes']??'Chưa có'); break;
    case 'auto_categorize':
        $sys="Phân loại ticket IT: danh mục, ưu tiên, ai xử lý. Tiếng Việt.";
        $um="Mô tả: $content"; break;
    default:
        $sys="Trợ lý IT HelpDesk. Tiếng Việt ngắn gọn."; break;
}
[$res,$code] = callGroq(['model'=>GROQ_MODEL,'messages'=>[['role'=>'system','content'=>$sys],['role'=>'user','content'=>$um]],'max_tokens'=>1024,'temperature'=>0.7]);
if ($res===false) aiJson(['success'=>false,'message'=>'Không thể kết nối Groq.']);
$d=json_decode($res,true); $reply=$d['choices'][0]['message']['content']??'';
if ($code!==200||empty($reply)) aiJson(['success'=>false,'message'=>'Groq lỗi: '.($d['error']['message']??'HTTP '.$code)]);
aiJson(['success'=>true,'reply'=>$reply]);
