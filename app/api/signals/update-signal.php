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
    echo json_encode(['success' => false, 'message' => 'Only owners, admins, or analysts can update signals.']);
    exit;
}

$update_data = [];
$allowed_fields = ['symbol', 'direction', 'entry_price', 'stop_loss', 'take_profit', 'notes', 'result', 'session'];
foreach ($allowed_fields as $field) {
    if (array_key_exists($field, $body)) {
        $update_data[$field] = is_string($body[$field]) ? trim($body[$field]) : $body[$field];
    }
}
if (isset($update_data['symbol'])) $update_data['symbol'] = strtoupper((string) $update_data['symbol']);
if (isset($update_data['direction'])) $update_data['direction'] = strtoupper((string) $update_data['direction']);

if (!$update_data) {
    echo json_encode(['success' => false, 'message' => 'No fields to update.']);
    exit;
}

$updated = $wpdb->update($signals_table, $update_data, ['id' => $signal_id]);
if ($updated === false) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to update signal.']);
    exit;
}

$wpdb->insert($audit_table, [
    'group_id' => (int) $signal['group_id'],
    'actor_user_id' => $user_id,
    'action' => 'signal_updated',
    'meta_json' => wp_json_encode(['signal_id' => $signal_id, 'fields' => array_keys($update_data)]),
]);

echo json_encode([
    'success' => true,
    'message' => 'Signal updated.',
    'signal_id' => $signal_id,
]);
