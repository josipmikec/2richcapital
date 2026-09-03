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

global $wpdb;

$user_id = intval($_SESSION['user_id']);
$symbol = isset($_GET['symbol']) ? sanitize_text_field($_GET['symbol']) : '';

if (empty($symbol)) {
    echo json_encode(['success' => false, 'message' => 'Missing symbol']);
    exit;
}

$table = $wpdb->prefix . 'rich_chart_drawings';

// Check if table exists (fail gracefully if it doesn't yet)
if ($wpdb->get_var("SHOW TABLES LIKE '$table'") != $table) {
    echo json_encode(['success' => true, 'drawings' => null]);
    exit;
}

$drawings = $wpdb->get_var($wpdb->prepare(
    "SELECT drawings FROM $table WHERE user_id = %d AND symbol = %s",
    $user_id, $symbol
));

echo json_encode(['success' => true, 'drawings' => $drawings]);
?>
