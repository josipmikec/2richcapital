<?php
define('WP_USE_THEMES', false);
require_once dirname(__DIR__, 3) . '/wp-load.php';
global $wpdb;

$symbols = $wpdb->prefix . 'rich_market_symbols';
$candles = $wpdb->prefix . 'rich_market_candles';

$row = $wpdb->get_row("SELECT * FROM {$symbols} WHERE enabled=1 AND (display_symbol='XAGUSD' OR mt5_symbol='XAGUSD') ORDER BY id ASC LIMIT 1", ARRAY_A);
if (!$row) die('Symbol not found');

$latest_candle_time = $wpdb->get_var($wpdb->prepare("SELECT candle_time_utc FROM {$candles} WHERE symbol_id=%d ORDER BY candle_time_utc DESC LIMIT 1", (int)$row['id']));
$broker_offset_seconds = 0;
if ($latest_candle_time) {
    $diff = strtotime($latest_candle_time . ' UTC') - time();
    $broker_offset_seconds = (int)round($diff / 3600) * 3600;
}

echo "Latest Candle: " . $latest_candle_time . "\n";
echo "Current Time: " . gmdate('Y-m-d H:i:s') . "\n";
echo "Diff seconds: " . $diff . "\n";
echo "Broker offset seconds: " . $broker_offset_seconds . "\n";
echo "Broker offset hours: " . ($broker_offset_seconds / 3600) . "\n";
