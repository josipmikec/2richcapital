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

$body = json_decode(file_get_contents('php://input'), true) ?: [];
$user_id = (int) $_SESSION['user_id'];
$action = $body['action'] ?? 'create';

global $wpdb;
$invites_table     = $wpdb->prefix . 'rich_signal_group_invites';
$groups_table      = $wpdb->prefix . 'rich_signal_groups';
$memberships_table = $wpdb->prefix . 'rich_signal_memberships';
$audit_table       = $wpdb->prefix . 'rich_signal_group_audit_log';

if ($action === 'create') {
    $group_id = (int) ($body['group_id'] ?? 0);
    $email = sanitize_email((string) ($body['email'] ?? ''));
    $role = in_array(($body['role'] ?? 'member'), ['member', 'analyst', 'admin'], true) ? $body['role'] : 'member';
    $access_type = in_array(($body['access_type'] ?? 'free'), ['free', 'paid', 'comped'], true) ? $body['access_type'] : 'free';

    $membership = $wpdb->get_row($wpdb->prepare("SELECT role, status FROM {$memberships_table} WHERE group_id = %d AND user_id = %d LIMIT 1", $group_id, $user_id), ARRAY_A);
    if (!$membership || $membership['status'] !== 'active' || !in_array(($membership['role'] ?? ''), ['owner', 'admin'], true)) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Only owners or admins can create invites.']);
        exit;
    }

    $token = wp_generate_password(24, false, false);
    $expires_at = !empty($body['expires_at']) ? gmdate('Y-m-d H:i:s', strtotime((string) $body['expires_at'])) : gmdate('Y-m-d H:i:s', time() + 7 * DAY_IN_SECONDS);

    $inserted = $wpdb->insert($invites_table, [
        'group_id' => $group_id,
        'email' => $email !== '' ? $email : null,
        'invite_token' => $token,
        'role' => $role,
        'access_type' => $access_type,
        'status' => 'pending',
        'created_by' => $user_id,
        'expires_at' => $expires_at,
        'created_at' => current_time('mysql'),
    ]);
    if ($inserted === false) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Failed to create invite.']);
        exit;
    }

    $wpdb->insert($audit_table, [
        'group_id' => $group_id,
        'actor_user_id' => $user_id,
        'action' => 'invite_created',
        'meta_json' => wp_json_encode(['email' => $email, 'role' => $role]),
    ]);

    echo json_encode([
        'success' => true,
        'message' => 'Invite created.',
        'invite_id' => (int) $wpdb->insert_id,
        'invite_token' => $token,
        'expires_at' => $expires_at,
    ]);
    exit;
}

if ($action === 'accept') {
    $token = trim((string) ($body['invite_token'] ?? ''));
    if ($token === '') {
        echo json_encode(['success' => false, 'message' => 'Invite token is required.']);
        exit;
    }

    $invite = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$invites_table} WHERE invite_token = %s LIMIT 1", $token), ARRAY_A);
    if (!$invite) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Invite not found.']);
        exit;
    }
    if (($invite['status'] ?? '') !== 'pending') {
        echo json_encode(['success' => false, 'message' => 'This invite is no longer active.']);
        exit;
    }
    if (!empty($invite['expires_at']) && strtotime((string) $invite['expires_at']) < time()) {
        echo json_encode(['success' => false, 'message' => 'This invite has expired.']);
        exit;
    }

    $wpdb->query('START TRANSACTION');

    $existing = $wpdb->get_row($wpdb->prepare("SELECT id FROM {$memberships_table} WHERE group_id = %d AND user_id = %d LIMIT 1", (int) $invite['group_id'], $user_id), ARRAY_A);
    if ($existing) {
        $mem_updated = $wpdb->update($memberships_table, [
            'status' => 'active',
            'role' => $invite['role'] ?: 'member',
            'access_type' => $invite['access_type'] ?: 'free',
            'approved_by' => (int) ($invite['created_by'] ?? 0),
            'approved_at' => current_time('mysql'),
            'joined_at' => current_time('mysql'),
        ], ['id' => (int) $existing['id']]);
        if ($mem_updated === false) {
            $wpdb->query('ROLLBACK');
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Failed to activate membership.']);
            exit;
        }
    } else {
        $mem_inserted = $wpdb->insert($memberships_table, [
            'user_id' => $user_id,
            'group_id' => (int) $invite['group_id'],
            'role' => $invite['role'] ?: 'member',
            'status' => 'active',
            'access_type' => $invite['access_type'] ?: 'free',
            'approved_by' => (int) ($invite['created_by'] ?? 0),
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

    $inv_updated = $wpdb->update($invites_table, [
        'status' => 'accepted',
        'accepted_by' => $user_id,
        'accepted_at' => current_time('mysql'),
    ], ['id' => (int) $invite['id']]);
    if ($inv_updated === false) {
        $wpdb->query('ROLLBACK');
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Failed to mark invite as accepted.']);
        exit;
    }

    $wpdb->query($wpdb->prepare("UPDATE {$groups_table} SET member_count = member_count + 1 WHERE id = %d", (int) $invite['group_id']));
    $wpdb->insert($audit_table, [
        'group_id' => (int) $invite['group_id'],
        'actor_user_id' => $user_id,
        'action' => 'invite_accepted',
        'meta_json' => wp_json_encode(['invite_id' => (int) $invite['id']]),
    ]);

    $wpdb->query('COMMIT');

    echo json_encode([
        'success' => true,
        'message' => 'Invite accepted.',
        'group_id' => (int) $invite['group_id'],
    ]);
    exit;
}

http_response_code(400);
echo json_encode(['success' => false, 'message' => 'Unsupported invite action.']);
