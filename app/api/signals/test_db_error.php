<?php
ob_start();
require_once '../../auth/session-config.php';
define('WP_USE_THEMES', false);
require_once('../../../wp-load.php');
ob_end_clean();

global $wpdb;
$wpdb->show_errors();

$messages_table = $wpdb->prefix . 'rich_signal_group_messages';

$ensure_table = "CREATE TABLE IF NOT EXISTS {$messages_table} (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    group_id BIGINT UNSIGNED NOT NULL,
    user_id BIGINT UNSIGNED NOT NULL,
    message TEXT NOT NULL,
    reply_to_id BIGINT UNSIGNED NULL DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL DEFAULT NULL,
    is_deleted TINYINT(1) NOT NULL DEFAULT 0,
    PRIMARY KEY (id),
    KEY group_created (group_id, created_at),
    KEY user_created (user_id, created_at),
    KEY reply_to (reply_to_id)
) {$wpdb->get_charset_collate()};";

require_once ABSPATH . 'wp-admin/includes/upgrade.php';
dbDelta($ensure_table);

echo "dbDelta completed.\n";

$messages = $wpdb->get_results("SELECT m.reply_to_id FROM {$messages_table} m LIMIT 1", ARRAY_A);
if ($wpdb->last_error) {
    echo "Error: " . $wpdb->last_error;
} else {
    echo "Column exists. Result: " . json_encode($messages);
}
