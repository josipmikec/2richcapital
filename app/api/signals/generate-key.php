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

$incoming_csrf = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
if (empty($incoming_csrf) || !hash_equals($_SESSION['csrf_token'] ?? '', $incoming_csrf)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'CSRF token mismatch']);
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
$memberships_table = $wpdb->prefix . 'rich_signal_memberships';

// Check if user is the owner using memberships table
$membership = $wpdb->get_row($wpdb->prepare(
    "SELECT role, status FROM {$memberships_table} WHERE group_id = %d AND user_id = %d LIMIT 1",
    $group_id, $user_id
), ARRAY_A);

if (!$membership || $membership['status'] !== 'active' || $membership['role'] !== 'owner') {
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
