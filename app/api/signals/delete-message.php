<?php
ob_start();
require_once '../../auth/session-config.php';
define('WP_USE_THEMES', false);
require_once('../../../wp-load.php');
ob_end_clean();

require_once '../csrf.php';
header('Content-Type: application/json');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

if (!isset($_SESSION['user_id']) || !isset($_SESSION['authenticated'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

verify_csrf();

require_once '../../auth/feature-flags.php';
rich_api_feature_guard('signals-groups');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

global $wpdb;
$user_id = (int) $_SESSION['user_id'];
session_write_close();

$raw = file_get_contents('php://input');
$payload = json_decode($raw, true);
if (!is_array($payload)) $payload = $_POST;

$message_id = isset($payload['message_id']) ? (int) $payload['message_id'] : 0;

if ($message_id <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid message ID']);
    exit;
}

$messages_table = $wpdb->prefix . 'rich_signal_group_messages';
$memberships_table = $wpdb->prefix . 'rich_signal_memberships';

$msg = $wpdb->get_row($wpdb->prepare(
    "SELECT id, user_id, group_id, message FROM {$messages_table} WHERE id = %d LIMIT 1",
    $message_id
), ARRAY_A);

if (!$msg) {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'Message not found']);
    exit;
}

$is_author = ((int)$msg['user_id'] === $user_id);

$is_staff = false;
if (!$is_author) {
    $staff = $wpdb->get_row($wpdb->prepare(
        "SELECT role FROM {$memberships_table} WHERE user_id = %d AND group_id = %d AND status = 'active' AND role IN ('owner', 'admin', 'moderator') LIMIT 1",
        $user_id, (int)$msg['group_id']
    ));
    if ($staff || rich_is_staff()) {
        $is_staff = true;
    }
}

if (!$is_author && !$is_staff) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Permission denied']);
    exit;
}

require_once '../../components/r2-storage.php';

$r2_base = preg_quote(R2_PUBLIC_URL, '/');
if (preg_match_all('/(' . $r2_base . '[^\s"\']+)/', $msg['message'], $matches)) {
    foreach ($matches[1] as $media_url) {
        rich_r2_delete_file($media_url);
    }
}

$wpdb->delete($messages_table, ['id' => $message_id], ['%d']);

$reactions_table = $wpdb->prefix . 'rich_signal_message_reactions';
$wpdb->delete($reactions_table, ['message_id' => $message_id], ['%d']);

echo json_encode(['success' => true, 'message' => 'Message deleted forever.']);
exit;
