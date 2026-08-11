<?php
ob_start();
require_once '../../auth/session-config.php';
define('WP_USE_THEMES', false);
require_once('../../../wp-load.php');
ob_end_clean();

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || !isset($_SESSION['authenticated'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$incoming_csrf = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
if (empty($incoming_csrf) || !hash_equals($_SESSION['csrf_token'] ?? '', $incoming_csrf)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'CSRF token mismatch']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$body = json_decode(file_get_contents('php://input'), true) ?: [];
$user_id = (int) $_SESSION['user_id'];
$signal_id = (int) ($body['signal_id'] ?? 0);
$result = strtolower(trim((string) ($body['result'] ?? 'won')));
$exit_price = isset($body['exit_price']) ? (float) $body['exit_price'] : null;
$close_note = trim((string) ($body['close_note'] ?? ''));
$allowed_results = ['won', 'lost', 'breakeven', 'cancelled'];
if (!in_array($result, $allowed_results, true)) $result = 'won';

global $wpdb;
$signals_table     = $wpdb->prefix . 'rich_signals';
$memberships_table = $wpdb->prefix . 'rich_signal_memberships';
$audit_table       = $wpdb->prefix . 'rich_signal_group_audit_log';

$signal = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$signals_table} WHERE id = %d LIMIT 1", $signal_id), ARRAY_A);
if (!$signal) {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'Signal not found.']);
    exit;
}

$membership = $wpdb->get_row($wpdb->prepare("SELECT role, status FROM {$memberships_table} WHERE group_id = %d AND user_id = %d LIMIT 1", (int) $signal['group_id'], $user_id), ARRAY_A);
if (!$membership || $membership['status'] !== 'active' || !in_array(($membership['role'] ?? ''), ['owner', 'admin', 'analyst'], true)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Only owners, admins, or analysts can close signals.']);
    exit;
}

$notes = trim((string) ($signal['notes'] ?? ''));
if ($close_note !== '') {
    $notes = trim($notes . "\n\nClose note: " . $close_note);
}

$updated = $wpdb->update($signals_table, [
    'result' => $result,
    'notes' => $notes !== '' ? $notes : null,
    'exit_price' => $exit_price,
    'closed_at' => current_time('mysql'),
], ['id' => $signal_id]);

if ($updated === false) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to close signal.']);
    exit;
}

$wpdb->insert($audit_table, [
    'group_id' => (int) $signal['group_id'],
    'actor_user_id' => $user_id,
    'action' => 'signal_closed',
    'meta_json' => wp_json_encode(['signal_id' => $signal_id, 'result' => $result]),
]);

echo json_encode([
    'success' => true,
    'message' => 'Signal closed.',
    'signal_id' => $signal_id,
    'result' => $result,
]);
