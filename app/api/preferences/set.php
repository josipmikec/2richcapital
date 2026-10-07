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
$b64   = !empty($input['b64']);
$table = $wpdb->prefix . 'rich_user_preferences';

// Decode base64-encoded values (used to bypass hosting WAF blocking nested JSON)
if ($b64 && $value !== null) {
    $decoded = base64_decode($value, true);
    if ($decoded !== false) {
        $value = $decoded;
    }
}

$allowed_keys = ['default_stop_distance', 'default_direction', 'default_session', 'show_pl_currency', 'compact_rows', 'auto_calc_pl', 'market_data_chart_settings', 'market_data_watchlist', 'market_data_chart_state', 'market_data_chart_layout', 'news_source_filter'];

$prefs = [];
if (isset($input['prefs']) && is_array($input['prefs'])) {
    $prefs = $input['prefs'];
} else if ($key) {
    $prefs[$key] = $value;
}

if (empty($prefs)) {
    echo json_encode(['success' => false, 'message' => 'No preferences provided']);
    exit;
}

foreach ($prefs as $k => $v) {
    if (!in_array($k, $allowed_keys)) continue;
    
    $existing = $wpdb->get_var($wpdb->prepare(
        "SELECT id FROM $table WHERE user_id = %d AND pref_key = %s",
        $user_id, $k
    ));

    if ($existing) {
        $wpdb->update(
            $table,
            ['pref_value' => $v, 'updated_at' => current_time('mysql')],
            ['user_id' => $user_id, 'pref_key' => $k]
        );
    } else {
        $wpdb->insert($table, [
            'user_id'    => $user_id,
            'pref_key'   => $k,
            'pref_value' => $v,
            'updated_at' => current_time('mysql')
        ]);
    }
}

if ($wpdb->last_error) {
    echo json_encode(['success' => false, 'message' => $wpdb->last_error]);
} else {
    echo json_encode(['success' => true, 'key' => $key, 'value' => $value]);
}
?>
