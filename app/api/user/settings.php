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
    
    global $wpdb;
    $profile_table = $wpdb->prefix . 'rich_user_profiles';
    $profile = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$profile_table} WHERE user_id = %d LIMIT 1", $user_id), ARRAY_A);
    
    $notifs_json = get_user_meta($user_id, 'notification_prefs', true);
    $notifs = $notifs_json ? json_decode($notifs_json, true) : [];
    
    echo json_encode([
        'success' => true,
        'settings' => [
            'timezone' => $timezone,
            'date_format' => $date_format,
            'time_format' => $time_format
        ],
        'profile' => $profile,
        'notifs' => $notifs
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
    
    if (isset($input['profile']) && is_array($input['profile'])) {
        global $wpdb;
        $profile_table = $wpdb->prefix . 'rich_user_profiles';
        $existing = $wpdb->get_row($wpdb->prepare("SELECT id FROM {$profile_table} WHERE user_id = %d LIMIT 1", $user_id));
        
        $data = [];
        if (isset($input['profile']['trading_handle'])) $data['trading_handle'] = sanitize_text_field($input['profile']['trading_handle']);
        if (isset($input['profile']['primary_market'])) $data['primary_market'] = sanitize_text_field($input['profile']['primary_market']);
        if (isset($input['profile']['trading_style'])) $data['trading_style'] = sanitize_text_field($input['profile']['trading_style']);
        
        if (!empty($data)) {
            $data['updated_at'] = current_time('mysql', 1);
            if ($existing) {
                $wpdb->update($profile_table, $data, ['user_id' => $user_id]);
            } else {
                $data['user_id'] = $user_id;
                $data['created_at'] = current_time('mysql', 1);
                $wpdb->insert($profile_table, $data);
            }
        }
    }
    
    echo json_encode(['success' => true, 'message' => 'Settings saved']);
    exit;
}

http_response_code(405);
echo json_encode(['success' => false, 'message' => 'Method not allowed']);
