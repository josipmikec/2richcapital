<?php
// API: Toggle a reaction on a group message
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

require_once '../../auth/feature-flags.php';
rich_api_feature_guard('signals-groups');

global $wpdb;
$user_id = (int) $_SESSION['user_id'];
session_write_close();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit;
}

$raw = file_get_contents('php://input');
$payload = json_decode($raw, true) ?: $_POST;

$message_id = isset($payload['message_id']) ? (int) $payload['message_id'] : 0;
$reaction = isset($payload['reaction']) ? trim((string) $payload['reaction']) : '';

if ($message_id <= 0 || $reaction === '') {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid payload']);
    exit;
}

$reactions_table = $wpdb->prefix . 'rich_signal_message_reactions';
$wpdb->query("CREATE TABLE IF NOT EXISTS {$reactions_table} (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    message_id BIGINT UNSIGNED NOT NULL,
    user_id BIGINT UNSIGNED NOT NULL,
    reaction VARCHAR(32) NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY msg_user_reaction (message_id, user_id, reaction)
) {$wpdb->get_charset_collate()}");

$messages_table = $wpdb->prefix . 'rich_signal_group_messages';
$memberships_table = $wpdb->prefix . 'rich_signal_memberships';

$msg = $wpdb->get_row($wpdb->prepare("SELECT group_id FROM {$messages_table} WHERE id = %d LIMIT 1", $message_id));
if (!$msg) {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'Message not found']);
    exit;
}

$is_member = (bool) $wpdb->get_var($wpdb->prepare(
    "SELECT id FROM {$memberships_table} WHERE user_id = %d AND group_id = %d AND status = 'active' LIMIT 1",
    $user_id, $msg->group_id
));
if (!$is_member) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Not a member of this group']);
    exit;
}

$existing = $wpdb->get_var($wpdb->prepare(
    "SELECT id FROM {$reactions_table} WHERE message_id = %d AND user_id = %d AND reaction = %s LIMIT 1",
    $message_id, $user_id, $reaction
));

if ($existing) {
    $wpdb->delete($reactions_table, ['id' => $existing], ['%d']);
    $action = 'removed';
} else {
    $wpdb->insert($reactions_table, [
        'message_id' => $message_id,
        'user_id' => $user_id,
        'reaction' => $reaction
    ], ['%d', '%d', '%s']);
    $action = 'added';
}

echo json_encode(['success' => true, 'action' => $action]);
