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
    'live_inserted'   => $inserted_live,
    'live_updated'    => $updated_live,
    'skipped_live'    => $skipped_live,
    'skipped_closed'  => $skipped_closed,
    'write_errors'    => $write_errors
], 200);
