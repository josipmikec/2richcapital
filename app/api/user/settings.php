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

$user_id = intval($_SESSION['user_id']);

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $timezone = get_user_meta($user_id, 'rich_timezone', true) ?: 'UTC';
    $date_format = get_user_meta($user_id, 'rich_date_format', true) ?: 'Y-m-d';
    $time_format = get_user_meta($user_id, 'rich_time_format', true) ?: 'H:i';
    
    echo json_encode([
        'success' => true,
        'settings' => [
            'timezone' => $timezone,
            'date_format' => $date_format,
            'time_format' => $time_format
        ]
    ]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    
    $input = json_decode(file_get_contents('php://input'), true);
    if (!$input) $input = $_POST;
    
    if (isset($input['timezone'])) {
        update_user_meta($user_id, 'rich_timezone', sanitize_text_field($input['timezone']));
    }
    if (isset($input['date_format'])) {
        update_user_meta($user_id, 'rich_date_format', sanitize_text_field($input['date_format']));
    }
    if (isset($input['time_format'])) {
        update_user_meta($user_id, 'rich_time_format', sanitize_text_field($input['time_format']));
    }
    
    echo json_encode(['success' => true, 'message' => 'Settings saved']);
    exit;
}

http_response_code(405);
echo json_encode(['success' => false, 'message' => 'Method not allowed']);
