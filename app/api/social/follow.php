<?php
/**
 * Trading Floor social graph endpoint.
 * GET: return follow state and counts for a viewed user.
 * POST: follow or unfollow a viewed user.
 */
ob_start();
require_once '../../auth/session-config.php';
define('WP_USE_THEMES', false);
require_once('../../../wp-load.php');
require_once '../csrf.php';
ob_end_clean();

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || !isset($_SESSION['authenticated'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

global $wpdb;
$current_user_id = (int) $_SESSION['user_id'];
$table = $wpdb->prefix . 'rich_user_follows';

$create_table_sql = "CREATE TABLE IF NOT EXISTS {$table} (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    follower_id BIGINT UNSIGNED NOT NULL,
    following_id BIGINT UNSIGNED NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY follower_following (follower_id, following_id),
    KEY following_idx (following_id),
    KEY follower_idx (follower_id)
) {$wpdb->get_charset_collate()};";
require_once ABSPATH . 'wp-admin/includes/upgrade.php';
dbDelta($create_table_sql);

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $target_user_id = isset($_GET['user_id']) ? (int) $_GET['user_id'] : 0;
    if ($target_user_id <= 0 || $target_user_id === $current_user_id || !get_userdata($target_user_id)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid target user']);
        exit;
    }
    $is_following = (bool) $wpdb->get_var($wpdb->prepare(
        "SELECT id FROM {$table} WHERE follower_id = %d AND following_id = %d LIMIT 1",
        $current_user_id,
        $target_user_id
    ));
    $followers = (int) $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM {$table} WHERE following_id = %d",
        $target_user_id
    ));
    $following = (int) $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM {$table} WHERE follower_id = %d",
        $target_user_id
    ));
    echo json_encode(['success' => true, 'is_following' => $is_following, 'followers' => $followers, 'following' => $following]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

verify_csrf();
$payload = json_decode(file_get_contents('php://input'), true);
if (!is_array($payload)) {
    $payload = $_POST;
}
$target_user_id = isset($payload['user_id']) ? (int) $payload['user_id'] : 0;
if ($target_user_id <= 0 || $target_user_id === $current_user_id || !get_userdata($target_user_id)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid target user']);
    exit;
}
$action = ($payload['action'] ?? '') === 'unfollow' ? 'unfollow' : 'follow';

if ($action === 'unfollow') {
    $wpdb->delete($table, ['follower_id' => $current_user_id, 'following_id' => $target_user_id], ['%d', '%d']);
} else {
    $wpdb->query($wpdb->prepare(
        "INSERT IGNORE INTO {$table} (follower_id, following_id) VALUES (%d, %d)",
        $current_user_id,
        $target_user_id
    ));
}

$followers = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$table} WHERE following_id = %d", $target_user_id));
$following = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$table} WHERE follower_id = %d", $target_user_id));
$is_following = $action === 'follow';
echo json_encode(['success' => true, 'is_following' => $is_following, 'followers' => $followers, 'following' => $following]);
