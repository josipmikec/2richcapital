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

$body = json_decode(file_get_contents('php://input'), true) ?: [];
$user_id = (int) $_SESSION['user_id'];
$group_id = (int) ($body['group_id'] ?? 0);
$mode = ($body['mode'] ?? 'publish') === 'review' ? 'review' : 'publish';

if ($group_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Valid group_id is required.']);
    exit;
}

global $wpdb;
$groups_table      = $wpdb->prefix . 'rich_signal_groups';
$memberships_table = $wpdb->prefix . 'rich_signal_memberships';
$audit_table       = $wpdb->prefix . 'rich_signal_group_audit_log';

$membership = $wpdb->get_row($wpdb->prepare(
    "SELECT role, status FROM {$memberships_table} WHERE group_id = %d AND user_id = %d LIMIT 1",
    $group_id, $user_id
), ARRAY_A);
if (!$membership || $membership['status'] !== 'active' || ($membership['role'] ?? '') !== 'owner') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Only the group owner can publish this group.']);
    exit;
}

$group = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$groups_table} WHERE id = %d LIMIT 1", $group_id), ARRAY_A);
if (!$group) {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'Group not found.']);
    exit;
}

$errors = [];
if (empty(trim((string) ($group['name'] ?? '')))) $errors[] = 'Group name is required.';
if (empty(trim((string) ($group['description'] ?? '')))) $errors[] = 'Description is required before publishing.';
if (empty(trim((string) ($group['intro_text'] ?? '')))) $errors[] = 'Intro text is required before publishing.';
if (empty(trim((string) ($group['rules_text'] ?? '')))) $errors[] = 'Rules text is required before publishing.';
if (($group['pricing_type'] ?? 'free') === 'paid' && (float) ($group['price'] ?? 0) <= 0) $errors[] = 'Paid groups require a price.';

if ($errors) {
    echo json_encode(['success' => false, 'message' => 'Publishing requirements not met.', 'errors' => $errors]);
    exit;
}

$status = $mode === 'review' ? 'pending_review' : 'live';
$is_active = $mode === 'publish' ? 1 : 0;
$updated = $wpdb->update($groups_table, [
    'status' => $status,
    'is_active' => $is_active,
], ['id' => $group_id]);

if ($updated === false) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to change group status.']);
    exit;
}

$wpdb->insert($audit_table, [
    'group_id'      => $group_id,
    'actor_user_id' => $user_id,
    'action'        => $mode === 'review' ? 'group_submitted_for_review' : 'group_published',
    'meta_json'     => wp_json_encode(['status' => $status]),
]);

echo json_encode([
    'success' => true,
    'message' => $mode === 'review' ? 'Group submitted for review.' : 'Group published successfully.',
    'group_id' => $group_id,
    'status' => $status,
]);
