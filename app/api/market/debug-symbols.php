<?php
define('WP_USE_THEMES', false);
require_once dirname(__DIR__, 3) . '/wp-load.php';
if(!current_user_can('manage_options')) wp_die('Forbidden', 403);
global $wpdb;
header('Content-Type: application/json; charset=utf-8');
$symbols = $wpdb->prefix . 'rich_market_symbols';
$candles = $wpdb->prefix . 'rich_market_candles';
$sync = $wpdb->prefix . 'rich_market_sync_state';
$out = [
  'symbols_table_exists' => ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $symbols)) === $symbols),
  'candles_table_exists' => ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $candles)) === $candles),
  'sync_table_exists' => ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $sync)) === $sync),
  'enabled_symbols' => (int)$wpdb->get_var("SELECT COUNT(*) FROM {$symbols} WHERE enabled = 1"),
  'all_symbols' => (int)$wpdb->get_var("SELECT COUNT(*) FROM {$symbols}"),
  'candles_total' => (int)$wpdb->get_var("SELECT COUNT(*) FROM {$candles}"),
  'latest_symbols' => $wpdb->get_results("SELECT id, broker_account_id, broker_name, broker_server, mt5_symbol, display_symbol, enabled, updated_at FROM {$symbols} ORDER BY updated_at DESC, id DESC LIMIT 20", ARRAY_A),
  'latest_sync_rows' => $wpdb->get_results("SELECT * FROM {$sync} ORDER BY id DESC LIMIT 20", ARRAY_A),
  'last_db_error' => $wpdb->last_error,
];
echo wp_json_encode(['ok' => true, 'debug' => $out], JSON_PRETTY_PRINT);
