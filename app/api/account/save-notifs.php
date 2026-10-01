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

$prefs = $data['prefs'] ?? [];
$sanitized_prefs = [];
if (is_array($prefs)) {
    foreach ($prefs as $k => $v) {
        $sanitized_prefs[sanitize_text_field($k)] = (int)$v;
    }
}

update_user_meta($user_id, 'notification_prefs', wp_json_encode($sanitized_prefs));

echo wp_json_encode(['success' => true, 'message' => 'Notification preferences saved']);
