<?php
// bulk-import.php

// Catch ALL PHP errors and return them as JSON instead of crashing
set_error_handler(function($errno, $errstr, $errfile, $errline) {
    while (ob_get_level()) ob_end_clean();
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'message' => "PHP Error [$errno]: $errstr in $errfile on line $errline"
    ]);
    exit;
});
register_shutdown_function(function() {
    $e = error_get_last();
    if ($e && in_array($e['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        while (ob_get_level()) ob_end_clean();
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'message' => "Fatal PHP Error: {$e['message']} in {$e['file']} on line {$e['line']}"
        ]);
    }
});

ob_start();
require_once '../../auth/session-config.php';
define('WP_USE_THEMES', false);
require_once '../../../wp-load.php';
ob_end_clean();

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || !isset($_SESSION['authenticated'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

global $wpdb;
$table_name     = $wpdb->prefix . 'rich_trades';
$journals_table = $wpdb->prefix . 'rich_journals';
$user_id        = intval($_SESSION['user_id']);

$input = file_get_contents('php://input');
$data  = json_decode($input, true);

error_log('Bulk import payload: ' . $input);

if (!isset($data['trades']) || !is_array($data['trades'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid data format']);
    exit;
}

$journal_id = isset($data['journal_id']) ? intval($data['journal_id']) : 0;
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

$trades   = $data['trades'];
$imported = 0;
$errors   = [];

function calc_pl($direction, $entry, $exit) {
    if ($entry <= 0 || $exit <= 0) return null;
    $dir = strtoupper(trim($direction));
    if ($dir === 'LONG')  return round((($exit - $entry) / $entry) * 100, 4);
    if ($dir === 'SHORT') return round((($entry - $exit) / $entry) * 100, 4);
    return null;
}

function calc_pl_amount($direction, $entry, $exit) {
    if ($entry <= 0 || $exit === null || $exit <= 0) return null;
    $dir = strtoupper(trim($direction));
    if ($dir === 'LONG')  return round($exit - $entry, 2);
    if ($dir === 'SHORT') return round($entry - $exit, 2);
    return null;
}

foreach ($trades as $index => $trade) {
    if (
        empty($trade['entry_date']) ||
        empty($trade['symbol'])     ||
        empty($trade['direction'])  ||
        empty($trade['entry_price'])
    ) {
        $errors[] = "Row " . ($index + 1) . ": Missing required fields";
        continue;
    }

    $entry_price = floatval($trade['entry_price']);
    $exit_price  = !empty($trade['exit_price']) ? floatval($trade['exit_price']) : null;
    $direction   = strtoupper(sanitize_text_field(trim($trade['direction'])));

    // ── P&L: trust JS value if present, otherwise calculate ──────────────────
    $profit_loss_pct = isset($trade['profit_loss_pct']) && $trade['profit_loss_pct'] !== '' && $trade['profit_loss_pct'] !== null
        ? floatval($trade['profit_loss_pct'])
        : calc_pl($direction, $entry_price, $exit_price);

	$profit_loss = isset($trade['profit_loss']) && $trade['profit_loss'] !== '' && $trade['profit_loss'] !== null
	    ? floatval($trade['profit_loss'])
	    : calc_pl_amount($direction, $entry_price, $exit_price);
	
    // ── Outcome ───────────────────────────────────────────────────────────────
    $outcome = !empty($trade['outcome'])
        ? strtoupper(sanitize_text_field($trade['outcome']))
        : null;
    if ($outcome === null && $profit_loss_pct !== null) {
        if ($profit_loss_pct > 0)     $outcome = 'WIN';
        elseif ($profit_loss_pct < 0) $outcome = 'LOSS';
        else                          $outcome = 'BREAKEVEN';
    }

    // ── Stop triggered: trust JS value (computed from actual SL price) ────────
    $stop_triggered = isset($trade['stop_triggered']) ? intval($trade['stop_triggered']) : 0;

    // ── stop_percentage: only used for display/reference, default 0.60 ───────
    $stop_percentage = !empty($trade['stop_percentage']) ? floatval($trade['stop_percentage']) : 0.60;

    $insert_data = [
        'user_id'         => $user_id,
        'journal_id'      => $journal_id,
        'entry_date'      => sanitize_text_field($trade['entry_date']),
        'symbol'          => strtoupper(sanitize_text_field($trade['symbol'])),
        'direction'       => $direction,
        'entry_price'     => $entry_price,
        'session'         => !empty($trade['session'])       ? strtoupper(sanitize_text_field($trade['session'])) : 'NY',
        'strategy_type'   => !empty($trade['strategy_type']) ? sanitize_text_field($trade['strategy_type'])       : 'MT5 IMPORT',
        'stop_percentage' => $stop_percentage,
        'stop_triggered'  => $stop_triggered,
        'profit_loss_pct' => $profit_loss_pct,
	    'profit_loss'     => $profit_loss,
	    'outcome'         => $outcome,
    ];

    $optional_fields = [
        'exit_price', 'exit_date',
        'lowest_price_from_entry_pct', 'highest_price_from_entry_pct',
        'imbalance_size_pct', 'nearby_imbalance',
        'w_histogram', 'm_histogram',
        'all_8h_bars_bullish', 'trade_time_bars', 'fill_time_bars',
        'm_vix_open', 'w_vix_open', 'd_vix_open', 'vix_moment',
        'note'
    ];

    foreach ($optional_fields as $field) {
        if (isset($trade[$field]) && $trade[$field] !== '' && $trade[$field] !== null) {
            if (in_array($field, ['nearby_imbalance', 'all_8h_bars_bullish', 'stop_triggered'])) {
                $insert_data[$field] = $trade[$field] ? 1 : 0;
            } elseif (in_array($field, ['trade_time_bars', 'fill_time_bars'])) {
                $insert_data[$field] = intval($trade[$field]);
            } elseif (
                strpos($field, '_pct') !== false ||
                strpos($field, 'vix')  !== false ||
                $field === 'exit_price'
            ) {
                $insert_data[$field] = floatval($trade[$field]);
            } else {
                $insert_data[$field] = sanitize_text_field($trade[$field]);
            }
        }
    }

    $result = $wpdb->insert($table_name, $insert_data);

    if ($result !== false) {
        $imported++;
    } else {
        $errors[] = "Row " . ($index + 1) . ": " . $wpdb->last_error;
    }
}

echo json_encode([
    'success'  => $imported > 0 || empty($errors),
    'imported' => $imported,
    'total'    => count($trades),
    'errors'   => $errors
]);
?>
