<?php
// Prevent output buffering
if (function_exists('apache_setenv')) {
    apache_setenv('no-gzip', '1');
}
ini_set('output_buffering', 'off');
ini_set('zlib.output_compression', false);
ini_set('implicit_flush', true);
ob_implicit_flush(true);

while (ob_get_level() > 0) {
    ob_end_flush();
}

require_once '../../auth/session-config.php';
define('WP_USE_THEMES', false);
require_once('../../../wp-load.php');

header('Content-Type: text/event-stream');
header('Cache-Control: no-cache, must-revalidate');
header('Connection: keep-alive');
header('X-Accel-Buffering: no'); // Essential for NGINX to not buffer SSE

session_write_close(); // Release lock AFTER wp-load to prevent plugins from holding it

if (!isset($_SESSION['user_id'])) {
    echo "event: error\ndata: Unauthorized\n\n";
    exit;
}

$user_id = (int) $_SESSION['user_id'];
$group_id = isset($_GET['group_id']) ? (int) $_GET['group_id'] : 0;
$last_message_id = isset($_GET['last_id']) ? (int) $_GET['last_id'] : 0;

global $wpdb;
$messages_table = $wpdb->prefix . 'rich_signal_messages';
$memberships_table = $wpdb->prefix . 'rich_signal_memberships';
$profile_table = $wpdb->prefix . 'rich_trader_profiles';

// Ensure user is member
$is_member = (bool) $wpdb->get_var($wpdb->prepare(
    "SELECT id FROM {$memberships_table} WHERE user_id = %d AND group_id = %d AND status = 'active' LIMIT 1",
    $user_id, $group_id
));

if (!$is_member) {
    echo "event: error\ndata: Not a member\n\n";
    exit;
}

$start_time = time();
$max_execution_time = 45; // Close after 45s to free PHP workers (JS EventSource auto-reconnects)

// Force immediate flush of headers
echo ": heartbeat\n\n";
flush();

while (true) {
    if (connection_aborted()) break;
    if (time() - $start_time > $max_execution_time) {
        // Break gracefully to allow auto-reconnect
        break; 
    }

    $new_messages = $wpdb->get_results($wpdb->prepare(
        "SELECT m.id, m.group_id, m.user_id, m.message, m.created_at, m.reply_to_id,
                p.message AS reply_to_message_text, p.user_id AS reply_to_user_id,
                COALESCE(NULLIF(pu.display_name, ''), NULLIF(pu.user_nicename, ''), NULLIF(pu.user_login, ''), CONCAT('User #', p.user_id)) AS reply_to_author_fallback,
                COALESCE(NULLIF(u.display_name, ''), NULLIF(u.user_nicename, ''), NULLIF(u.user_login, ''), CONCAT('User #', m.user_id)) AS author_name
         FROM {$messages_table} m
         LEFT JOIN {$wpdb->users} u ON u.ID = m.user_id
         LEFT JOIN {$messages_table} p ON p.id = m.reply_to_id
         LEFT JOIN {$wpdb->users} pu ON pu.ID = p.user_id
         WHERE m.group_id = %d AND m.is_deleted = 0 AND m.id > %d
         ORDER BY m.id ASC",
        $group_id, $last_message_id
    ), ARRAY_A);

    if (!empty($new_messages)) {
        foreach ($new_messages as $item) {
            $profile_name = $wpdb->get_var($wpdb->prepare("SELECT display_name FROM {$profile_table} WHERE user_id = %d LIMIT 1", (int) $item['user_id']));
            $item['author_name'] = is_string($profile_name) && trim($profile_name) !== '' ? $profile_name : ($item['author_name'] ?? ('User #' . (int) $item['user_id']));
            
            if ($item['reply_to_id']) {
                $reply_profile_name = $wpdb->get_var($wpdb->prepare("SELECT display_name FROM {$profile_table} WHERE user_id = %d LIMIT 1", (int) $item['reply_to_user_id']));
                $item['reply_to_author_name'] = is_string($reply_profile_name) && trim($reply_profile_name) !== '' ? $reply_profile_name : ($item['reply_to_author_fallback'] ?? ('User #' . (int) $item['reply_to_user_id']));
                unset($item['reply_to_author_fallback']);
            }
            
            echo "event: message\ndata: " . wp_json_encode($item) . "\n\n";
            $last_message_id = max($last_message_id, (int)$item['id']);
        }
    } else {
        // No messages, just heartbeat
        echo ": heartbeat\n\n";
    }

    if (ob_get_length()) ob_flush();
    flush();

    session_write_close(); // Free session lock so user can navigate elsewhere concurrently
    sleep(2);
}
