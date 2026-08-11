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
$user_id             = (int) $_SESSION['user_id'];

$trade_id   = intval($_POST['trade_id'] ?? 0);
$journal_id = intval($_POST['journal_id'] ?? 0);

if ($trade_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid trade ID']);
    exit;
}

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

$existing = $wpdb->get_row($wpdb->prepare(
    "SELECT * FROM $table_name WHERE id = %d AND user_id = %d AND journal_id = %d",
    $trade_id, $user_id, $journal_id
));

if (!$existing) {
    echo json_encode(['success' => false, 'message' => 'Trade not found or access denied']);
    exit;
}

$data = [];

if (isset($_POST['entry_date']))    $data['entry_date']    = sanitize_text_field($_POST['entry_date']);
if (isset($_POST['session']))       $data['session']       = sanitize_text_field($_POST['session']);
if (isset($_POST['symbol']))        $data['symbol']        = strtoupper(sanitize_text_field($_POST['symbol']));
if (isset($_POST['direction']))     $data['direction']     = strtoupper(sanitize_text_field($_POST['direction']));
if (isset($_POST['entry_price']) && $_POST['entry_price'] !== '') $data['entry_price'] = floatval($_POST['entry_price']);
if (isset($_POST['strategy_type'])) $data['strategy_type'] = sanitize_text_field($_POST['strategy_type']);

if (isset($_POST['imbalance_size_pct'])) $data['imbalance_size_pct'] = $_POST['imbalance_size_pct'] !== '' ? floatval($_POST['imbalance_size_pct']) : null;
$data['nearby_imbalance']    = isset($_POST['nearby_imbalance']) ? 1 : 0;

if (isset($_POST['w_histogram'])) $data['w_histogram'] = $_POST['w_histogram'] !== '' ? sanitize_text_field($_POST['w_histogram']) : null;
if (isset($_POST['m_histogram'])) $data['m_histogram'] = $_POST['m_histogram'] !== '' ? sanitize_text_field($_POST['m_histogram']) : null;
$data['all_8h_bars_bullish'] = isset($_POST['all_8h_bars_bullish']) ? 1 : 0;

if (isset($_POST['trade_time_bars'])) $data['trade_time_bars'] = $_POST['trade_time_bars'] !== '' ? intval($_POST['trade_time_bars'])  : null;
if (isset($_POST['fill_time_bars']))  $data['fill_time_bars']  = $_POST['fill_time_bars']  !== '' ? intval($_POST['fill_time_bars'])   : null;

$final_entry_price = isset($data['entry_price']) ? $data['entry_price'] : floatval($existing->entry_price);
$final_direction   = isset($data['direction'])   ? $data['direction']   : strtoupper($existing->direction ?? '');

$stop_price_posted = isset($_POST['stop_price'])      && $_POST['stop_price']      !== '';
$stop_pct_posted   = isset($_POST['stop_percentage']) && $_POST['stop_percentage'] !== '';

if ($stop_price_posted && $stop_pct_posted) {
    $data['stop_price']      = floatval($_POST['stop_price']);
    $data['stop_percentage'] = floatval($_POST['stop_percentage']);
} elseif ($stop_price_posted) {
    $data['stop_price']      = floatval($_POST['stop_price']);
    if ($final_entry_price > 0) {
        $data['stop_percentage'] = round(abs($final_entry_price - $data['stop_price']) / $final_entry_price * 100, 4);
    }
} elseif ($stop_pct_posted) {
    $data['stop_percentage'] = floatval($_POST['stop_percentage']);
    if ($final_entry_price > 0) {
        if ($final_direction === 'SHORT') {
            $data['stop_price'] = round($final_entry_price * (1 + $data['stop_percentage'] / 100), 8);
        } else {
            $data['stop_price'] = round($final_entry_price * (1 - $data['stop_percentage'] / 100), 8);
        }
    }
} else {
    $data['stop_percentage'] = floatval($existing->stop_percentage ?? 0.60);
    $data['stop_price']      = $existing->stop_price ? floatval($existing->stop_price) : null;
}

if (isset($_POST['m_vix_open'])) $data['m_vix_open'] = $_POST['m_vix_open'] !== '' ? floatval($_POST['m_vix_open']) : null;
if (isset($_POST['w_vix_open'])) $data['w_vix_open'] = $_POST['w_vix_open'] !== '' ? floatval($_POST['w_vix_open']) : null;
if (isset($_POST['d_vix_open'])) $data['d_vix_open'] = $_POST['d_vix_open'] !== '' ? floatval($_POST['d_vix_open']) : null;
if (isset($_POST['vix_moment'])) $data['vix_moment'] = $_POST['vix_moment'] !== '' ? floatval($_POST['vix_moment']) : null;

if (isset($_POST['lowest_price_from_entry_pct']))  $data['lowest_price_from_entry_pct']  = $_POST['lowest_price_from_entry_pct']  !== '' ? floatval($_POST['lowest_price_from_entry_pct'])  : null;
if (isset($_POST['highest_price_from_entry_pct'])) $data['highest_price_from_entry_pct'] = $_POST['highest_price_from_entry_pct'] !== '' ? floatval($_POST['highest_price_from_entry_pct']) : null;

if (isset($_POST['exit_price'])) $data['exit_price'] = $_POST['exit_price'] !== '' ? floatval($_POST['exit_price']) : null;
if (isset($_POST['exit_date']))  $data['exit_date']  = $_POST['exit_date']  !== '' ? sanitize_text_field($_POST['exit_date']) : null;

if (isset($_POST['outcome'])) $data['outcome'] = $_POST['outcome'] !== '' ? sanitize_text_field($_POST['outcome']) : 'OPEN';
if (isset($_POST['note']))    $data['note']    = sanitize_textarea_field($_POST['note']);

$final_exit_price = array_key_exists('exit_price', $data) ? $data['exit_price'] : floatval($existing->exit_price ?? 0);

$posted_profit_loss     = isset($_POST['profit_loss']) && $_POST['profit_loss'] !== '';
$posted_profit_loss_pct = isset($_POST['profit_loss_pct']) && $_POST['profit_loss_pct'] !== '';

if ($posted_profit_loss) {
    $data['profit_loss'] = floatval($_POST['profit_loss']);
}

if ($posted_profit_loss_pct) {
    $data['profit_loss_pct'] = floatval($_POST['profit_loss_pct']);
}

if (!$posted_profit_loss_pct && $final_exit_price > 0 && $final_entry_price > 0) {
    if ($final_direction === 'LONG') {
        $data['profit_loss_pct'] = round((($final_exit_price - $final_entry_price) / $final_entry_price) * 100, 4);
    } elseif ($final_direction === 'SHORT') {
        $data['profit_loss_pct'] = round((($final_entry_price - $final_exit_price) / $final_entry_price) * 100, 4);
    }
} elseif (!$posted_profit_loss_pct && isset($_POST['profit_loss_pct'])) {
    $data['profit_loss_pct'] = null;
}

if (!$posted_profit_loss && $final_exit_price > 0 && $final_entry_price > 0) {
    if ($final_direction === 'LONG') {
        $data['profit_loss'] = round($final_exit_price - $final_entry_price, 2);
    } elseif ($final_direction === 'SHORT') {
        $data['profit_loss'] = round($final_entry_price - $final_exit_price, 2);
    }
} elseif (!$posted_profit_loss && isset($_POST['profit_loss'])) {
    $data['profit_loss'] = null;
}

$final_stop_pct = floatval($data['stop_percentage'] ?? $existing->stop_percentage ?? 0.60);
if (isset($_POST['stop_triggered'])) {
    $data['stop_triggered'] = 1;
} elseif ($final_exit_price > 0 && $final_entry_price > 0 && $final_stop_pct > 0) {
    if ($final_direction === 'LONG' && $final_exit_price <= $final_entry_price * (1 - $final_stop_pct / 100)) {
        $data['stop_triggered'] = 1;
    } elseif ($final_direction === 'SHORT' && $final_exit_price >= $final_entry_price * (1 + $final_stop_pct / 100)) {
        $data['stop_triggered'] = 1;
    } else {
        $data['stop_triggered'] = 0;
    }
} else {
    $data['stop_triggered'] = 0;
}

$result = $wpdb->update(
    $table_name,
    $data,
    ['id' => $trade_id, 'user_id' => $user_id, 'journal_id' => $journal_id]
);

if ($result === false) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $wpdb->last_error]);
    exit;
}

// ── Save custom field values ──────────────────────────────────────────
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

echo json_encode(['success' => true, 'message' => 'Trade updated successfully']);
?>
