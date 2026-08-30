<?php
/**
 * One-time migration for market-data personalization and feature rollout.
 * Run while authenticated as an administrator, then remove or restrict this file.
 */
require_once '../../auth/session-config.php';
require_once '../../auth/feature-flags.php';
if (!isset($_SESSION['user_id']) || !rich_is_staff($_SESSION['user_id'])) { http_response_code(403); exit('Forbidden'); }
define('WP_USE_THEMES', false);
require_once dirname(__DIR__, 3) . '/wp-load.php';
global $wpdb;
$charset = $wpdb->get_charset_collate();
$preferences = $wpdb->prefix . 'rich_user_preferences';
$wpdb->query("CREATE TABLE IF NOT EXISTS {$preferences} (id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, user_id BIGINT UNSIGNED NOT NULL, pref_key VARCHAR(100) NOT NULL, pref_value LONGTEXT NULL, updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, PRIMARY KEY (id), UNIQUE KEY user_pref (user_id,pref_key), KEY user_idx (user_id)) {$charset}");
$watchlist = $wpdb->prefix . 'rich_market_watchlists';
$wpdb->query("CREATE TABLE IF NOT EXISTS {$watchlist} (id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, user_id BIGINT UNSIGNED NOT NULL, symbol VARCHAR(80) NOT NULL, sort_order INT NOT NULL DEFAULT 0, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, PRIMARY KEY (id), UNIQUE KEY user_symbol (user_id,symbol), KEY user_order (user_id,sort_order)) {$charset}");
echo 'Migration complete';
