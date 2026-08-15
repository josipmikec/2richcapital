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

$user_id = (int) $_SESSION['user_id'];
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
global $wpdb;
$memberships_table = $wpdb->prefix . 'rich_signal_memberships';
$users_table = $wpdb->users;
$groups_table = $wpdb->prefix . 'rich_signal_groups';
$audit_table = $wpdb->prefix . 'rich_signal_group_audit_log';

if ($method === 'GET') {
    $group_id = (int) ($_GET['group_id'] ?? 0);
    $my = $wpdb->get_row($wpdb->prepare("SELECT role, status FROM {$memberships_table} WHERE group_id = %d AND user_id = %d LIMIT 1", $group_id, $user_id), ARRAY_A);
    if (!$my || $my['status'] !== 'active') {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Forbidden']);
        exit;
    }
    $staff = $wpdb->get_results($wpdb->prepare(
        "SELECT m.id, m.user_id, m.role, m.status, m.access_type, m.approved_at,
                COALESCE(NULLIF((SELECT rp.display_name FROM {$wpdb->prefix}rich_user_profiles rp WHERE rp.user_id = m.user_id LIMIT 1), ''), NULLIF(u.display_name, ''), NULLIF(u.user_nicename, ''), NULLIF(u.user_login, ''), CONCAT('User #', m.user_id)) AS display_name,
                u.user_email
         FROM {$memberships_table} m
         LEFT JOIN {$users_table} u ON u.ID = m.user_id
         WHERE m.group_id = %d
         ORDER BY FIELD(m.role, 'owner','admin','analyst','member'), m.id ASC",
        $group_id
    ), ARRAY_A);
    echo json_encode(['success' => true, 'staff' => $staff ?: []]);
    exit;
}

$incoming_csrf = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
if (empty($incoming_csrf) || !hash_equals($_SESSION['csrf_token'] ?? '', $incoming_csrf)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'CSRF token mismatch']);
    exit;
}

$body = json_decode(file_get_contents('php://input'), true) ?: [];
$group_id = (int) ($body['group_id'] ?? 0);
$target_user_id = (int) ($body['target_user_id'] ?? 0);
$role = in_array(($body['role'] ?? 'member'), ['admin', 'analyst', 'member'], true) ? $body['role'] : 'member';
$action = $body['action'] ?? 'set-role';

$my = $wpdb->get_row($wpdb->prepare("SELECT role, status FROM {$memberships_table} WHERE group_id = %d AND user_id = %d LIMIT 1", $group_id, $user_id), ARRAY_A);
if (!$my || $my['status'] !== 'active' || ($my['role'] ?? '') !== 'owner') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Only the owner can manage staff roles.']);
    exit;
}

$target = $wpdb->get_row($wpdb->prepare("SELECT id, role FROM {$memberships_table} WHERE group_id = %d AND user_id = %d LIMIT 1", $group_id, $target_user_id), ARRAY_A);
if (!$target) {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'Membership not found.']);
    exit;
}
if (($target['role'] ?? '') === 'owner') {
    echo json_encode(['success' => false, 'message' => 'Owner role cannot be changed here.']);
    exit;
}

if ($action === 'remove') {
    $updated = $wpdb->update($memberships_table, ['status' => 'cancelled', 'cancelled_at' => current_time('mysql')], ['id' => (int) $target['id']]);
    $audit_action = 'staff_removed';
} else {
    $updated = $wpdb->update($memberships_table, ['role' => $role], ['id' => (int) $target['id']]);
    $audit_action = 'staff_role_updated';
}

if ($updated === false) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to update staff membership.']);
    exit;
}

$wpdb->insert($audit_table, [
    'group_id' => $group_id,
    'actor_user_id' => $user_id,
    'action' => $audit_action,
    'meta_json' => wp_json_encode(['target_user_id' => $target_user_id, 'role' => $role, 'action' => $action]),
]);

echo json_encode(['success' => true, 'message' => 'Staff updated.']);
