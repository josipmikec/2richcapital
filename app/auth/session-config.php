<?php
// Central session configuration for app.2rich.capital
// Include this file at the TOP of every PHP file that uses sessions

if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'secure'   => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'httponly' => true,
        'samesite' => 'Lax',
    ]);

    ini_set('session.use_strict_mode', 1);
    ini_set('session.gc_maxlifetime', 3600);  // 1 hour

    // Custom session name to avoid conflicts
    session_name('TWORICH_SESSION');

    if (!headers_sent()) {
        session_start();
    }
}

// Generate CSRF token once per session
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// ---------------------------------------------------------
// Global Shield (App Shutdown) Enforcement
// ---------------------------------------------------------
if (isset($_SESSION['user_id'])) {
    $shield_uri = $_SERVER['REQUEST_URI'] ?? '';
    // Let users access auth routes (so admins can log in and out)
    if (strpos($shield_uri, '/login/') === false && strpos($shield_uri, '/register/') === false && strpos($shield_uri, '/auth/') === false) {
        $shield_file_db = __DIR__ . '/db.php';
        if (file_exists($shield_file_db)) {
            require_once $shield_file_db;
            if (isset($pdo)) {
                // Try to find if shield is enabled
                $shield_row = null;
                try {
                    $stmt = $pdo->prepare("SELECT is_enabled, overlay_message FROM wp_rich_feature_flags WHERE flag_key = 'global-shield' LIMIT 1");
                    $stmt->execute();
                    $shield_row = $stmt->fetch(PDO::FETCH_ASSOC);
                } catch (Exception $e) {
                    try {
                        $stmt = $pdo->prepare("SELECT is_enabled, overlay_message FROM rich_feature_flags WHERE flag_key = 'global-shield' LIMIT 1");
                        $stmt->execute();
                        $shield_row = $stmt->fetch(PDO::FETCH_ASSOC);
                    } catch (Exception $e2) {
                        // ignore if tables don't exist
                    }
                }
                
                if ($shield_row && (int)$shield_row['is_enabled'] === 1) {
                    // Shield is ON. Check if current user is admin.
                    $is_admin = false;
                    try {
                        $stmt_admin = $pdo->prepare("SELECT meta_value FROM wp_usermeta WHERE user_id = ? AND meta_key = 'wp_capabilities' LIMIT 1");
                        $stmt_admin->execute([(int)$_SESSION['user_id']]);
                        $caps = $stmt_admin->fetchColumn();
                        if ($caps) {
                            $unserialized = @unserialize($caps);
                            if (is_array($unserialized) && !empty($unserialized['administrator'])) {
                                $is_admin = true;
                            }
                        }
                    } catch (Exception $e) {}
                    
                    if (!$is_admin) {
                        // Is API request?
                        if (strpos($shield_uri, '/api/') !== false) {
                            http_response_code(503);
                            header('Content-Type: application/json');
                            echo json_encode(['success' => false, 'error' => 'App is in maintenance mode.']);
                            exit;
                        } else {
                            http_response_code(503);
                            $msg = htmlspecialchars($shield_row['overlay_message'] ?: 'We are currently performing maintenance. Please check back later.');
                            echo "<!doctype html><html lang='en'><head><title>Maintenance</title><style>body{background:#0e0e0e;color:#f1f1f1;font-family:system-ui;display:grid;place-items:center;height:100vh;margin:0;}div{text-align:center;padding:40px;border:1px solid #333;border-radius:16px;background:#151515;max-width:400px;}h1{color:#f2ca50;margin-top:0;}</style></head><body><div><h1>Maintenance</h1><p>$msg</p></div></body></html>";
                            exit;
                        }
                    }
                }
            }
        }
    }
}
?>
