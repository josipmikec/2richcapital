<?php
require_once '../auth/session-config.php';
define('WP_USE_THEMES', false);
require_once dirname(__DIR__, 2) . '/wp-load.php';
require_once '../auth/feature-flags.php';

rich_grant_feature_capability();

if (!isset($_SESSION['user_id']) || !isset($_SESSION['authenticated'])) {
    header('Location: /login/'); exit;
}
if (!rich_is_staff()) {
    http_response_code(403);
    header('Location: /dashboard/'); exit;
}

rich_feature_bootstrap();
global $wpdb;
$table = rich_feature_table($wpdb);
$resolved_table = rich_find_feature_table($wpdb);
$flash = '';
$roles = rich_feature_roles();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['admin_nonce']) || !wp_verify_nonce($_POST['admin_nonce'], 'rich_admin_features')) {
        $flash = 'Security check failed.';
    } else {
        $action = sanitize_key($_POST['feature_action'] ?? '');

        if ($action === 'toggle') {
            $key = rich_normalize_feature_key($_POST['flag_key'] ?? '');
            $enabled = !empty($_POST['is_enabled']) ? 1 : 0;
            if ($key !== '') {
                $wpdb->update($resolved_table, ['is_enabled' => $enabled, 'updated_by' => (int)$_SESSION['user_id']], ['flag_key' => $key], ['%d','%d'], ['%s']);
                $flash = $enabled ? 'Feature enabled.' : 'Feature disabled.';
            }
        }

        if ($action === 'roles') {
            $key = rich_normalize_feature_key($_POST['flag_key'] ?? '');
            $selected_roles = isset($_POST['allowed_roles']) && is_array($_POST['allowed_roles']) ? array_values(array_intersect(array_map('sanitize_key', $_POST['allowed_roles']), array_keys($roles))) : [];
            $wpdb->update($resolved_table, ['allowed_roles' => implode(',', $selected_roles), 'updated_by' => (int)$_SESSION['user_id']], ['flag_key' => $key], ['%s','%d'], ['%s']);
            $flash = 'Role visibility updated.';
        }

        if ($action === 'overlay') {
            $key = rich_normalize_feature_key($_POST['flag_key'] ?? '');
            $overlay_enabled = !empty($_POST['is_overlay_enabled']) ? 1 : 0;
            $overlay_message = sanitize_text_field($_POST['overlay_message'] ?? 'This feature is temporarily unavailable.');
            if ($key !== '') {
                $wpdb->update($resolved_table, ['is_overlay_enabled' => $overlay_enabled, 'overlay_message' => $overlay_message, 'updated_by' => (int)$_SESSION['user_id']], ['flag_key' => $key], ['%d','%s','%d'], ['%s']);
                $flash = $overlay_enabled ? 'Overlay enabled.' : 'Overlay disabled.';
            }
        }

        if ($action === 'delete') {
            $key = rich_normalize_feature_key($_POST['flag_key'] ?? '');
            if ($key !== '' && !in_array($key, ['dashboard','trading-floor','journal','market-data','mt5-sync','signals-groups','card-market','card-signals','card-news','card-classroom','card-strategies','card-trades','card-mentors','card-ai','card-chat','card-journal'], true)) {
                $wpdb->delete($resolved_table, ['flag_key' => $key], ['%s']);
                $flash = 'Feature removed.';
            } else {
                $flash = 'Core feature cannot be removed.';
            }
        }
    }
}

$flags = $wpdb->get_results("SELECT * FROM {$resolved_table} ORDER BY label ASC", ARRAY_A);
$core_flags = ['dashboard','trading-floor','journal','market-data','mt5-sync','signals-groups'];
$core_feature_flags = [];
$dashboard_card_flags = [];
$custom_feature_flags = [];
foreach ($flags as $flag) {
    $key = (string)($flag['flag_key'] ?? '');
    if (in_array($key, $core_flags, true)) {
        $core_feature_flags[] = $flag;
    } elseif (strpos($key, 'card-') === 0) {
        $dashboard_card_flags[] = $flag;
    } else {
        $custom_feature_flags[] = $flag;
    }
}
?><!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Admin Control Panel · 2RICH</title><style>
:root{color-scheme:dark}*{box-sizing:border-box}body{margin:0;background:radial-gradient(circle at top,#181a20 0%,#0d0e11 42%,#090a0c 100%);color:#f1f1f1;font:15px/1.5 Inter,system-ui,sans-serif}.shell{max-width:1180px;margin:0 auto;padding:32px 24px 56px}.eyebrow{color:#f2ca50;font-size:11px;font-weight:800;letter-spacing:.14em;text-transform:uppercase}.head{display:flex;justify-content:space-between;align-items:end;gap:20px;margin-bottom:24px}.head h1{margin:8px 0 0;font-size:34px;letter-spacing:-.03em}.head p{margin:8px 0 0;color:#8b9098}.back{display:inline-flex;align-items:center;min-height:44px;padding:0 14px;border:1px solid rgba(255,255,255,.1);border-radius:10px;color:#d9e2ff;text-decoration:none;background:rgba(255,255,255,.04)}.flash{margin-bottom:18px;padding:12px 16px;border:1px solid rgba(242,202,80,.28);border-radius:10px;background:rgba(242,202,80,.09);color:#ffe082}.admin-nav{position:sticky;top:12px;z-index:5;display:flex;gap:8px;flex-wrap:wrap;margin:0 0 20px;padding:8px;border:1px solid rgba(255,255,255,.09);border-radius:14px;background:rgba(16,18,23,.78);backdrop-filter:blur(16px)}.admin-nav a{padding:10px 14px;border-radius:9px;color:#aeb5c2;text-decoration:none}.admin-nav a:hover,.admin-nav a:focus-visible{background:rgba(242,202,80,.12);color:#ffe082}.grid{display:grid;grid-template-columns:1fr;gap:18px;align-items:start}.panel{border:1px solid rgba(255,255,255,.09);border-radius:16px;background:rgba(21,23,29,.84);padding:20px}.panel h2{margin:0;font-size:19px}.panel-intro{margin:5px 0 16px;color:#858c98;font-size:13px}.list{display:grid;gap:12px}.row{padding:16px;border:1px solid rgba(255,255,255,.08);border-radius:14px;background:rgba(12,13,17,.7);scroll-margin-top:92px}.row:target{border-color:rgba(242,202,80,.55);box-shadow:0 0 0 3px rgba(242,202,80,.08)}.row-top{display:flex;justify-content:space-between;gap:16px;align-items:start}.label{font-weight:750}.meta{margin-top:3px;color:#777d86;font-size:13px}.status{display:flex;align-items:center;gap:12px;color:#8b9098;font-size:12px;font-weight:700;text-transform:uppercase;letter-spacing:.06em}.switch{position:relative;width:48px;height:28px}.switch input{opacity:0;width:0;height:0}.slider{position:absolute;inset:0;border-radius:999px;background:#303030;cursor:pointer;transition:.2s}.slider:before{content:'';position:absolute;width:20px;height:20px;left:4px;top:4px;border-radius:50%;background:#888;transition:.2s}.switch input:checked+.slider{background:#b18b24}.switch input:checked+.slider:before{transform:translateX(20px);background:#fff3b1}.roles{display:flex;flex-wrap:wrap;gap:8px;margin-top:12px}.role-pill{display:inline-flex;align-items:center;gap:6px;padding:6px 10px;border:1px solid #2c2c2c;border-radius:999px;background:#171717;color:#babec5;font-size:12px}.role-pill input{accent-color:#f2ca50}.actions{display:flex;justify-content:space-between;align-items:center;gap:12px;margin-top:14px}.small-btn,.danger-btn{border:1px solid #2c2c2c;border-radius:10px;background:#191919;color:#f1f1f1;padding:9px 12px;cursor:pointer}.danger-btn{color:#ffb8b8;border-color:#4a2a2a;background:#1b1212}.debug-panel{display:none}.section-anchor{scroll-margin-top:92px}@media(max-width:900px){.head{align-items:start;flex-direction:column}}@media(max-width:600px){.shell{padding-inline:14px}.row-top,.actions{flex-direction:column;align-items:start}.admin-nav{top:6px}.admin-nav a{flex:1 1 calc(50% - 8px);text-align:center}}
</style></head><body><main class="shell"><div class="head"><div><div class="eyebrow">2RICH INTERNAL</div><h1>Admin Control Panel</h1><p>Manage app features and limit visibility by role.</p></div><a class="back" href="/dashboard/">Back to dashboard</a></div><?php if ($flash): ?><div class="flash"><?php echo esc_html($flash); ?></div><?php endif; ?><div class="debug-panel"><?php echo htmlspecialchars(json_encode(['table'=>$table,'resolved_table'=>$resolved_table,'flags'=>$flags,'runtime'=>['__FILE__'=>__FILE__,'wp_load_path'=>dirname(__DIR__, 2) . '/wp-load.php','db_name'=>defined('DB_NAME') ? DB_NAME : null,'db_host'=>defined('DB_HOST') ? DB_HOST : null,'table_count'=>isset($wpdb) ? $wpdb->get_var("SELECT COUNT(*) FROM " . $resolved_table) : null]], JSON_PRETTY_PRINT)); ?></div><nav class="admin-nav" aria-label="Admin sections"><a href="#core-features">Core features</a><a href="#dashboard-cards">Dashboard cards</a><a href="#custom-features">Custom features</a></nav><div class="grid"><section class="panel section-anchor" id="core-features"><h2>Core features</h2><p class="panel-intro">Control access to the main areas of the app.</p><div class="list"><?php foreach ($core_feature_flags as $flag): $selected_roles = array_filter(array_map('trim', explode(',', (string)($flag['allowed_roles'] ?? '')))); $is_on = (int)($flag['is_enabled'] ?? 0) === 1; ?><div class="row"><div class="row-top"><div><div class="label"><?php echo esc_html($flag['label']); ?></div><div class="meta"><code><?php echo esc_html($flag['flag_key']); ?></code><?php if (!empty($flag['description'])): ?> · <?php echo esc_html($flag['description']); ?><?php endif; ?></div></div><form class="status" method="post"><span><?php echo $is_on ? 'On' : 'Off'; ?></span><input type="hidden" name="feature_action" value="toggle"><input type="hidden" name="flag_key" value="<?php echo esc_attr($flag['flag_key']); ?>"><input type="hidden" name="admin_nonce" value="<?php echo esc_attr(wp_create_nonce('rich_admin_features')); ?>"><label class="switch"><input type="checkbox" name="is_enabled" value="1" <?php checked($is_on,true); ?> onchange="this.form.submit()"><span class="slider"></span></label></form></div><form method="post"><input type="hidden" name="feature_action" value="roles"><input type="hidden" name="flag_key" value="<?php echo esc_attr($flag['flag_key']); ?>"><input type="hidden" name="admin_nonce" value="<?php echo esc_attr(wp_create_nonce('rich_admin_features')); ?>"><div class="roles"><?php foreach ($roles as $role_key => $role_name): ?><label class="role-pill"><input type="checkbox" name="allowed_roles[]" value="<?php echo esc_attr($role_key); ?>" <?php checked(in_array($role_key, $selected_roles, true)); ?>><?php echo esc_html($role_name); ?></label><?php endforeach; ?></div><div class="actions"><button class="small-btn" type="submit">Save role visibility</button><?php if (!in_array($flag['flag_key'], $core_flags, true)): ?><button class="danger-btn" type="submit" name="feature_action" value="delete" onclick="return confirm('Remove this feature flag?');">Remove feature</button><?php else: ?><span class="meta">Core feature</span><?php endif; ?></div></form><form method="post"><input type="hidden" name="feature_action" value="overlay"><input type="hidden" name="flag_key" value="<?php echo esc_attr($flag['flag_key']); ?>"><input type="hidden" name="admin_nonce" value="<?php echo esc_attr(wp_create_nonce('rich_admin_features')); ?>"><div style="margin-top:12px;padding-top:12px;border-top:1px solid #242424;"><p style="margin:0 0 10px;font-size:13px;font-weight:700;">Overlay Message</p><div style="display:flex;align-items:center;gap:10px;margin-bottom:10px;"><label class="switch"><input type="checkbox" name="is_overlay_enabled" value="1" <?php checked((int)($flag['is_overlay_enabled'] ?? 0) === 1, true); ?> onchange="this.form.submit()"><span class="slider"></span></label><span style="font-size:13px;">Show temporarily unavailable overlay</span></div><?php if ((int)($flag['is_overlay_enabled'] ?? 0) === 1): ?><div style="margin-top:10px;"><input type="text" name="overlay_message" value="<?php echo esc_attr($flag['overlay_message'] ?? 'This feature is temporarily unavailable.'); ?>" style="width:100%;padding:8px 10px;border:1px solid #2c2c2c;border-radius:8px;background:#111;color:#f1f1f1;font-size:13px;" placeholder="Overlay message"></div><div class="actions"><button class="small-btn" type="submit">Save overlay</button></div><?php endif; ?></div></form></div><?php endforeach; ?></div></section></div></main></body></html>
