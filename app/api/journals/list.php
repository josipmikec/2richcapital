<?php
ob_start();
require_once '../../auth/session-config.php';
define('WP_USE_THEMES', false);
require_once('../../../wp-load.php');
ob_end_clean();

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || !isset($_SESSION['authenticated'])) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'message' => 'Unauthorized'
    ]);
    exit;
}

global $wpdb;

$user_id = intval($_SESSION['user_id']);
$table = $wpdb->prefix . 'rich_journals';

$journals = $wpdb->get_results(
    $wpdb->prepare(
        "SELECT id, name, broker, account_number, platform, is_default, created_at, updated_at
         FROM {$table}
         WHERE user_id = %d
         ORDER BY is_default DESC, created_at ASC",
        $user_id
    ),
    ARRAY_A
);

if (empty($journals)) {
    $inserted = $wpdb->insert(
        $table,
        [
            'user_id' => $user_id,
            'name' => 'Main Journal',
            'broker' => '',
            'account_number' => '',
            'platform' => '',
            'is_default' => 1,
            'created_at' => current_time('mysql'),
            'updated_at' => current_time('mysql')
        ],
        ['%d', '%s', '%s', '%s', '%s', '%d', '%s', '%s']
    );

    if ($inserted === false) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Failed to create default journal'
        ]);
        exit;
    }

    $journals = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT id, name, broker, account_number, platform, is_default, created_at, updated_at
             FROM {$table}
             WHERE user_id = %d
             ORDER BY is_default DESC, created_at ASC",
            $user_id
        ),
        ARRAY_A
    );
}

echo json_encode([
    'success' => true,
    'journals' => $journals ?: []
]);
