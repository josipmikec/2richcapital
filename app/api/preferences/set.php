<?php
ob_start();
require_once '../../auth/session-config.php';
define('WP_USE_THEMES', false);
require_once dirname(__DIR__, 3) . '/wp-load.php';
ob_end_clean();

require_once '../csrf.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || !isset($_SESSION['authenticated'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

verify_csrf();

global $wpdb;

$user_id = intval($_SESSION['user_id']);
$input   = json_decode(file_get_contents('php://input'), true);
if (!$input) $input = $_POST;

$key   = $input['key']   ?? null;
$value = $input['value'] ?? null;
$table = $wpdb->prefix . 'rich_user_preferences';

if (!$key) {
    echo json_encode(['success' => false, 'message' => 'Missing key']);
    exit;
}

$allowed_keys = ['default_stop_distance'];
if (!in_array($key, $allowed_keys)) {
    echo json_encode(['success' => false, 'message' => 'Invalid preference key']);
    exit;
}

$existing = $wpdb->get_var($wpdb->prepare(
    "SELECT id FROM $table WHERE user_id = %d AND pref_key = %s",
    $user_id, $key
));

if ($existing) {
    $wpdb->update(
        $table,
        ['pref_value' => $value, 'updated_at' => current_time('mysql')],
        ['user_id' => $user_id, 'pref_key' => $key]
    );
} else {
    $wpdb->insert($table, [
        'user_id'    => $user_id,
        'pref_key'   => $key,
        'pref_value' => $value,
        'updated_at' => current_time('mysql')
    ]);
}

if ($wpdb->last_error) {
    echo json_encode(['success' => false, 'message' => $wpdb->last_error]);
} else {
    echo json_encode(['success' => true, 'key' => $key, 'value' => $value]);
}
?>
