<?php
// API: Get single trade by ID
ob_start();
require_once '../../auth/session-config.php';
define('WP_USE_THEMES', false);
require_once('../../../wp-load.php');
ob_end_clean();

header('Content-Type: application/json');

// Check authentication
if (!isset($_SESSION['user_id']) || !isset($_SESSION['authenticated'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$trade_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$journal_id = isset($_GET['journal_id']) ? intval($_GET['journal_id']) : 0;

if ($trade_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid trade ID']);
    exit;
}

if ($journal_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Journal ID is required']);
    exit;
}

global $wpdb;
$table_name = $wpdb->prefix . 'rich_trades';
$journals_table = $wpdb->prefix . 'rich_journals';
$user_id = $_SESSION['user_id'];

$journal_exists = $wpdb->get_var($wpdb->prepare(
    "SELECT id FROM $journals_table WHERE id = %d AND user_id = %d",
    $journal_id,
    $user_id
));

if (!$journal_exists) {
    echo json_encode(['success' => false, 'message' => 'Invalid journal selected']);
    exit;
}

$trade = $wpdb->get_row($wpdb->prepare(
    "SELECT * FROM $table_name WHERE id = %d AND user_id = %d AND journal_id = %d",
    $trade_id,
    $user_id,
    $journal_id
), ARRAY_A);

if ($trade) {
    echo json_encode([
        'success' => true,
        'trade' => $trade
    ]);
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Trade not found or access denied'
    ]);
}
?>
