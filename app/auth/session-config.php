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
                            echo "<!DOCTYPE html>
<html lang='en'>
<head>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <title>Maintenance - 2RICH</title>
    <link href='https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700;800&display=swap' rel='stylesheet'>
    <link rel='stylesheet' href='/assets/css/dashboard.css'>
</head>
<body>
    <div class='layout'>
        <header class='topbar'>
            <div class='logo'>
                <div class='logo-icon'></div>
                <div class='logo-text'>2RICH</div>
            </div>
        </header>
        <aside class='sidebar'>
            <ul class='nav-menu'>
                <li class='menu-item active'>
                    <svg width='20' height='20' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2'>
                        <rect x='3' y='3' width='7' height='7'></rect>
                        <rect x='14' y='3' width='7' height='7'></rect>
                        <rect x='14' y='14' width='7' height='7'></rect>
                        <rect x='3' y='14' width='7' height='7'></rect>
                    </svg>
                    <span>Dashboard</span>
                </li>
            </ul>
        </aside>
        <main class='main-content' style='display:flex;align-items:center;justify-content:center;'>
            <div style='text-align:center;padding:40px;border:1px solid #333;border-radius:16px;background:#151515;max-width:400px;width:100%;box-shadow:0 10px 30px rgba(0,0,0,0.5);'>
                <h1 style='color:#f2ca50;margin-top:0;font-family:Montserrat,sans-serif;'>Maintenance</h1>
                <p style='color:#a1a1a1;font-size:15px;line-height:1.5;margin-bottom:24px;'>$msg</p>
                <a href='/logout.php' class='btn-secondary' style='text-decoration:none;display:inline-block;padding:10px 20px;'>Logout</a>
            </div>
        </main>
    </div>
</body>
</html>";
                            exit;
                        }
                    }
                }
            }
        }
    }
}
?>
