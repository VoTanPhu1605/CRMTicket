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

if (empty($content) && empty($context) && $action !== 'crm_agent') {
    aiJson(['success' => false, 'message' => 'Thiếu nội dung.']);
}

// ── CRM Tool Execution ────────────────────────────────────────────────────────
function executeCRMTool($pdo, $name, $args) {
    switch ($name) {
        case 'get_ticket_stats':
            $row = $pdo->query("
                SELECT
                    COUNT(*) as tong_so,
                    SUM(CASE WHEN s.name='Mở'          THEN 1 ELSE 0 END) as mo,
                    SUM(CASE WHEN s.name='Đang chờ'    THEN 1 ELSE 0 END) as dang_cho,
                    SUM(CASE WHEN s.name='Đang xử lý'  THEN 1 ELSE 0 END) as dang_xu_ly,
                    SUM(CASE WHEN s.name='Đã đóng'     THEN 1 ELSE 0 END) as da_dong,
                    SUM(CASE WHEN t.due_date < CURDATE() AND s.name != 'Đã đóng' THEN 1 ELSE 0 END) as qua_han
                FROM tickets t JOIN statuses s ON s.id = t.status_id
            ")->fetch(PDO::FETCH_ASSOC);

            $prio = $pdo->query("
                SELECT p.name as uu_tien, COUNT(*) as so_luong
                FROM tickets t
                JOIN priorities p ON p.id = t.priority_id
                JOIN statuses s   ON s.id = t.status_id
                WHERE s.name != 'Đã đóng'
                GROUP BY p.name ORDER BY so_luong DESC
            ")->fetchAll(PDO::FETCH_ASSOC);

            $cat = $pdo->query("
                SELECT c.name as danh_muc, COUNT(*) as so_luong
                FROM tickets t
                JOIN categories c ON c.id = t.category_id
                JOIN statuses s   ON s.id = t.status_id
                WHERE s.name != 'Đã đóng'
                GROUP BY c.name ORDER BY so_luong DESC LIMIT 5
            ")->fetchAll(PDO::FETCH_ASSOC);

            return ['thong_ke' => $row, 'theo_uu_tien' => $prio, 'theo_danh_muc' => $cat];

        case 'search_tickets':
            $q      = '%' . ($args['query'] ?? '') . '%';
            $status = $args['status'] ?? '';
            $limit  = min((int)($args['limit'] ?? 5), 10);
            $where  = '(t.title LIKE ? OR t.customer_name LIKE ? OR t.customer_phone LIKE ? OR t.customer_email LIKE ?)';
            $params = [$q, $q, $q, $q];
            if ($status) { $where .= ' AND s.name = ?'; $params[] = $status; }
            $stmt = $pdo->prepare("
                SELECT t.id, t.title, t.customer_name, t.customer_phone,
                       s.name as trang_thai, p.name as uu_tien,
                       t.due_date, u.fullname as phan_cong
                FROM tickets t
                JOIN statuses s    ON s.id = t.status_id
                JOIN priorities p  ON p.id = t.priority_id
                LEFT JOIN users u  ON u.id = t.assigned_to
                WHERE $where
                ORDER BY t.created_at DESC
                LIMIT $limit
            ");
            $stmt->execute($params);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);

        case 'get_overdue_tickets':
            $stmt = $pdo->query("
                SELECT t.id, t.title, t.customer_name, t.due_date,
                       s.name as trang_thai, p.name as uu_tien,
                       u.fullname as phan_cong,
                       DATEDIFF(CURDATE(), t.due_date) as so_ngay_qua_han
                FROM tickets t
                JOIN statuses s    ON s.id = t.status_id
                JOIN priorities p  ON p.id = t.priority_id
                LEFT JOIN users u  ON u.id = t.assigned_to
                WHERE t.due_date < CURDATE() AND s.name != 'Đã đóng'
                ORDER BY t.due_date ASC
                LIMIT 10
            ");
            return $stmt->fetchAll(PDO::FETCH_ASSOC);

        case 'get_recent_tickets':
            $limit = min((int)($args['limit'] ?? 5), 10);
            $stmt  = $pdo->query("
                SELECT t.id, t.title, t.customer_name,
                       s.name as trang_thai, p.name as uu_tien,
                       t.created_at, u.fullname as phan_cong
                FROM tickets t
                JOIN statuses s    ON s.id = t.status_id
                JOIN priorities p  ON p.id = t.priority_id
                LEFT JOIN users u  ON u.id = t.assigned_to
                ORDER BY t.created_at DESC
                LIMIT $limit
            ");
            return $stmt->fetchAll(PDO::FETCH_ASSOC);

        case 'get_unassigned_tickets':
            $stmt = $pdo->query("
                SELECT t.id, t.title, t.customer_name,
                       s.name as trang_thai, p.name as uu_tien, t.created_at
                FROM tickets t
                JOIN statuses s   ON s.id = t.status_id
                JOIN priorities p ON p.id = t.priority_id
                WHERE t.assigned_to IS NULL AND s.name != 'Đã đóng'
                ORDER BY t.created_at DESC LIMIT 10
            ");
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    return ['loi' => 'Tool không tồn tại: ' . $name];
}

// ── Groq HTTP call ─────────────────────────────────────────────────────────────
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
        CURLOPT_TIMEOUT        => 30,
        CURLOPT_SSL_VERIFYPEER => false,
    ]);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return [$response, $httpCode];
}

// ── Build user message for non-CRM actions ────────────────────────────────────
$systemPrompt = "Bạn là trợ lý AI cho hệ thống HelpDesk CRM. Trả lời ngắn gọn, chính xác bằng tiếng Việt.";
$userMsg      = $content;

switch ($action) {
    // ── CRM Agent (tool calling) ─────────────────────────────────────────────
    case 'crm_agent':
        $tools = [
            [
                'type'     => 'function',
                'function' => [
                    'name'        => 'get_ticket_stats',
                    'description' => 'Lấy thống kê tổng quan ticket: tổng số, theo trạng thái (Mở/Đang chờ/Đang xử lý/Đã đóng), quá hạn, theo ưu tiên và danh mục',
                    'parameters'  => ['type' => 'object', 'properties' => new stdClass, 'required' => []],
                ],
            ],
            [
                'type'     => 'function',
                'function' => [
                    'name'        => 'search_tickets',
                    'description' => 'Tìm kiếm ticket theo tên khách hàng, tiêu đề, số điện thoại hoặc email. Có thể lọc theo trạng thái.',
                    'parameters'  => [
                        'type'       => 'object',
                        'properties' => [
                            'query'  => ['type' => 'string',  'description' => 'Từ khóa tìm kiếm (tên KH, tiêu đề, SĐT)'],
                            'status' => ['type' => 'string',  'description' => 'Lọc trạng thái: Mở | Đang chờ | Đang xử lý | Đã đóng'],
                            'limit'  => ['type' => 'integer', 'description' => 'Số kết quả tối đa (mặc định 5)'],
                        ],
                        'required'   => ['query'],
                    ],
                ],
            ],
            [
                'type'     => 'function',
                'function' => [
                    'name'        => 'get_overdue_tickets',
                    'description' => 'Lấy danh sách ticket đã quá hạn xử lý (due_date < hôm nay, chưa đóng)',
                    'parameters'  => ['type' => 'object', 'properties' => new stdClass, 'required' => []],
                ],
            ],
            [
                'type'     => 'function',
                'function' => [
                    'name'        => 'get_recent_tickets',
                    'description' => 'Lấy danh sách ticket mới nhất trong hệ thống',
                    'parameters'  => [
                        'type'       => 'object',
                        'properties' => ['limit' => ['type' => 'integer', 'description' => 'Số lượng (mặc định 5, tối đa 10)']],
                        'required'   => [],
                    ],
                ],
            ],
            [
                'type'     => 'function',
                'function' => [
                    'name'        => 'get_unassigned_tickets',
                    'description' => 'Lấy danh sách ticket chưa được phân công nhân viên xử lý',
                    'parameters'  => ['type' => 'object', 'properties' => new stdClass, 'required' => []],
                ],
            ],
        ];

        $systemPrompt = "Bạn là trợ lý AI vận hành hệ thống CRM HelpDesk IT. Bạn có quyền tra cứu dữ liệu ticket thời gian thực qua các công cụ được cung cấp.

Hãy LUÔN dùng tool để lấy dữ liệu thực trước khi trả lời các câu hỏi về ticket, thống kê, khách hàng.

Khi trả lời:
- Ngắn gọn, có cấu trúc rõ ràng
- Dùng tiếng Việt
- Khi liệt kê ticket: hiển thị ID, tiêu đề, trạng thái, khách hàng
- Gợi ý hành động tiếp theo khi phù hợp (ví dụ: phân công, ưu tiên xử lý)";

        $messages = [
            ['role' => 'system', 'content' => $systemPrompt],
            ['role' => 'user',   'content' => $content ?: 'Xin chào, hệ thống CRM đang thế nào?'],
        ];

        // Tool calling loop — tối đa 4 vòng
        for ($round = 0; $round < 4; $round++) {
            $payload = [
                'model'       => GROQ_MODEL,
                'messages'    => $messages,
                'tools'       => $tools,
                'tool_choice' => 'auto',
                'max_tokens'  => 2048,
                'temperature' => 0.3,
            ];

            [$response, $httpCode] = callGroq($payload);
            if ($response === false) aiJson(['success' => false, 'message' => 'Không thể kết nối Groq.']);

            $data = json_decode($response, true);
            if ($httpCode !== 200) {
                $errMsg = $data['error']['message'] ?? ('HTTP ' . $httpCode);
                aiJson(['success' => false, 'message' => 'Groq lỗi: ' . $errMsg]);
            }

            $choice    = $data['choices'][0];
            $assistMsg = $choice['message'];
            $messages[] = $assistMsg;

            // Không có tool call → trả về câu trả lời cuối
            if (empty($assistMsg['tool_calls'])) {
                aiJson(['success' => true, 'reply' => $assistMsg['content'] ?? '']);
            }

            // Thực thi tool calls
            foreach ($assistMsg['tool_calls'] as $tc) {
                $fnName = $tc['function']['name'];
                $args   = json_decode($tc['function']['arguments'], true) ?? [];
                $result = executeCRMTool($pdo, $fnName, $args);
                $messages[] = [
                    'role'         => 'tool',
                    'tool_call_id' => $tc['id'],
                    'content'      => json_encode($result, JSON_UNESCAPED_UNICODE),
                ];
            }
        }

        aiJson(['success' => true, 'reply' => 'Xin lỗi, không thể xử lý yêu cầu lúc này.']);
        break; // unreachable

    // ── Phân tích ticket cụ thể ───────────────────────────────────────────────
    case 'analyze_ticket':
        $systemPrompt = "Bạn là trợ lý AI cho hệ thống HelpDesk IT. Phân tích ticket và đưa ra:\n1. Phân loại vấn đề\n2. Mức ưu tiên gợi ý\n3. Các bước xử lý\n4. Thời gian ước tính\nTrả lời bằng tiếng Việt, ngắn gọn.";
        $userMsg = "Phân tích ticket:\nTiêu đề: " . ($context['title'] ?? '') . "\nMô tả: " . ($context['description'] ?? '') . "\nDanh mục: " . ($context['category'] ?? '') . "\nƯu tiên: " . ($context['priority'] ?? '');
        break;

    case 'suggest_solution':
        $systemPrompt = "Bạn là chuyên gia IT HelpDesk. Đề xuất giải pháp chi tiết, thực tế. Trả lời bằng tiếng Việt.";
        $userMsg = "Vấn đề: " . $content . "\nGhi chú đã có:\n" . ($context['notes'] ?? 'Chưa có');
        break;

    case 'auto_categorize':
        $systemPrompt = "Bạn là trợ lý phân loại ticket IT. Trả lời ngắn gọn bằng tiếng Việt.";
        $userMsg = "Phân loại ticket này:\n1. Danh mục (Kỹ thuật/Tài khoản/Thanh toán/Cải tiến)\n2. Ưu tiên (Cao/Trung bình/Thấp/Khẩn cấp)\n3. Ai xử lý (IT Helpdesk hay IT Support)\n\nMô tả: " . $content;
        break;

    case 'chat':
    default:
        $systemPrompt = "Bạn là trợ lý AI cho hệ thống HelpDesk CRM. Hỗ trợ nhân viên IT xử lý vấn đề kỹ thuật. Trả lời ngắn gọn bằng tiếng Việt.";
        $userMsg = $content;
        break;
}

// ── Simple (non-tool) Groq call ───────────────────────────────────────────────
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

if ($response === false) {
    aiJson(['success' => false, 'message' => 'Không thể kết nối đến Groq API.']);
}

$data  = json_decode($response, true);
$reply = $data['choices'][0]['message']['content'] ?? '';
if ($httpCode !== 200 || empty($reply)) {
    $errMsg = $data['error']['message'] ?? ('HTTP ' . $httpCode . ': ' . substr($response, 0, 200));
    aiJson(['success' => false, 'message' => 'Groq lỗi: ' . $errMsg]);
}

aiJson(['success' => true, 'reply' => $reply]);
