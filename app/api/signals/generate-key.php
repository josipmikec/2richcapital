<?php
ob_start();
require_once '../../auth/session-config.php';
define('WP_USE_THEMES', false);
require_once('../../../wp-load.php');
ob_end_clean();

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || !isset($_SESSION['authenticated'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$group_id = intval($input['group_id'] ?? 0);

if (!$group_id) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Missing group_id']);
    exit;
}

global $wpdb;
$user_id = intval($_SESSION['user_id']);
$groups_table = $wpdb->prefix . 'rich_signal_groups';

// Check if user is the owner
$group = $wpdb->get_row($wpdb->prepare("SELECT id, owner_user_id FROM {$groups_table} WHERE id = %d", $group_id));

if (!$group) {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'Group not found']);
    exit;
}

if (intval($group->owner_user_id) !== $user_id) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Only the group owner can generate an API key']);
    exit;
}

// Generate new secure API key
$new_key = bin2hex(random_bytes(32));

$updated = $wpdb->update(
    $groups_table,
    ['api_key' => $new_key],
    ['id' => $group_id],
    ['%s'],
    ['%d']
);

if ($updated === false) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to update API key']);
    exit;
}

echo json_encode([
    'success' => true,
    'api_key' => $new_key,
    'message' => 'API key generated successfully'
]);
