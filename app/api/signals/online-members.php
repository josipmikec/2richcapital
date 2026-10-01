<?php
// API: Get online members for a specific group (active within last 120s)
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
$group_id = isset($_GET['group_id']) ? (int) $_GET['group_id'] : 0;
if ($group_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid group']);
    exit;
}

global $wpdb;
$memberships_table = $wpdb->prefix . 'rich_signal_memberships';

$is_member = $wpdb->get_var($wpdb->prepare(
    "SELECT id FROM {$memberships_table} WHERE user_id = %d AND group_id = %d AND status = 'active'",
    $user_id, $group_id
));

if (!$is_member) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Not a member of this group']);
    exit;
}

// Fetch all active members in this group and their last_active
$online_members = $wpdb->get_results($wpdb->prepare(
    "SELECT m.user_id, um.meta_value as last_active
     FROM {$memberships_table} m
     LEFT JOIN {$wpdb->usermeta} um ON um.user_id = m.user_id AND um.meta_key = 'last_active'
     WHERE m.group_id = %d AND m.status = 'active'",
    $group_id
), ARRAY_A);

$user_activity = [];
foreach ($online_members as $m) {
    $user_activity[(int)$m['user_id']] = (int)$m['last_active'];
}

echo json_encode([
    'success' => true,
    'user_activity' => $user_activity
]);
