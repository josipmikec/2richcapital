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

$name = trim($data['name'] ?? '');

if ($name === '') {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Journal name is required']);
    exit;
}

global $wpdb;

$user_id = intval($_SESSION['user_id']);
$table = $wpdb->prefix . 'rich_journals';

$has_journals = (int) $wpdb->get_var(
    $wpdb->prepare("SELECT COUNT(*) FROM {$table} WHERE user_id = %d", $user_id)
);

$is_default = $has_journals === 0 ? 1 : 0;

$inserted = $wpdb->insert(
    $table,
    [
        'user_id'        => $user_id,
        'name'           => $name,
        'broker'         => '',
        'account_number' => '',
        'platform'       => '',
        'is_default'     => $is_default,
        'created_at'     => current_time('mysql'),
        'updated_at'     => current_time('mysql')
    ],
    ['%d', '%s', '%s', '%s', '%s', '%d', '%s', '%s']
);

if ($inserted === false) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to create journal']);
    exit;
}

echo json_encode([
    'success'    => true,
    'journal_id' => $wpdb->insert_id,
    'message'    => 'Journal created successfully'
]);
