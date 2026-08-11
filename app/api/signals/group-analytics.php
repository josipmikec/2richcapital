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
$group_id = (int) ($_GET['group_id'] ?? 0);

global $wpdb;
$memberships_table = $wpdb->prefix . 'rich_signal_memberships';
$groups_table      = $wpdb->prefix . 'rich_signal_groups';
$signals_table     = $wpdb->prefix . 'rich_signals';
$metrics_table     = $wpdb->prefix . 'rich_signal_group_metrics_daily';

$membership = $wpdb->get_row($wpdb->prepare("SELECT role, status FROM {$memberships_table} WHERE group_id = %d AND user_id = %d LIMIT 1", $group_id, $user_id), ARRAY_A);
if (!$membership || $membership['status'] !== 'active' || !in_array(($membership['role'] ?? ''), ['owner', 'admin'], true)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Only owners or admins can view analytics.']);
    exit;
}

$group = $wpdb->get_row($wpdb->prepare("SELECT id, name, member_count, posted_signals_count, last_signal_at, status, visibility FROM {$groups_table} WHERE id = %d LIMIT 1", $group_id), ARRAY_A);
if (!$group) {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'Group not found.']);
    exit;
}

$open_signals = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$signals_table} WHERE group_id = %d AND (result IS NULL OR result = 'pending' OR result = 'draft')", $group_id));
$closed_signals = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$signals_table} WHERE group_id = %d AND result IN ('won','lost','breakeven','cancelled')", $group_id));
$wins = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$signals_table} WHERE group_id = %d AND result = 'won'", $group_id));
$losses = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$signals_table} WHERE group_id = %d AND result = 'lost'", $group_id));
$daily = $wpdb->get_results($wpdb->prepare("SELECT * FROM {$metrics_table} WHERE group_id = %d ORDER BY metric_date DESC LIMIT 30", $group_id), ARRAY_A);

$win_rate = ($wins + $losses) > 0 ? round(($wins / ($wins + $losses)) * 100, 2) : null;

echo json_encode([
    'success' => true,
    'group' => $group,
    'summary' => [
        'open_signals' => $open_signals,
        'closed_signals' => $closed_signals,
        'wins' => $wins,
        'losses' => $losses,
        'win_rate' => $win_rate,
    ],
    'daily_metrics' => $daily ?: [],
]);
