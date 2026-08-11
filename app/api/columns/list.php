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
$user_id = (int) $_SESSION['user_id'];

$visibility_meta = get_user_meta($user_id, 'rich_column_visibility', true);
$visibility      = ($visibility_meta && is_string($visibility_meta))
    ? (json_decode($visibility_meta, true) ?: [])
    : [];

$builtin_columns = [
    ['key' => 'date',               'name' => 'Date',        'type' => 'builtin', 'locked' => true,  'visible' => true,                                    'order' => 1],
    ['key' => 'symbol',             'name' => 'Symbol',      'type' => 'builtin', 'locked' => true,  'visible' => true,                                    'order' => 2],
    ['key' => 'direction',          'name' => 'Direction',   'type' => 'builtin', 'locked' => false, 'visible' => $visibility['direction']          ?? true,  'order' => 3],
    ['key' => 'session',            'name' => 'Session',     'type' => 'builtin', 'locked' => false, 'visible' => $visibility['session']            ?? true,  'order' => 4],
    ['key' => 'entry_price',        'name' => 'Entry',       'type' => 'builtin', 'locked' => false, 'visible' => $visibility['entry_price']        ?? true,  'order' => 5],
    ['key' => 'exit_price',         'name' => 'Exit',        'type' => 'builtin', 'locked' => false, 'visible' => $visibility['exit_price']         ?? true,  'order' => 6],
    ['key' => 'profit_loss',        'name' => 'P&L $',       'type' => 'builtin', 'locked' => false, 'visible' => $visibility['profit_loss']        ?? false, 'order' => 7],
    ['key' => 'profit_loss_pct',    'name' => 'P&L %',       'type' => 'builtin', 'locked' => false, 'visible' => $visibility['profit_loss_pct']    ?? true,  'order' => 8],
    ['key' => 'outcome',            'name' => 'Outcome',     'type' => 'builtin', 'locked' => false, 'visible' => $visibility['outcome']            ?? true,  'order' => 9],
    ['key' => 'strategy_type',      'name' => 'Strategy',    'type' => 'builtin', 'locked' => false, 'visible' => $visibility['strategy_type']      ?? false, 'order' => 10],
    ['key' => 'imbalance_size_pct', 'name' => 'Imbalance %', 'type' => 'builtin', 'locked' => false, 'visible' => $visibility['imbalance_size_pct'] ?? false, 'order' => 11],
    ['key' => 'fill_time_bars',     'name' => 'Fill Time',   'type' => 'builtin', 'locked' => false, 'visible' => $visibility['fill_time_bars']     ?? false, 'order' => 12],
    ['key' => 'w_histogram',        'name' => 'W Histogram', 'type' => 'builtin', 'locked' => false, 'visible' => $visibility['w_histogram']        ?? false, 'order' => 13],
    ['key' => 'm_histogram',        'name' => 'M Histogram', 'type' => 'builtin', 'locked' => false, 'visible' => $visibility['m_histogram']        ?? false, 'order' => 14],
    ['key' => 'vix_moment',         'name' => 'VIX Entry',   'type' => 'builtin', 'locked' => false, 'visible' => $visibility['vix_moment']         ?? false, 'order' => 15],
    ['key' => 'stop_triggered',     'name' => 'Stop Hit',    'type' => 'builtin', 'locked' => false, 'visible' => $visibility['stop_triggered']     ?? false, 'order' => 16],
    ['key' => 'actions',            'name' => 'Actions',     'type' => 'builtin', 'locked' => true,  'visible' => true,                                    'order' => 999],
];

$custom_columns_table = $wpdb->prefix . 'rich_custom_columns';
$custom_columns = [];

$wpdb->suppress_errors(true);
$result = $wpdb->get_results($wpdb->prepare(
    "SELECT id, column_name, data_type, select_options, display_order
     FROM {$custom_columns_table}
     WHERE user_id = %d
     ORDER BY display_order ASC",
    $user_id
), ARRAY_A);
$wpdb->suppress_errors(false);

if (!empty($result)) {
    $custom_columns = $result;
}

$formatted_custom = [];
foreach ($custom_columns as $col) {
    $formatted_custom[] = [
        'key'            => 'custom_' . (int) $col['id'],
        'id'             => (int) $col['id'],
        'name'           => $col['column_name'],
        'type'           => 'custom',
        'data_type'      => $col['data_type'],
        'select_options' => $col['select_options'] ? json_decode($col['select_options'], true) : null,
        'locked'         => false,
        'visible'        => $visibility['custom_' . (int) $col['id']] ?? true,
        'order'          => (int) $col['display_order'],
    ];
}

$all_columns = array_merge($builtin_columns, $formatted_custom);
usort($all_columns, function($a, $b) {
    return $a['order'] - $b['order'];
});

echo json_encode([
    'success' => true,
    'columns' => $all_columns
]);
?>
