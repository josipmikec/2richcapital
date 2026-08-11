<?php
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
$table_name     = $wpdb->prefix . 'rich_trades';
$journals_table = $wpdb->prefix . 'rich_journals';
$user_id        = (int) $_SESSION['user_id'];

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

$row = $wpdb->get_row($wpdb->prepare(
    "SELECT
        COUNT(*)                                                     AS total_trades,
        SUM(outcome = 'WIN')                                         AS wins,
        SUM(outcome = 'LOSS')                                        AS losses,
        SUM(outcome = 'OPEN')                                        AS open_trades,
        AVG(CASE WHEN outcome != 'OPEN' AND profit_loss_pct IS NOT NULL THEN profit_loss_pct END) AS avg_pl_pct,
        MAX(CASE WHEN outcome != 'OPEN' THEN profit_loss_pct END)     AS best_trade_pct,
        MIN(CASE WHEN outcome != 'OPEN' THEN profit_loss_pct END)     AS worst_trade_pct,
        AVG(CASE WHEN outcome != 'OPEN' AND profit_loss IS NOT NULL THEN profit_loss END)         AS avg_pl_amount,
        SUM(CASE WHEN outcome != 'OPEN' AND profit_loss IS NOT NULL THEN profit_loss ELSE 0 END)  AS total_pl_amount
     FROM {$table_name}
     WHERE user_id = %d AND journal_id = %d",
    $user_id,
    $journal_id
), ARRAY_A);

$total    = (int) ($row['total_trades'] ?? 0);
$wins     = (int) ($row['wins'] ?? 0);
$losses   = (int) ($row['losses'] ?? 0);
$open     = (int) ($row['open_trades'] ?? 0);
$win_rate = $total > 0 ? round(($wins / $total) * 100, 2) : 0;

echo json_encode([
    'success' => true,
    'stats'   => [
        'total_trades'        => $total,
        'wins'                => $wins,
        'losses'              => $losses,
        'open_trades'         => $open,
        'win_rate'            => $win_rate,
        'avg_profit_loss_pct' => round((float) ($row['avg_pl_pct'] ?? 0), 2),
        'best_trade_pct'      => round((float) ($row['best_trade_pct'] ?? 0), 2),
        'worst_trade_pct'     => round((float) ($row['worst_trade_pct'] ?? 0), 2),
        'avg_profit_loss'     => round((float) ($row['avg_pl_amount'] ?? 0), 2),
        'total_profit_loss'   => round((float) ($row['total_pl_amount'] ?? 0), 2),
    ]
]);
?>
