<?php
// API: Save column visibility settings
ob_start();
require_once '../../auth/session-config.php';
define('WP_USE_THEMES', false);
require_once('../../../wp-load.php');
ob_end_clean();

require_once '../csrf.php';
header('Content-Type: application/json');

// Check authentication
if (!isset($_SESSION['user_id']) || !isset($_SESSION['authenticated'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

verify_csrf();

$user_id = $_SESSION['user_id'];

// Get POST data
$input = json_decode(file_get_contents('php://input'), true);

if (!isset($input['visibility']) || !is_array($input['visibility'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid visibility data']);
    exit;
}

// Save to user meta
$updated = update_user_meta($user_id, 'rich_column_visibility', json_encode($input['visibility']));

echo json_encode([
    'success' => true,
    'message' => 'Column visibility updated'
]);
?>
