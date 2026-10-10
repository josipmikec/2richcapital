<?php
ob_start();
require_once 'session-config.php';

define('WP_USE_THEMES', false);
$wp_load = dirname(__DIR__, 2) . '/wp-load.php';
if (!file_exists($wp_load)) {
    ob_end_clean();
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Server bootstrap missing']);
    exit;
}
require_once $wp_load;
ob_end_clean();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$credential = $input['credential'] ?? '';

if (empty($credential)) {
    echo json_encode(['success' => false, 'message' => 'Missing Google token']);
    exit;
}

try {
    // Verify the token with Google
    $ch = curl_init('https://oauth2.googleapis.com/tokeninfo?id_token=' . urlencode($credential));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($http_code !== 200) {
        echo json_encode(['success' => false, 'message' => 'Invalid Google token']);
        exit;
    }

    $payload = json_decode($response, true);
    $email = $payload['email'] ?? '';
    $name = $payload['name'] ?? '';

    if (empty($email)) {
        echo json_encode(['success' => false, 'message' => 'Google account has no email']);
        exit;
    }

    $user = get_user_by('email', $email);

    if (!$user) {
        // Create new user
        $username = sanitize_user(explode('@', $email)[0], true);
        if (username_exists($username)) {
            $username .= '_' . wp_generate_password(4, false);
        }

        $user_id = wp_insert_user([
            'user_login' => $username,
            'user_pass'  => wp_generate_password(24),
            'user_email' => $email,
            'display_name' => $name,
            'first_name' => $payload['given_name'] ?? '',
            'last_name' => $payload['family_name'] ?? '',
            'role'       => 'subscriber'
        ]);

        if (is_wp_error($user_id)) {
            echo json_encode(['success' => false, 'message' => 'Failed to create account']);
            exit;
        }

        // Handle referral tracking
        $ref_code = $_COOKIE['rich_ref'] ?? '';
        if ($ref_code) {
            update_user_meta($user_id, 'rich_referred_by', sanitize_text_field($ref_code));
        }

        $user = get_userdata($user_id);

        // Also create rich_user_profiles entry
        global $wpdb;
        $profiles_table = $wpdb->prefix . 'rich_user_profiles';
        $wpdb->insert($profiles_table, [
            'user_id' => $user->ID,
            'display_name' => $name,
            'avatar_type' => 'url',
            'avatar_url' => $payload['picture'] ?? '',
            'onboarding_completed' => 0
        ]);
    }

    // Login the user
    global $wpdb;
    $profiles_table = $wpdb->prefix . 'rich_user_profiles';
    $profile_display_name = $wpdb->get_var($wpdb->prepare(
        "SELECT display_name FROM {$profiles_table} WHERE user_id = %d LIMIT 1",
        $user->ID
    ));
    $resolved_display_name = $profile_display_name ?: ($user->display_name ?: $user->user_login);

    $_SESSION = array();
    session_regenerate_id(true);

    $_SESSION['user_id'] = $user->ID;
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    $_SESSION['user_email'] = $user->user_email;
    $_SESSION['user_name'] = $resolved_display_name;
    $_SESSION['user_login'] = $user->user_login;
    $_SESSION['login_time'] = time();
    $_SESSION['authenticated'] = true;

    // Remember me cookie for 30 days
    ini_set('session.gc_maxlifetime', 30 * 24 * 60 * 60);
    setcookie(session_name(), session_id(), time() + (30 * 24 * 60 * 60), '/', 'app.2rich.capital', true, true);

    require_once 'feature-flags.php';
    $pages = ['dashboard', 'journal', 'trading-floor', 'market-data'];
    $redirect_path = '/account/';
    foreach ($pages as $p) {
        if (rich_feature_enabled($p, true, $user->ID)) {
            $redirect_path = "/$p/";
            break;
        }
    }

    echo json_encode([
        'success' => true,
        'redirect' => 'https://app.2rich.capital' . $redirect_path
    ]);
    exit;

} catch (Exception $e) {
    error_log("Google Login error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Login failed. Please try again.']);
    exit;
}
