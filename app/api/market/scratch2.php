<?php
define('WP_USE_THEMES', false);
require_once dirname(__DIR__, 3) . '/wp-load.php';
global $wpdb;
$res = $wpdb->get_results("SELECT candle_time_utc, open_price, close_price, is_closed FROM wp_rich_market_candles WHERE symbol_id IN (SELECT id FROM wp_rich_market_symbols WHERE mt5_symbol = 'XAGUSD') AND timeframe = 'M15' ORDER BY candle_time_utc DESC LIMIT 20", ARRAY_A);
wp_send_json($res);
