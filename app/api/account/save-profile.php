<?php
require_once dirname(__DIR__, 2) . '/auth/session-config.php';
require_once dirname(__DIR__, 2) . '/auth/feature-flags.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || !isset($_SESSION['authenticated'])) {
    echo wp_json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$user_id = $_SESSION['user_id'];

// Get JSON body
$raw = file_get_contents('php://input');
$data = json_decode($raw, true);

if (!$data) {
    echo wp_json_encode(['success' => false, 'message' => 'Invalid JSON']);
    exit;
}

// CSRF check
$csrf_token = $data['csrf_token'] ?? '';
if (!isset($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $csrf_token)) {
    echo wp_json_encode(['success' => false, 'message' => 'Security token invalid']);
    exit;
}

define('WP_USE_THEMES', false);
require_once dirname(__DIR__, 3) . '/wp-load.php';
global $wpdb;

$profile_table = $wpdb->prefix . 'rich_user_profiles';

$display_name = sanitize_text_field($data['display_name'] ?? $_SESSION['user_name']);
$trading_handle = sanitize_text_field($data['trading_handle'] ?? '');
$bio = sanitize_textarea_field($data['bio'] ?? '');
$primary_market = sanitize_text_field($data['primary_market'] ?? '');
$trading_style = sanitize_text_field($data['trading_style'] ?? '');

if ($trading_handle === '') {
    $trading_handle = '@' . strtolower(str_replace(' ','', $display_name));
}
if (strpos($trading_handle, '@') !== 0) {
    $trading_handle = '@' . ltrim($trading_handle, '@');
}

$existing_profile_id = $wpdb->get_var($wpdb->prepare("SELECT id FROM {$profile_table} WHERE user_id = %d LIMIT 1", $user_id));
$profile_data = [
    'user_id' => $user_id,
    'display_name' => $display_name,
    'trading_handle' => $trading_handle,
    'bio' => $bio,
    'primary_market' => $primary_market,
    'trading_style' => $trading_style,
    'updated_at' => current_time('mysql')
];

$save_ok = false;
if ($existing_profile_id) {
    $save_ok = ($wpdb->update($profile_table, $profile_data, ['id' => (int)$existing_profile_id]) !== false);
} else {
    $profile_data['created_at'] = current_time('mysql');
    $save_ok = ($wpdb->insert($profile_table, $profile_data) !== false);
}

if (!$save_ok) {
    echo wp_json_encode(['success' => false, 'message' => 'Profile save failed']);
} else {
    $_SESSION['user_name'] = $display_name;
    echo wp_json_encode(['success' => true, 'message' => 'Profile saved successfully']);
}
