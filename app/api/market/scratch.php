<?php
define('WP_USE_THEMES', false);
require_once dirname(__DIR__, 3) . '/wp-load.php';
global $wpdb;
$res = $wpdb->get_results("SELECT id, mt5_symbol, display_symbol, broker_account_id FROM wp_rich_market_symbols WHERE mt5_symbol = 'XAGUSD' OR display_symbol = 'XAGUSD'", ARRAY_A);
print_r($res);
$res2 = $wpdb->get_results("SELECT timeframe, COUNT(*) as c FROM wp_rich_market_candles WHERE symbol_id IN (SELECT id FROM wp_rich_market_symbols WHERE mt5_symbol = 'XAGUSD') GROUP BY timeframe", ARRAY_A);
print_r($res2);
