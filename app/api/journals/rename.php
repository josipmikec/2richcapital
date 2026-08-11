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
$name = trim($data['name'] ?? '');

if ($journal_id <= 0 || $name === '') {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Journal ID and name are required']);
    exit;
}

global $wpdb;

$user_id = intval($_SESSION['user_id']);
$table = $wpdb->prefix . 'rich_journals';

$owned = $wpdb->get_var(
    $wpdb->prepare(
        "SELECT id FROM {$table} WHERE id = %d AND user_id = %d",
        $journal_id,
        $user_id
    )
);

if (!$owned) {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'Journal not found']);
    exit;
}

$updated = $wpdb->update(
    $table,
    [
        'name'       => $name,
        'updated_at' => current_time('mysql')
    ],
    [
        'id'      => $journal_id,
        'user_id' => $user_id
    ],
    ['%s', '%s'],
    ['%d', '%d']
);

if ($updated === false) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to rename journal']);
    exit;
}

echo json_encode([
    'success' => true,
    'message' => 'Journal renamed successfully'
]);
