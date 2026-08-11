<?php
ob_start();
define('WP_USE_THEMES', false);
require_once('../../../wp-load.php');
ob_end_clean();

header('Content-Type: application/json');

// Secret key — bot must send this header
define('BOT_SECRET', '2c08820cf91fa7c45403c8772922ccbbd3e874d3a9a135b9d99d91622beb883e');

$incoming_secret = $_SERVER['HTTP_X_BOT_SECRET'] ?? '';
if (!hash_equals(BOT_SECRET, $incoming_secret)) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$body = json_decode(file_get_contents('php://input'), true);

$message    = isset($body['message'])    ? trim($body['message'])    : '';
$author     = isset($body['author'])     ? trim($body['author'])     : null;
$discord_id = isset($body['discord_id']) ? trim($body['discord_id']) : '';

if (empty($message) || empty($discord_id)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Missing message or discord_id']);
    exit;
}

global $wpdb;
$table = $wpdb->prefix . 'rich_news_feed';

// Ignore duplicates silently
$existing = $wpdb->get_var($wpdb->prepare(
    "SELECT id FROM {$table} WHERE discord_id = %s",
    $discord_id
));

if ($existing) {
    echo json_encode(['success' => true, 'message' => 'Already exists']);
    exit;
}

$wpdb->insert($table, [
    'message'    => $message,
    'author'     => $author,
    'discord_id' => $discord_id,
    'created_at' => current_time('mysql')
]);

if ($wpdb->last_error) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'DB error: ' . $wpdb->last_error]);
    exit;
}

echo json_encode(['success' => true, 'id' => $wpdb->insert_id]);
?>
