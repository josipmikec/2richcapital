<?php
require_once dirname(__DIR__, 2) . '/auth/session-config.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

function mt5_live_send_raw($data, $code = 200) {
    http_response_code($code);
    echo json_encode($data);
    exit;
}

if (!isset($_SESSION['user_id']) || !isset($_SESSION['authenticated'])) {
    mt5_live_send_raw([
        'success' => false,
        'message' => 'Not authenticated',
        'debug' => [
            'session_name' => session_name(),
            'session_id' => session_id(),
            'session_keys' => array_keys($_SESSION ?? []),
            'has_user_id' => isset($_SESSION['user_id']),
            'has_authenticated' => isset($_SESSION['authenticated']),
            'request_uri' => $_SERVER['REQUEST_URI'] ?? '',
            'http_host' => $_SERVER['HTTP_HOST'] ?? '',
        ]
    ], 401);
}

$session_user_id = (int)$_SESSION['user_id'];

define('WP_USE_THEMES', false);
require_once dirname(__DIR__, 3) . '/wp-load.php';

global $wpdb;

function mt5_live_send($data, $code = 200) {
    status_header($code);
    echo wp_json_encode($data);
    exit;
}

function mt5_live_normalize_trade_row($row) {
    return [
        'id' => (int)($row['id'] ?? 0),
        'user_id' => (int)($row['user_id'] ?? 0),
        'journal_id' => (int)($row['journal_id'] ?? 0),
        'mt5_ticket' => (string)($row['mt5_ticket'] ?? ''),
        'ticket' => (string)($row['mt5_ticket'] ?? ''),
        'symbol' => (string)($row['symbol'] ?? ''),
        'direction' => (strtoupper((string)($row['direction'] ?? 'LONG')) === 'SHORT') ? 'SELL' : 'BUY',
        'entry_date' => (string)($row['entry_date'] ?? ''),
        'exit_date' => (string)($row['exit_date'] ?? ''),
        'entry_price' => (float)($row['entry_price'] ?? 0),
        'exit_price' => isset($row['exit_price']) && $row['exit_price'] !== null ? (float)$row['exit_price'] : 0,
        'profit_loss' => isset($row['profit_loss']) && $row['profit_loss'] !== null ? (float)$row['profit_loss'] : 0,
        'profit_loss_pct' => isset($row['profit_loss_pct']) && $row['profit_loss_pct'] !== null ? (float)$row['profit_loss_pct'] : 0,
        'outcome' => (string)($row['outcome'] ?? ''),
        'status' => (string)($row['status'] ?? ''),
        'note' => (string)($row['note'] ?? ''),
        'notes' => (string)($row['notes'] ?? ''),
        'created_at' => (string)($row['created_at'] ?? ''),
        'updated_at' => (string)($row['updated_at'] ?? ''),
    ];
}

function mt5_live_connection_status($last_sync_at) {
    if (empty($last_sync_at)) return 'offline';
    $ts = strtotime($last_sync_at);
    if ($ts === false) return 'offline';
    $diff = time() - $ts;
    if ($diff <= 15 * 60) return 'online';
    if ($diff <= 60 * 60) return 'stale';
    return 'offline';
}

$conn_table   = $wpdb->prefix . 'rich_mt5_connections';
$live_table   = $wpdb->prefix . 'rich_live_trades';
$trades_table = $wpdb->prefix . 'rich_trades';

$connection = $wpdb->get_row(
    $wpdb->prepare(
        "SELECT * FROM {$conn_table} WHERE user_id = %d AND is_active = 1 ORDER BY updated_at DESC, id DESC LIMIT 1",
        $session_user_id
    ),
    ARRAY_A
);

if (!$connection) {
    mt5_live_send([
        'success' => true,
        'account' => [
            'login' => 0,
            'balance' => 0.0,
            'equity' => 0.0,
            'currency' => 'USD',
            'server' => '',
            'synced_at' => ''
        ],
        'live' => [],
        'recent' => [],
        'closed' => [],
        'connection_status' => 'offline',
        'last_sync_at' => '',
        'debug' => [
            'resolved_user_id' => 0,
            'connection_journal_id' => 0,
            'resolved_journal_id' => 0,
            'live_trade_rows' => 0,
            'recent_trade_rows' => 0,
            'closed_trade_rows' => 0
        ]
    ], 200);
}

$user_id = (int)($connection['user_id'] ?? 0);
$connection_journal_id = (int)($connection['journal_id'] ?? 0);
$resolved_journal_id = $connection_journal_id;
$mt5_login = (int)($connection['mt5_login'] ?? 0);
$last_sync_at = (string)($connection['last_sync_at'] ?? '');

if ($user_id > 0) {
    $fallback_journal_id = (int)$wpdb->get_var($wpdb->prepare(
        "SELECT journal_id
         FROM {$trades_table}
         WHERE user_id = %d
           AND mt5_ticket IS NOT NULL
           AND journal_id IS NOT NULL
           AND journal_id > 0
         ORDER BY updated_at DESC, created_at DESC, id DESC
         LIMIT 1",
        $user_id
    ));

    if ($resolved_journal_id <= 0 && $fallback_journal_id > 0) {
        $resolved_journal_id = $fallback_journal_id;
    }

    if ($resolved_journal_id > 0 && $fallback_journal_id > 0 && $resolved_journal_id !== $fallback_journal_id) {
        $resolved_journal_id = $fallback_journal_id;
    }
}

$account = [
    'login' => $mt5_login,
    'balance' => (float)($connection['last_balance'] ?? 0),
    'equity' => (float)($connection['last_equity'] ?? 0),
    'currency' => (string)($connection['account_currency'] ?? 'USD'),
    'server' => (string)($connection['server_name'] ?? ''),
    'synced_at' => $last_sync_at,
];

$live_trades = [];$live_trades = [];
if ($mt5_login > 0) {
    $live_trades = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT
                id,
                mt5_login,
                ticket,
                symbol,
                direction,
                volume,
                open_price,
                current_price,
                sl,
                tp,
                profit,
                swap,
                open_time,
                synced_at
             FROM {$live_table}
             WHERE mt5_login = %d
             ORDER BY open_time DESC, id DESC",
            $mt5_login
        ),
        ARRAY_A
    );
}

$live = [];
foreach ($live_trades as $row) {
    $live[] = [
        'id' => (int)($row['id'] ?? 0),
        'ticket' => (string)($row['ticket'] ?? ''),
        'symbol' => (string)($row['symbol'] ?? ''),
        'direction' => strtoupper((string)($row['direction'] ?? 'BUY')),
        'volume' => (float)($row['volume'] ?? 0),
        'open_price' => (float)($row['open_price'] ?? 0),
        'current_price' => (float)($row['current_price'] ?? 0),
        'profit' => (float)($row['profit'] ?? 0),
        'swap' => (float)($row['swap'] ?? 0),
        'sl' => (float)($row['sl'] ?? 0),
        'tp' => (float)($row['tp'] ?? 0),
        'open_time' => (string)($row['open_time'] ?? ''),
        'comment' => (string)($row['comment'] ?? ''),
        'synced_at' => (string)($row['synced_at'] ?? ''),
        'source' => 'live_table',
    ];
}

$live_query_debug = [
    'mt5_login' => $mt5_login,
    'live_table' => $live_table,
    'live_rows_found' => is_array($live_trades) ? count($live_trades) : 0,
    'live_first_row' => $live_trades[0] ?? null,
    'wpdb_last_error' => $wpdb->last_error,
];


$recent_rows = [];
$closed_rows = [];
$open_rows = [];

if ($user_id > 0 && $resolved_journal_id > 0) {
    $recent_rows = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT *
             FROM {$trades_table}
             WHERE user_id = %d
               AND journal_id = %d
               AND mt5_ticket IS NOT NULL
             ORDER BY COALESCE(exit_date, entry_date) DESC, id DESC
             LIMIT 10",
            $user_id,
            $resolved_journal_id
        ),
        ARRAY_A
    );

    $closed_rows = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT *
             FROM {$trades_table}
             WHERE user_id = %d
               AND journal_id = %d
               AND mt5_ticket IS NOT NULL
               AND status = 'closed'
             ORDER BY exit_date DESC, id DESC
             LIMIT 50",
            $user_id,
            $resolved_journal_id
        ),
        ARRAY_A
    );

    $open_rows = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT *
             FROM {$trades_table}
             WHERE user_id = %d
               AND journal_id = %d
               AND mt5_ticket IS NOT NULL
               AND status = 'open'
             ORDER BY entry_date DESC, id DESC
             LIMIT 20",
            $user_id,
            $resolved_journal_id
        ),
        ARRAY_A
    );
}

if (empty($live) && !empty($open_rows)) {
    foreach ($open_rows as $row) {
        $live[] = [
            'id' => (int)($row['id'] ?? 0),
            'ticket' => (string)($row['mt5_ticket'] ?? ''),
            'symbol' => (string)($row['symbol'] ?? ''),
            'direction' => (strtoupper((string)($row['direction'] ?? 'LONG')) === 'SHORT') ? 'SELL' : 'BUY',
            'volume' => isset($row['lot_size']) ? (float)$row['lot_size'] : (isset($row['size']) ? (float)$row['size'] : 0),
            'open_price' => (float)($row['entry_price'] ?? 0),
            'current_price' => null,
            'profit' => isset($row['profit_loss']) && $row['profit_loss'] !== null ? (float)$row['profit_loss'] : 0,
            'swap' => 0.0,
            'sl' => 0.0,
            'tp' => 0.0,
            'open_time' => (string)($row['entry_date'] ?? ''),
            'comment' => (string)($row['note'] ?? $row['notes'] ?? ''),
            'synced_at' => $last_sync_at,
            'source' => 'journal_open_fallback',
        ];
    }
}

$recent = array_map('mt5_live_normalize_trade_row', $recent_rows);
$closed = array_map('mt5_live_normalize_trade_row', $closed_rows);

mt5_live_send([
    'success' => true,
    'account' => $account,
    'live' => $live,
    'recent' => $recent,
    'closed' => $closed,
    'connection_status' => mt5_live_connection_status($last_sync_at),
    'last_sync_at' => $last_sync_at,
    'debug' => [
        'resolved_user_id' => $user_id,
        'connection_journal_id' => $connection_journal_id,
        'resolved_journal_id' => $resolved_journal_id,
        'mt5_login' => $mt5_login,
        'live_table_rows' => count($live_trades),
        'first_live_row' => $live_trades[0] ?? null,
        'open_fallback_rows' => count($open_rows),
        'recent_trade_rows' => count($recent),
        'closed_trade_rows' => count($closed),
        'live_query_debug' => $live_query_debug,
    ]
], 200);
