<?php
// API: List and create group room messages for an active member.
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

global $wpdb;
$user_id = (int) $_SESSION['user_id'];
$messages_table = $wpdb->prefix . 'rich_signal_group_messages';
$memberships_table = $wpdb->prefix . 'rich_signal_memberships';
$groups_table = $wpdb->prefix . 'rich_signal_groups';
$profile_table = $wpdb->prefix . 'rich_user_profiles';

function rich_group_messages_resolve_display_name($wpdb, $profile_table, $fallback_name, $user_id) {
    $profile_name = $wpdb->get_var($wpdb->prepare(
        "SELECT display_name FROM {$profile_table} WHERE user_id = %d LIMIT 1",
        (int) $user_id
    ));
    if (is_string($profile_name) && trim($profile_name) !== '') {
        return $profile_name;
    }
    if (is_string($fallback_name) && trim($fallback_name) !== '') {
        return $fallback_name;
    }
    return 'User #' . (int) $user_id;
}

$ensure_table = "CREATE TABLE IF NOT EXISTS {$messages_table} (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    group_id BIGINT UNSIGNED NOT NULL,
    user_id BIGINT UNSIGNED NOT NULL,
    message TEXT NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL DEFAULT NULL,
    is_deleted TINYINT(1) NOT NULL DEFAULT 0,
    PRIMARY KEY (id),
    KEY group_created (group_id, created_at),
    KEY user_created (user_id, created_at)
) {$wpdb->get_charset_collate()};";
require_once ABSPATH . 'wp-admin/includes/upgrade.php';
dbDelta($ensure_table);

function rich_group_messages_is_member($wpdb, $memberships_table, $user_id, $group_id) {
    return (bool) $wpdb->get_var($wpdb->prepare(
        "SELECT id FROM {$memberships_table} WHERE user_id = %d AND group_id = %d AND status = 'active' LIMIT 1",
        $user_id,
        $group_id
    ));
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $group_id = isset($_GET['group_id']) ? (int) $_GET['group_id'] : 0;
    if ($group_id <= 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid group']);
        exit;
    }

    if (!rich_group_messages_is_member($wpdb, $memberships_table, $user_id, $group_id)) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Not a member of this group']);
        exit;
    }

    $messages = $wpdb->get_results($wpdb->prepare(
        "SELECT m.id, m.group_id, m.user_id, m.message, m.created_at,
                COALESCE(NULLIF(u.display_name, ''), NULLIF(u.user_nicename, ''), NULLIF(u.user_login, ''), CONCAT('User #', m.user_id)) AS author_name
         FROM {$messages_table} m
         LEFT JOIN {$wpdb->users} u ON u.ID = m.user_id
         WHERE m.group_id = %d AND m.is_deleted = 0
         ORDER BY m.created_at DESC, m.id DESC
         LIMIT 50",
        $group_id
    ), ARRAY_A);

    $messages = array_reverse($messages ?: []);
    $messages = array_map(static function ($item) use ($wpdb, $profile_table) {
        $item['author_name'] = rich_group_messages_resolve_display_name(
            $wpdb,
            $profile_table,
            $item['author_name'] ?? '',
            $item['user_id'] ?? 0
        );
        return $item;
    }, $messages);
    echo json_encode(['success' => true, 'messages' => $messages]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $raw = file_get_contents('php://input');
    $payload = json_decode($raw, true);
    if (!is_array($payload)) {
        $payload = $_POST;
    }

    $group_id = isset($payload['group_id']) ? (int) $payload['group_id'] : 0;
    $message = isset($payload['message']) ? trim((string) $payload['message']) : '';

    if ($group_id <= 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid group']);
        exit;
    }

    if ($message === '') {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Message is required']);
        exit;
    }

    if (!rich_group_messages_is_member($wpdb, $memberships_table, $user_id, $group_id)) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Not a member of this group']);
        exit;
    }

    $group_exists = (bool) $wpdb->get_var($wpdb->prepare(
        "SELECT id FROM {$groups_table} WHERE id = %d LIMIT 1",
        $group_id
    ));
    if (!$group_exists) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Group not found']);
        exit;
    }

    $inserted = $wpdb->insert(
        $messages_table,
        [
            'group_id' => $group_id,
            'user_id' => $user_id,
            'message' => $message,
            'created_at' => current_time('mysql'),
        ],
        ['%d', '%d', '%s', '%s']
    );

    if (!$inserted) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Could not send message']);
        exit;
    }

    $message_id = (int) $wpdb->insert_id;
    $author = wp_get_current_user();
    $author_fallback_name = $author && $author->exists() ? ($author->display_name ?: $author->user_login) : ('User #' . $user_id);

    echo json_encode([
        'success' => true,
        'message' => 'Message sent.',
        'item' => [
            'id' => $message_id,
            'group_id' => $group_id,
            'user_id' => $user_id,
            'author_name' => rich_group_messages_resolve_display_name($wpdb, $profile_table, $author_fallback_name, $user_id),
            'message' => $message,
            'created_at' => current_time('mysql'),
        ]
    ]);
    exit;
}

http_response_code(405);
echo json_encode(['success' => false, 'message' => 'Method not allowed']);
