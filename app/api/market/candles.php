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

$where = "WHERE symbol_id=%d AND timeframe='M15'";
$params = [(int)$row['id']];
if ($from_sql !== '') {
    $where .= " AND candle_time_utc >= %s";
    $params[] = $from_sql;
}
if ($to_sql !== '') {
    $where .= " AND candle_time_utc < %s";
    $params[] = $to_sql;
}

// Calculate how many M15 candles we need to fulfill the limit
$multiplier = 1;
if ($timeframe === 'H8') $multiplier = 32;
if ($timeframe === 'D1') $multiplier = 96;
if ($timeframe === 'W1') $multiplier = 96 * 5; // Forex week is ~5 days
if ($timeframe === 'MN1') $multiplier = 96 * 22;

$m15_limit = min(100000, $limit * $multiplier);

$sql = "SELECT candle_time_utc, open_price, high_price, low_price, close_price, tick_volume, real_volume, is_closed FROM {$candles} {$where} ORDER BY candle_time_utc DESC LIMIT %d";
$params[] = $m15_limit;
$raw_rows = $wpdb->get_results($wpdb->prepare($sql, ...$params), ARRAY_A);

$aggregated = [];
$current_bucket = null;
$bucket_time = null;

foreach ($raw_rows ?: [] as $c) {
    $ts = strtotime($c['candle_time_utc']);
    
    if ($timeframe === 'D1') {
        $b_ts = strtotime(gmdate('Y-m-d 00:00:00', $ts));
    } elseif ($timeframe === 'H8') {
        $h = (int)gmdate('H', $ts);
        $bucket_h = floor($h / 8) * 8;
        $b_ts = strtotime(gmdate("Y-m-d " . sprintf('%02d', $bucket_h) . ":00:00", $ts));
    } elseif ($timeframe === 'W1') {
        $w = (int)gmdate('w', $ts);
        $diff = $w == 0 ? 6 : $w - 1;
        $b_ts = strtotime(gmdate('Y-m-d 00:00:00', $ts)) - ($diff * 86400);
    } elseif ($timeframe === 'MN1') {
        $b_ts = strtotime(gmdate('Y-m-01 00:00:00', $ts));
    } else {
        $b_ts = $ts; // fallback
    }

    if ($bucket_time !== $b_ts) {
        if ($current_bucket) $aggregated[] = $current_bucket;
        $current_bucket = [
            'candle_time_utc' => gmdate('Y-m-d H:i:s', $b_ts),
            'open_price' => $c['open_price'],
            'high_price' => $c['high_price'],
            'low_price' => $c['low_price'],
            'close_price' => $c['close_price'],
            'tick_volume' => $c['tick_volume'],
            'real_volume' => $c['real_volume'],
            'is_closed' => $c['is_closed'],
        ];
        $bucket_time = $b_ts;
    } else {
        $current_bucket['open_price'] = $c['open_price'];
        $current_bucket['high_price'] = max($current_bucket['high_price'], $c['high_price']);
        $current_bucket['low_price'] = min($current_bucket['low_price'], $c['low_price']);
        $current_bucket['tick_volume'] += $c['tick_volume'];
        $current_bucket['real_volume'] += $c['real_volume'];
        // if any candle in the bucket is open, the bucket is open
        if ($c['is_closed'] == 0) $current_bucket['is_closed'] = 0; 
    }
}
if ($current_bucket) $aggregated[] = $current_bucket;

// Limit to requested amount
$aggregated = array_slice($aggregated, 0, $limit);
// Reverse to ASC for the frontend
$rows = array_reverse($aggregated);

$state = $wpdb->get_row($wpdb->prepare("SELECT last_success_at, last_error_message, consecutive_failures FROM {$sync} WHERE symbol_id=%d AND timeframe='M15' LIMIT 1", (int)$row['id']), ARRAY_A);
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
