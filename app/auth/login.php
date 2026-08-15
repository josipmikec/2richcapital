<?php
// Prevent any output before JSON
ob_start();

// Load session configuration
require_once 'session-config.php';

// Load WordPress
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

// Clear any accidental output
ob_end_clean();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$email = trim($_POST['email'] ?? '');
$password = $_POST['password'] ?? '';
$remember = isset($_POST['remember']);

if (empty($email) || empty($password)) {
    echo json_encode(['success' => false, 'message' => 'Email and password required']);
    exit;
}

try {
    // Try to get user by email
    $user = get_user_by('email', $email);
    
    // If not found by email, try by username
    if (!$user) {
        $user = get_user_by('login', $email);
    }
    
    if (!$user) {
        echo json_encode(['success' => false, 'message' => 'Invalid credentials']);
        exit;
    }
    
    // Verify password using WordPress function
    if (!wp_check_password($password, $user->user_pass, $user->ID)) {
        echo json_encode(['success' => false, 'message' => 'Invalid credentials']);
        exit;
    }
    
    // Resolve preferred display name from app profile first, then fall back to WordPress user fields
    global $wpdb;
    $profiles_table = $wpdb->prefix . 'rich_user_profiles';
    $profile_display_name = $wpdb->get_var($wpdb->prepare(
        "SELECT display_name FROM {$profiles_table} WHERE user_id = %d LIMIT 1",
        $user->ID
    ));
    $resolved_display_name = $profile_display_name ?: ($user->display_name ?: $user->user_login);

    // Clear any existing session data first
    $_SESSION = array();
    
    // Regenerate session ID for security
    session_regenerate_id(true);
    
    // Set new session data
    $_SESSION['user_id'] = $user->ID;
    $_SESSION['user_email'] = $user->user_email;
    $_SESSION['user_name'] = $resolved_display_name;
    $_SESSION['user_login'] = $user->user_login;
    $_SESSION['login_time'] = time();
    $_SESSION['authenticated'] = true;
    
    if ($remember) {
        ini_set('session.gc_maxlifetime', 30 * 24 * 60 * 60);
        setcookie(session_name(), session_id(), time() + (30 * 24 * 60 * 60), '/', 'app.2rich.capital', true, true);
    }
    
    echo json_encode([
        'success' => true,
        'redirect' => 'https://app.2rich.capital/dashboard/'
    ]);
    exit;

} catch (Exception $e) {
    error_log("Login error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Login failed. Please try again.']);
    exit;
}
?>