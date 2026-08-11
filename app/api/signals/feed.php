<?php
// API: Get the live signal feed for a specific group (must be an active member)
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

$group_id = isset($_GET['group_id']) ? (int) $_GET['group_id'] : 0;
if ($group_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid group']);
    exit;
}

global $wpdb;
$user_id           = (int) $_SESSION['user_id'];
$groups_table      = $wpdb->prefix . 'rich_signal_groups';
$memberships_table = $wpdb->prefix . 'rich_signal_memberships';
$signals_table     = $wpdb->prefix . 'rich_signals';

$is_member = $wpdb->get_var($wpdb->prepare(
    "SELECT id FROM {$memberships_table} WHERE user_id = %d AND group_id = %d AND status = 'active'",
    $user_id, $group_id
));

if (!$is_member) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Not a member of this group']);
    exit;
}

$group = $wpdb->get_row($wpdb->prepare(
    "SELECT id, name, team_name FROM {$groups_table} WHERE id = %d",
    $group_id
), ARRAY_A);

$signals = $wpdb->get_results($wpdb->prepare(
    "SELECT id, symbol, direction, entry_price, stop_loss, take_profit, status, result, notes, posted_at
     FROM {$signals_table}
     WHERE group_id = %d
     ORDER BY posted_at DESC
     LIMIT 20",
    $group_id
), ARRAY_A);

$signals = $signals ?: [];
foreach ($signals as &$s) {
    $s['id'] = (int) $s['id'];
    foreach (['entry_price', 'stop_loss', 'take_profit'] as $k) {
        $s[$k] = $s[$k] !== null ? (float) $s[$k] : null;
    }
}
unset($s);

echo json_encode(['success' => true, 'group' => $group, 'signals' => $signals]);
