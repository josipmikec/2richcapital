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
action:
$action = in_array(($body['action'] ?? 'archive'), ['archive', 'delete'], true) ? $body['action'] : 'archive';
$archive_status = in_array(($body['status'] ?? 'archived'), ['archived', 'suspended'], true) ? $body['status'] : 'archived';

if ($group_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Valid group_id is required.']);
    exit;
}

global $wpdb;
$groups_table      = $wpdb->prefix . 'rich_signal_groups';
$memberships_table = $wpdb->prefix . 'rich_signal_memberships';
$audit_table       = $wpdb->prefix . 'rich_signal_group_audit_log';

$membership = $wpdb->get_row($wpdb->prepare(
    "SELECT role, status FROM {$memberships_table} WHERE group_id = %d AND user_id = %d LIMIT 1",
    $group_id, $user_id
), ARRAY_A);
if (!$membership || $membership['status'] !== 'active' || ($membership['role'] ?? '') !== 'owner') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Only group owners can modify this group.']);
    exit;
}

if ($action === 'delete') {
    $deleted = $wpdb->delete($groups_table, ['id' => $group_id], ['%d']);
    if ($deleted === false) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Failed to delete group.']);
        exit;
    }
    $wpdb->delete($memberships_table, ['group_id' => $group_id], ['%d']);
    $wpdb->insert($audit_table, [
        'group_id'      => $group_id,
        'actor_user_id' => $user_id,
        'action'        => 'group_deleted',
        'meta_json'     => wp_json_encode([]),
    ]);
    echo json_encode(['success' => true, 'message' => 'Group deleted permanently.', 'group_id' => $group_id, 'status' => 'deleted']);
    exit;
}

$updated = $wpdb->update($groups_table, [
    'status' => $archive_status,
    'is_active' => 0,
], ['id' => $group_id]);

if ($updated === false) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to archive group.']);
    exit;
}

$wpdb->insert($audit_table, [
    'group_id'      => $group_id,
    'actor_user_id' => $user_id,
    'action'        => 'group_' . $archive_status,
    'meta_json'     => wp_json_encode(['status' => $archive_status]),
]);

echo json_encode([
    'success' => true,
    'message' => 'Group moved to ' . $archive_status . '.',
    'group_id' => $group_id,
    'status' => $archive_status,
]);

