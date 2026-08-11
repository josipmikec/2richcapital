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
$group_id = isset($body['group_id']) ? (int) $body['group_id'] : 0;
$user_id = (int) ($_SESSION['user_id'] ?? 0);

if ($group_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid group']);
    exit;
}

global $wpdb;
$groups_table = $wpdb->prefix . 'rich_signal_groups';
$memberships_table = $wpdb->prefix . 'rich_signal_memberships';

$group = $wpdb->get_row($wpdb->prepare(
    "SELECT id, name, owner_user_id FROM {$groups_table} WHERE id = %d AND is_active = 1 LIMIT 1",
    $group_id
), ARRAY_A);
if (!$group) {
    echo json_encode(['success' => false, 'message' => 'Group not found']);
    exit;
}

$membership = $wpdb->get_row($wpdb->prepare(
    "SELECT id, role, status FROM {$memberships_table} WHERE user_id = %d AND group_id = %d LIMIT 1",
    $user_id,
    $group_id
), ARRAY_A);
if (!$membership || ($membership['status'] ?? '') !== 'active') {
    echo json_encode(['success' => false, 'message' => 'You are not an active member of this group.']);
    exit;
}

if ((int) ($group['owner_user_id'] ?? 0) === $user_id || ($membership['role'] ?? '') === 'owner') {
    echo json_encode(['success' => false, 'message' => 'Owners cannot leave their own group. Transfer ownership or archive the group instead.']);
    exit;
}

$updated = $wpdb->update(
    $memberships_table,
    [
        'status' => 'cancelled',
        'updated_at' => current_time('mysql'),
    ],
    [
        'id' => (int) $membership['id'],
    ]
);

if ($updated === false) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Could not leave group.']);
    exit;
}

$wpdb->query($wpdb->prepare(
    "UPDATE {$groups_table} SET member_count = GREATEST(member_count - 1, 0) WHERE id = %d",
    $group_id
));

echo json_encode([
    'success' => true,
    'message' => 'Left ' . ($group['name'] ?? 'group') . '.',
    'group_id' => $group_id,
]);
