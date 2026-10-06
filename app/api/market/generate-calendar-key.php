<?php
// app/api/market/generate-calendar-key.php
require_once dirname(__DIR__, 3) . '/wp-load.php';

header('Content-Type: application/json');

if (!is_user_logged_in()) {
    http_response_code(401);
    die(json_encode(['success' => false, 'error' => 'Not logged in']));
}

$user_id = get_current_user_id();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Generate a new 32-character hex API key
    $api_key = 'cal_' . bin2hex(random_bytes(16));
    update_user_meta($user_id, '2rich_calendar_api_key', $api_key);
    echo json_encode(['success' => true, 'api_key' => $api_key]);
} else {
    // Just fetch the existing key
    $existing = get_user_meta($user_id, '2rich_calendar_api_key', true);
    echo json_encode(['success' => true, 'api_key' => $existing ?: null]);
}
