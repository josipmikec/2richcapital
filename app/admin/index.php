<?php
require_once '../auth/session-config.php';
define('WP_USE_THEMES', false);
require_once dirname(__DIR__, 2) . '/wp-load.php';
require_once '../auth/feature-flags.php';

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
$flash = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['admin_nonce']) || !wp_verify_nonce($_POST['admin_nonce'], 'rich_admin_features')) {
        $flash = 'Security check failed.';
    } else {
        $key = sanitize_key($_POST['flag_key'] ?? '');
        $enabled = !empty($_POST['is_enabled']) ? 1 : 0;
        if ($key !== '') {
            $wpdb->update($table, ['is_enabled' => $enabled, 'updated_by' => (int)$_SESSION['user_id']], ['flag_key' => $key], ['%d','%d'], ['%s']);
            $flash = $enabled ? 'Feature enabled.' : 'Feature disabled.';
        }
    }
}
$flags = $wpdb->get_results("SELECT * FROM {$table} ORDER BY label ASC", ARRAY_A);
?><!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Admin Control Panel · 2RICH</title><style>
:root{color-scheme:dark}*{box-sizing:border-box}body{margin:0;background:#0e0e0e;color:#f1f1f1;font:15px/1.5 Inter,system-ui,sans-serif}.shell{max-width:1000px;margin:0 auto;padding:48px 24px}.eyebrow{color:#f2ca50;font-size:11px;font-weight:800;letter-spacing:.14em;text-transform:uppercase}.head{display:flex;justify-content:space-between;align-items:end;gap:20px;margin-bottom:30px}.head h1{margin:8px 0 0;font-size:34px;letter-spacing:-.03em}.head p{margin:8px 0 0;color:#8b9098}.back{color:#c9d8ff;text-decoration:none}.flash{margin-bottom:18px;padding:12px 16px;border:1px solid rgba(242,202,80,.28);border-radius:10px;background:rgba(242,202,80,.09);color:#ffe082}.list{display:grid;gap:10px}.row{display:flex;align-items:center;justify-content:space-between;gap:20px;padding:18px 20px;border:1px solid #242424;border-radius:14px;background:#151515}.label{font-weight:750}.description{margin-top:3px;color:#777d86;font-size:13px}.status{display:flex;align-items:center;gap:12px;color:#8b9098;font-size:12px;font-weight:700;text-transform:uppercase;letter-spacing:.06em}.switch{position:relative;width:48px;height:28px}.switch input{opacity:0;width:0;height:0}.slider{position:absolute;inset:0;border-radius:999px;background:#303030;cursor:pointer;transition:.2s}.slider:before{content:'';position:absolute;width:20px;height:20px;left:4px;top:4px;border-radius:50%;background:#888;transition:.2s}.switch input:checked+.slider{background:#b18b24}.switch input:checked+.slider:before{transform:translateX(20px);background:#fff3b1}@media(max-width:600px){.head{align-items:start;flex-direction:column}.row{align-items:flex-start}.status{flex-direction:column-reverse;gap:6px}}
</style></head><body><main class="shell"><div class="head"><div><div class="eyebrow">2RICH INTERNAL</div><h1>Admin Control Panel</h1><p>Manage pages and feature availability without a code deployment.</p></div><a class="back" href="/dashboard/">Back to dashboard</a></div><?php if ($flash): ?><div class="flash"><?php echo esc_html($flash); ?></div><?php endif; ?><div class="list"><?php foreach ($flags as $flag): ?><form class="row" method="post"><div><div class="label"><?php echo esc_html($flag['label']); ?></div><div class="description"><?php echo esc_html($flag['description']); ?></div></div><div class="status"><span><?php echo (int)$flag['is_enabled'] ? 'On' : 'Off'; ?></span><label class="switch"><input type="hidden" name="flag_key" value="<?php echo esc_attr($flag['flag_key']); ?>"><input type="hidden" name="admin_nonce" value="<?php echo esc_attr(wp_create_nonce('rich_admin_features')); ?>"><input type="checkbox" name="is_enabled" value="1" <?php checked((int)$flag['is_enabled'],1); ?> onchange="this.form.submit()"><span class="slider"></span></label></div></form><?php endforeach; ?></div></main></body></html>
