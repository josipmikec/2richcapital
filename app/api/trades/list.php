<?php
// API: Get user's trades
ob_start();
require_once '../../auth/session-config.php';
define('WP_USE_THEMES', false);
require_once('../../../wp-load.php');
ob_end_clean();

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || !isset($_SESSION['authenticated'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

global $wpdb;
$table_name          = $wpdb->prefix . 'rich_trades';
$journals_table      = $wpdb->prefix . 'rich_journals';
$custom_values_table = $wpdb->prefix . 'rich_custom_column_values';
$user_id             = (int) $_SESSION['user_id'];

if (empty($_GET['journal_id'])) {
    echo json_encode(['success' => false, 'message' => 'Journal ID is required']);
    exit;
}

$journal_id = (int) $_GET['journal_id'];

$journal_exists = $wpdb->get_var($wpdb->prepare(
    "SELECT id FROM {$journals_table} WHERE id = %d AND user_id = %d",
    $journal_id,
    $user_id
));

if (!$journal_exists) {
    echo json_encode(['success' => false, 'message' => 'Invalid journal selected']);
    exit;
}

$limit  = isset($_GET['limit'])  ? (int) $_GET['limit']  : 50;
$offset = isset($_GET['offset']) ? (int) $_GET['offset'] : 0;
$order  = isset($_GET['order']) && strtoupper($_GET['order']) === 'ASC' ? 'ASC' : 'DESC';

$trades = $wpdb->get_results($wpdb->prepare(
    "SELECT
        id,
        user_id,
        journal_id,
        entry_date,
        exit_date,
        entry_price,
        exit_price,
        profit_loss,
        profit_loss_pct,
        outcome,
        status,
        symbol,
        direction,
        session,
        strategy_type,
        imbalance_size_pct,
        nearby_imbalance,
        w_histogram,
        m_histogram,
        all_8h_bars_bullish,
        trade_time_bars,
        fill_time_bars,
        stop_price,
        stop_percentage,
        stop_triggered,
        m_vix_open,
        w_vix_open,
        d_vix_open,
        vix_moment,
        lowest_price_from_entry_pct,
        highest_price_from_entry_pct,
        note,
        created_at,
        updated_at
     FROM {$table_name}
     WHERE user_id = %d AND journal_id = %d
     ORDER BY entry_date {$order}, created_at {$order}
     LIMIT %d OFFSET %d",
    $user_id,
    $journal_id,
    $limit,
    $offset
), ARRAY_A);

$trades = $trades ?: [];

// ── Attach custom field values to each trade ──────────────────────────────────
if (!empty($trades)) {
    $trade_ids    = array_map('intval', array_column($trades, 'id'));
    $placeholders = implode(',', array_fill(0, count($trade_ids), '%d'));

    $custom_values = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT trade_id, column_id, value
             FROM {$custom_values_table}
             WHERE trade_id IN ({$placeholders})",
            ...$trade_ids
        ),
        ARRAY_A
    );

    // Build a map: trade_id => [ 'custom_X' => value ]
    $custom_map = [];
    foreach (($custom_values ?: []) as $cv) {
        $custom_map[(int) $cv['trade_id']]['custom_' . $cv['column_id']] = $cv['value'];
    }

    // Attach to each trade
    foreach ($trades as &$trade) {
        $trade['custom_fields'] = $custom_map[(int) $trade['id']] ?? [];
    }
    unset($trade);
}

$total = (int) $wpdb->get_var($wpdb->prepare(
    "SELECT COUNT(*) FROM {$table_name} WHERE user_id = %d AND journal_id = %d",
    $user_id,
    $journal_id
));

echo json_encode([
    'success' => true,
    'trades'  => $trades,
    'total'   => $total,
    'limit'   => $limit,
    'offset'  => $offset
]);
?>
