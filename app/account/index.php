<?php
require_once '../auth/session-config.php';

if (!isset($_SESSION['user_id']) || !isset($_SESSION['authenticated'])) {
    header('Location: https://app.2rich.capital/login/');
    exit;
}

$user_name  = $_SESSION['user_name']  ?? 'Member';
$user_email = $_SESSION['user_email'] ?? '';
$user_id    = $_SESSION['user_id']    ?? 0;

define('WP_USE_THEMES', false);
require_once dirname(__DIR__, 2) . '/wp-load.php';
global $wpdb;
$conn_table = $wpdb->prefix . 'rich_mt5_connections';

// ─────────────────────────────────────────────
// Profile persistence logic
// ─────────────────────────────────────────────
$profile_table = $wpdb->prefix . 'rich_user_profiles';
$profile_row = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$profile_table} WHERE user_id = %d LIMIT 1", $user_id), ARRAY_A);
$profile_display_name = $profile_row['display_name'] ?? $user_name;
$profile_handle = $profile_row['trading_handle'] ?? '@' . strtolower(str_replace(' ','',$user_name));
$profile_bio = $profile_row['bio'] ?? '';
$profile_primary_market = $profile_row['primary_market'] ?? '';
$profile_trading_style = $profile_row['trading_style'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['profile_form_action']) && $_POST['profile_form_action'] === 'save_profile') {
    if (!isset($_POST['profile_nonce']) || !wp_verify_nonce($_POST['profile_nonce'], 'save_profile')) {
        $mt5_flash = ['type' => 'error', 'message' => 'Security check failed.'];
    } else {
        $display_name = sanitize_text_field($_POST['display_name'] ?? $user_name);
        $trading_handle = sanitize_text_field($_POST['trading_handle'] ?? '');
        $bio = sanitize_text_field($_POST['bio'] ?? '');
        $primary_market = sanitize_text_field($_POST['primary_market'] ?? '');
        $trading_style = sanitize_text_field($_POST['trading_style'] ?? '');

        if ($trading_handle === '') {
            $trading_handle = '@' . strtolower(str_replace(' ','', $display_name));
        }
        if (strpos($trading_handle, '@') !== 0) {
            $trading_handle = '@' . ltrim($trading_handle, '@');
        }

        $existing_profile_id = $wpdb->get_var($wpdb->prepare("SELECT id FROM {$profile_table} WHERE user_id = %d LIMIT 1", $user_id));
        $profile_data = [
            'user_id' => $user_id,
            'display_name' => $display_name,
            'trading_handle' => $trading_handle,
            'bio' => $bio,
            'primary_market' => $primary_market,
            'trading_style' => $trading_style,
            'updated_at' => current_time('mysql')
        ];
        $save_ok = false;
        $save_mode = $existing_profile_id ? 'update' : 'insert';
        if ($existing_profile_id) {
            $save_ok = ($wpdb->update($profile_table, $profile_data, ['id' => (int)$existing_profile_id]) !== false);
        } else {
            $profile_data['created_at'] = current_time('mysql');
            $save_ok = ($wpdb->insert($profile_table, $profile_data) !== false);
        }

        if (!$save_ok) {
            $mt5_flash = [
                'type' => 'error',
                'message' => 'Profile save failed. Debug: table=' . $profile_table . '; user_id=' . (int)$user_id . '; mode=' . $save_mode . '; db_error=' . ($wpdb->last_error ?: 'none')
            ];
        } else {
            $_SESSION['user_name'] = $display_name;
            $profile_display_name = $display_name;
            $profile_handle = $trading_handle;
            $profile_bio = $bio;
            $profile_primary_market = $primary_market;
            $profile_trading_style = $trading_style;
            $mt5_flash = ['type' => 'success', 'message' => 'Profile saved. Debug: table=' . $profile_table . '; user_id=' . (int)$user_id . '; mode=' . $save_mode];
        }
    }
}

// ─────────────────────────────────────────────
// MT5 connection panel logic
// ─────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['mt5_form_action'])) {
    if (!isset($_POST['rich_mt5_nonce']) || !wp_verify_nonce($_POST['rich_mt5_nonce'], 'rich_mt5_manage')) {
        $mt5_flash = ['type' => 'error', 'message' => 'Security check failed.'];
    } else {
        $action = sanitize_text_field($_POST['mt5_form_action']);

        if ($action === 'connect') {
            $mt5_login = (int)($_POST['mt5_login'] ?? 0);
            $server_name = sanitize_text_field($_POST['server_name'] ?? '');
            $broker_name = sanitize_text_field($_POST['broker_name'] ?? '');
            $connection_name = sanitize_text_field($_POST['connection_name'] ?? 'Primary MT5');

            if ($mt5_login <= 0 || $server_name === '') {
                $mt5_flash = ['type' => 'error', 'message' => 'MT5 login and server name are required.'];
            } else {
                $plain_api_key = wp_generate_password(32, false, false) . '-' . wp_generate_password(12, false, false);
                $connection_token = wp_generate_password(40, false, false);
                $api_key_hash = wp_hash_password($plain_api_key);

                $existing_id = $wpdb->get_var($wpdb->prepare(
                    "SELECT id FROM {$conn_table} WHERE user_id = %d ORDER BY id DESC LIMIT 1",
                    $user_id
                ));

                $row = [
                    'user_id' => $user_id,
                    'mt5_login' => $mt5_login,
                    'server_name' => $server_name,
                    'broker_name' => $broker_name,
                    'connection_name' => $connection_name,
                    'connection_token' => $connection_token,
                    'api_key_hash' => $api_key_hash,
                    'is_active' => 1,
                    'updated_at' => current_time('mysql')
                ];

                if ($existing_id) {
                    $wpdb->update($conn_table, $row, ['id' => (int)$existing_id]);
                } else {
                    $row['created_at'] = current_time('mysql');
                    $wpdb->insert($conn_table, $row);
                }

                $new_plain_api_key = $plain_api_key;
                $mt5_flash = ['type' => 'success', 'message' => 'MT5 connection saved. Copy your API key now; it will not be shown again.'];
            }
        }

        if ($action === 'reset_key') {
            $plain_api_key = wp_generate_password(32, false, false) . '-' . wp_generate_password(12, false, false);
            $connection_token = wp_generate_password(40, false, false);

            $wpdb->update($conn_table, [
                'api_key_hash' => wp_hash_password($plain_api_key),
                'connection_token' => $connection_token,
                'last_error' => null,
                'is_active' => 1,
                'updated_at' => current_time('mysql')
            ], ['user_id' => $user_id]);

            $new_plain_api_key = $plain_api_key;
            $mt5_flash = ['type' => 'success', 'message' => 'API key reset. Update your EA immediately.'];
        }

        if ($action === 'disconnect') {
            $wpdb->update($conn_table, [
                'is_active' => 0,
                'updated_at' => current_time('mysql')
            ], ['user_id' => $user_id]);

            $mt5_flash = ['type' => 'success', 'message' => 'MT5 connection disconnected.'];
        }

		        if ($action === 'set_journal') {
            $journal_id = (int)($_POST['mt5_journal_id'] ?? 0);

            if ($journal_id <= 0) {
                $mt5_flash = ['type' => 'error', 'message' => 'Please choose a journal for MT5 trades.'];
            } else {
                // Make sure this journal belongs to the current user
                $owns_journal = (int)$wpdb->get_var($wpdb->prepare(
                    "SELECT COUNT(*) FROM {$journals_table} WHERE id = %d AND user_id = %d",
                    $journal_id,
                    $user_id
                ));

                if (!$owns_journal) {
                    $mt5_flash = ['type' => 'error', 'message' => 'Invalid journal selection.'];
                } else {
                    // Update MT5 connection row with new journal_id
                    $wpdb->update(
                        $conn_table,
                        [
                            'journal_id' => $journal_id,
                            'updated_at' => current_time('mysql'),
                        ],
                        ['user_id' => $user_id]
                    );

                    // Refresh connection data so the UI reflects the new journal
                    $mt5_conn = $wpdb->get_row($wpdb->prepare(
                        "SELECT * FROM {$conn_table} WHERE user_id = %d ORDER BY id DESC LIMIT 1",
                        $user_id
                    ), ARRAY_A);

                    $mt5_flash = ['type' => 'success', 'message' => 'MT5 trades will now go into the selected journal.'];
                }
            }
        }
    }
}

$mt5_conn = $wpdb->get_row($wpdb->prepare(
    "SELECT * FROM {$conn_table} WHERE user_id = %d ORDER BY id DESC LIMIT 1",
    $user_id
), ARRAY_A);

$mt5_sync_url = home_url('/app/api/trades/mt5-sync.php');

// Fetch real stats from journal
$total_trades = (int) $wpdb->get_var($wpdb->prepare(
    "SELECT COUNT(*) FROM {$wpdb->prefix}rich_trades WHERE user_id = %d", $user_id
));
$wins = (int) $wpdb->get_var($wpdb->prepare(
    "SELECT COUNT(*) FROM {$wpdb->prefix}rich_trades WHERE user_id = %d AND outcome = 'WIN'", $user_id
));
$losses = (int) $wpdb->get_var($wpdb->prepare(
    "SELECT COUNT(*) FROM {$wpdb->prefix}rich_trades WHERE user_id = %d AND outcome = 'LOSS'", $user_id
));
$avg_pnl = (float) $wpdb->get_var($wpdb->prepare(
    "SELECT AVG(profit_loss_pct) FROM {$wpdb->prefix}rich_trades WHERE user_id = %d AND profit_loss_pct IS NOT NULL", $user_id
));
$best_trade = (float) $wpdb->get_var($wpdb->prepare(
    "SELECT MAX(profit_loss_pct) FROM {$wpdb->prefix}rich_trades WHERE user_id = %d", $user_id
));
$win_rate = $total_trades > 0 ? round(($wins / $total_trades) * 100, 1) : 0;

// Fetch default stop distance preference
$pref_table = $wpdb->prefix . 'rich_user_preferences';
$default_stop = $wpdb->get_var($wpdb->prepare(
    "SELECT pref_value FROM $pref_table WHERE user_id = %d AND pref_key = 'default_stop_distance'", $user_id
)) ?? '1.00';

// Recent trades for activity feed
$recent_trades = $wpdb->get_results($wpdb->prepare(
    "SELECT symbol, direction, outcome, profit_loss_pct, entry_date
     FROM {$wpdb->prefix}rich_trades
     WHERE user_id = %d
     ORDER BY entry_date DESC LIMIT 6",
    $user_id
));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Account - 2RICH CAPITAL</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/dashboard.css">
    <style>
        .account-tab-nav {
            display: flex;
            gap: 4px;
            padding: 0 0 28px 0;
            border-bottom: 1px solid #1a1a1a;
            margin-bottom: 32px;
            flex-wrap: wrap;
        }
        .account-tab {
            padding: 9px 18px;
            font-size: 12px;
            font-weight: 600;
            letter-spacing: 0.04em;
            color: #555;
            background: none;
            border: 1px solid transparent;
            border-radius: 6px;
            cursor: pointer;
            font-family: "Montserrat", sans-serif;
            transition: all 0.2s ease;
        }
        .account-tab:hover { color: #bbb; background: rgba(255,255,255,0.03); }
        .account-tab.active { color: #F2CA50; border-color: rgba(242,202,80,0.3); background: rgba(242,202,80,0.04); }

        .account-section { display: none; }
        .account-section.active { display: block; }

        .page-header {
            padding: 0 0 28px 0;
            border-bottom: 1px solid #1a1a1a;
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 32px;
        }
        .page-header-left h2 { font-size: 20px; font-weight: 800; color: #e0e0e0; letter-spacing: 0.02em; }
        .page-header-left p { font-size: 11px; font-weight: 500; color: #444; margin-top: 4px; letter-spacing: 0.04em; }

        .page-header-action {
            padding: 10px 20px;
            background: linear-gradient(135deg, #F2CA50, #FFDB70);
            color: #0E0E0E;
            font-size: 10px;
            font-weight: 800;
            letter-spacing: 0.1em;
            text-transform: uppercase;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-family: "Montserrat", sans-serif;
            transition: all 0.2s;
        }
        .page-header-action:hover { box-shadow: 0 4px 16px rgba(242,202,80,0.35); transform: translateY(-1px); }
        .page-header-actions {
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .page-header-action--ghost {
            padding: 10px 0;
            background: transparent;
            color: #8f8f8f;
            border: none;
            box-shadow: none;
        }
        .page-header-action--ghost:hover {
            color: #f2ca50;
            box-shadow: none;
            transform: none;
        }

        .profile-hero {
            background: rgba(18,18,18,0.8);
            border: 1px solid #1a1a1a;
            border-radius: 14px;
            padding: 32px;
            display: flex;
            align-items: center;
            gap: 28px;
            margin-bottom: 28px;
            position: relative;
            overflow: hidden;
        }
        .profile-hero::before {
            content: '';
            position: absolute;
            inset: 0;
            background: radial-gradient(ellipse at 80% 50%, rgba(242,202,80,0.04) 0%, transparent 70%);
            pointer-events: none;
        }
        .profile-avatar-wrap { position: relative; flex-shrink: 0; }
        .profile-avatar-large {
            width: 88px;
            height: 88px;
            border-radius: 50%;
            background: linear-gradient(135deg, #F2CA50, #FFDB70);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 36px;
            font-weight: 800;
            color: #0E0E0E;
            border: 3px solid rgba(242,202,80,0.3);
        }
        .profile-avatar-edit {
            position: absolute;
            bottom: 0;
            right: 0;
            width: 26px;
            height: 26px;
            background: #1e1e1e;
            border: 2px solid #0E0E0E;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: background 0.2s;
            color: #666;
        }
        .profile-avatar-edit:hover { background: #2a2a2a; color: #aaa; }
        .profile-info { flex: 1; }
        .profile-name { font-size: 22px; font-weight: 800; color: #e8e8e8; letter-spacing: 0.01em; }
        .profile-handle { font-size: 12px; font-weight: 500; color: #444; margin-top: 3px; letter-spacing: 0.06em; }
        .profile-bio-text { font-size: 12px; font-weight: 500; color: #666; margin-top: 10px; line-height: 1.6; max-width: 400px; }
        .profile-stats-row { display: flex; gap: 28px; margin-top: 16px; }
        .profile-stat { text-align: center; }
        .profile-stat-value { font-size: 18px; font-weight: 800; color: #F2CA50; }
        .profile-stat-label { font-size: 9px; font-weight: 600; color: #444; text-transform: uppercase; letter-spacing: 0.08em; margin-top: 2px; }
        .profile-badge-row { display: flex; gap: 8px; margin-top: 14px; flex-wrap: wrap; }
        .profile-badge {
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 9px;
            font-weight: 700;
            letter-spacing: 0.08em;
            text-transform: uppercase;
        }
        .profile-badge.gold { background: rgba(242,202,80,0.12); color: #F2CA50; border: 1px solid rgba(242,202,80,0.2); }
        .profile-badge.green { background: rgba(74,222,128,0.1); color: #4ade80; border: 1px solid rgba(74,222,128,0.15); }
        .profile-badge.blue { background: rgba(96,165,250,0.1); color: #60a5fa; border: 1px solid rgba(96,165,250,0.15); }

        .stats-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 14px; margin-bottom: 28px; }
        .stat-card {
            background: rgba(18,18,18,0.8);
            border: 1px solid #1a1a1a;
            border-radius: 12px;
            padding: 20px;
            transition: border-color 0.2s;
        }
        .stat-card:hover { border-color: #252525; }
        .stat-card-label { font-size: 9px; font-weight: 700; letter-spacing: 0.1em; color: #444; text-transform: uppercase; margin-bottom: 8px; }
        .stat-card-value { font-size: 26px; font-weight: 800; color: #e0e0e0; }
        .stat-card-value.positive { color: #4ade80; }
        .stat-card-value.negative { color: #f87171; }
        .stat-card-value.gold { color: #F2CA50; }
        .stat-card-sub { font-size: 10px; font-weight: 500; color: #444; margin-top: 4px; }

        .section-card {
            background: rgba(18,18,18,0.8);
            border: 1px solid #1a1a1a;
            border-radius: 12px;
            overflow: hidden;
        }
        .section-card-header {
            padding: 16px 20px;
            border-bottom: 1px solid #1a1a1a;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        .section-card-title { font-size: 11px; font-weight: 700; color: #888; letter-spacing: 0.08em; text-transform: uppercase; }
        .section-card-link {
            font-size: 10px;
            font-weight: 700;
            color: #F2CA50;
            letter-spacing: 0.06em;
            cursor: pointer;
            background: none;
            border: none;
            font-family: "Montserrat", sans-serif;
            padding: 0;
        }
        .section-card-link:hover { color: #FFDB70; }

        .activity-row { display: flex; align-items: center; gap: 14px; padding: 14px 20px; border-bottom: 1px solid #0f0f0f; transition: background 0.15s; }
        .activity-row:last-child { border-bottom: none; }
        .activity-row:hover { background: rgba(255,255,255,0.015); }
        .activity-symbol { font-size: 14px; font-weight: 800; color: #e0e0e0; min-width: 80px; letter-spacing: 0.04em; }
        .activity-dir { font-size: 9px; font-weight: 700; letter-spacing: 0.1em; text-transform: uppercase; padding: 3px 8px; border-radius: 4px; min-width: 46px; text-align: center; }
        .activity-dir.long { color: #4ade80; background: rgba(74,222,128,0.1); }
        .activity-dir.short { color: #f87171; background: rgba(248,113,113,0.1); }
        .activity-outcome { font-size: 9px; font-weight: 700; letter-spacing: 0.1em; padding: 3px 8px; border-radius: 4px; min-width: 58px; text-align: center; }
        .activity-outcome.win { color: #4ade80; background: rgba(74,222,128,0.08); border: 1px solid rgba(74,222,128,0.15); }
        .activity-outcome.loss { color: #f87171; background: rgba(248,113,113,0.08); border: 1px solid rgba(248,113,113,0.15); }
        .activity-outcome.be { color: #888; background: rgba(255,255,255,0.04); border: 1px solid #222; }
        .activity-pnl { font-size: 13px; font-weight: 700; margin-left: auto; }
        .activity-pnl.pos { color: #4ade80; }
        .activity-pnl.neg { color: #f87171; }
        .activity-date { font-size: 10px; font-weight: 500; color: #444; min-width: 80px; text-align: right; }

        .settings-card {
            background: rgba(18,18,18,0.8);
            border: 1px solid #1a1a1a;
            border-radius: 12px;
            overflow: hidden;
            margin-bottom: 24px;
        }
        .settings-card-header { padding: 18px 24px; border-bottom: 1px solid #1a1a1a; }
        .settings-card-title { font-size: 11px; font-weight: 700; color: #888; letter-spacing: 0.08em; text-transform: uppercase; }
        .settings-card-body { padding: 24px; }
        .settings-field { margin-bottom: 20px; }
        .settings-field:last-child { margin-bottom: 0; }
        .settings-label { font-size: 9px; font-weight: 700; letter-spacing: 0.1em; color: #555; text-transform: uppercase; display: block; margin-bottom: 8px; }
        .settings-input {
            width: 100%;
            background: #0E0E0E;
            border: 1px solid #1e1e1e;
            border-radius: 8px;
            padding: 11px 14px;
            font-size: 13px;
            font-weight: 500;
            color: #ccc;
            font-family: "Montserrat", sans-serif;
            transition: border-color 0.2s;
        }
        .settings-input:focus { outline: none; border-color: rgba(242,202,80,0.3); }
        .settings-input:disabled { color: #444; cursor: not-allowed; }
        .settings-input-hint { font-size: 10px; font-weight: 500; color: #444; margin-top: 6px; }
        .settings-row { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; }
		.mt5-two-col {
		    display: grid;
		    grid-template-columns: 1fr 1fr 1fr;
		    gap: 24px;
		    align-items: stretch;
		    margin-bottom: 24px;
		}

		
		.mt5-two-col .settings-card {
		    margin-bottom: 0;
		    display: flex;
		    flex-direction: column;
		    height: 100%;
		}
		
		.mt5-two-col .settings-card-body {
		    flex: 1;
		    display: flex;
		    flex-direction: column;
		}
		
		.mt5-two-col .settings-card-body form {
		    display: flex;
		    flex-direction: column;
		    flex: 1;
		}
		
		.mt5-two-col .settings-card-body .settings-save-btn {
		    margin-top: auto;
		}

		.mt5-ea-link {
		    display: inline-flex;
		    align-items: center;
		    gap: 8px;
		    padding: 11px 20px;
		    background: rgba(242,202,80,0.10);
		    border: 1px solid rgba(242,202,80,0.30);
		    border-radius: 8px;
		    color: #F2CA50;
		    font-size: 12px;
		    font-weight: 700;
		    text-decoration: none;
		    letter-spacing: 0.3px;
		    transition: background 0.15s ease, border-color 0.15s ease;
		}
		
		.mt5-ea-link:hover {
		    background: rgba(242,202,80,0.18);
		    border-color: rgba(242,202,80,0.5);
		}
		
		.mt5-steps {
		    list-style: none;
		    margin: 0;
		    padding: 0;
		    counter-reset: mt5-step;
		}
		
		.mt5-steps li {
		    counter-increment: mt5-step;
		    position: relative;
		    padding-left: 34px;
		    margin-bottom: 14px;
		    font-size: 12px;
		    line-height: 1.7;
		    color: #a8a8a8;
		}
		
		.mt5-steps li:last-child {
		    margin-bottom: 0;
		}
		
		.mt5-steps li::before {
		    content: counter(mt5-step);
		    position: absolute;
		    left: 0;
		    top: 0;
		    width: 22px;
		    height: 22px;
		    border-radius: 50%;
		    background: rgba(242,202,80,0.12);
		    color: #F2CA50;
		    font-size: 11px;
		    font-weight: 700;
		    display: flex;
		    align-items: center;
		    justify-content: center;
		}
		
		.mt5-steps code {
		    color: #F2CA50;
		    background: rgba(242,202,80,0.08);
		    padding: 2px 6px;
		    border-radius: 4px;
		    font-size: 11.5px;
		}

		
		@media (max-width: 980px) {
		    .mt5-two-col {
		        grid-template-columns: 1fr;
		    }
		}
		
        .settings-save-btn {
            padding: 11px 24px;
            background: linear-gradient(135deg, #F2CA50, #FFDB70);
            color: #0E0E0E;
            font-size: 10px;
            font-weight: 800;
            letter-spacing: 0.1em;
            text-transform: uppercase;
            border: none;
            border-radius: 7px;
            cursor: pointer;
            font-family: "Montserrat", sans-serif;
            transition: all 0.2s;
            margin-top: 4px;
        }
        .settings-save-btn:hover { box-shadow: 0 4px 16px rgba(242,202,80,0.35); transform: translateY(-1px); }
        .settings-save-btn:disabled { opacity: 0.5; cursor: not-allowed; transform: none; box-shadow: none; }
		
        .settings-toggle-row { display: flex; align-items: center; justify-content: space-between; padding: 14px 0; border-bottom: 1px solid #0f0f0f; }
        .settings-toggle-row:last-child { border-bottom: none; padding-bottom: 0; }
        .settings-toggle-info { flex: 1; }
        .settings-toggle-name { font-size: 12px; font-weight: 600; color: #ccc; }
        .settings-toggle-desc { font-size: 10px; font-weight: 500; color: #444; margin-top: 2px; }

        .toggle-switch { position: relative; width: 38px; height: 22px; flex-shrink: 0; }
        .toggle-switch input { opacity: 0; width: 0; height: 0; }
        .toggle-slider {
            position: absolute;
            cursor: pointer;
            inset: 0;
            background: #1e1e1e;
            border-radius: 22px;
            transition: background 0.2s;
            border: 1px solid #2a2a2a;
        }
        .toggle-slider::before {
            content: '';
            position: absolute;
            width: 16px;
            height: 16px;
            left: 2px;
            bottom: 2px;
            background: #555;
            border-radius: 50%;
            transition: all 0.2s;
        }
        .toggle-switch input:checked + .toggle-slider { background: rgba(242,202,80,0.15); border-color: rgba(242,202,80,0.3); }
        .toggle-switch input:checked + .toggle-slider::before { transform: translateX(16px); background: #F2CA50; }

        .security-status {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 12px 14px;
            background: rgba(74,222,128,0.06);
            border: 1px solid rgba(74,222,128,0.15);
            border-radius: 8px;
            margin-bottom: 16px;
        }
        .security-status svg { color: #4ade80; flex-shrink: 0; }
        .security-status-text { font-size: 11px; font-weight: 600; color: #4ade80; }

        .save-feedback {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            font-size: 10px;
            font-weight: 700;
            letter-spacing: 0.06em;
            color: #4ade80;
            margin-left: 12px;
            opacity: 0;
            transition: opacity 0.3s;
        }
        .save-feedback.visible { opacity: 1; }

        @media (max-width: 900px) {
            .stats-grid { grid-template-columns: 1fr 1fr; }
        }
        @media (max-width: 768px) {
            .account-tab-nav { gap: 2px; }
            .account-tab { padding: 8px 12px; font-size: 11px; }
            .profile-hero { flex-direction: column; text-align: center; }
            .profile-stats-row { justify-content: center; }
            .settings-row { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>

<div class="dashboard-background"></div>

<nav class="top-nav">
    <div class="nav-container">
        <div class="nav-brand">
            <h1>2RICH CAPITAL</h1>
            <span class="nav-tagline">INSTITUTIONAL GRADE TRADING</span>
        </div>
        <div class="nav-right">
            <span class="user-email"><?php echo htmlspecialchars($user_email); ?></span>
            <a href="../auth/logout.php" class="logout-btn">LOGOUT</a>
        </div>
    </div>
</nav>

<div class="dashboard-container" style="position:relative;z-index:1;">
    <aside class="sidebar">
        <ul class="sidebar-menu">
            <li class="menu-item" onclick="window.location.href='/dashboard'">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <rect x="3" y="3" width="7" height="7"></rect>
                    <rect x="14" y="3" width="7" height="7"></rect>
                    <rect x="14" y="14" width="7" height="7"></rect>
                    <rect x="3" y="14" width="7" height="7"></rect>
                </svg>
                <span>Dashboard</span>
            </li>
            <li class="menu-item" onclick="window.location.href='/journal'">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                    <polyline points="14 2 14 8 20 8"></polyline>
                    <line x1="16" y1="13" x2="8" y2="13"></line>
                    <line x1="16" y1="17" x2="8" y2="17"></line>
                    <polyline points="10 9 9 9 8 9"></polyline>
                </svg>
                <span>Trading Journal</span>
            </li>
            <li class="menu-item" onclick="window.location.href='/trading-floor'">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <line x1="12" y1="1" x2="12" y2="23"></line>
                    <path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"></path>
                </svg>
                <span>Trading Floor</span>
            </li>
            <li class="menu-item" onclick="window.location.href='/market-data'">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <rect x="2" y="3" width="20" height="14" rx="2" ry="2"></rect>
                    <line x1="8" y1="21" x2="16" y2="21"></line>
                    <line x1="12" y1="17" x2="12" y2="21"></line>
                </svg>
                <span>Market Data</span>
            </li>
            <li class="menu-item active">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                    <circle cx="12" cy="7" r="4"></circle>
                </svg>
                <span>Account</span>
            </li>
        </ul>
    </aside>

    <main class="main-content">
        <div class="account-tab-nav">
            <button class="account-tab active" data-section="profile" onclick="switchSection('profile')">Profile</button>
            <button class="account-tab" data-section="preferences" onclick="switchSection('preferences')">Preferences</button>
            <button class="account-tab" data-section="notifications" onclick="switchSection('notifications')">Notifications</button>
            <button class="account-tab" data-section="security" onclick="switchSection('security')">Security</button>
            <button class="account-tab" data-section="mt5" onclick="switchSection('mt5')">MT5 Sync</button>
        </div>

        <div class="account-section active" id="section-profile">
            <div class="page-header">
                <div class="page-header-left">
                    <h2>Profile</h2>
                    <p>Manage your public trading identity</p>
                </div>
                <div class="page-header-actions">
                    <button class="page-header-action page-header-action--ghost" onclick="window.location.href='/trading-floor#profile'">View Profile</button>
                    <button class="page-header-action" type="submit" form="profileSaveForm">Save Changes</button>
                </div>
            </div>

            <div class="settings-card">
                <div class="settings-card-header"><span class="settings-card-title">Edit Profile</span></div>
                <div class="settings-card-body">
                    <form method="post" id="profileSaveForm">
                        <?php wp_nonce_field('save_profile', 'profile_nonce'); ?>
                        <input type="hidden" name="profile_form_action" value="save_profile">
                        <div class="settings-row">
                        <div class="settings-field">
                            <label class="settings-label">Display Name</label>
                            <input type="text" class="settings-input" id="displayName" name="display_name" value="<?= htmlspecialchars($profile_display_name) ?>" placeholder="Your name">
                        </div>
                        <div class="settings-field">
                            <label class="settings-label">Trading Handle</label>
                            <input type="text" class="settings-input" id="tradingHandle" name="trading_handle" value="<?= htmlspecialchars($profile_handle) ?>" placeholder="@handle">
                        </div>
                    </div>
                    <div class="settings-field">
                        <label class="settings-label">Bio</label>
                        <input type="text" class="settings-input" id="userBio" name="bio" value="<?= htmlspecialchars($profile_bio) ?>" placeholder="e.g. Forex trader · SMC · NY Session specialist" maxlength="120" oninput="document.getElementById('bioPreview').textContent=this.value||'Trader. No bio yet — add one below.'">
                        <div class="settings-input-hint">Max 120 characters. Shows on your Trading Floor profile.</div>
                    </div>
                    <div class="settings-row">
                        <div class="settings-field">
                            <label class="settings-label">Primary Market</label>
                            <select class="settings-input" id="primaryMarket" name="primary_market">
                                <option value="">Select market...</option>
                                <option <?= $profile_primary_market==='Forex' ? 'selected' : '' ?>>Forex</option>
                                <option <?= $profile_primary_market==='Crypto' ? 'selected' : '' ?>>Crypto</option>
                                <option <?= $profile_primary_market==='Indices' ? 'selected' : '' ?>>Indices</option>
                                <option <?= $profile_primary_market==='Commodities' ? 'selected' : '' ?>>Commodities</option>
                                <option <?= $profile_primary_market==='Futures' ? 'selected' : '' ?>>Futures</option>
                                <option <?= $profile_primary_market==='Stocks' ? 'selected' : '' ?>>Stocks</option>
                            </select>
                        </div>
                        <div class="settings-field">
                            <label class="settings-label">Trading Style</label>
                            <select class="settings-input" id="tradingStyle" name="trading_style">
                                <option value="">Select style...</option>
                                <option <?= $profile_trading_style==='Scalper' ? 'selected' : '' ?>>Scalper</option>
                                <option <?= $profile_trading_style==='Day Trader' ? 'selected' : '' ?>>Day Trader</option>
                                <option <?= $profile_trading_style==='Swing Trader' ? 'selected' : '' ?>>Swing Trader</option>
                                <option <?= $profile_trading_style==='Position Trader' ? 'selected' : '' ?>>Position Trader</option>
                                <option <?= $profile_trading_style==='Algorithmic' ? 'selected' : '' ?>>Algorithmic</option>
                            </select>
                        </div>
                    </div>
                    <div class="settings-field">
                        <label class="settings-label">Email</label>
                        <input type="email" class="settings-input" value="<?= htmlspecialchars($user_email) ?>" disabled>
                        <div class="settings-input-hint">Email is managed through your WordPress account.</div>
                    </div>
                </div>
                    </form>
            </div>
        </div>

        <div class="account-section" id="section-preferences">
            <div class="page-header">
                <div class="page-header-left">
                    <h2>Preferences</h2>
                    <p>Customise your trading defaults</p>
                </div>
            </div>

                <div class="settings-card">
                <div class="settings-card-header"><span class="settings-card-title">Trade Defaults</span></div>
                <div class="settings-card-body">
                    <?php if (!empty($profile_flash['message'])): ?>
                    <div class="settings-input-hint" style="margin-bottom:12px;color:<?= $profile_flash['type']==='success' ? '#4ade80' : '#f87171' ?>;"><?= htmlspecialchars($profile_flash['message']) ?></div>
                    <?php endif; ?>
                    <div class="settings-row">
                        <div class="settings-field">
                            <label class="settings-label">Default Stop Distance (%)</label>
                            <input type="number" class="settings-input" id="defaultStopDist"
                                   value="<?= htmlspecialchars($default_stop) ?>"
                                   min="0.01" max="100" step="0.01" placeholder="1.00">
                            <div class="settings-input-hint">Pre-fills the stop % field when logging a new trade.</div>
                        </div>
                        <div class="settings-field">
                            <label class="settings-label">Default Direction</label>
                            <select class="settings-input" id="defaultDirection">
                                <option value="">No default</option>
                                <option>LONG</option>
                                <option>SHORT</option>
                            </select>
                        </div>
                    </div>
                    <div class="settings-field">
                        <label class="settings-label">Default Session</label>
                        <select class="settings-input" id="defaultSession">
                            <option value="">No default</option>
                            <option>London</option>
                            <option>New York</option>
                            <option>Asia</option>
                            <option>Sydney</option>
                        </select>
                    </div>
                    <button class="settings-save-btn" onclick="savePreferences()" id="savePrefBtn">Save Preferences</button>
                    <span class="save-feedback" id="prefFeedback">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                            <polyline points="20 6 9 17 4 12"></polyline>
                        </svg>
                        Saved
                    </span>
                </div>
            </div>

            <div class="settings-card">
                <div class="settings-card-header"><span class="settings-card-title">Display</span></div>
                <div class="settings-card-body">
                    <div class="settings-toggle-row">
                        <div class="settings-toggle-info">
                            <div class="settings-toggle-name">Show P&L in currency</div>
                            <div class="settings-toggle-desc">Display dollar amounts alongside percentages</div>
                        </div>
                        <label class="toggle-switch"><input type="checkbox"><span class="toggle-slider"></span></label>
                    </div>
                    <div class="settings-toggle-row">
                        <div class="settings-toggle-info">
                            <div class="settings-toggle-name">Compact trade rows</div>
                            <div class="settings-toggle-desc">Show more trades per screen in the journal</div>
                        </div>
                        <label class="toggle-switch"><input type="checkbox" checked><span class="toggle-slider"></span></label>
                    </div>
                    <div class="settings-toggle-row">
                        <div class="settings-toggle-info">
                            <div class="settings-toggle-name">Auto-calculate P&L</div>
                            <div class="settings-toggle-desc">Automatically fill P&L when entry and exit are set</div>
                        </div>
                        <label class="toggle-switch"><input type="checkbox" checked><span class="toggle-slider"></span></label>
                    </div>
                </div>
            </div>
        </div>

        <div class="account-section" id="section-notifications">
            <div class="page-header">
                <div class="page-header-left">
                    <h2>Notifications</h2>
                    <p>Control what alerts you receive</p>
                </div>
            </div>

            <div class="settings-card">
                <div class="settings-card-header"><span class="settings-card-title">Trading Floor</span></div>
                <div class="settings-card-body">
                    <?php
                    $notifs = [
                        ['New followers','When someone follows you on the Trading Floor', true],
                        ['Post likes','When someone likes your trade post', true],
                        ['Comments','When someone comments on your post', true],
                        ['Direct messages','When you receive a new DM', true],
                        ['Story views','When someone views your 24h story', false],
                        ['Suggested traders','Weekly curated trader suggestions', false],
                    ];
                    foreach ($notifs as [$name, $desc, $on]): ?>
                    <div class="settings-toggle-row">
                        <div class="settings-toggle-info">
                            <div class="settings-toggle-name"><?= $name ?></div>
                            <div class="settings-toggle-desc"><?= $desc ?></div>
                        </div>
                        <label class="toggle-switch"><input type="checkbox" <?= $on ? 'checked' : '' ?>><span class="toggle-slider"></span></label>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="settings-card">
                <div class="settings-card-header"><span class="settings-card-title">Email Digest</span></div>
                <div class="settings-card-body">
                    <?php
                    $emails = [
                        ['Weekly performance summary','Your win rate and P&L overview every Monday', true],
                        ['Trade streak alerts','When you hit 3+ wins or losses in a row', true],
                        ['Platform updates','New features and announcements', false],
                    ];
                    foreach ($emails as [$name, $desc, $on]): ?>
                    <div class="settings-toggle-row">
                        <div class="settings-toggle-info">
                            <div class="settings-toggle-name"><?= $name ?></div>
                            <div class="settings-toggle-desc"><?= $desc ?></div>
                        </div>
                        <label class="toggle-switch"><input type="checkbox" <?= $on ? 'checked' : '' ?>><span class="toggle-slider"></span></label>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <div class="account-section" id="section-security">
            <div class="page-header">
                <div class="page-header-left">
                    <h2>Security</h2>
                    <p>Protect your account</p>
                </div>
            </div>

            <div class="settings-card" style="margin-bottom:24px;">
                <div class="settings-card-header"><span class="settings-card-title">Password</span></div>
                <div class="settings-card-body">
                    <div class="security-status">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path>
                        </svg>
                        <span class="security-status-text">Account secured via WordPress authentication</span>
                    </div>
                    <div class="settings-field">
                        <label class="settings-label">Current Password</label>
                        <input type="password" class="settings-input" placeholder="Enter current password">
                    </div>
                    <div class="settings-row">
                        <div class="settings-field">
                            <label class="settings-label">New Password</label>
                            <input type="password" class="settings-input" id="newPass" placeholder="Min. 8 characters" oninput="checkPasswordStrength(this.value)">
                        </div>
                        <div class="settings-field">
                            <label class="settings-label">Confirm New Password</label>
                            <input type="password" class="settings-input" placeholder="Repeat new password">
                        </div>
                    </div>
                    <div id="passStrength" style="display:none;margin-bottom:16px;">
                        <div style="font-size:9px;font-weight:700;letter-spacing:0.1em;color:#555;text-transform:uppercase;margin-bottom:6px;">Password Strength</div>
                        <div style="height:4px;background:#1a1a1a;border-radius:4px;overflow:hidden;">
                            <div id="passStrengthBar" style="height:100%;width:0%;background:#f87171;border-radius:4px;transition:all 0.3s;"></div>
                        </div>
                        <div id="passStrengthLabel" style="font-size:10px;color:#555;margin-top:4px;"></div>
                    </div>
                    <button class="settings-save-btn">Update Password</button>
                </div>
            </div>

            <div class="settings-card">
                <div class="settings-card-header"><span class="settings-card-title">Sessions & Access</span></div>
                <div class="settings-card-body">
                    <div class="settings-toggle-row">
                        <div class="settings-toggle-info">
                            <div class="settings-toggle-name">Remember me on this device</div>
                            <div class="settings-toggle-desc">Stay logged in for 30 days</div>
                        </div>
                        <label class="toggle-switch"><input type="checkbox" checked><span class="toggle-slider"></span></label>
                    </div>

                    <div style="margin-top:16px;padding-top:16px;border-top:1px solid #1a1a1a;">
                        <div style="font-size:9px;font-weight:700;letter-spacing:0.1em;color:#444;text-transform:uppercase;margin-bottom:12px;">Active Session</div>
                        <div style="display:flex;align-items:center;gap:12px;padding:12px 14px;background:rgba(255,255,255,0.02);border:1px solid #1a1a1a;border-radius:8px;">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#555" stroke-width="2">
                                <rect x="2" y="3" width="20" height="14" rx="2" ry="2"></rect>
                                <line x1="8" y1="21" x2="16" y2="21"></line>
                                <line x1="12" y1="17" x2="12" y2="21"></line>
                            </svg>
                            <div>
                                <div style="font-size:12px;font-weight:600;color:#ccc;">Current Browser · This Device</div>
                                <div style="font-size:10px;color:#444;margin-top:2px;">Active now</div>
                            </div>
                            <span style="margin-left:auto;font-size:9px;font-weight:700;color:#4ade80;letter-spacing:0.06em;text-transform:uppercase;">Active</span>
                        </div>
                    </div>

                    <div style="margin-top:16px;">
                        <button class="settings-save-btn" style="background:rgba(248,113,113,0.1);color:#f87171;background-image:none;" onclick="window.location.href='../auth/logout.php'">
                            Logout of All Devices
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <div class="account-section" id="section-mt5">
            <div class="page-header">
                <div class="page-header-left">
                    <h2>MT5 Sync</h2>
                    <p>Connect your MetaTrader 5 account and sync trades every 5 minutes</p>
                </div>
            </div>

            <?php if (!empty($mt5_flash['message'])): ?>
                <div class="settings-card" style="border-color: <?php echo $mt5_flash['type'] === 'success' ? 'rgba(74,222,128,0.20)' : 'rgba(248,113,113,0.20)'; ?>;">
                    <div class="settings-card-body" style="color: <?php echo $mt5_flash['type'] === 'success' ? '#4ade80' : '#f87171'; ?>; font-weight: 600;">
                        <?php echo esc_html($mt5_flash['message']); ?>
                    </div>
                </div>
            <?php endif; ?>

            <?php if ($new_plain_api_key): ?>
                <div class="settings-card" style="border-color: rgba(242,202,80,0.18);">
                    <div class="settings-card-header">
                        <span class="settings-card-title">Copy Into Your EA Now</span>
                    </div>
                    <div class="settings-card-body">
                        <div class="settings-field">
                            <label class="settings-label">Sync URL</label>
                            <input type="text" class="settings-input" readonly value="<?php echo esc_attr($mt5_sync_url); ?>">
                        </div>
                        <div class="settings-field">
                            <label class="settings-label">Connection Token</label>
                            <input type="text" class="settings-input" readonly value="<?php echo esc_attr($mt5_conn['connection_token'] ?? ''); ?>">
                        </div>
                        <div class="settings-field">
                            <label class="settings-label">API Key</label>
                            <input type="text" class="settings-input" readonly value="<?php echo esc_attr($new_plain_api_key); ?>">
                            <div class="settings-input-hint">Copy this now. For security, it will not be shown again.</div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <div class="mt5-two-col">
                <div class="settings-card">
                    <div class="settings-card-header">
                        <span class="settings-card-title">Connection Details</span>
                    </div>
                    <div class="settings-card-body">
                        <form method="post">
                            <?php wp_nonce_field('rich_mt5_manage', 'rich_mt5_nonce'); ?>
                            <input type="hidden" name="mt5_form_action" value="connect">

                            <div class="settings-row">
                                <div class="settings-field">
                                    <label class="settings-label">Connection Name</label>
                                    <input type="text" name="connection_name" class="settings-input"
                                           value="<?php echo esc_attr($mt5_conn['connection_name'] ?? 'Primary MT5'); ?>">
                                </div>
                                <div class="settings-field">
                                    <label class="settings-label">MT5 Login</label>
                                    <input type="number" name="mt5_login" class="settings-input" required
                                           value="<?php echo esc_attr($mt5_conn['mt5_login'] ?? ''); ?>">
                                </div>
                            </div>

                            <div class="settings-row">
                                <div class="settings-field">
                                    <label class="settings-label">Server Name</label>
                                    <input type="text" name="server_name" class="settings-input" required
                                           value="<?php echo esc_attr($mt5_conn['server_name'] ?? ''); ?>"
                                           placeholder="Example-Server">
                                </div>
                                <div class="settings-field">
                                    <label class="settings-label">Broker Name</label>
                                    <input type="text" name="broker_name" class="settings-input"
                                           value="<?php echo esc_attr($mt5_conn['broker_name'] ?? ''); ?>"
                                           placeholder="Broker">
                                </div>
                            </div>

                            <button type="submit" class="settings-save-btn">Save MT5 Connection</button>
                        </form>
                    </div>
                </div>

                <div class="settings-card">
                    <div class="settings-card-header">
                        <span class="settings-card-title">Journal Routing</span>
                    </div>
                    <div class="settings-card-body">
                        <?php if ($mt5_conn): ?>
                            <form method="post">
                                <?php wp_nonce_field('rich_mt5_manage', 'rich_mt5_nonce'); ?>
                                <input type="hidden" name="mt5_form_action" value="set_journal">

                                <div class="settings-field">
                                    <label class="settings-label">MT5 trades go to journal</label>
                                    <select name="mt5_journal_id" class="settings-input">
                                        <option value="">Select journal…</option>
                                        <?php foreach ($user_journals as $j): ?>
                                            <option value="<?= (int)$j['id'] ?>"
                                                <?= isset($mt5_conn['journal_id']) && (int)$mt5_conn['journal_id'] === (int)$j['id'] ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($j['name']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <div class="settings-input-hint">
                                        Choose which journal receives all MT5 trades for this connection.
                                    </div>
                                </div>

                                <div class="settings-field">
                                    <label class="settings-label">Sync Endpoint</label>
                                    <input type="text" class="settings-input" readonly value="<?php echo esc_attr($mt5_sync_url); ?>">
                                    <div class="settings-input-hint">
                                        Use this endpoint in your MT5 EA configuration.
                                    </div>
                                </div>

                                <button class="settings-save-btn" type="submit" style="width:100%;margin-top:10px;">
                                    Save MT5 Journal
                                </button>
                            </form>
                        <?php else: ?>
                            <div class="settings-field">
                                <div class="settings-input-hint">
                                    Create and save your MT5 connection first to configure journal routing.
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="settings-card">
                    <div class="settings-card-header">
                        <span class="settings-card-title">Connection Status</span>
                    </div>
                    <div class="settings-card-body">
                        <div class="settings-toggle-row">
                            <div class="settings-toggle-info">
                                <div class="settings-toggle-name">Current Status</div>
                                <div class="settings-toggle-desc">
                                    <?php echo !empty($mt5_conn['is_active']) ? 'Active and ready to receive MT5 syncs' : 'Inactive'; ?>
                                </div>
                            </div>
                            <span style="font-size:11px;font-weight:700;color:<?php echo !empty($mt5_conn['is_active']) ? '#4ade80' : '#888'; ?>;">
                                <?php echo !empty($mt5_conn['is_active']) ? 'ACTIVE' : 'INACTIVE'; ?>
                            </span>
                        </div>

                        <div class="settings-toggle-row">
                            <div class="settings-toggle-info">
                                <div class="settings-toggle-name">Last Sync</div>
                                <div class="settings-toggle-desc"><?php echo esc_html($mt5_conn['last_sync_at'] ?: 'Never'); ?></div>
                            </div>
                        </div>

                        <div class="settings-toggle-row">
                            <div class="settings-toggle-info">
                                <div class="settings-toggle-name">Last Error</div>
                                <div class="settings-toggle-desc"><?php echo esc_html($mt5_conn['last_error'] ?: 'None'); ?></div>
                            </div>
                        </div>

                        <?php if ($mt5_conn): ?>
                            <div style="display:flex;gap:12px;flex-wrap:wrap;margin-top:18px;">
                                <form method="post" style="margin:0;">
                                    <?php wp_nonce_field('rich_mt5_manage', 'rich_mt5_nonce'); ?>
                                    <input type="hidden" name="mt5_form_action" value="reset_key">
                                    <button type="submit" class="settings-save-btn">Reset API Key</button>
                                </form>

                                <form method="post" style="margin:0;">
                                    <?php wp_nonce_field('rich_mt5_manage', 'rich_mt5_nonce'); ?>
                                    <input type="hidden" name="mt5_form_action" value="disconnect">
                                    <button type="submit" class="settings-save-btn" style="background:rgba(248,113,113,0.1);color:#f87171;background-image:none;">
                                        Disconnect MT5
                                    </button>
                                </form>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="settings-card">
                <div class="settings-card-header" style="display:flex;align-items:center;justify-content:space-between;gap:16px;flex-wrap:wrap;">
                    <span class="settings-card-title">EA Setup Instructions</span>
                    <a href="https://www.mql5.com/en/market/product/185350?source=Site+Market+MT5+Search+Rating006%3a2rich"
                       target="_blank" rel="noopener noreferrer" class="mt5-ea-link">
                        Get the 2RICH EA on MQL5 Market
                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                            <line x1="7" y1="17" x2="17" y2="7"></line>
                            <polyline points="7 7 17 7 17 17"></polyline>
                        </svg>
                    </a>
                </div>
                <div class="settings-card-body">
                    <ol class="mt5-steps">
                        <li>Download the <strong style="color:#F2CA50;">2RICH EA</strong> from MQL5 Market and install it in MetaTrader 5.</li>
                        <li>Attach the EA to any chart. It will sync the connected account, so the symbol and timeframe do not matter.</li>
                        <li>In MT5, go to <strong>Tools → Options → Expert Advisors</strong>, enable <strong>Allow WebRequest for listed URL</strong>, and whitelist:<br>
                            <code><?php echo esc_html(home_url('/')); ?></code>
                        </li>
                        <li>Paste your <strong>Sync URL</strong>, <strong>Connection Token</strong>, and <strong>API Key</strong> from this page into the EA inputs.</li>
                        <li>Make sure the <strong>MT5 Login</strong> and <strong>Server Name</strong> in this dashboard match the exact account currently running in MT5.</li>
                        <li>Keep the EA running. It will sync automatically every 5 minutes.</li>
                    </ol>
                </div>
            </div>
        </div>
    </main>
</div>

<script>
function switchSection(name) {
    document.querySelectorAll('.account-section').forEach(s => s.classList.remove('active'));
    document.querySelectorAll('.account-tab').forEach(b => b.classList.remove('active'));
    document.getElementById('section-' + name).classList.add('active');
    document.querySelector('.account-tab[data-section="' + name + '"]').classList.add('active');
}

function switchSection(name) {
    document.querySelectorAll('.account-section').forEach(s => s.classList.remove('active'));
    document.querySelectorAll('.account-tab').forEach(b => b.classList.remove('active'));
    document.getElementById('section-' + name).classList.add('active');
    document.querySelector('.account-tab[data-section="' + name + '"]').classList.add('active');
}

function openSectionFromHash() {
    const hash = window.location.hash.replace('#', '').toLowerCase();
    if (!hash) return;

    const validSections = ['profile', 'preferences', 'notifications', 'security', 'mt5'];
    if (validSections.includes(hash)) {
        switchSection(hash);
    }
}

document.addEventListener('DOMContentLoaded', openSectionFromHash);
window.addEventListener('hashchange', openSectionFromHash);
	
function savePreferences() {
    const btn = document.getElementById('savePrefBtn');
    const feedback = document.getElementById('prefFeedback');
    const val = parseFloat(document.getElementById('defaultStopDist').value);

    if (isNaN(val) || val <= 0) {
        alert('Please enter a valid stop distance.');
        return;
    }

    btn.disabled = true;

    fetch('/api/preferences/set.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        credentials: 'include',
        body: JSON.stringify({ key: 'default_stop_distance', value: val.toFixed(2) })
    })
    .then(r => r.json())
    .then(d => {
        btn.disabled = false;
        if (d.success) {
            feedback.classList.add('visible');
            setTimeout(() => feedback.classList.remove('visible'), 2500);
        } else {
            alert('Save failed: ' + (d.message || 'Unknown error'));
        }
    })
    .catch(() => {
        btn.disabled = false;
        alert('Connection error. Please try again.');
    });
}

function saveProfile() {
    const form = document.getElementById('profileSaveForm');
    if (form) form.submit();
}


function checkPasswordStrength(val) {
    const wrap = document.getElementById('passStrength');
    const bar = document.getElementById('passStrengthBar');
    const label = document.getElementById('passStrengthLabel');

    if (!val) {
        wrap.style.display = 'none';
        return;
    }

    wrap.style.display = 'block';

    let score = 0;
    if (val.length >= 8) score++;
    if (/[A-Z]/.test(val)) score++;
    if (/[0-9]/.test(val)) score++;
    if (/[^A-Za-z0-9]/.test(val)) score++;

    const levels = [
        ['25%', '#f87171', 'Weak'],
        ['50%', '#fb923c', 'Fair'],
        ['75%', '#facc15', 'Good'],
        ['100%', '#4ade80', 'Strong']
    ];

    const [w, c, t] = levels[score - 1] || levels[0];
    bar.style.width = w;
    bar.style.background = c;
    label.textContent = t;
    label.style.color = c;
}
</script>
</body>
</html>