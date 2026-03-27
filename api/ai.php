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

if (empty($_SESSION['user_id'])) {
    aiJson(['success' => false, 'message' => 'Chưa đăng nhập.']);
}
if (!AI_ENABLED) {
    aiJson(['success' => false, 'message' => 'AI chưa được cấu hình. Vui lòng thêm GROQ_API_KEY vào biến môi trường.']);
}

$input   = json_decode(file_get_contents('php://input'), true);
$action  = $input['action']  ?? 'crm_agent';
$content = trim($input['content'] ?? '');
$context = $input['context'] ?? [];
$history = $input['history'] ?? []; // conversation history from client

// ══════════════════════════════════════════════════════════════════════════════
// CRM TOOL DEFINITIONS
// ══════════════════════════════════════════════════════════════════════════════
function getCRMTools() {
    return [
        // ── Ticket queries ────────────────────────────────────────────────────
        [
            'type' => 'function',
            'function' => [
                'name' => 'get_ticket_detail',
                'description' => 'Lấy thông tin chi tiết đầy đủ của một ticket: thông tin khách hàng, trạng thái, phân công, ghi chú, lịch sử hoạt động, thanh toán, bảo hành.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'ticket_id' => ['type' => 'integer', 'description' => 'ID của ticket cần xem'],
                    ],
                    'required' => ['ticket_id'],
                ],
            ],
        ],
        [
            'type' => 'function',
            'function' => [
                'name' => 'get_ticket_stats',
                'description' => 'Thống kê tổng quan hệ thống: tổng ticket, theo trạng thái, quá hạn, theo ưu tiên, theo danh mục. Dùng khi hỏi về tình trạng chung.',
                'parameters' => ['type' => 'object', 'properties' => new stdClass, 'required' => []],
            ],
        ],
        [
            'type' => 'function',
            'function' => [
                'name' => 'search_tickets',
                'description' => 'Tìm kiếm ticket theo từ khóa (tên KH, tiêu đề, SĐT, email). Có thể lọc theo trạng thái hoặc danh mục.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'query'    => ['type' => 'string',  'description' => 'Từ khóa tìm kiếm'],
                        'status'   => ['type' => 'string',  'description' => 'Lọc trạng thái: Mở | Đang chờ | Đang xử lý | Đã đóng'],
                        'category' => ['type' => 'string',  'description' => 'Lọc theo danh mục'],
                        'limit'    => ['type' => 'integer', 'description' => 'Số kết quả (mặc định 5, tối đa 15)'],
                    ],
                    'required' => ['query'],
                ],
            ],
        ],
        [
            'type' => 'function',
            'function' => [
                'name' => 'get_customer_history',
                'description' => 'Xem toàn bộ lịch sử ticket của một khách hàng: tất cả ticket từ trước đến nay, bảo hành, thanh toán.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'query' => ['type' => 'string', 'description' => 'Tên, email hoặc số điện thoại khách hàng'],
                    ],
                    'required' => ['query'],
                ],
            ],
        ],
        [
            'type' => 'function',
            'function' => [
                'name' => 'get_priority_queue',
                'description' => 'Danh sách ticket cần xử lý gấp nhất: kết hợp quá hạn + ưu tiên cao + chưa phân công. Dùng để biết cần làm gì trước.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'limit' => ['type' => 'integer', 'description' => 'Số lượng (mặc định 10)'],
                    ],
                    'required' => [],
                ],
            ],
        ],
        [
            'type' => 'function',
            'function' => [
                'name' => 'get_overdue_tickets',
                'description' => 'Danh sách ticket đã quá hạn xử lý (due_date qua rồi, chưa đóng).',
                'parameters' => ['type' => 'object', 'properties' => new stdClass, 'required' => []],
            ],
        ],
        [
            'type' => 'function',
            'function' => [
                'name' => 'get_recent_tickets',
                'description' => 'Ticket mới nhất trong hệ thống.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'limit' => ['type' => 'integer', 'description' => 'Số lượng (mặc định 5)'],
                    ],
                    'required' => [],
                ],
            ],
        ],
        [
            'type' => 'function',
            'function' => [
                'name' => 'get_unassigned_tickets',
                'description' => 'Ticket chưa được phân công nhân viên xử lý.',
                'parameters' => ['type' => 'object', 'properties' => new stdClass, 'required' => []],
            ],
        ],

        // ── Staff intelligence ────────────────────────────────────────────────
        [
            'type' => 'function',
            'function' => [
                'name' => 'get_staff_workload',
                'description' => 'Tải lượng công việc của từng nhân viên: số ticket đang xử lý, quá hạn. Dùng để biết ai đang rảnh, ai đang bận.',
                'parameters' => ['type' => 'object', 'properties' => new stdClass, 'required' => []],
            ],
        ],
        [
            'type' => 'function',
            'function' => [
                'name' => 'get_staff_expertise',
                'description' => 'Chuyên môn của từng nhân viên: họ đã xử lý thành công loại ticket nào nhiều nhất, tốc độ xử lý trung bình.',
                'parameters' => ['type' => 'object', 'properties' => new stdClass, 'required' => []],
            ],
        ],
        [
            'type' => 'function',
            'function' => [
                'name' => 'recommend_assignee',
                'description' => 'Đề xuất nhân viên phù hợp nhất để xử lý một ticket cụ thể, dựa trên chuyên môn + tải lượng + độ phức tạp của ticket.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'ticket_id' => ['type' => 'integer', 'description' => 'ID ticket cần đề xuất người xử lý'],
                    ],
                    'required' => ['ticket_id'],
                ],
            ],
        ],
        [
            'type' => 'function',
            'function' => [
                'name' => 'get_performance_report',
                'description' => 'Báo cáo hiệu suất nhân viên: số ticket đã giải quyết, thời gian xử lý trung bình, tỷ lệ đúng hạn.',
                'parameters' => ['type' => 'object', 'properties' => new stdClass, 'required' => []],
            ],
        ],

        // ── Knowledge & solutions ─────────────────────────────────────────────
        [
            'type' => 'function',
            'function' => [
                'name' => 'find_similar_solutions',
                'description' => 'Tìm các ticket tương tự đã được giải quyết thành công, kèm ghi chú giải pháp. Dùng để gợi ý cách xử lý.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'query' => ['type' => 'string', 'description' => 'Mô tả vấn đề cần tìm giải pháp tương tự'],
                    ],
                    'required' => ['query'],
                ],
            ],
        ],

        // ── Warranty & billing ────────────────────────────────────────────────
        [
            'type' => 'function',
            'function' => [
                'name' => 'get_warranty_expiring',
                'description' => 'Danh sách bảo hành sắp hết hạn trong N ngày tới. Dùng để nhắc nhở khách hàng.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'days' => ['type' => 'integer', 'description' => 'Số ngày tới cần kiểm tra (mặc định 30)'],
                    ],
                    'required' => [],
                ],
            ],
        ],
        [
            'type' => 'function',
            'function' => [
                'name' => 'get_revenue_summary',
                'description' => 'Tổng hợp doanh thu: tổng đã thu, chờ thanh toán, miễn phí. Theo danh mục và theo tháng.',
                'parameters' => ['type' => 'object', 'properties' => new stdClass, 'required' => []],
            ],
        ],
    ];
}

// ══════════════════════════════════════════════════════════════════════════════
// TOOL EXECUTION
// ══════════════════════════════════════════════════════════════════════════════
function executeCRMTool($pdo, $name, $args) {
    switch ($name) {

        // ── Full ticket detail ─────────────────────────────────────────────
        case 'get_ticket_detail':
            $id = (int)($args['ticket_id'] ?? 0);
            if (!$id) return ['loi' => 'Thiếu ticket_id'];

            $ticket = $pdo->prepare("
                SELECT t.*, c.name as danh_muc, p.name as uu_tien, p.color as uu_tien_mau,
                       s.name as trang_thai, u_a.fullname as phan_cong_cho,
                       u_c.fullname as nguoi_tao,
                       b.price as gia_dich_vu, b.payment_status as thanh_toan,
                       b.template_name as ten_dich_vu
                FROM tickets t
                JOIN categories c  ON c.id = t.category_id
                JOIN priorities p  ON p.id = t.priority_id
                JOIN statuses s    ON s.id = t.status_id
                LEFT JOIN users u_a ON u_a.id = t.assigned_to
                LEFT JOIN users u_c ON u_c.id = t.created_by
                LEFT JOIN billing b ON b.ticket_id = t.id
                WHERE t.id = ?
            ");
            $ticket->execute([$id]);
            $ticketData = $ticket->fetch(PDO::FETCH_ASSOC);
            if (!$ticketData) return ['loi' => "Ticket #$id không tồn tại"];

            $notes = $pdo->prepare("
                SELECT n.note, n.created_at, u.fullname as nguoi_viet
                FROM notes n JOIN users u ON u.id = n.created_by
                WHERE n.ticket_id = ? ORDER BY n.created_at ASC
            ");
            $notes->execute([$id]);

            $activity = $pdo->prepare("
                SELECT al.action_type, al.description, al.created_at, u.fullname as nguoi_thuc_hien
                FROM activity_logs al LEFT JOIN users u ON u.id = al.user_id
                WHERE al.ticket_id = ? ORDER BY al.created_at DESC LIMIT 10
            ");
            $activity->execute([$id]);

            return [
                'ticket'    => $ticketData,
                'ghi_chu'   => $notes->fetchAll(PDO::FETCH_ASSOC),
                'lich_su'   => $activity->fetchAll(PDO::FETCH_ASSOC),
            ];

        // ── Overall stats ──────────────────────────────────────────────────
        case 'get_ticket_stats':
            $row = $pdo->query("
                SELECT COUNT(*) as tong_so,
                    SUM(CASE WHEN s.name='Mở'         THEN 1 ELSE 0 END) as mo,
                    SUM(CASE WHEN s.name='Đang chờ'   THEN 1 ELSE 0 END) as dang_cho,
                    SUM(CASE WHEN s.name='Đang xử lý' THEN 1 ELSE 0 END) as dang_xu_ly,
                    SUM(CASE WHEN s.name='Đã đóng'    THEN 1 ELSE 0 END) as da_dong,
                    SUM(CASE WHEN t.due_date < CURDATE() AND s.name!='Đã đóng' THEN 1 ELSE 0 END) as qua_han,
                    SUM(CASE WHEN t.assigned_to IS NULL AND s.name!='Đã đóng' THEN 1 ELSE 0 END) as chua_phan_cong
                FROM tickets t JOIN statuses s ON s.id=t.status_id
            ")->fetch(PDO::FETCH_ASSOC);

            $prio = $pdo->query("
                SELECT p.name as uu_tien, COUNT(*) as so_luong
                FROM tickets t JOIN priorities p ON p.id=t.priority_id
                JOIN statuses s ON s.id=t.status_id WHERE s.name!='Đã đóng'
                GROUP BY p.name ORDER BY so_luong DESC
            ")->fetchAll(PDO::FETCH_ASSOC);

            $cat = $pdo->query("
                SELECT c.name as danh_muc, COUNT(*) as tong,
                    SUM(CASE WHEN s.name='Đã đóng' THEN 1 ELSE 0 END) as da_giai_quyet
                FROM tickets t JOIN categories c ON c.id=t.category_id
                JOIN statuses s ON s.id=t.status_id
                GROUP BY c.name ORDER BY tong DESC
            ")->fetchAll(PDO::FETCH_ASSOC);

            $today = $pdo->query("
                SELECT COUNT(*) as tao_hom_nay FROM tickets WHERE DATE(created_at)=CURDATE()
            ")->fetchColumn();

            return ['thong_ke' => $row, 'theo_uu_tien' => $prio, 'theo_danh_muc' => $cat, 'tao_hom_nay' => $today];

        // ── Search tickets ─────────────────────────────────────────────────
        case 'search_tickets':
            $q      = '%' . ($args['query'] ?? '') . '%';
            $status = $args['status']   ?? '';
            $cat    = $args['category'] ?? '';
            $limit  = min((int)($args['limit'] ?? 5), 15);
            $where  = '(t.title LIKE ? OR t.customer_name LIKE ? OR t.customer_phone LIKE ? OR t.customer_email LIKE ?)';
            $params = [$q, $q, $q, $q];
            if ($status) { $where .= ' AND s.name=?'; $params[] = $status; }
            if ($cat)    { $where .= ' AND c.name LIKE ?'; $params[] = "%$cat%"; }
            $stmt = $pdo->prepare("
                SELECT t.id, t.title, t.customer_name, t.customer_phone, t.customer_address,
                       s.name as trang_thai, p.name as uu_tien, t.due_date,
                       u.fullname as phan_cong, c.name as danh_muc, t.created_at
                FROM tickets t
                JOIN statuses s   ON s.id=t.status_id
                JOIN priorities p ON p.id=t.priority_id
                JOIN categories c ON c.id=t.category_id
                LEFT JOIN users u ON u.id=t.assigned_to
                WHERE $where ORDER BY t.created_at DESC LIMIT $limit
            ");
            $stmt->execute($params);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);

        // ── Customer history ───────────────────────────────────────────────
        case 'get_customer_history':
            $q    = '%' . ($args['query'] ?? '') . '%';
            $stmt = $pdo->prepare("
                SELECT t.id, t.title, t.created_at, t.customer_name, t.customer_phone,
                       t.customer_email, t.customer_address,
                       s.name as trang_thai, p.name as uu_tien, c.name as danh_muc,
                       t.warranty_end_date,
                       b.price as gia, b.payment_status as thanh_toan,
                       u.fullname as phan_cong
                FROM tickets t
                JOIN statuses s   ON s.id=t.status_id
                JOIN priorities p ON p.id=t.priority_id
                JOIN categories c ON c.id=t.category_id
                LEFT JOIN users u ON u.id=t.assigned_to
                LEFT JOIN billing b ON b.ticket_id=t.id
                WHERE t.customer_name LIKE ? OR t.customer_email LIKE ? OR t.customer_phone LIKE ?
                ORDER BY t.created_at DESC LIMIT 20
            ");
            $stmt->execute([$q, $q, $q]);
            $tickets = $stmt->fetchAll(PDO::FETCH_ASSOC);
            return [
                'ticket_khach' => $tickets,
                'so_luong'     => count($tickets),
                'tong_chi_phi' => array_sum(array_column($tickets, 'gia')),
            ];

        // ── Priority queue ─────────────────────────────────────────────────
        case 'get_priority_queue':
            $limit = min((int)($args['limit'] ?? 10), 20);
            $stmt  = $pdo->query("
                SELECT t.id, t.title, t.customer_name, t.due_date,
                       s.name as trang_thai, p.name as uu_tien,
                       u.fullname as phan_cong,
                       CASE
                         WHEN t.due_date < CURDATE() AND p.name='Khẩn cấp' THEN 1
                         WHEN t.due_date < CURDATE() AND p.name='Cao'       THEN 2
                         WHEN t.due_date < CURDATE()                         THEN 3
                         WHEN p.name='Khẩn cấp' AND t.assigned_to IS NULL   THEN 4
                         WHEN p.name='Khẩn cấp'                              THEN 5
                         WHEN t.assigned_to IS NULL                          THEN 6
                         WHEN p.name='Cao'                                   THEN 7
                         ELSE 8
                       END as muc_do_khan
                FROM tickets t
                JOIN statuses s   ON s.id=t.status_id
                JOIN priorities p ON p.id=t.priority_id
                LEFT JOIN users u ON u.id=t.assigned_to
                WHERE s.name != 'Đã đóng'
                ORDER BY muc_do_khan ASC, t.due_date ASC
                LIMIT $limit
            ");
            return $stmt->fetchAll(PDO::FETCH_ASSOC);

        // ── Overdue ────────────────────────────────────────────────────────
        case 'get_overdue_tickets':
            $stmt = $pdo->query("
                SELECT t.id, t.title, t.customer_name, t.due_date,
                       s.name as trang_thai, p.name as uu_tien,
                       u.fullname as phan_cong,
                       DATEDIFF(CURDATE(), t.due_date) as so_ngay_tre
                FROM tickets t
                JOIN statuses s   ON s.id=t.status_id
                JOIN priorities p ON p.id=t.priority_id
                LEFT JOIN users u ON u.id=t.assigned_to
                WHERE t.due_date < CURDATE() AND s.name != 'Đã đóng'
                ORDER BY t.due_date ASC LIMIT 15
            ");
            return $stmt->fetchAll(PDO::FETCH_ASSOC);

        // ── Recent ─────────────────────────────────────────────────────────
        case 'get_recent_tickets':
            $limit = min((int)($args['limit'] ?? 5), 10);
            $stmt  = $pdo->query("
                SELECT t.id, t.title, t.customer_name, s.name as trang_thai,
                       p.name as uu_tien, c.name as danh_muc, t.created_at,
                       u.fullname as phan_cong
                FROM tickets t
                JOIN statuses s   ON s.id=t.status_id
                JOIN priorities p ON p.id=t.priority_id
                JOIN categories c ON c.id=t.category_id
                LEFT JOIN users u ON u.id=t.assigned_to
                ORDER BY t.created_at DESC LIMIT $limit
            ");
            return $stmt->fetchAll(PDO::FETCH_ASSOC);

        // ── Unassigned ─────────────────────────────────────────────────────
        case 'get_unassigned_tickets':
            $stmt = $pdo->query("
                SELECT t.id, t.title, t.customer_name, s.name as trang_thai,
                       p.name as uu_tien, c.name as danh_muc, t.created_at
                FROM tickets t
                JOIN statuses s   ON s.id=t.status_id
                JOIN priorities p ON p.id=t.priority_id
                JOIN categories c ON c.id=t.category_id
                WHERE t.assigned_to IS NULL AND s.name != 'Đã đóng'
                ORDER BY p.id ASC, t.created_at ASC LIMIT 15
            ");
            return $stmt->fetchAll(PDO::FETCH_ASSOC);

        // ── Staff workload ─────────────────────────────────────────────────
        case 'get_staff_workload':
            $stmt = $pdo->query("
                SELECT u.id, u.fullname, r.name as vai_tro,
                    COUNT(t.id)        as ticket_dang_xu_ly,
                    SUM(CASE WHEN t.due_date < CURDATE() THEN 1 ELSE 0 END) as ticket_qua_han,
                    SUM(CASE WHEN p.name='Khẩn cấp' THEN 1 ELSE 0 END) as ticket_khan_cap,
                    SUM(CASE WHEN p.name='Cao'       THEN 1 ELSE 0 END) as ticket_cao
                FROM users u
                JOIN roles r ON r.id=u.role_id
                LEFT JOIN tickets t  ON t.assigned_to=u.id
                    AND t.status_id NOT IN (SELECT id FROM statuses WHERE name='Đã đóng')
                LEFT JOIN priorities p ON p.id=t.priority_id
                WHERE u.status='active'
                  AND r.name IN ('Admin','Manager','IT Helpdesk','IT Support','IT Intern')
                GROUP BY u.id, u.fullname, r.name
                ORDER BY ticket_dang_xu_ly ASC, r.hierarchy_level DESC
            ");
            return $stmt->fetchAll(PDO::FETCH_ASSOC);

        // ── Staff expertise ────────────────────────────────────────────────
        case 'get_staff_expertise':
            // Category expertise — based on closed tickets
            $byCategory = $pdo->query("
                SELECT u.id, u.fullname, r.name as vai_tro,
                       c.name as danh_muc, COUNT(*) as so_ticket_da_giai_quyet,
                       ROUND(AVG(DATEDIFF(t.updated_at, t.created_at)), 1) as tb_ngay_xu_ly
                FROM tickets t
                JOIN statuses s   ON s.id=t.status_id AND s.name='Đã đóng'
                JOIN users u      ON u.id=t.assigned_to
                JOIN roles r      ON r.id=u.role_id
                JOIN categories c ON c.id=t.category_id
                GROUP BY u.id, c.id
                ORDER BY u.fullname, so_ticket_da_giai_quyet DESC
            ")->fetchAll(PDO::FETCH_ASSOC);

            // Overall performance per staff
            $overall = $pdo->query("
                SELECT u.id, u.fullname, r.name as vai_tro,
                       COUNT(*) as tong_da_giai_quyet,
                       ROUND(AVG(DATEDIFF(t.updated_at, t.created_at)), 1) as tb_ngay_xu_ly,
                       SUM(CASE WHEN t.due_date IS NULL OR t.updated_at <= t.due_date THEN 1 ELSE 0 END) as dung_han
                FROM tickets t
                JOIN statuses s ON s.id=t.status_id AND s.name='Đã đóng'
                JOIN users u    ON u.id=t.assigned_to
                JOIN roles r    ON r.id=u.role_id
                GROUP BY u.id ORDER BY tong_da_giai_quyet DESC
            ")->fetchAll(PDO::FETCH_ASSOC);

            return ['chuyen_mon_theo_danh_muc' => $byCategory, 'tong_quan' => $overall];

        // ── Smart assignee recommendation ──────────────────────────────────
        case 'recommend_assignee':
            $tid = (int)($args['ticket_id'] ?? 0);
            if (!$tid) return ['loi' => 'Thiếu ticket_id'];

            // Get ticket info
            $t = $pdo->prepare("
                SELECT t.title, t.description, c.name as danh_muc, p.name as uu_tien,
                       c.id as category_id
                FROM tickets t
                JOIN categories c ON c.id=t.category_id
                JOIN priorities p ON p.id=t.priority_id
                WHERE t.id=?
            ");
            $t->execute([$tid]);
            $ticket = $t->fetch(PDO::FETCH_ASSOC);
            if (!$ticket) return ['loi' => "Ticket #$tid không tồn tại"];

            // Workload score
            $workload = $pdo->query("
                SELECT u.id, u.fullname, r.name as vai_tro, r.hierarchy_level,
                       COUNT(t2.id) as ticket_hien_tai
                FROM users u
                JOIN roles r ON r.id=u.role_id
                LEFT JOIN tickets t2 ON t2.assigned_to=u.id
                    AND t2.status_id NOT IN (SELECT id FROM statuses WHERE name='Đã đóng')
                WHERE u.status='active'
                  AND r.name IN ('Manager','IT Helpdesk','IT Support','IT Intern')
                GROUP BY u.id ORDER BY ticket_hien_tai ASC
            ")->fetchAll(PDO::FETCH_ASSOC);

            // Expertise in this category
            $exp = $pdo->prepare("
                SELECT t.assigned_to as user_id,
                       COUNT(*) as da_giai_quyet,
                       ROUND(AVG(DATEDIFF(t.updated_at, t.created_at)),1) as tb_ngay
                FROM tickets t
                JOIN statuses s ON s.id=t.status_id AND s.name='Đã đóng'
                WHERE t.category_id=? AND t.assigned_to IS NOT NULL
                GROUP BY t.assigned_to
            ");
            $exp->execute([$ticket['category_id']]);
            $expertise = [];
            foreach ($exp->fetchAll(PDO::FETCH_ASSOC) as $e) {
                $expertise[$e['user_id']] = $e;
            }

            // Score each staff member
            $scored = [];
            foreach ($workload as $staff) {
                $uid        = $staff['id'];
                $expData    = $expertise[$uid] ?? null;
                $expScore   = $expData ? min($expData['da_giai_quyet'] * 3, 30) : 0;
                $loadPenalty = $staff['ticket_hien_tai'] * 2;
                // Role bonus: Manager/senior get higher-priority assignments
                $roleBonus  = match($staff['vai_tro']) {
                    'Manager'     => 5,
                    'IT Helpdesk' => 8,
                    'IT Support'  => 6,
                    'IT Intern'   => 2,
                    default       => 0,
                };
                $score = $expScore + $roleBonus - $loadPenalty;
                $scored[] = array_merge($staff, [
                    'diem'          => $score,
                    'chuyen_mon'    => $expData ? $expData['da_giai_quyet'] . ' ticket đã xử lý' : '0 ticket',
                    'tb_ngay_xu_ly' => $expData['tb_ngay'] ?? null,
                ]);
            }
            usort($scored, fn($a, $b) => $b['diem'] - $a['diem']);

            return [
                'ticket'        => $ticket,
                'de_xuat'       => array_slice($scored, 0, 5),
                'ghi_chu'       => 'Diem cao = chuyen mon tot + it viec hien tai. AI nen xem xet them tinh chat ticket de quyet dinh.',
            ];

        // ── Performance report ─────────────────────────────────────────────
        case 'get_performance_report':
            $staff = $pdo->query("
                SELECT u.fullname, r.name as vai_tro,
                    COUNT(t.id) as da_giai_quyet,
                    ROUND(AVG(DATEDIFF(t.updated_at, t.created_at)),1) as tb_ngay,
                    SUM(CASE WHEN t.due_date IS NULL OR t.updated_at<=t.due_date THEN 1 ELSE 0 END) as dung_han,
                    SUM(CASE WHEN t.updated_at > t.due_date AND t.due_date IS NOT NULL THEN 1 ELSE 0 END) as tre_han,
                    (SELECT COUNT(*) FROM tickets t2
                     JOIN statuses s2 ON s2.id=t2.status_id
                     WHERE t2.assigned_to=u.id AND s2.name!='Đã đóng') as dang_xu_ly
                FROM tickets t
                JOIN statuses s ON s.id=t.status_id AND s.name='Đã đóng'
                JOIN users u    ON u.id=t.assigned_to
                JOIN roles r    ON r.id=u.role_id
                GROUP BY u.id ORDER BY da_giai_quyet DESC
            ")->fetchAll(PDO::FETCH_ASSOC);

            $this_month = $pdo->query("
                SELECT u.fullname, COUNT(*) as giai_quyet_thang_nay
                FROM tickets t
                JOIN statuses s ON s.id=t.status_id AND s.name='Đã đóng'
                JOIN users u    ON u.id=t.assigned_to
                WHERE MONTH(t.updated_at)=MONTH(CURDATE()) AND YEAR(t.updated_at)=YEAR(CURDATE())
                GROUP BY u.id ORDER BY giai_quyet_thang_nay DESC
            ")->fetchAll(PDO::FETCH_ASSOC);

            return ['hieu_suat_tong' => $staff, 'thang_nay' => $this_month];

        // ── Similar solutions ──────────────────────────────────────────────
        case 'find_similar_solutions':
            $q    = '%' . ($args['query'] ?? '') . '%';
            $stmt = $pdo->prepare("
                SELECT t.id, t.title, t.description, c.name as danh_muc,
                       u.fullname as nguoi_xu_ly,
                       ROUND(DATEDIFF(t.updated_at, t.created_at),0) as ngay_xu_ly,
                       GROUP_CONCAT(n.note ORDER BY n.created_at SEPARATOR '\n---\n') as ghi_chu_giai_phap
                FROM tickets t
                JOIN statuses s   ON s.id=t.status_id AND s.name='Đã đóng'
                JOIN categories c ON c.id=t.category_id
                LEFT JOIN users u  ON u.id=t.assigned_to
                LEFT JOIN notes n  ON n.ticket_id=t.id
                WHERE t.title LIKE ? OR t.description LIKE ?
                GROUP BY t.id
                ORDER BY t.updated_at DESC LIMIT 5
            ");
            $stmt->execute([$q, $q]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);

        // ── Warranty expiring ──────────────────────────────────────────────
        case 'get_warranty_expiring':
            $days = (int)($args['days'] ?? 30);
            $stmt = $pdo->prepare("
                SELECT t.id, t.title, t.customer_name, t.customer_phone,
                       t.warranty_end_date,
                       DATEDIFF(t.warranty_end_date, CURDATE()) as con_bao_nhieu_ngay
                FROM tickets t
                WHERE t.warranty_end_date BETWEEN CURDATE()
                      AND DATE_ADD(CURDATE(), INTERVAL ? DAY)
                ORDER BY t.warranty_end_date ASC
            ");
            $stmt->execute([$days]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);

        // ── Revenue summary ────────────────────────────────────────────────
        case 'get_revenue_summary':
            $total = $pdo->query("
                SELECT
                    SUM(CASE WHEN payment_status='paid'    THEN price ELSE 0 END) as da_thu,
                    SUM(CASE WHEN payment_status='unpaid'
                          OR payment_status='pending'      THEN price ELSE 0 END) as cho_thu,
                    SUM(CASE WHEN payment_status='waived'  THEN price ELSE 0 END) as mien_phi,
                    COUNT(*)                                                        as tong_hoa_don
                FROM billing
            ")->fetch(PDO::FETCH_ASSOC);

            $by_cat = $pdo->query("
                SELECT c.name as danh_muc,
                       SUM(CASE WHEN b.payment_status='paid' THEN b.price ELSE 0 END) as doanh_thu
                FROM billing b
                JOIN tickets t ON t.id=b.ticket_id
                JOIN categories c ON c.id=t.category_id
                GROUP BY c.name ORDER BY doanh_thu DESC
            ")->fetchAll(PDO::FETCH_ASSOC);

            $by_month = $pdo->query("
                SELECT DATE_FORMAT(updated_at,'%Y-%m') as thang,
                       SUM(price) as doanh_thu
                FROM billing WHERE payment_status='paid'
                GROUP BY thang ORDER BY thang DESC LIMIT 6
            ")->fetchAll(PDO::FETCH_ASSOC);

            return ['tong_hop' => $total, 'theo_danh_muc' => $by_cat, 'theo_thang' => $by_month];
    }

    return ['loi' => 'Tool không tồn tại: ' . $name];
}

// ══════════════════════════════════════════════════════════════════════════════
// GROQ HTTP
// ══════════════════════════════════════════════════════════════════════════════
function callGroq($payload) {
    $ch = curl_init('https://api.groq.com/openai/v1/chat/completions');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => json_encode($payload),
        CURLOPT_HTTPHEADER     => [
            'Content-Type: application/json',
            'Authorization: Bearer ' . GROQ_API_KEY,
        ],
        CURLOPT_TIMEOUT        => 45,
        CURLOPT_SSL_VERIFYPEER => false,
    ]);
    $res  = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return [$res, $code];
}

// ══════════════════════════════════════════════════════════════════════════════
// CRM AGENT (main)
// ══════════════════════════════════════════════════════════════════════════════
if ($action === 'crm_agent') {
    $currentUser = $_SESSION['fullname'] ?? 'Nhân viên';
    $currentRole = $_SESSION['role_name'] ?? '';

    $systemPrompt = "Bạn là CRM AI Agent của hệ thống HelpDesk IT — một trợ lý thông minh có quyền truy cập toàn bộ dữ liệu hệ thống theo thời gian thực thông qua các công cụ (tools).

Người dùng hiện tại: $currentUser ($currentRole)

## Khả năng của bạn:
- Xem chi tiết bất kỳ ticket nào (thông tin KH, ghi chú, lịch sử, thanh toán)
- Tra cứu toàn bộ lịch sử ticket của một khách hàng
- Thống kê và báo cáo theo thời gian thực
- Đề xuất nhân viên phù hợp để xử lý ticket (dựa trên chuyên môn + tải lượng)
- Tìm giải pháp tương tự từ ticket đã giải quyết
- Cảnh báo ticket quá hạn, bảo hành sắp hết
- Báo cáo hiệu suất nhân viên

## Quy tắc đề xuất phân công:
- IT Intern: ticket đơn giản, ưu tiên thấp/trung bình
- IT Support/Helpdesk: ticket kỹ thuật phổ biến
- Manager: ticket phức tạp, khách quan trọng, khẩn cấp, hoặc khi IT không đủ chuyên môn
- Ưu tiên người ít ticket nhất VÀ có kinh nghiệm trong danh mục đó
- Nếu khẩn cấp: chọn người có chuyên môn cao dù đang bận

## Quy tắc đề xuất giải pháp:
- Luôn tìm ticket tương tự đã giải quyết trước khi đề xuất
- Đưa ra giải pháp cụ thể, từng bước rõ ràng
- Ước tính thời gian xử lý dựa trên lịch sử

## Cách trả lời:
- Dùng tiếng Việt, ngắn gọn, có cấu trúc
- Ticket ID viết dạng #123 (sẽ thành link)
- Khi liệt kê nhiều ticket: dùng dạng bảng hoặc bullet
- Luôn DÙNG TOOL để lấy dữ liệu thực, không bịa số liệu
- Khi đề xuất phân công: giải thích lý do (chuyên môn bao nhiêu ticket, tải lượng hiện tại)";

    // Build messages with history
    $messages = [['role' => 'system', 'content' => $systemPrompt]];
    // Append recent conversation history (max 10 turns)
    foreach (array_slice($history, -10) as $h) {
        if (!empty($h['role']) && !empty($h['content'])) {
            $messages[] = ['role' => $h['role'], 'content' => $h['content']];
        }
    }
    $messages[] = ['role' => 'user', 'content' => $content ?: 'Xin chào, hệ thống CRM đang thế nào?'];

    // Tool calling loop — max 5 rounds
    for ($round = 0; $round < 5; $round++) {
        $payload = [
            'model'       => GROQ_MODEL,
            'messages'    => $messages,
            'tools'       => getCRMTools(),
            'tool_choice' => 'auto',
            'max_tokens'  => 3000,
            'temperature' => 0.25,
        ];

        [$response, $httpCode] = callGroq($payload);
        if ($response === false) aiJson(['success' => false, 'message' => 'Không thể kết nối Groq.']);

        $data = json_decode($response, true);
        if ($httpCode !== 200) {
            aiJson(['success' => false, 'message' => 'Groq lỗi: ' . ($data['error']['message'] ?? 'HTTP ' . $httpCode)]);
        }

        $assistMsg  = $data['choices'][0]['message'];
        $messages[] = $assistMsg;

        if (empty($assistMsg['tool_calls'])) {
            aiJson(['success' => true, 'reply' => $assistMsg['content'] ?? '']);
        }

        foreach ($assistMsg['tool_calls'] as $tc) {
            $args   = json_decode($tc['function']['arguments'], true) ?? [];
            $result = executeCRMTool($pdo, $tc['function']['name'], $args);
            $messages[] = [
                'role'         => 'tool',
                'tool_call_id' => $tc['id'],
                'content'      => json_encode($result, JSON_UNESCAPED_UNICODE),
            ];
        }
    }

    aiJson(['success' => true, 'reply' => 'Xin lỗi, không thể xử lý yêu cầu.']);
}

// ══════════════════════════════════════════════════════════════════════════════
// OTHER ACTIONS (analyze_ticket, suggest_solution, etc.)
// ══════════════════════════════════════════════════════════════════════════════
$systemPrompt = '';
$userMsg      = $content;

switch ($action) {
    case 'analyze_ticket':
        $systemPrompt = "Bạn là chuyên gia IT HelpDesk. Phân tích ticket và đưa ra:\n1. Phân loại vấn đề\n2. Mức ưu tiên gợi ý\n3. Các bước xử lý cụ thể\n4. Thời gian xử lý ước tính\nTrả lời bằng tiếng Việt, ngắn gọn, thực tế.";
        $userMsg = "Phân tích ticket:\nTiêu đề: " . ($context['title'] ?? '') . "\nMô tả: " . ($context['description'] ?? '') . "\nDanh mục: " . ($context['category'] ?? '') . "\nƯu tiên: " . ($context['priority'] ?? '');
        break;
    case 'suggest_solution':
        $systemPrompt = "Bạn là chuyên gia IT. Đề xuất giải pháp chi tiết từng bước. Tiếng Việt.";
        $userMsg = "Vấn đề: " . $content . "\nGhi chú: " . ($context['notes'] ?? 'Chưa có');
        break;
    case 'auto_categorize':
        $systemPrompt = "Phân loại ticket IT. Trả lời: danh mục, ưu tiên, ai xử lý. Tiếng Việt.";
        $userMsg = "Mô tả: " . $content;
        break;
    default:
        $systemPrompt = "Trợ lý IT HelpDesk. Trả lời ngắn gọn tiếng Việt.";
        break;
}

$payload = [
    'model'       => GROQ_MODEL,
    'messages'    => [
        ['role' => 'system', 'content' => $systemPrompt],
        ['role' => 'user',   'content' => $userMsg],
    ],
    'max_tokens'  => 2048,
    'temperature' => 0.7,
];

[$response, $httpCode] = callGroq($payload);
if ($response === false) aiJson(['success' => false, 'message' => 'Không thể kết nối Groq.']);

$data  = json_decode($response, true);
$reply = $data['choices'][0]['message']['content'] ?? '';
if ($httpCode !== 200 || empty($reply)) {
    aiJson(['success' => false, 'message' => 'Groq lỗi: ' . ($data['error']['message'] ?? 'HTTP ' . $httpCode)]);
}
aiJson(['success' => true, 'reply' => $reply]);
