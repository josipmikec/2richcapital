<?php
ob_start();
require_once '../../auth/session-config.php';
define('WP_USE_THEMES', false);
require_once dirname(__DIR__, 3) . '/wp-load.php';
ob_end_clean();

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || !isset($_SESSION['authenticated'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

global $wpdb;

$user_id = intval($_SESSION['user_id']);
$key     = $_GET['key'] ?? null;
$table   = $wpdb->prefix . 'rich_user_preferences';

if ($key) {
    $row = $wpdb->get_row($wpdb->prepare(
        "SELECT pref_value FROM $table WHERE user_id = %d AND pref_key = %s",
        $user_id, $key
    ));
    echo json_encode([
        'success' => true,
        'key'     => $key,
        'value'   => $row ? $row->pref_value : null
    ]);
} else {
    $rows = $wpdb->get_results($wpdb->prepare(
        "SELECT pref_key, pref_value FROM $table WHERE user_id = %d",
        $user_id
    ));
    $prefs = [];
    foreach ($rows as $row) {
        $prefs[$row->pref_key] = $row->pref_value;
    }
    echo json_encode(['success' => true, 'preferences' => $prefs]);
}
?>
