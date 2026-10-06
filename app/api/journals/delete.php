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

$data = json_decode(file_get_contents('php://input'), true);
$journal_id = intval($data['journal_id'] ?? 0);

if ($journal_id <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Journal ID is required']);
    exit;
}

global $wpdb;

$user_id = intval($_SESSION['user_id']);
$table = $wpdb->prefix . 'rich_journals';

$journal = $wpdb->get_row(
    $wpdb->prepare(
        "SELECT id, is_default FROM {$table} WHERE id = %d AND user_id = %d",
        $journal_id,
        $user_id
    ),
    ARRAY_A
);

if (!$journal) {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'Journal not found']);
    exit;
}

$total = (int) $wpdb->get_var(
    $wpdb->prepare("SELECT COUNT(*) FROM {$table} WHERE user_id = %d", $user_id)
);

if ($total <= 1) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'You must keep at least one journal'
    ]);
    exit;
}

require_once __DIR__ . '/../../components/r2-storage.php';

// Hard-delete any R2 media linked in this journal's trades (note or custom columns)
$trades_table = $wpdb->prefix . 'rich_trades';
$custom_values_table = $wpdb->prefix . 'rich_custom_column_values';

$trades_notes = $wpdb->get_col($wpdb->prepare("SELECT note FROM {$trades_table} WHERE journal_id = %d", $journal_id));
$custom_values = $wpdb->get_col($wpdb->prepare(
    "SELECT c.value FROM {$custom_values_table} c INNER JOIN {$trades_table} t ON c.trade_id = t.id WHERE t.journal_id = %d", 
    $journal_id
));

$all_text = implode(' ', $trades_notes ?: []) . ' ' . implode(' ', $custom_values ?: []);
if (preg_match_all('/' . preg_quote(R2_PUBLIC_URL, '/') . '([^\s"\'<]+)/', $all_text, $matches)) {
    foreach ($matches[1] as $r2_path) {
        $r2_path = ltrim($r2_path, '/');
        rich_r2_delete_file($r2_path);
    }
}

$deleted = $wpdb->delete(
    $table,
    ['id' => $journal_id, 'user_id' => $user_id],
    ['%d', '%d']
);

if ($deleted === false) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to delete journal']);
    exit;
}

if ((int) $journal['is_default'] === 1) {
    $next_journal_id = (int) $wpdb->get_var(
        $wpdb->prepare(
            "SELECT id FROM {$table} WHERE user_id = %d ORDER BY created_at ASC LIMIT 1",
            $user_id
        )
    );

    if ($next_journal_id > 0) {
        $wpdb->update(
            $table,
            ['is_default' => 1, 'updated_at' => current_time('mysql')],
            ['id' => $next_journal_id, 'user_id' => $user_id],
            ['%d', '%s'],
            ['%d', '%d']
        );
    }
}

echo json_encode([
    'success' => true,
    'message' => 'Journal deleted successfully'
]);
