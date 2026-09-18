<?php
/**
 * Feature flags and staff access helpers.
 */

if (!function_exists('rich_feature_require_wp')) {
    function rich_feature_require_wp() {
        if (function_exists('get_userdata') && function_exists('get_role')) {
            return;
        }
        if (!defined('WP_USE_THEMES')) {
            define('WP_USE_THEMES', false);
        }
        $wp_load = dirname(__DIR__, 2) . '/wp-load.php';
        if (file_exists($wp_load)) {
            require_once $wp_load;
        }
    }
}

if (!function_exists('rich_normalize_feature_key')) {
    function rich_normalize_feature_key($key) {
        $key = strtolower(trim((string)$key));
        $key = preg_replace('/[^a-z0-9\-_]+/', '-', $key);
        return trim((string)$key, '-_');
    }
}

if (!function_exists('rich_feature_roles')) {
    function rich_feature_roles() {
        rich_feature_require_wp();
        $roles = [];
        if (function_exists('wp_roles')) {
            $wp_roles = wp_roles();
            if ($wp_roles && !empty($wp_roles->roles)) {
                foreach ($wp_roles->roles as $role_key => $role_data) {
                    $roles[$role_key] = $role_data['name'] ?? $role_key;
                }
            }
        }
        return $roles;
    }
}

if (!function_exists('rich_user_role_keys')) {
    function rich_user_role_keys($user_id = 0) {
        rich_feature_require_wp();
        $user_id = (int)($user_id ?: ($_SESSION['user_id'] ?? 0));
        if ($user_id <= 0) return [];
        $user = get_userdata($user_id);
        if (!$user || empty($user->roles) || !is_array($user->roles)) return [];
        return array_values(array_map('sanitize_key', $user->roles));
    }
}

if (!function_exists('rich_feature_table')) {
    function rich_feature_table($wpdb) {
        return $wpdb->prefix . 'rich_feature_flags';
    }
}

if (!function_exists('rich_feature_table_candidates')) {
    function rich_feature_table_candidates($wpdb) {
        $primary = rich_feature_table($wpdb);
        $candidates = [$primary];

        $fallbacks = [
            'wp_rich_feature_flags',
            'rich_feature_flags',
        ];

        foreach ($fallbacks as $table_name) {
            if (!in_array($table_name, $candidates, true)) {
                $candidates[] = $table_name;
            }
        }

        return $candidates;
    }
}

if (!function_exists('rich_find_feature_table')) {
    function rich_find_feature_table($wpdb) {
        foreach (rich_feature_table_candidates($wpdb) as $table_name) {
            $exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table_name));
            if ($exists === $table_name) {
                return $table_name;
            }
        }
        return rich_feature_table($wpdb);
    }
}

if (!function_exists('rich_get_feature_row')) {
    function rich_get_feature_row($key) {
        rich_feature_require_wp();
        global $wpdb;
        if (!$wpdb) return null;
        $table = rich_find_feature_table($wpdb);
        return $wpdb->get_row($wpdb->prepare(
            "SELECT flag_key, label, is_enabled, allowed_roles, is_overlay_enabled, overlay_message FROM {$table} WHERE flag_key = %s LIMIT 1",
            rich_normalize_feature_key($key)
        ), ARRAY_A);
    }
}

if (!function_exists('rich_is_staff')) {
    function rich_is_staff($user_id = 0) {
        rich_feature_require_wp();
        $user_id = (int)($user_id ?: ($_SESSION['user_id'] ?? 0));
        if ($user_id <= 0) return false;

        $user = get_userdata($user_id);
        if (!$user) return false;

        return user_can($user, 'manage_options')
            || user_can($user, 'rich_manage_features')
            || in_array('administrator', (array)$user->roles, true);
    }
}

if (!function_exists('rich_feature_enabled')) {
    function rich_feature_enabled($key, $default = true, $user_id = 0) {
        $row = rich_get_feature_row($key);
        if (!$row) return (bool)$default;

        if ((int)($row['is_enabled'] ?? 0) !== 1) {
            return false;
        }

        $allowed_roles = trim((string)($row['allowed_roles'] ?? ''));
        if ($allowed_roles === '') {
            return true;
        }

        $allowed = array_filter(array_map('sanitize_key', array_map('trim', explode(',', $allowed_roles))));
        if (!$allowed) {
            return true;
        }

        $user_roles = rich_user_role_keys($user_id);
        if (!$user_roles) {
            return false;
        }

        return count(array_intersect($allowed, $user_roles)) > 0;
    }
}

if (!function_exists('rich_feature_overlay')) {
    function rich_feature_overlay($key, $user_id = 0) {
        $row = rich_get_feature_row($key);
        if (!$row) return null;

        if ((int)($row['is_overlay_enabled'] ?? 0) !== 1) {
            return null;
        }

        $message = trim((string)($row['overlay_message'] ?? 'This feature is temporarily unavailable.'));
        return [
            'enabled' => true,
            'message' => $message !== '' ? $message : 'This feature is temporarily unavailable.',
        ];
    }
}

if (!function_exists('rich_card_visible')) {
    function rich_card_visible($card_id, $user_id = 0) {
        $card_id = rich_normalize_feature_key($card_id);
        $flag_key = 'card-' . $card_id;
        $row = rich_get_feature_row($flag_key);
        
        if (!$row) {
            return true;
        }
        
        if ((int)($row['is_enabled'] ?? 0) !== 1) {
            return false;
        }
        
        $allowed_roles = trim((string)($row['allowed_roles'] ?? ''));
        if ($allowed_roles === '') {
            return true;
        }
        
        $allowed = array_filter(array_map('sanitize_key', array_map('trim', explode(',', $allowed_roles))));
        if (!$allowed) {
            return true;
        }
        
        $user_roles = rich_user_role_keys($user_id);
        if (!$user_roles) {
            return false;
        }
        
        return count(array_intersect($allowed, $user_roles)) > 0;
    }
}

if (!function_exists('rich_api_feature_guard')) {
    function rich_api_feature_guard($key, $user_id = 0) {
        $user_id = (int)($user_id ?: ($_SESSION['user_id'] ?? 0));
        $row = rich_get_feature_row($key);

        if (!$row) {
            return;
        }

        if (rich_is_staff($user_id)) {
            return;
        }

        if ((int)($row['is_enabled'] ?? 0) !== 1) {
            http_response_code(503);
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => 'This feature is temporarily unavailable.']);
            exit;
        }

        $allowed_roles = trim((string)($row['allowed_roles'] ?? ''));
        if ($allowed_roles === '') {
            return;
        }

        $allowed = array_filter(array_map('sanitize_key', array_map('trim', explode(',', $allowed_roles))));
        if (!$allowed) {
            return;
        }

        $user_roles = rich_user_role_keys($user_id);
        if (!$user_roles || count(array_intersect($allowed, $user_roles)) === 0) {
            http_response_code(403);
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => 'This feature is not available for your account.']);
            exit;
        }
    }
}

if (!function_exists('rich_feature_guard')) {
    function rich_feature_guard($key, $label = 'This feature', $user_id = 0) {
        $user_id = (int)($user_id ?: ($_SESSION['user_id'] ?? 0));
        $row = rich_get_feature_row($key);

        if (!$row) {
            return;
        }

        if (rich_is_staff($user_id)) {
            return;
        }

        if ((int)($row['is_enabled'] ?? 0) !== 1) {
            http_response_code(503);
            $msg = 'We are making updates. Please check back soon.';
            $email = htmlspecialchars($_SESSION['user_email'] ?? 'Member');
            echo "<!DOCTYPE html>
<html lang='en'>
<head>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <title>" . esc_html($label) . " - 2RICH</title>
    <link href='https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700;800&display=swap' rel='stylesheet'>
    <link rel='stylesheet' href='/assets/css/dashboard.css'>
</head>
<body>
    <div class='dashboard-background'></div>
    <nav class='top-nav'>
        <div class='nav-container'>
            <div class='nav-brand'>
                <h1>2RICH CAPITAL</h1>
                <span class='nav-tagline'>INSTITUTIONAL GRADE TRADING</span>
            </div>
            <div class='nav-right'>
                <span class='user-email'>$email</span>
                <a href='/auth/logout.php' class='logout-btn'>LOGOUT</a>
            </div>
        </div>
    </nav>
    <div class='dashboard-container'>
        <aside class='sidebar'>
            <ul class='sidebar-menu'>
                <li class='menu-item' style='opacity:0.5;cursor:not-allowed;'>
                    <svg width='20' height='20' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2'><rect x='3' y='3' width='7' height='7'></rect><rect x='14' y='3' width='7' height='7'></rect><rect x='14' y='14' width='7' height='7'></rect><rect x='3' y='14' width='7' height='7'></rect></svg>
                    <span>Dashboard</span>
                </li>
                <li class='menu-item' style='opacity:0.5;cursor:not-allowed;'>
                    <svg width='20' height='20' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2'><path d='M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z'></path><polyline points='14 2 14 8 20 8'></polyline><line x1='16' y1='13' x2='8' y2='13'></line><line x1='16' y1='17' x2='8' y2='17'></line><polyline points='10 9 9 9 8 9'></polyline></svg>
                    <span>Trading Journal</span>
                </li>
                <li class='menu-item' style='opacity:0.5;cursor:not-allowed;'>
                    <svg width='20' height='20' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2'><line x1='12' y1='1' x2='12' y2='23'></line><path d='M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6'></path></svg>
                    <span>Trading Floor</span>
                </li>
                <li class='menu-item' style='opacity:0.5;cursor:not-allowed;'>
                    <svg width='20' height='20' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2'><rect x='2' y='3' width='20' height='14' rx='2' ry='2'></rect><line x1='8' y1='21' x2='16' y2='21'></line><line x1='12' y1='17' x2='12' y2='21'></line></svg>
                    <span>Market Data</span>
                </li>
                <li class='menu-item' style='opacity:0.5;cursor:not-allowed;'>
                    <svg width='20' height='20' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2'><path d='M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2'></path><circle cx='12' cy='7' r='4'></circle></svg>
                    <span>Account</span>
                </li>
            </ul>
        </aside>
        <main class='main-content' style='display:flex;align-items:center;justify-content:center;min-height:80vh;'>
            <div style='text-align:center;padding:40px;border:1px solid #333;border-radius:16px;background:#151515;max-width:400px;width:100%;box-shadow:0 10px 30px rgba(0,0,0,0.5);'>
                <h1 style='color:#f2ca50;margin-top:0;margin-bottom:16px;font-family:Montserrat,sans-serif;font-size:24px;'>" . esc_html($label) . " Unavailable</h1>
                <p style='color:#a1a1a1;font-size:15px;line-height:1.5;margin-bottom:0;'>$msg</p>
                <a href='/dashboard/' class='btn-secondary' style='text-decoration:none;display:inline-block;padding:10px 20px;margin-top:24px;'>Back to Dashboard</a>
            </div>
        </main>
    </div>
</body>
</html>";
            exit;
        }

        $allowed_roles = trim((string)($row['allowed_roles'] ?? ''));
        if ($allowed_roles === '') {
            return;
        }

        $allowed = array_filter(array_map('sanitize_key', array_map('trim', explode(',', $allowed_roles))));
        if (!$allowed) {
            return;
        }

        $user_roles = rich_user_role_keys($user_id);
        if (!$user_roles || count(array_intersect($allowed, $user_roles)) === 0) {
            http_response_code(403);
            $msg = 'Please contact support if you believe this is a mistake.';
            $email = htmlspecialchars($_SESSION['user_email'] ?? 'Member');
            echo "<!DOCTYPE html>
<html lang='en'>
<head>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <title>" . esc_html($label) . " - 2RICH</title>
    <link href='https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700;800&display=swap' rel='stylesheet'>
    <link rel='stylesheet' href='/assets/css/dashboard.css'>
</head>
<body>
    <div class='dashboard-background'></div>
    <nav class='top-nav'>
        <div class='nav-container'>
            <div class='nav-brand'>
                <h1>2RICH CAPITAL</h1>
                <span class='nav-tagline'>INSTITUTIONAL GRADE TRADING</span>
            </div>
            <div class='nav-right'>
                <span class='user-email'>$email</span>
                <a href='/auth/logout.php' class='logout-btn'>LOGOUT</a>
            </div>
        </div>
    </nav>
    <div class='dashboard-container'>
        <aside class='sidebar'>
            <ul class='sidebar-menu'>
                <li class='menu-item' style='opacity:0.5;cursor:not-allowed;'>
                    <svg width='20' height='20' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2'><rect x='3' y='3' width='7' height='7'></rect><rect x='14' y='3' width='7' height='7'></rect><rect x='14' y='14' width='7' height='7'></rect><rect x='3' y='14' width='7' height='7'></rect></svg>
                    <span>Dashboard</span>
                </li>
                <li class='menu-item' style='opacity:0.5;cursor:not-allowed;'>
                    <svg width='20' height='20' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2'><path d='M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z'></path><polyline points='14 2 14 8 20 8'></polyline><line x1='16' y1='13' x2='8' y2='13'></line><line x1='16' y1='17' x2='8' y2='17'></line><polyline points='10 9 9 9 8 9'></polyline></svg>
                    <span>Trading Journal</span>
                </li>
                <li class='menu-item' style='opacity:0.5;cursor:not-allowed;'>
                    <svg width='20' height='20' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2'><line x1='12' y1='1' x2='12' y2='23'></line><path d='M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6'></path></svg>
                    <span>Trading Floor</span>
                </li>
                <li class='menu-item' style='opacity:0.5;cursor:not-allowed;'>
                    <svg width='20' height='20' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2'><rect x='2' y='3' width='20' height='14' rx='2' ry='2'></rect><line x1='8' y1='21' x2='16' y2='21'></line><line x1='12' y1='17' x2='12' y2='21'></line></svg>
                    <span>Market Data</span>
                </li>
                <li class='menu-item' style='opacity:0.5;cursor:not-allowed;'>
                    <svg width='20' height='20' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2'><path d='M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2'></path><circle cx='12' cy='7' r='4'></circle></svg>
                    <span>Account</span>
                </li>
            </ul>
        </aside>
        <main class='main-content' style='display:flex;align-items:center;justify-content:center;min-height:80vh;'>
            <div style='text-align:center;padding:40px;border:1px solid #333;border-radius:16px;background:#151515;max-width:400px;width:100%;box-shadow:0 10px 30px rgba(0,0,0,0.5);'>
                <h1 style='color:#f2ca50;margin-top:0;margin-bottom:16px;font-family:Montserrat,sans-serif;font-size:24px;'>Access Denied</h1>
                <p style='color:#a1a1a1;font-size:15px;line-height:1.5;margin-bottom:0;'>$msg</p>
                <a href='/dashboard/' class='btn-secondary' style='text-decoration:none;display:inline-block;padding:10px 20px;margin-top:24px;'>Back to Dashboard</a>
            </div>
        </main>
    </div>
</body>
</html>";
            exit;
        }
    }
}

if (!function_exists('rich_feature_bootstrap')) {
    function rich_feature_bootstrap() {
        rich_feature_require_wp();
        global $wpdb;
        if (!$wpdb) return;

        $charset = $wpdb->get_charset_collate();
        $tables = rich_feature_table_candidates($wpdb);

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        foreach ($tables as $table) {
            $sql = "CREATE TABLE {$table} (
                id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                flag_key varchar(100) NOT NULL,
                label varchar(160) NOT NULL,
                description varchar(255) NOT NULL DEFAULT '',
                is_enabled tinyint(1) NOT NULL DEFAULT 1,
                allowed_roles text NULL,
                is_overlay_enabled tinyint(1) NOT NULL DEFAULT 0,
                overlay_message varchar(255) NOT NULL DEFAULT 'This feature is temporarily unavailable.',
                updated_by bigint(20) unsigned NULL,
                updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id), UNIQUE KEY flag_key (flag_key)
            ) {$charset};";
            dbDelta($sql);
        }

        $defaults = [
            ['dashboard', 'Dashboard', 'Main member dashboard'],
            ['trading-floor', 'Trading Floor', 'Public trading profile and groups'],
            ['journal', 'Journal', 'Trade journal and history'],
            ['market-data', 'Market Data', 'Charts and live market data'],
            ['mt5-sync', 'MT5 Sync', 'MetaTrader connection and sync'],
            ['signals-groups', 'Signals Groups', 'Signals and group messaging'],
            ['card-market', 'Market Card', 'Market overview card on dashboard'],
            ['card-signals', 'Signals Card', 'Signals card on dashboard'],
            ['card-news', 'News Card', 'News card on dashboard'],
            ['card-classroom', 'Classroom Card', 'Classroom card on dashboard'],
            ['card-strategies', 'Strategies Card', 'Strategies card on dashboard'],
            ['card-trades', 'Trades Card', 'Trades card on dashboard'],
            ['card-mentors', 'Mentors Card', 'Mentors card on dashboard'],
            ['card-ai', 'AI Card', 'AI card on dashboard'],
            ['card-chat', 'Chat Card', 'Chat card on dashboard'],
            ['card-chat-group', 'Chat Group Tab', 'Joined group chat tab overlay on dashboard'],
            ['card-chat-private', 'Chat Private Tab', 'Private chats tab overlay on dashboard'],
            ['card-journal', 'Journal Card', 'Journal card on dashboard'],
        ];

        foreach ($tables as $table) {
            foreach ($defaults as $item) {
                $wpdb->query($wpdb->prepare(
                    "INSERT IGNORE INTO {$table} (flag_key,label,description,is_enabled) VALUES (%s,%s,%s,1)",
                    $item[0], $item[1], $item[2]
                ));
            }
        }
    }
}

if (!function_exists('rich_grant_feature_capability')) {
    function rich_grant_feature_capability() {
        rich_feature_require_wp();
        if (!function_exists('get_role')) return;
        $role = get_role('administrator');
        if ($role) $role->add_cap('rich_manage_features');
    }
}
?>
