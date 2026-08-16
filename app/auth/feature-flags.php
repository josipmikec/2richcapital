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

if (!function_exists('rich_is_staff')) {
    function rich_is_staff($user_id = 0) {
        rich_feature_require_wp();
        $user_id = (int)($user_id ?: ($_SESSION['user_id'] ?? 0));
        if ($user_id <= 0) return false;
        $user = get_userdata($user_id);
        if (!$user) return false;
        return user_can($user, 'manage_options') || user_can($user, 'rich_manage_features');
    }
}

if (!function_exists('rich_feature_enabled')) {
    function rich_feature_enabled($key, $default = true, $user_id = 0) {
        rich_feature_require_wp();
        global $wpdb;
        if (!$wpdb) return (bool)$default;
        $table = rich_feature_table($wpdb);
        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT is_enabled, allowed_roles FROM {$table} WHERE flag_key = %s LIMIT 1",
            rich_normalize_feature_key($key)
        ), ARRAY_A);
        if (!$row) return (bool)$default;
        if ((int)($row['is_enabled'] ?? 0) !== 1) return false;
        $allowed_roles = trim((string)($row['allowed_roles'] ?? ''));
        if ($allowed_roles === '') return true;
        $allowed = array_filter(array_map('sanitize_key', array_map('trim', explode(',', $allowed_roles))));
        if (!$allowed) return true;
        $user_roles = rich_user_role_keys($user_id);
        return count(array_intersect($allowed, $user_roles)) > 0;
    }
}

if (!function_exists('rich_feature_guard')) {
    function rich_feature_guard($key, $label = 'This feature', $user_id = 0) {
        $user_id = (int)($user_id ?: ($_SESSION['user_id'] ?? 0));
        if (rich_is_staff($user_id)) {
            return;
        }
        if (rich_feature_enabled($key, true, $user_id)) {
            return;
        }
        http_response_code(503);
        ?><!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title><?php echo esc_html($label); ?></title><style>body{margin:0;min-height:100vh;display:grid;place-items:center;background:#0e0e0e;color:#f1f1f1;font:16px system-ui,sans-serif}.card{max-width:460px;padding:36px;border:1px solid #292929;border-radius:18px;background:#151515;text-align:center}p{color:#989da5;line-height:1.6}</style></head><body><main class="card"><h1><?php echo esc_html($label); ?> is temporarily unavailable</h1><p>We are making updates. Please check back soon.</p></main></body></html><?php
        exit;
    }
}

if (!function_exists('rich_feature_bootstrap')) {
    function rich_feature_bootstrap() {
        rich_feature_require_wp();
        global $wpdb;
        if (!$wpdb) return;
        $table = rich_feature_table($wpdb);
        $charset = $wpdb->get_charset_collate();
        $sql = "CREATE TABLE IF NOT EXISTS {$table} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            flag_key varchar(100) NOT NULL,
            label varchar(160) NOT NULL,
            description varchar(255) NOT NULL DEFAULT '',
            is_enabled tinyint(1) NOT NULL DEFAULT 1,
            allowed_roles text NULL,
            updated_by bigint(20) unsigned NULL,
            updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id), UNIQUE KEY flag_key (flag_key)
        ) {$charset};";
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);
        $defaults = [
            ['dashboard', 'Dashboard', 'Main member dashboard'],
            ['trading-floor', 'Trading Floor', 'Public trading profile and groups'],
            ['journal', 'Journal', 'Trade journal and history'],
            ['market-data', 'Market Data', 'Charts and live market data'],
            ['mt5-sync', 'MT5 Sync', 'MetaTrader connection and sync'],
            ['signals-groups', 'Signals Groups', 'Signals and group messaging'],
        ];
        foreach ($defaults as $item) {
            $wpdb->query($wpdb->prepare(
                "INSERT IGNORE INTO {$table} (flag_key,label,description,is_enabled) VALUES (%s,%s,%s,1)",
                $item[0], $item[1], $item[2]
            ));
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
