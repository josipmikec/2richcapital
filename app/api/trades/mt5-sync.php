<?php
define('WP_USE_THEMES', false);
require_once dirname(__DIR__, 3) . '/wp-load.php';

global $wpdb;

header('Content-Type: application/json');

function mt5_send($data, $code = 200) {
    status_header($code);
    echo wp_json_encode($data);
    exit;
}

function mt5_get_header($name) {
    $key = 'HTTP_' . strtoupper(str_replace('-', '_', $name));
    return $_SERVER[$key] ?? '';
}

function mt5_clean_text($value, $max = 255) {
    return substr(sanitize_text_field((string)$value), 0, $max);
}

function mt5_num($value, $default = 0) {
    return is_numeric($value) ? (float)$value : $default;
}

function mt5_int($value, $default = 0) {
    return is_numeric($value) ? (int)$value : $default;
}

function mt5_datetime($timestamp) {
    $ts = mt5_int($timestamp);
    return $ts > 0 ? gmdate('Y-m-d H:i:s', $ts) : current_time('mysql');
}

function mt5_get_user_journal_id($user_id, $wpdb) {
    $journals_table = $wpdb->prefix . 'rich_journals';
    $conn_table = $wpdb->prefix . 'rich_mt5_connections';

    $selected_journal_id = (int)$wpdb->get_var($wpdb->prepare(
        "SELECT journal_id
         FROM {$conn_table}
         WHERE user_id = %d
           AND is_active = 1
           AND journal_id IS NOT NULL
           AND journal_id > 0
         ORDER BY updated_at DESC, id DESC
         LIMIT 1",
        $user_id
    ));

    if ($selected_journal_id > 0) {
        $owns_selected = (int)$wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*)
             FROM {$journals_table}
             WHERE id = %d AND user_id = %d",
            $selected_journal_id,
            $user_id
        ));

        if ($owns_selected > 0) {
            return $selected_journal_id;
        }
    }

    $journal_id = (int)$wpdb->get_var($wpdb->prepare(
        "SELECT id
         FROM {$journals_table}
         WHERE user_id = %d
         ORDER BY updated_at DESC, created_at DESC, id DESC
         LIMIT 1",
        $user_id
    ));

    if ($journal_id > 0) {
        return $journal_id;
    }

    $wpdb->insert($journals_table, [
        'user_id'    => $user_id,
        'name'       => 'MT5 Journal',
        'created_at' => current_time('mysql'),
        'updated_at' => current_time('mysql')
    ]);

    return (int)$wpdb->insert_id;

}

function mt5_market_install_tables($wpdb) {
    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    $charset = $wpdb->get_charset_collate();
    $symbols = $wpdb->prefix . 'rich_market_symbols';
    $candles = $wpdb->prefix . 'rich_market_candles';
    $sync = $wpdb->prefix . 'rich_market_sync_state';
    dbDelta("CREATE TABLE {$symbols} (id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, provider VARCHAR(20) NOT NULL DEFAULT 'mt5', broker_account_id BIGINT UNSIGNED NULL, broker_name VARCHAR(120) NULL, broker_server VARCHAR(190) NULL, mt5_symbol VARCHAR(80) NOT NULL, display_symbol VARCHAR(40) NOT NULL, asset_class VARCHAR(20) NULL, digits TINYINT NULL, point_size DECIMAL(24,12) NULL, timezone VARCHAR(64) NULL, enabled TINYINT(1) NOT NULL DEFAULT 1, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, PRIMARY KEY(id), UNIQUE KEY broker_symbol(provider,broker_account_id,mt5_symbol), KEY display_symbol(display_symbol)) {$charset}");
    dbDelta("CREATE TABLE {$candles} (id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, symbol_id BIGINT UNSIGNED NOT NULL, timeframe VARCHAR(8) NOT NULL, candle_time_utc DATETIME NOT NULL, open_price DECIMAL(30,12) NOT NULL, high_price DECIMAL(30,12) NOT NULL, low_price DECIMAL(30,12) NOT NULL, close_price DECIMAL(30,12) NOT NULL, tick_volume BIGINT UNSIGNED NULL, real_volume BIGINT UNSIGNED NULL, spread_points INT NULL, is_closed TINYINT(1) NOT NULL DEFAULT 1, source VARCHAR(20) NOT NULL DEFAULT 'mt5', broker_name VARCHAR(120) NULL, broker_server VARCHAR(190) NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, PRIMARY KEY(id), UNIQUE KEY candle_unique(symbol_id,timeframe,candle_time_utc,source), KEY lookup(symbol_id,timeframe,candle_time_utc)) {$charset}");
    dbDelta("CREATE TABLE {$sync} (id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, symbol_id BIGINT UNSIGNED NOT NULL, timeframe VARCHAR(8) NOT NULL, last_closed_candle_utc DATETIME NULL, last_attempt_at DATETIME NULL, last_success_at DATETIME NULL, last_error_code VARCHAR(64) NULL, last_error_message TEXT NULL, consecutive_failures INT NOT NULL DEFAULT 0, rows_synced BIGINT UNSIGNED NOT NULL DEFAULT 0, locked_until DATETIME NULL, PRIMARY KEY(id), UNIQUE KEY sync_unique(symbol_id,timeframe)) {$charset}");
}

function mt5_market_sync_candles($payload, $connection, $wpdb) {
    $bars = isset($payload['candles']) && is_array($payload['candles']) ? $payload['candles'] : [];
    if (!$bars) {
        return ['received' => 0, 'saved' => 0];
    }
    mt5_market_install_tables($wpdb);
    $symbols = $wpdb->prefix . 'rich_market_symbols';
    $candles = $wpdb->prefix . 'rich_market_candles';
    $sync = $wpdb->prefix . 'rich_market_sync_state';
    $allowed = ['H8', 'D1', 'W1', 'MN1'];
    $received = 0;
    $saved = 0;
    $now = current_time('mysql', true);
    foreach ($bars as $bar) {
        $received++;
        $mt5_symbol = mt5_clean_text($bar['symbol'] ?? '', 80);
        $timeframe = strtoupper(mt5_clean_text($bar['timeframe'] ?? '', 8));
        $timestamp = isset($bar['time']) && is_numeric($bar['time']) ? gmdate('Y-m-d H:i:s', (int) $bar['time']) : gmdate('Y-m-d H:i:s', strtotime((string) ($bar['time'] ?? '')));
        if ($mt5_symbol === '' || !in_array($timeframe, $allowed, true) || $timestamp === '1970-01-01 00:00:00') continue;
        if (!is_numeric($bar['open'] ?? null) || !is_numeric($bar['high'] ?? null) || !is_numeric($bar['low'] ?? null) || !is_numeric($bar['close'] ?? null)) continue;
        $display_symbol = preg_replace('/[^A-Za-z0-9._-]/', '', (string) ($bar['display_symbol'] ?? $mt5_symbol));
        $asset_class = mt5_clean_text($bar['asset_class'] ?? '', 20);
        $digits = (int) ($bar['digits'] ?? 0);
        $point_size = (float) ($bar['point_size'] ?? 0);
        $broker_name = mt5_clean_text($bar['broker_name'] ?? (($payload['account']['broker_name'] ?? '')), 120);
        $broker_server = mt5_clean_text($bar['broker_server'] ?? (($payload['account']['server'] ?? '')), 190);
        $wpdb->query($wpdb->prepare("INSERT INTO {$symbols} (provider, broker_account_id, broker_name, broker_server, mt5_symbol, display_symbol, asset_class, digits, point_size, timezone, enabled, created_at, updated_at) VALUES ('mt5', %d, %s, %s, %s, %s, %s, %d, %f, 'UTC', 1, %s, %s) ON DUPLICATE KEY UPDATE broker_name = VALUES(broker_name), broker_server = VALUES(broker_server), display_symbol = VALUES(display_symbol), asset_class = VALUES(asset_class), digits = VALUES(digits), point_size = VALUES(point_size), updated_at = VALUES(updated_at)", (int) $connection['id'], $broker_name, $broker_server, $mt5_symbol, $display_symbol, $asset_class, $digits, $point_size, $now, $now));
        $symbol_id = (int) $wpdb->get_var($wpdb->prepare("SELECT id FROM {$symbols} WHERE mt5_symbol = %s AND broker_account_id = %d LIMIT 1", $mt5_symbol, (int) $connection['id']));
        if ($symbol_id <= 0) continue;
        $wpdb->query($wpdb->prepare("INSERT INTO {$candles} (symbol_id, timeframe, candle_time_utc, open_price, high_price, low_price, close_price, tick_volume, real_volume, spread_points, is_closed, source, broker_name, broker_server, created_at, updated_at) VALUES (%d, %s, %s, %f, %f, %f, %f, %d, %d, %d, 1, 'mt5', %s, %s, %s, %s) ON DUPLICATE KEY UPDATE open_price = VALUES(open_price), high_price = VALUES(high_price), low_price = VALUES(low_price), close_price = VALUES(close_price), tick_volume = VALUES(tick_volume), real_volume = VALUES(real_volume), spread_points = VALUES(spread_points), broker_name = VALUES(broker_name), broker_server = VALUES(broker_server), updated_at = VALUES(updated_at)", $symbol_id, $timeframe, $timestamp, (float) $bar['open'], (float) $bar['high'], (float) $bar['low'], (float) $bar['close'], (int) ($bar['tick_volume'] ?? 0), (int) ($bar['real_volume'] ?? 0), (int) ($bar['spread_points'] ?? 0), $broker_name, $broker_server, $now, $now));
        $saved++;
        $wpdb->query($wpdb->prepare("INSERT INTO {$sync} (symbol_id, timeframe, last_closed_candle_utc, last_attempt_at, last_success_at, last_error_code, last_error_message, consecutive_failures, rows_synced, locked_until) VALUES (%d, %s, %s, %s, %s, '', '', 0, %d, NULL) ON DUPLICATE KEY UPDATE last_closed_candle_utc = VALUES(last_closed_candle_utc), last_attempt_at = VALUES(last_attempt_at), last_success_at = VALUES(last_success_at), last_error_code = '', last_error_message = '', consecutive_failures = 0, rows_synced = rows_synced + VALUES(rows_synced), locked_until = NULL", $symbol_id, $timeframe, $timestamp, $now, $now, 1));
    }
    return ['received' => $received, 'saved' => $saved];
}

$raw = file_get_contents('php://input');
$payload = [];

if (is_string($raw) && trim($raw) !== '') {
    $decoded = json_decode($raw, true);
    if (is_array($decoded)) {
        $payload = $decoded;
    }
}

if (!$payload && !empty($_POST) && is_array($_POST)) {
    $payload = $_POST;
}

if (!$payload && !empty($_GET) && is_array($_GET)) {
    $payload = $_GET;
}

if (!$payload) {
    mt5_send(['ok' => false, 'message' => 'Empty request body'], 400);
}

$api_key = trim(mt5_get_header('X-MT5-Key'));
$token   = trim(mt5_get_header('X-MT5-Token'));

if ($api_key === '') {
    mt5_send(['ok' => false, 'message' => 'Missing X-MT5-Key header'], 401);
}

if ($token === '') {
    mt5_send(['ok' => false, 'message' => 'Missing X-MT5-Token header'], 401);
}

$conn_table   = $wpdb->prefix . 'rich_mt5_connections';
$live_table   = $wpdb->prefix . 'rich_live_trades';
$trades_table = $wpdb->prefix . 'rich_trades';

$connections = $wpdb->get_results(
    "SELECT * FROM {$conn_table} WHERE is_active = 1 ORDER BY id DESC",
    ARRAY_A
);

if (!$connections) {
    mt5_send(['ok' => false, 'message' => 'No active MT5 connections found'], 404);
}

$connection = null;
foreach ($connections as $row) {
    $api_ok = !empty($row['api_key_hash']) && wp_check_password($api_key, $row['api_key_hash']);
    $token_ok = !empty($row['connection_token']) && hash_equals((string)$row['connection_token'], $token);

    if ($api_ok && $token_ok) {
        $connection = $row;
        break;
    }
}

if (!$connection) {
    mt5_send(['ok' => false, 'message' => 'Invalid API key or connection token'], 403);
}

$user_id = (int)$connection['user_id'];
$journal_id = !empty($connection['journal_id']) ? (int)$connection['journal_id'] : mt5_get_user_journal_id($user_id, $wpdb);
if ($journal_id <= 0) {
    $journal_id = mt5_get_user_journal_id($user_id, $wpdb);
}

$account = is_array($payload['account'] ?? null) ? $payload['account'] : [];
$live_trades = isset($payload['live_trades']) && is_array($payload['live_trades']) ? $payload['live_trades'] : [];
$closed_trades = isset($payload['closed_trades']) && is_array($payload['closed_trades']) ? $payload['closed_trades'] : [];
$market_sync_result = ['received' => 0, 'saved' => 0];

$account_login = mt5_int($account['login'] ?? 0);
$account_server = mt5_clean_text($account['server'] ?? '', 120);

if ($account_login <= 0 && isset($payload['login'])) {
    $account_login = mt5_int($payload['login']);
}

if ($account_server === '' && isset($payload['server'])) {
    $account_server = mt5_clean_text($payload['server'], 120);
}

if ($account_login <= 0) {
    $fallback_login = mt5_int($connection['mt5_login'] ?? 0);
    if ($fallback_login > 0) {
        $account_login = $fallback_login;
    }
}

if ($account_server === '' && !empty($connection['server_name'])) {
    $account_server = mt5_clean_text($connection['server_name'], 120);
}

if (!empty($connection['mt5_login']) && $account_login > 0 && (int)$connection['mt5_login'] !== $account_login) {
    $wpdb->update($conn_table, [
        'last_error' => 'MT5 login mismatch',
        'updated_at' => current_time('mysql')
    ], ['id' => (int)$connection['id']]);

    mt5_send(['ok' => false, 'message' => 'MT5 login does not match connected account'], 403);
}

if (!empty($connection['server_name']) && $account_server !== '' && strtolower((string)$connection['server_name']) !== strtolower($account_server)) {
    $wpdb->update($conn_table, [
        'last_error' => 'MT5 server mismatch',
        'updated_at' => current_time('mysql')
    ], ['id' => (int)$connection['id']]);

    mt5_send(['ok' => false, 'message' => 'MT5 server does not match connected account'], 403);
}

$wpdb->update($conn_table, [
    'journal_id'       => $journal_id,
    'mt5_login'        => $account_login > 0 ? $account_login : (int)$connection['mt5_login'],
    'last_balance'     => mt5_num($account['balance'] ?? 0),
    'last_equity'      => mt5_num($account['equity'] ?? 0),
    'account_currency' => mt5_clean_text($account['currency'] ?? 'USD', 20),
    'server_name'      => $account_server,
    'updated_at'       => current_time('mysql')
], ['id' => (int)$connection['id']]);

if (!empty($wpdb->last_error)) {
    mt5_send([
        'ok' => false,
        'message' => 'Failed to update connection row',
        'db_error' => $wpdb->last_error
    ], 500);
}

$inserted_closed = 0;
$updated_closed = 0;
$inserted_live = 0;
$updated_live = 0;
$write_errors = [];
$skipped_live = [];
$skipped_closed = [];

if ($account_login > 0) {
    $wpdb->delete($live_table, ['mt5_login' => $account_login], ['%d']);

    if (!empty($wpdb->last_error)) {
        mt5_send([
            'ok' => false,
            'message' => 'Failed to clear live trades',
            'db_error' => $wpdb->last_error
        ], 500);
    }

    foreach ($live_trades as $index => $trade) {
        $ticket = mt5_int($trade['position_id'] ?? $trade['ticket'] ?? 0);
        $symbol = mt5_clean_text($trade['symbol'] ?? '', 20);
        $direction = strtoupper(mt5_clean_text($trade['direction'] ?? '', 10));

        if ($ticket <= 0 || $symbol === '') {
            $skipped_live[] = [
                'index' => $index,
                'reason' => 'missing ticket or symbol',
                'position_id' => $trade['position_id'] ?? null,
                'ticket' => $trade['ticket'] ?? null,
                'symbol' => $trade['symbol'] ?? null
            ];
            continue;
        }

        $ok = $wpdb->insert($live_table, [
            'mt5_login'     => $account_login,
            'ticket'        => $ticket,
            'symbol'        => $symbol,
            'direction'     => $direction === 'SELL' ? 'SELL' : 'BUY',
            'volume'        => mt5_num($trade['volume'] ?? 0),
            'open_price'    => mt5_num($trade['open_price'] ?? $trade['price_open'] ?? 0),
            'current_price' => mt5_num($trade['current_price'] ?? 0),
            'sl'            => mt5_num($trade['sl'] ?? 0),
            'tp'            => mt5_num($trade['tp'] ?? 0),
            'profit'        => mt5_num($trade['profit'] ?? 0),
            'swap'          => mt5_num($trade['swap'] ?? 0),
            'open_time'     => mt5_datetime($trade['open_time'] ?? 0),
            'synced_at'     => current_time('mysql'),
        ]);

        if ($ok) {
            $inserted_live++;
        } else {
            $write_errors[] = [
                'scope' => 'live_table_insert',
                'index' => $index,
                'ticket' => $ticket,
                'symbol' => $symbol,
                'db_error' => $wpdb->last_error
            ];
        }
    }
}

foreach ($closed_trades as $index => $trade) {
    $deal_ticket = mt5_int($trade['ticket'] ?? 0);
    $position_id = mt5_int($trade['position_id'] ?? 0);
    $ticket = $position_id > 0 ? $position_id : $deal_ticket;

    $symbol = mt5_clean_text($trade['symbol'] ?? '', 20);
    $direction = strtoupper(mt5_clean_text($trade['direction'] ?? '', 10));
    $outcome = strtoupper(mt5_clean_text($trade['outcome'] ?? '', 20));
    $comment = mt5_clean_text($trade['comment'] ?? '', 255);

    if ($ticket <= 0 || $symbol === '') {
        $skipped_closed[] = [
            'index' => $index,
            'reason' => 'missing ticket or symbol',
            'position_id' => $trade['position_id'] ?? null,
            'ticket' => $trade['ticket'] ?? null,
            'symbol' => $trade['symbol'] ?? null
        ];
        continue;
    }

    $entry_datetime = mt5_datetime($trade['open_time'] ?? 0);
    $exit_datetime  = mt5_datetime($trade['close_time'] ?? 0);

    $entry_price = mt5_num($trade['open_price'] ?? $trade['price_open'] ?? 0);
    $exit_price  = mt5_num($trade['close_price'] ?? $trade['price_close'] ?? 0);

    $profit     = mt5_num($trade['profit'] ?? 0);
    $swap       = mt5_num($trade['swap'] ?? 0);
    $commission = mt5_num($trade['commission'] ?? 0);
    $net_profit = $profit + $swap + $commission;

    $profit_loss_pct = 0;
    if ($entry_price > 0 && $exit_price > 0) {
        if ($direction === 'SELL') {
            $profit_loss_pct = (($entry_price - $exit_price) / $entry_price) * 100;
        } else {
            $profit_loss_pct = (($exit_price - $entry_price) / $entry_price) * 100;
        }
    }

    $stored_outcome = in_array($outcome, ['WIN', 'LOSS', 'BREAKEVEN', 'OPEN'], true)
        ? $outcome
        : ($net_profit > 0 ? 'WIN' : ($net_profit < 0 ? 'LOSS' : 'BREAKEVEN'));

    $existing_trade_id = $wpdb->get_var($wpdb->prepare(
        "SELECT id
         FROM {$trades_table}
         WHERE user_id = %d
           AND journal_id = %d
           AND mt5_ticket = %d
         LIMIT 1",
        $user_id,
        $journal_id,
        $ticket
    ));

    if (!empty($wpdb->last_error)) {
        $write_errors[] = [
            'scope' => 'closed_lookup',
            'index' => $index,
            'ticket' => $ticket,
            'db_error' => $wpdb->last_error
        ];
        continue;
    }

    $row = [
        'user_id'         => $user_id,
        'journal_id'      => $journal_id,
        'entry_date'      => substr($entry_datetime, 0, 10),
        'exit_date'       => substr($exit_datetime, 0, 10),
        'session'         => 'NY',
        'strategy_type'   => 'MT5 SYNC',
        'symbol'          => $symbol,
        'direction'       => $direction === 'SELL' ? 'SHORT' : 'LONG',
        'entry_price'     => $entry_price,
        'exit_price'      => $exit_price > 0 ? $exit_price : null,
        'profit_loss'     => round($net_profit, 2),
        'profit_loss_pct' => round($profit_loss_pct, 2),
        'outcome'         => $stored_outcome,
        'status'          => 'closed',
        'note'            => $comment,
        'notes'           => $comment,
        'mt5_ticket'      => $ticket,
        'updated_at'      => current_time('mysql')
    ];

    if ($existing_trade_id) {
        $ok = $wpdb->update($trades_table, $row, ['id' => (int)$existing_trade_id]);
        if ($ok !== false) {
            $updated_closed++;
        } else {
            $write_errors[] = [
                'scope' => 'closed_update',
                'index' => $index,
                'ticket' => $ticket,
                'db_error' => $wpdb->last_error
            ];
        }
    } else {
        $row['created_at'] = current_time('mysql');
        $ok = $wpdb->insert($trades_table, $row);
        if ($ok) {
            $inserted_closed++;
        } else {
            $write_errors[] = [
                'scope' => 'closed_insert',
                'index' => $index,
                'ticket' => $ticket,
                'db_error' => $wpdb->last_error,
                'row_keys' => array_keys($row)
            ];
        }
    }
}

foreach ($live_trades as $index => $trade) {
    $ticket = mt5_int($trade['position_id'] ?? $trade['ticket'] ?? 0);
    $symbol = mt5_clean_text($trade['symbol'] ?? '', 20);
    $direction = strtoupper(mt5_clean_text($trade['direction'] ?? '', 10));
    $comment = mt5_clean_text($trade['comment'] ?? '', 255);
    $entry_price = mt5_num($trade['open_price'] ?? $trade['price_open'] ?? 0);

    if ($ticket <= 0 || $symbol === '') {
        continue;
    }

    $entry_datetime = mt5_datetime($trade['open_time'] ?? 0);

    $existing_trade_id = $wpdb->get_var($wpdb->prepare(
        "SELECT id
         FROM {$trades_table}
         WHERE user_id = %d
           AND mt5_ticket = %d
         LIMIT 1",
        $user_id,
        $ticket
    ));

    if (!empty($wpdb->last_error)) {
        $write_errors[] = [
            'scope' => 'live_lookup',
            'index' => $index,
            'ticket' => $ticket,
            'db_error' => $wpdb->last_error
        ];
        continue;
    }

    $live_profit = mt5_num($trade['profit'] ?? 0);
    $live_swap   = mt5_num($trade['swap'] ?? 0);
    $live_comm   = mt5_num($trade['commission'] ?? 0);
    $live_net    = $live_profit + $live_swap + $live_comm;

    $row = [
        'user_id'         => $user_id,
        'journal_id'      => $journal_id,
        'entry_date'      => substr($entry_datetime, 0, 10),
        'session'         => 'NY',
        'strategy_type'   => 'MT5 SYNC',
        'symbol'          => $symbol,
        'direction'       => $direction === 'SELL' ? 'SHORT' : 'LONG',
        'entry_price'     => $entry_price,
        'profit_loss'     => round($live_net, 2),
        'profit_loss_pct' => 0,
        'outcome'         => 'OPEN',
        'status'          => 'open',
        'note'            => $comment,
        'notes'           => $comment,
        'mt5_ticket'      => $ticket,
        'updated_at'      => current_time('mysql')
    ];

    if ($existing_trade_id) {
        $existing_status = $wpdb->get_var($wpdb->prepare(
            "SELECT status FROM {$trades_table} WHERE id = %d LIMIT 1",
            (int)$existing_trade_id
        ));

        if (!empty($wpdb->last_error)) {
            $write_errors[] = [
                'scope' => 'live_status_lookup',
                'index' => $index,
                'ticket' => $ticket,
                'db_error' => $wpdb->last_error
            ];
            continue;
        }

        if ($existing_status !== 'closed') {
            $ok = $wpdb->update($trades_table, $row, ['id' => (int)$existing_trade_id]);
            if ($ok !== false) {
                $updated_live++;
            } else {
                $write_errors[] = [
                    'scope' => 'live_update',
                    'index' => $index,
                    'ticket' => $ticket,
                    'db_error' => $wpdb->last_error
                ];
            }
        }
    } else {
        $row['created_at'] = current_time('mysql');
        $ok = $wpdb->insert($trades_table, $row);
        if ($ok) {
            $inserted_live++;
        } else {
            $write_errors[] = [
                'scope' => 'live_insert',
                'index' => $index,
                'ticket' => $ticket,
                'db_error' => $wpdb->last_error,
                'row_keys' => array_keys($row)
            ];
        }
    }
}

$market_sync_result = mt5_market_sync_candles($payload, $connection, $wpdb);

$wpdb->update($conn_table, [
    'journal_id'   => $journal_id,
    'last_sync_at' => current_time('mysql'),
    'last_error'   => empty($write_errors) ? null : wp_json_encode($write_errors),
    'is_active'    => 1,
    'updated_at'   => current_time('mysql')
], ['id' => (int)$connection['id']]);

if (!empty($wpdb->last_error)) {
    mt5_send([
        'ok' => false,
        'message' => 'Failed to update connection sync status',
        'db_error' => $wpdb->last_error,
        'write_errors' => $write_errors
    ], 500);
}

mt5_send([
    'ok'              => empty($write_errors),
    'message'         => empty($write_errors) ? 'MT5 sync successful' : 'MT5 sync completed with database write errors',
    'user_id'         => $user_id,
    'journal_id'      => $journal_id,
    'account_login'   => $account_login,
    'live_received'   => count($live_trades),
    'closed_received' => count($closed_trades),
    'closed_inserted' => $inserted_closed,
    'closed_updated'  => $updated_closed,
    'market_received' => (int) ($market_sync_result['received'] ?? 0),
    'market_saved'    => (int) ($market_sync_result['saved'] ?? 0),
    'live_inserted'   => $inserted_live,
    'live_updated'    => $updated_live,
    'skipped_live'    => $skipped_live,
    'skipped_closed'  => $skipped_closed,
    'write_errors'    => $write_errors
], 200);
