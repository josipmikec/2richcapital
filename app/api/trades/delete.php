<?php
ob_start();
require_once '../../auth/session-config.php';
define('WP_USE_THEMES', false);
require_once('../../../wp-load.php');
ob_end_clean();

require_once '../csrf.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || !isset($_SESSION['authenticated'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

verify_csrf();

$input = json_decode(file_get_contents('php://input'), true);

if (!$input || !isset($input['trade_id'])) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Missing trade_id'
    ]);
    exit;
}

global $wpdb;

$user_id = intval($_SESSION['user_id']);
$trade_id = intval($input['trade_id']);
$journal_id = isset($input['journal_id']) ? intval($input['journal_id']) : 0;

$trades_table = $wpdb->prefix . 'rich_trades';
$journals_table = $wpdb->prefix . 'rich_journals';
$custom_values_table = $wpdb->prefix . 'rich_custom_column_values';

$trade = $wpdb->get_row(
    $wpdb->prepare(
        "SELECT t.id, t.journal_id, j.user_id
         FROM {$trades_table} t
         INNER JOIN {$journals_table} j ON t.journal_id = j.id
         WHERE t.id = %d
         LIMIT 1",
        $trade_id
    ),
    ARRAY_A
);

if (!$trade) {
    http_response_code(404);
    echo json_encode([
        'success' => false,
        'message' => 'Trade not found'
    ]);
    exit;
}

if (intval($trade['user_id']) !== $user_id) {
    http_response_code(403);
    echo json_encode([
        'success' => false,
        'message' => 'You do not have permission to delete this trade'
    ]);
    exit;
}

if ($journal_id > 0 && intval($trade['journal_id']) !== $journal_id) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Trade does not belong to the selected journal'
    ]);
    exit;
}

$wpdb->query('START TRANSACTION');

try {
    $wpdb->delete($custom_values_table, ['trade_id' => $trade_id], ['%d']);
    $deleted = $wpdb->delete($trades_table, ['id' => $trade_id], ['%d']);

    if ($deleted === false) {
        throw new Exception('Failed to delete trade');
    }

    $wpdb->query('COMMIT');

    echo json_encode([
        'success' => true,
        'message' => 'Trade deleted successfully'
    ]);
} catch (Exception $e) {
    $wpdb->query('ROLLBACK');

    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
