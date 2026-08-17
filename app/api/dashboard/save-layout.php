<?php
// /api/dashboard/save-layout.php
require_once '../../auth/session-config.php';
require_once '../../auth/db.php';

header('Content-Type: application/json');

// Auth check
if (empty($_SESSION['user_id']) || empty($_SESSION['authenticated'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

// CSRF check
$incoming_csrf = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
if (empty($incoming_csrf) || !hash_equals($_SESSION['csrf_token'] ?? '', $incoming_csrf)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'CSRF token mismatch']);
    exit;
}

// Only accept POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

// Parse JSON body
$body = json_decode(file_get_contents('php://input'), true);
$order = $body['order'] ?? null;

if (!is_array($order) || count($order) === 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid order']);
    exit;
}

// Whitelist allowed widget IDs
$allowed = ['market','signals','news','classroom','strategies','trades','mentors','ai','chat','journal'];
$order   = array_values(array_filter($order, fn($id) => in_array($id, $allowed)));

if (!$pdo) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'DB unavailable']);
    exit;
}

$uid         = (int)$_SESSION['user_id'];
$layout_json = json_encode($order);

// Upsert: insert or update on duplicate user_id
try {
    $stmt = $pdo->prepare("
    INSERT INTO user_dashboard_layouts (user_id, layout_order, updated_at)
    VALUES (?, ?, NOW())
    ON DUPLICATE KEY UPDATE layout_order = VALUES(layout_order), updated_at = NOW()
");
    $stmt->execute([$uid, $layout_json]);

    echo json_encode(['success' => true, 'saved_order' => $order]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Save exception',
        'message' => $e->getMessage(),
        'user_id' => $uid,
        'order' => $order
    ]);
}
