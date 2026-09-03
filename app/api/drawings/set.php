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
$input = json_decode(file_get_contents('php://input'), true);
if (!$input) $input = $_POST;

$symbol = isset($input['symbol']) ? sanitize_text_field($input['symbol']) : '';
$drawings = isset($input['drawings']) ? $input['drawings'] : '';

if (empty($symbol)) {
    echo json_encode(['success' => false, 'message' => 'Missing symbol']);
    exit;
}

$table = $wpdb->prefix . 'rich_chart_drawings';

// Ensure table exists
$wpdb->query("CREATE TABLE IF NOT EXISTS $table (
    id BIGINT(20) AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT(20) NOT NULL,
    symbol VARCHAR(100) NOT NULL,
    drawings LONGTEXT,
    updated_at DATETIME,
    UNIQUE KEY user_symbol (user_id, symbol)
) CHARSET=utf8mb4");

$existing = $wpdb->get_var($wpdb->prepare(
    "SELECT id FROM $table WHERE user_id = %d AND symbol = %s",
    $user_id, $symbol
));

if ($existing) {
    $wpdb->update(
        $table,
        ['drawings' => $drawings, 'updated_at' => current_time('mysql')],
        ['user_id' => $user_id, 'symbol' => $symbol]
    );
} else {
    $wpdb->insert($table, [
        'user_id' => $user_id,
        'symbol' => $symbol,
        'drawings' => $drawings,
        'updated_at' => current_time('mysql')
    ]);
}

if ($wpdb->last_error) {
    echo json_encode(['success' => false, 'message' => $wpdb->last_error]);
} else {
    echo json_encode(['success' => true]);
}
?>
