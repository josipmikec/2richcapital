<?php
/** Public followers/following list endpoint for Trading Floor profiles. */
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

global $wpdb;
$view_user_id = isset($_GET['user_id']) ? (int) $_GET['user_id'] : 0;
$type = ($_GET['type'] ?? '') === 'following' ? 'following' : 'followers';
$table = $wpdb->prefix . 'rich_user_follows';

if ($view_user_id <= 0 || !get_userdata($view_user_id)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid user']);
    exit;
}

if ($type === 'following') {
    $rows = $wpdb->get_results($wpdb->prepare(
        "SELECT u.ID AS user_id, COALESCE(NULLIF(p.display_name, ''), NULLIF(u.display_name, ''), NULLIF(u.user_nicename, ''), NULLIF(u.user_login, ''), CONCAT('User #', u.ID)) AS display_name
         FROM {$table} f
         INNER JOIN {$wpdb->users} u ON u.ID = f.following_id
         LEFT JOIN {$wpdb->prefix}rich_user_profiles p ON p.user_id = u.ID
         WHERE f.follower_id = %d ORDER BY display_name ASC LIMIT 200",
        $view_user_id
    ), ARRAY_A);
} else {
    $rows = $wpdb->get_results($wpdb->prepare(
        "SELECT u.ID AS user_id, COALESCE(NULLIF(p.display_name, ''), NULLIF(u.display_name, ''), NULLIF(u.user_nicename, ''), NULLIF(u.user_login, ''), CONCAT('User #', u.ID)) AS display_name
         FROM {$table} f
         INNER JOIN {$wpdb->users} u ON u.ID = f.follower_id
         LEFT JOIN {$wpdb->prefix}rich_user_profiles p ON p.user_id = u.ID
         WHERE f.following_id = %d ORDER BY display_name ASC LIMIT 200",
        $view_user_id
    ), ARRAY_A);
}

$users = array_map(static function ($row) {
    $row['user_id'] = (int) $row['user_id'];
    $row['color'] = substr(md5((string) $row['user_id']), 0, 6);
    return $row;
}, $rows ?: []);

echo json_encode(['success' => true, 'users' => $users]);
