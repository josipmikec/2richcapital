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

$target_user_id = isset($_GET['user_id']) ? (int) $_GET['user_id'] : 0;
if ($target_user_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid user']);
    exit;
}

global $wpdb;
$profile_table = $wpdb->prefix . 'rich_user_profiles';
$social_table = $wpdb->prefix . 'rich_social_follows';

$user = get_userdata($target_user_id);
if (!$user) {
    echo json_encode(['success' => false, 'message' => 'User not found']);
    exit;
}

$profile = $wpdb->get_row($wpdb->prepare(
    "SELECT display_name, trading_handle, bio, primary_market, trading_style FROM {$profile_table} WHERE user_id = %d LIMIT 1",
    $target_user_id
), ARRAY_A);

$display_name = $profile['display_name'] ?? $user->display_name;
$handle = trim((string)($profile['trading_handle'] ?? ''));
$handle = $handle !== '' ? ltrim($handle, '@') : strtolower(str_replace(' ', '', $display_name));
$bio = trim((string)($profile['bio'] ?? ''));

$followers = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$social_table} WHERE following_id = %d", $target_user_id));

$last_active = get_user_meta($target_user_id, 'last_active', true);
$last_active = $last_active ? (int) $last_active : 0;

echo json_encode([
    'success' => true,
    'profile' => [
        'user_id' => $target_user_id,
        'display_name' => $display_name,
        'handle' => '@' . $handle,
        'bio' => $bio,
        'followers' => $followers,
        'last_active' => $last_active,
        'avatar_char' => strtoupper(substr($display_name, 0, 1))
    ]
]);
