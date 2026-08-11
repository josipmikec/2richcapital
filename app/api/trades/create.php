<?php
ob_start();
require_once '../../auth/session-config.php';
define('WP_USE_THEMES', false);
require_once('../../../wp-load.php');
ob_end_clean();

require_once '../csrf.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || !isset($_SESSION['authenticated'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

verify_csrf();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

global $wpdb;
$table_name          = $wpdb->prefix . 'rich_trades';
$journals_table      = $wpdb->prefix . 'rich_journals';
$custom_values_table = $wpdb->prefix . 'rich_custom_column_values';
$custom_cols_table   = $wpdb->prefix . 'rich_custom_columns';

$user_id    = (int) $_SESSION['user_id'];
$journal_id = intval($_POST['journal_id'] ?? 0);

if ($journal_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Journal ID is required']);
    exit;
}

$journal_exists = $wpdb->get_var($wpdb->prepare(
    "SELECT id FROM $journals_table WHERE id = %d AND user_id = %d",
    $journal_id, $user_id
));

if (!$journal_exists) {
    echo json_encode(['success' => false, 'message' => 'Invalid journal selected']);
    exit;
}

$entry_date  = sanitize_text_field($_POST['entry_date'] ?? '');
$session     = sanitize_text_field($_POST['session'] ?? '');
$symbol      = strtoupper(sanitize_text_field($_POST['symbol'] ?? ''));
$direction   = strtoupper(sanitize_text_field($_POST['direction'] ?? ''));
$entry_price = floatval($_POST['entry_price'] ?? 0);

if (empty($entry_date) || empty($session) || empty($symbol) || empty($direction) || $entry_price <= 0) {
    echo json_encode(['success' => false, 'message' => 'Missing required fields']);
    exit;
}

$exit_price      = isset($_POST['exit_price']) && $_POST['exit_price'] !== '' ? floatval($_POST['exit_price']) : null;
$stop_percentage = isset($_POST['stop_percentage']) && $_POST['stop_percentage'] !== '' ? floatval($_POST['stop_percentage']) : 0.60;
$outcome         = isset($_POST['outcome']) && $_POST['outcome'] !== '' ? sanitize_text_field($_POST['outcome']) : 'OPEN';

// Stop price — server-side fallback calculation if not sent
$stop_price = null;
if (isset($_POST['stop_price']) && $_POST['stop_price'] !== '') {
    $stop_price = floatval($_POST['stop_price']);
} elseif ($stop_percentage > 0 && $entry_price > 0) {
    if ($direction === 'SHORT') {
        $stop_price = round($entry_price * (1 + $stop_percentage / 100), 8);
    } else {
        $stop_price = round($entry_price * (1 - $stop_percentage / 100), 8);
    }
}

// Stop distance — server-side fallback calculation if not sent
if ((!isset($_POST['stop_percentage']) || $_POST['stop_percentage'] === '') && $stop_price !== null && $entry_price > 0) {
    $stop_percentage = round(abs($entry_price - $stop_price) / $entry_price * 100, 4);
}

// Server-side P&L calculation
$profit_loss = null;
$profit_loss_pct = null;

if (isset($_POST['profit_loss']) && $_POST['profit_loss'] !== '') {
    $profit_loss = floatval($_POST['profit_loss']);
}

if (isset($_POST['profit_loss_pct']) && $_POST['profit_loss_pct'] !== '') {
    $profit_loss_pct = floatval($_POST['profit_loss_pct']);
} elseif ($exit_price !== null && $entry_price > 0) {
    if ($direction === 'LONG') {
        $profit_loss_pct = round((($exit_price - $entry_price) / $entry_price) * 100, 4);
    } elseif ($direction === 'SHORT') {
        $profit_loss_pct = round((($entry_price - $exit_price) / $entry_price) * 100, 4);
    }
}

if ($profit_loss === null && $exit_price !== null) {
    if ($profit_loss_pct !== null) {
        $profit_loss = round($profit_loss_pct, 2);
    } elseif ($entry_price > 0) {
        $profit_loss = round($exit_price - $entry_price, 2);
        if ($direction === 'SHORT') {
            $profit_loss = round($entry_price - $exit_price, 2);
        }
    }
}

// Server-side stop_triggered auto-calc
$stop_triggered = isset($_POST['stop_triggered']) ? 1 : 0;
if (!$stop_triggered && $exit_price !== null && $entry_price > 0 && $stop_percentage > 0) {
    if ($direction === 'LONG' && $exit_price <= $entry_price * (1 - $stop_percentage / 100)) {
        $stop_triggered = 1;
    } elseif ($direction === 'SHORT' && $exit_price >= $entry_price * (1 + $stop_percentage / 100)) {
        $stop_triggered = 1;
    }
}

$data = [
    'user_id'                      => $user_id,
    'journal_id'                   => $journal_id,
    'entry_date'                   => $entry_date,
    'session'                      => $session,
    'strategy_type'                => sanitize_text_field($_POST['strategy_type'] ?? 'DEFAULT'),
    'symbol'                       => $symbol,
    'direction'                    => $direction,
    'entry_price'                  => $entry_price,
    'imbalance_size_pct'           => isset($_POST['imbalance_size_pct']) && $_POST['imbalance_size_pct'] !== '' ? floatval($_POST['imbalance_size_pct']) : null,
    'nearby_imbalance'             => isset($_POST['nearby_imbalance']) ? 1 : 0,
    'w_histogram'                  => isset($_POST['w_histogram']) && $_POST['w_histogram'] !== '' ? sanitize_text_field($_POST['w_histogram']) : null,
    'm_histogram'                  => isset($_POST['m_histogram']) && $_POST['m_histogram'] !== '' ? sanitize_text_field($_POST['m_histogram']) : null,
    'all_8h_bars_bullish'          => isset($_POST['all_8h_bars_bullish']) ? 1 : 0,
    'trade_time_bars'              => isset($_POST['trade_time_bars']) && $_POST['trade_time_bars'] !== '' ? intval($_POST['trade_time_bars']) : null,
    'fill_time_bars'               => isset($_POST['fill_time_bars']) && $_POST['fill_time_bars'] !== '' ? intval($_POST['fill_time_bars']) : null,
    'stop_price'                   => $stop_price,
    'stop_percentage'              => $stop_percentage,
    'stop_triggered'               => $stop_triggered,
    'm_vix_open'                   => isset($_POST['m_vix_open']) && $_POST['m_vix_open'] !== '' ? floatval($_POST['m_vix_open']) : null,
    'w_vix_open'                   => isset($_POST['w_vix_open']) && $_POST['w_vix_open'] !== '' ? floatval($_POST['w_vix_open']) : null,
    'd_vix_open'                   => isset($_POST['d_vix_open']) && $_POST['d_vix_open'] !== '' ? floatval($_POST['d_vix_open']) : null,
    'vix_moment'                   => isset($_POST['vix_moment']) && $_POST['vix_moment'] !== '' ? floatval($_POST['vix_moment']) : null,
    'exit_price'                   => $exit_price,
    'exit_date'                    => isset($_POST['exit_date']) && $_POST['exit_date'] !== '' ? sanitize_text_field($_POST['exit_date']) : null,
    'lowest_price_from_entry_pct'  => isset($_POST['lowest_price_from_entry_pct']) && $_POST['lowest_price_from_entry_pct'] !== '' ? floatval($_POST['lowest_price_from_entry_pct']) : null,
    'highest_price_from_entry_pct' => isset($_POST['highest_price_from_entry_pct']) && $_POST['highest_price_from_entry_pct'] !== '' ? floatval($_POST['highest_price_from_entry_pct']) : null,
    'profit_loss'                  => $profit_loss,
	'profit_loss_pct'              => $profit_loss_pct,
    'outcome'                      => $outcome,
    'note'                         => sanitize_textarea_field($_POST['note'] ?? ''),
];

$result = $wpdb->insert($table_name, $data);

if ($result === false) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $wpdb->last_error]);
    exit;
}

$trade_id = $wpdb->insert_id;

// ── Save custom field values ──────────────────────────────────────────
// Fetch all custom columns belonging to this user
$custom_columns = $wpdb->get_results($wpdb->prepare(
    "SELECT id FROM {$custom_cols_table} WHERE user_id = %d",
    $user_id
), ARRAY_A);

foreach (($custom_columns ?: []) as $col) {
    $field_key = 'custom_' . $col['id'];
    if (!isset($_POST[$field_key])) continue;

    $value = sanitize_text_field($_POST[$field_key]);

    $wpdb->replace($custom_values_table, [
        'trade_id'  => $trade_id,
        'column_id' => (int) $col['id'],
        'value'     => $value,
    ]);
}

echo json_encode(['success' => true, 'message' => 'Trade logged successfully', 'trade_id' => $trade_id]);
?>
