<?php
ob_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/ai_config.php';
require_once __DIR__ . '/../includes/functions.php';

// Manual session-only auth check (avoid redirect in API context)
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
    aiJson(['success' => false, 'message' => 'AI chưa được cấu hình. Vui lòng thêm GEMINI_API_KEY vào config/ai_config.php.']);
}

$input = json_decode(file_get_contents('php://input'), true);
$action  = $input['action']  ?? '';
$content = trim($input['content'] ?? '');
$context = $input['context'] ?? [];

if (empty($content) && empty($context)) {
    aiJson(['success' => false, 'message' => 'Thiếu nội dung.']);
}

// Build system prompt
$systemPrompt = "Bạn là trợ lý AI cho hệ thống HelpDesk IT. Bạn hỗ trợ nhân viên IT xử lý ticket hỗ trợ kỹ thuật.
Trả lời ngắn gọn, chính xác bằng tiếng Việt. Khi phân tích ticket, hãy đưa ra:
1. Phân loại vấn đề
2. Mức độ ưu tiên gợi ý
3. Các bước xử lý đề xuất
4. Thời gian xử lý ước tính";

// Build user message based on action
switch ($action) {
    case 'analyze_ticket':
        $userMsg = "Phân tích ticket IT sau đây và đưa ra gợi ý:\n\nTiêu đề: " . ($context['title'] ?? '') . "\nMô tả: " . ($context['description'] ?? '') . "\nDanh mục: " . ($context['category'] ?? '') . "\nỨu tiên: " . ($context['priority'] ?? '');
        break;
    case 'suggest_solution':
        $userMsg = "Đề xuất giải pháp chi tiết cho vấn đề IT sau:\n\n" . $content . "\n\nCác ghi chú đã có:\n" . ($context['notes'] ?? 'Chưa có');
        break;
    case 'auto_categorize':
        $userMsg = "Dựa vào mô tả ticket sau, hãy gợi ý:\n1. Danh mục phù hợp nhất (Kỹ thuật/Tài khoản/Thanh toán/Cải tiến)\n2. Mức ưu tiên (Cao/Trung bình/Thấp/Khẩn cấp)\n3. Ai nên xử lý (IT Helpdesk hay IT Support)\n\nMô tả: " . $content;
        break;
    case 'chat':
    default:
        $userMsg = $content;
        break;
}

// Call Google Gemini API
$fullMsg = $systemPrompt . "\n\n---\n\n" . $userMsg;
$payload = [
    'contents'         => [['role' => 'user', 'parts' => [['text' => $fullMsg]]]],
    'generationConfig' => ['maxOutputTokens' => 4096, 'temperature' => 0.7],
];

$url = 'https://generativelanguage.googleapis.com/v1beta/models/' . GEMINI_MODEL . ':generateContent?key=' . GEMINI_API_KEY;

$ch = curl_init($url);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => json_encode($payload),
    CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
    CURLOPT_TIMEOUT        => 30,
    CURLOPT_SSL_VERIFYPEER => false,
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($response === false) {
    aiJson(['success' => false, 'message' => 'Không thể kết nối đến Gemini API.']);
}

$data = json_decode($response, true);
$reply = $data['candidates'][0]['content']['parts'][0]['text'] ?? '';
if ($httpCode !== 200 || empty($reply)) {
    $errMsg = $data['error']['message'] ?? ('HTTP ' . $httpCode . ': ' . substr($response, 0, 200));
    aiJson(['success' => false, 'message' => 'Gemini lỗi: ' . $errMsg]);
}

aiJson(['success' => true, 'reply' => $reply]);
