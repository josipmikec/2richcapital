<?php
define('WP_USE_THEMES', false);
require_once dirname(__DIR__, 3) . '/wp-load.php';
if (!current_user_can('manage_options')) wp_die('Forbidden', 403);
global $wpdb;
header('Content-Type: application/json; charset=utf-8');
$symbols = $wpdb->prefix . 'rich_market_symbols';
$candles = $wpdb->prefix . 'rich_market_candles';
$columns = $wpdb->get_col("SHOW COLUMNS FROM {$symbols}", 0);
$add = [];
if (!in_array('broker_name', $columns, true)) {
    $add[] = $wpdb->query("ALTER TABLE {$symbols} ADD COLUMN broker_name VARCHAR(120) NULL AFTER broker_account_id");
}
if (!in_array('broker_server', $columns, true)) {
    $add[] = $wpdb->query("ALTER TABLE {$symbols} ADD COLUMN broker_server VARCHAR(190) NULL AFTER broker_name");
}
$candleColumns = $wpdb->get_col("SHOW COLUMNS FROM {$candles}", 0);
if (!in_array('broker_name', $candleColumns, true)) {
    $add[] = $wpdb->query("ALTER TABLE {$candles} ADD COLUMN broker_name VARCHAR(120) NULL AFTER source");
}
if (!in_array('broker_server', $candleColumns, true)) {
    $add[] = $wpdb->query("ALTER TABLE {$candles} ADD COLUMN broker_server VARCHAR(190) NULL AFTER broker_name");
}
$wpdb->flush();
echo wp_json_encode([
    'ok' => empty($wpdb->last_error),
    'message' => empty($wpdb->last_error) ? 'Market broker columns installed' : 'Market broker column installation failed',
    'symbols_table' => $symbols,
    'candles_table' => $candles,
    'symbols_columns_added' => $add,
    'db_error' => $wpdb->last_error,
]);
