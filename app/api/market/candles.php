<?php
define('WP_USE_THEMES', false);
require_once dirname(__DIR__, 3) . '/wp-load.php';
global $wpdb;
header('Content-Type: application/json; charset=utf-8');

$allowed = ['H8','D1','W1','MN1'];
$symbol = sanitize_text_field(wp_unslash($_GET['symbol'] ?? ''));
$timeframe = strtoupper(sanitize_text_field(wp_unslash($_GET['timeframe'] ?? 'D1')));
$limit = min(10000, max(1, absint($_GET['limit'] ?? 2000)));
$from = sanitize_text_field(wp_unslash($_GET['from'] ?? ''));
$to = sanitize_text_field(wp_unslash($_GET['to'] ?? ''));
$from_sql = '';
$to_sql = '';
if ($from !== '') {
    $ts = is_numeric($from) ? (int)$from : strtotime($from);
    if ($ts) $from_sql = gmdate('Y-m-d H:i:s', $ts > 2000000000 ? (int) floor($ts / 1000) : $ts);
}
if ($to !== '') {
    $ts = is_numeric($to) ? (int)$to : strtotime($to);
    if ($ts) $to_sql = gmdate('Y-m-d H:i:s', $ts > 2000000000 ? (int) floor($ts / 1000) : $ts);
}
if ($symbol === '' || !in_array($timeframe, $allowed, true)) {
    wp_send_json(['ok'=>false,'message'=>'Valid symbol and timeframe are required.'], 400);
}
$symbols = $wpdb->prefix . 'rich_market_symbols';
$candles = $wpdb->prefix . 'rich_market_candles';
$sync = $wpdb->prefix . 'rich_market_sync_state';
$row = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$symbols} WHERE enabled=1 AND (display_symbol=%s OR mt5_symbol=%s) ORDER BY id ASC LIMIT 1", $symbol, $symbol), ARRAY_A);
if (!$row) wp_send_json(['ok'=>false,'message'=>'Symbol not available.'], 404);

$where = "WHERE symbol_id=%d AND timeframe=%s";
$params = [(int)$row['id'], $timeframe];
if ($from_sql !== '') {
    $where .= " AND candle_time_utc >= %s";
    $params[] = $from_sql;
}
if ($to_sql !== '') {
    $where .= " AND candle_time_utc < %s";
    $params[] = $to_sql;
}

$closed_params = $params;
$closed_params[] = max(1, $limit);
$closed_sql = "SELECT candle_time_utc, open_price, high_price, low_price, close_price, tick_volume, real_volume, is_closed FROM {$candles} {$where} AND is_closed=1 ORDER BY candle_time_utc DESC LIMIT %d";
$closed_rows = $wpdb->get_results($wpdb->prepare($closed_sql, ...$closed_params), ARRAY_A);
$closed_rows = array_reverse($closed_rows ?: []);

$open_sql = "SELECT candle_time_utc, open_price, high_price, low_price, close_price, tick_volume, real_volume, is_closed FROM {$candles} {$where} AND is_closed=0 ORDER BY candle_time_utc DESC LIMIT 1";
$open_row = $wpdb->get_row($wpdb->prepare($open_sql, ...$params), ARRAY_A);

$rows = $closed_rows;
if ($open_row) {
    $last_index = count($rows) - 1;
    if ($last_index >= 0 && ($rows[$last_index]['candle_time_utc'] ?? '') === ($open_row['candle_time_utc'] ?? '')) {
        $rows[$last_index] = $open_row;
    } else {
        $rows[] = $open_row;
    }
}

$state = $wpdb->get_row($wpdb->prepare("SELECT last_success_at, last_error_message, consecutive_failures FROM {$sync} WHERE symbol_id=%d AND timeframe=%s LIMIT 1", (int)$row['id'], $timeframe), ARRAY_A);
$last = $state['last_success_at'] ?? null;
$status = (!$last || strtotime($last) < time()-7200) ? 'stale' : ((int)($state['consecutive_failures'] ?? 0) > 0 ? 'degraded' : 'healthy');

wp_send_json([
    'ok'=>true,
    'symbol'=>$row['display_symbol'],
    'mt5_symbol'=>$row['mt5_symbol'],
    'timeframe'=>$timeframe,
    'source'=>'mt5',
    'timezone'=>'UTC',
    'candles'=>array_map(static function($c){
        return [
            'time'=>gmdate('c',strtotime($c['candle_time_utc'])),
            'open'=>(float)$c['open_price'],
            'high'=>(float)$c['high_price'],
            'low'=>(float)$c['low_price'],
            'close'=>(float)$c['close_price'],
            'volume'=>(int)($c['real_volume'] ?: $c['tick_volume']),
            'closed'=>!empty($c['is_closed'])
        ];
    }, $rows),
    'last_sync_at'=>$last,
    'status'=>$status,
    'last_error'=>$state['last_error_message'] ?? ''
]);
