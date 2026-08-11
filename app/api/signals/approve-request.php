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
$request_id = (int) ($body['request_id'] ?? 0);
$decision = ($body['decision'] ?? 'approve') === 'reject' ? 'reject' : 'approve';
$note = trim((string) ($body['note'] ?? ''));

global $wpdb;
$requests_table    = $wpdb->prefix . 'rich_signal_group_requests';
$memberships_table = $wpdb->prefix . 'rich_signal_memberships';
$groups_table      = $wpdb->prefix . 'rich_signal_groups';
$audit_table       = $wpdb->prefix . 'rich_signal_group_audit_log';

$request = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$requests_table} WHERE id = %d LIMIT 1", $request_id), ARRAY_A);
if (!$request) {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'Request not found.']);
    exit;
}

$membership = $wpdb->get_row($wpdb->prepare("SELECT role, status FROM {$memberships_table} WHERE group_id = %d AND user_id = %d LIMIT 1", (int) $request['group_id'], $user_id), ARRAY_A);
if (!$membership || $membership['status'] !== 'active' || !in_array(($membership['role'] ?? ''), ['owner', 'admin'], true)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Only owners or admins can review requests.']);
    exit;
}
if (($request['status'] ?? '') !== 'pending') {
    echo json_encode(['success' => false, 'message' => 'This request has already been handled.']);
    exit;
}

$wpdb->query('START TRANSACTION');

$req_updated = $wpdb->update($requests_table, [
    'status' => $decision === 'approve' ? 'approved' : 'rejected',
    'reviewed_by' => $user_id,
    'reviewed_at' => current_time('mysql'),
    'review_note' => $note !== '' ? $note : null,
], ['id' => $request_id]);

if ($req_updated === false) {
    $wpdb->query('ROLLBACK');
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to update request.']);
    exit;
}

if ($decision === 'approve') {
    $existing_member = $wpdb->get_row($wpdb->prepare(
        "SELECT id FROM {$memberships_table} WHERE group_id = %d AND user_id = %d LIMIT 1",
        (int) $request['group_id'], (int) $request['requester_user_id']
    ), ARRAY_A);

    if ($existing_member) {
        $mem_updated = $wpdb->update($memberships_table, [
            'status' => 'active',
            'role' => 'member',
            'approved_by' => $user_id,
            'approved_at' => current_time('mysql'),
            'joined_at' => current_time('mysql'),
        ], ['id' => (int) $existing_member['id']]);
        if ($mem_updated === false) {
            $wpdb->query('ROLLBACK');
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Failed to activate membership.']);
            exit;
        }
    } else {
        $mem_inserted = $wpdb->insert($memberships_table, [
            'user_id' => (int) $request['requester_user_id'],
            'group_id' => (int) $request['group_id'],
            'role' => 'member',
            'status' => 'active',
            'access_type' => 'free',
            'approved_by' => $user_id,
            'approved_at' => current_time('mysql'),
            'joined_at' => current_time('mysql'),
        ]);
        if ($mem_inserted === false) {
            $wpdb->query('ROLLBACK');
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Failed to create membership.']);
            exit;
        }
    }

    $wpdb->query($wpdb->prepare("UPDATE {$groups_table} SET member_count = member_count + 1 WHERE id = %d", (int) $request['group_id']));
}

$wpdb->insert($audit_table, [
    'group_id' => (int) $request['group_id'],
    'actor_user_id' => $user_id,
    'action' => $decision === 'approve' ? 'join_request_approved' : 'join_request_rejected',
    'meta_json' => wp_json_encode(['request_id' => $request_id, 'note' => $note]),
]);

$wpdb->query('COMMIT');

echo json_encode([
    'success' => true,
    'message' => $decision === 'approve' ? 'Request approved.' : 'Request rejected.',
    'request_id' => $request_id,
]);
