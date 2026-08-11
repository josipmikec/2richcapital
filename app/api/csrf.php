<?php
/**
 * CSRF protection helper.
 * Call verify_csrf() at the top of every state-changing endpoint.
 * Token is sent by JS as X-CSRF-Token header (fetch) or csrf_token POST field (forms).
 */
function verify_csrf(): void {
    $session_token = $_SESSION['csrf_token'] ?? '';
    if (empty($session_token)) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'CSRF session token missing.']);
        exit;
    }
    $header_token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if ($header_token) {
        if (!hash_equals($session_token, $header_token)) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Invalid CSRF token.']);
            exit;
        }
        return;
    }
    $post_token = $_POST['csrf_token'] ?? '';
    if (!$post_token || !hash_equals($session_token, $post_token)) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Invalid CSRF token.']);
        exit;
    }
}
