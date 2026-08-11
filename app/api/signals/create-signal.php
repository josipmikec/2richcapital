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
$user_id = (int) ($_SESSION['user_id'] ?? 0);
$group_id = (int) ($body['group_id'] ?? 0);
$symbol = strtoupper(trim((string) ($body['symbol'] ?? '')));
$direction = strtoupper(trim((string) ($body['direction'] ?? '')));
$entry = array_key_exists('entry_price', $body) && $body['entry_price'] !== '' ? (float) $body['entry_price'] : null;
$stop = array_key_exists('stop_loss', $body) && $body['stop_loss'] !== '' ? (float) $body['stop_loss'] : null;
$take = array_key_exists('take_profit', $body) && $body['take_profit'] !== '' ? (float) $body['take_profit'] : null;
$notes = trim((string) ($body['notes'] ?? ''));

$requested_status = strtolower(trim((string) ($body['status'] ?? 'open')));
$result = 'pending';
if ($requested_status === 'closed') {
    $result = 'win';
} elseif ($requested_status === 'cancelled') {
    $result = 'loss';
}

global $wpdb;
$groups_table      = $wpdb->prefix . 'rich_signal_groups';
$memberships_table = $wpdb->prefix . 'rich_signal_memberships';
$signals_table     = $wpdb->prefix . 'rich_signals';
$audit_table       = $wpdb->prefix . 'rich_signal_group_audit_log';

$group = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$groups_table} WHERE id = %d LIMIT 1", $group_id), ARRAY_A);
if (!$group) {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'Group not found.']);
    exit;
}

$membership = $wpdb->get_row($wpdb->prepare(
    "SELECT role, status FROM {$memberships_table} WHERE group_id = %d AND user_id = %d LIMIT 1",
    $group_id,
    $user_id
), ARRAY_A);
if (!$membership || ($membership['status'] ?? '') !== 'active' || !in_array(($membership['role'] ?? ''), ['owner', 'admin', 'analyst'], true)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Only owners, admins, or analysts can create signals.']);
    exit;
}

if ($symbol === '' || !in_array($direction, ['LONG', 'SHORT'], true)) {
    echo json_encode(['success' => false, 'message' => 'Symbol and direction are required.']);
    exit;
}
if (!empty($group['requires_stop_loss']) && $stop === null) {
    echo json_encode(['success' => false, 'message' => 'This group requires a stop loss.']);
    exit;
}
if (!empty($group['requires_take_profit']) && $take === null) {
    echo json_encode(['success' => false, 'message' => 'This group requires a take profit.']);
    exit;
}

$allowed_symbols = json_decode((string) ($group['allowed_symbols_json'] ?? '[]'), true) ?: [];
if (!empty($allowed_symbols) && !in_array($symbol, array_map('strtoupper', $allowed_symbols), true)) {
    echo json_encode(['success' => false, 'message' => 'This symbol is not allowed for the group.']);
    exit;
}

$direction_for_db = $direction === 'LONG' ? 'BUY' : 'SELL';
$insert_data = [
    'group_id' => $group_id,
    'symbol' => $symbol,
    'direction' => $direction_for_db,
    'entry_price' => $entry,
    'stop_loss' => $stop,
    'take_profit' => $take,
    'notes' => $notes !== '' ? $notes : null,
    'result' => $result,
    'created_at' => current_time('mysql'),
];

$inserted = $wpdb->insert($signals_table, $insert_data);
if ($inserted === false) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Failed to create signal.',
        'debug' => [
            'db_error' => $wpdb->last_error,
            'insert_data' => $insert_data,
        ],
    ]);
    exit;
}

$signal_id = (int) $wpdb->insert_id;
$wpdb->query($wpdb->prepare(
    "UPDATE {$groups_table} SET posted_signals_count = posted_signals_count + 1, last_signal_at = %s WHERE id = %d",
    current_time('mysql'),
    $group_id
));
$wpdb->insert($audit_table, [
    'group_id' => $group_id,
    'actor_user_id' => $user_id,
    'action' => 'signal_created',
    'meta_json' => wp_json_encode([
        'signal_id' => $signal_id,
        'symbol' => $symbol,
        'direction' => $direction_for_db,
    ]),
]);

echo json_encode([
    'success' => true,
    'message' => 'Signal created.',
    'signal_id' => $signal_id,
]);
