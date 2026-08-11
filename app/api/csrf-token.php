<?php
ob_start();
require_once '../auth/session-config.php';
define('WP_USE_THEMES', false);
require_once('../../wp-load.php');
ob_end_clean();

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || !isset($_SESSION['authenticated'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

echo json_encode(['success' => true, 'token' => $_SESSION['csrf_token']]);
