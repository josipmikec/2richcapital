<?php
define('WP_USE_THEMES', false); require_once dirname(__DIR__, 3) . '/wp-load.php'; global $wpdb; header('Content-Type: application/json; charset=utf-8'); $table=$wpdb->prefix.'rich_market_sync_state'; $rows=$wpdb->get_results("SELECT * FROM {$table} ORDER BY last_attempt_at DESC LIMIT 200", ARRAY_A); wp_send_json(['ok'=>true,'states'=>$rows?:[]]);
