<?php
// API: Clear all messages and media from a group chat
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

if ($_SERVER['REQUEST_METHOD'] !== 'POST' && $_SERVER['REQUEST_METHOD'] !== 'DELETE') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$raw = file_get_contents('php://input');
$payload = json_decode($raw, true);
if (!is_array($payload)) $payload = $_POST;

$group_id = isset($payload['group_id']) ? (int) $payload['group_id'] : 0;

if ($group_id <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid group ID']);
    exit;
}

require_once '../../auth/feature-flags.php';
global $wpdb;

$user_id = (int) $_SESSION['user_id'];
$memberships_table = $wpdb->prefix . 'rich_signal_memberships';
$messages_table = $wpdb->prefix . 'rich_signal_group_messages';
$reactions_table = $wpdb->prefix . 'rich_signal_message_reactions';

// Check if user has permission to clear chat (must be owner, admin, or global staff)
$is_staff = false;
$membership = $wpdb->get_row($wpdb->prepare(
    "SELECT role FROM {$memberships_table} WHERE user_id = %d AND group_id = %d AND status = 'active' LIMIT 1",
    $user_id, $group_id
), ARRAY_A);

if (($membership && in_array($membership['role'], ['owner', 'admin'])) || rich_is_staff()) {
    $is_staff = true;
}

if (!$is_staff) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'You do not have permission to clear this chat.']);
    exit;
}

// 1. Fetch all messages in the group to find attached R2 media
$messages = $wpdb->get_col($wpdb->prepare(
    "SELECT message FROM {$messages_table} WHERE group_id = %d",
    $group_id
));

require_once '../../components/r2-storage.php';
$r2_base = preg_quote(R2_PUBLIC_URL, '/');

$deleted_files_count = 0;

foreach ($messages as $msg_text) {
    // Match URLs like: https://pub-...r2.dev/group_X/chat/123.jpg
    if (preg_match_all('/(' . $r2_base . '[^\s"\']+)/', $msg_text, $matches)) {
        foreach ($matches[1] as $media_url) {
            if (rich_r2_delete_file($media_url)) {
                $deleted_files_count++;
            }
        }
    }
}

// 2. Delete all reactions for messages in this group
$wpdb->query($wpdb->prepare(
    "DELETE r FROM {$reactions_table} r 
     INNER JOIN {$messages_table} m ON r.message_id = m.id 
     WHERE m.group_id = %d",
    $group_id
));

// 3. Hard delete all messages from the database
$deleted_messages = $wpdb->delete($messages_table, ['group_id' => $group_id], ['%d']);

if ($deleted_messages === false) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to clear chat database rows.']);
    exit;
}

echo json_encode([
    'success' => true, 
    'message' => 'Chat cleared successfully.',
    'stats' => [
        'messages_deleted' => $deleted_messages,
        'media_files_deleted' => $deleted_files_count
    ]
]);
exit;
