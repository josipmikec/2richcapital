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
$group_id = (int) ($body['group_id'] ?? 0);
$message = trim((string) ($body['message'] ?? ''));

global $wpdb;
$groups_table      = $wpdb->prefix . 'rich_signal_groups';
$memberships_table = $wpdb->prefix . 'rich_signal_memberships';
$requests_table    = $wpdb->prefix . 'rich_signal_group_requests';
$audit_table       = $wpdb->prefix . 'rich_signal_group_audit_log';

$group = $wpdb->get_row($wpdb->prepare("SELECT id, name, join_mode, pricing_type, status FROM {$groups_table} WHERE id = %d LIMIT 1", $group_id), ARRAY_A);
if (!$group) {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'Group not found.']);
    exit;
}
if (($group['status'] ?? '') !== 'live') {
    echo json_encode(['success' => false, 'message' => 'Only live groups can accept requests.']);
    exit;
}
if (($group['join_mode'] ?? 'open') !== 'request') {
    echo json_encode(['success' => false, 'message' => 'This group does not use request-based joining.']);
    exit;
}

$membership = $wpdb->get_row($wpdb->prepare("SELECT id, status FROM {$memberships_table} WHERE group_id = %d AND user_id = %d LIMIT 1", $group_id, $user_id), ARRAY_A);
if ($membership && ($membership['status'] ?? '') === 'active') {
    echo json_encode(['success' => false, 'message' => 'You are already a member of this group.']);
    exit;
}

$existing = $wpdb->get_row($wpdb->prepare("SELECT id, status FROM {$requests_table} WHERE group_id = %d AND requester_user_id = %d ORDER BY id DESC LIMIT 1", $group_id, $user_id), ARRAY_A);
if ($existing && in_array(($existing['status'] ?? ''), ['pending', 'approved'], true)) {
    echo json_encode(['success' => false, 'message' => 'You already have an active request for this group.']);
    exit;
}

$inserted = $wpdb->insert($requests_table, [
    'group_id' => $group_id,
    'requester_user_id' => $user_id,
    'status' => 'pending',
    'message' => $message !== '' ? $message : null,
    'created_at' => current_time('mysql'),
]);
if ($inserted === false) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to submit join request.']);
    exit;
}

$wpdb->insert($audit_table, [
    'group_id' => $group_id,
    'actor_user_id' => $user_id,
    'action' => 'join_request_created',
    'meta_json' => wp_json_encode(['message' => $message]),
]);

echo json_encode([
    'success' => true,
    'message' => 'Join request submitted.',
    'group_id' => $group_id,
]);
