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

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

global $wpdb;
$user_id = (int) $_SESSION['user_id'];

$input     = json_decode(file_get_contents('php://input'), true);
$column_id = isset($input['column_id']) ? (int) $input['column_id'] : 0;

if ($column_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid column ID']);
    exit;
}

$custom_columns_table = $wpdb->prefix . 'rich_custom_columns';
$custom_values_table  = $wpdb->prefix . 'rich_custom_column_values';

$owns = $wpdb->get_var($wpdb->prepare(
    "SELECT id FROM {$custom_columns_table} WHERE id = %d AND user_id = %d LIMIT 1",
    $column_id,
    $user_id
));

if (!$owns) {
    echo json_encode(['success' => false, 'message' => 'Column not found or access denied']);
    exit;
}

$wpdb->delete(
    $custom_values_table,
    ['column_id' => $column_id],
    ['%d']
);

$deleted = $wpdb->delete(
    $custom_columns_table,
    ['id' => $column_id, 'user_id' => $user_id],
    ['%d', '%d']
);

if ($deleted === false) {
    echo json_encode([
        'success'  => false,
        'message'  => 'Failed to delete custom column',
        'db_error' => $wpdb->last_error
    ]);
    exit;
}

echo json_encode(['success' => true, 'message' => 'Custom column deleted']);
exit;
?>
