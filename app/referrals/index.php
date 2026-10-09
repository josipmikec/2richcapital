<?php
require_once dirname(__DIR__) . '/auth/session-config.php';
require_once dirname(__DIR__) . '/auth/db.php';

if (!defined('WP_USE_THEMES')) {
    define('WP_USE_THEMES', false);
}
require_once dirname(__DIR__, 2) . '/wp-load.php';
require_once dirname(__DIR__) . '/auth/feature-flags.php';

rich_feature_bootstrap();

if (!isset($_SESSION['user_id']) || !isset($_SESSION['authenticated'])) {
    header('Location: https://app.2rich.capital/login/');
    exit;
}

$user_name  = $_SESSION['user_name']  ?? 'Member';
$user_email = $_SESSION['user_email'] ?? '';
$user_id    = $_SESSION['user_id']    ?? 0;

$ref_code = get_user_meta($user_id, 'rich_referral_code', true);
if (!$ref_code) {
    $ref_code = substr(strtoupper(md5($user_id . time())), 0, 8);
    update_user_meta($user_id, 'rich_referral_code', $ref_code);
}
$ref_link = "https://app.2rich.capital/register/?ref=" . $ref_code;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Referrals - 2RICH CAPITAL</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/dashboard.css?v=<?php echo filemtime(__DIR__ . '/../assets/css/dashboard.css'); ?>">
    <link rel="stylesheet" href="../assets/css/column-manager.css">
    <style>
        /* ── Dashboard Settings Panel ───────────────────────────── */
        @keyframes unreadPulse {
            0% { background-color: rgba(242, 202, 80, 0.25); border-color: rgba(242, 202, 80, 0.5); }
            80% { background-color: rgba(242, 202, 80, 0.1); border-color: rgba(242, 202, 80, 0.2); }
            100% { background-color: rgba(255, 255, 255, 0.025); border-color: #242424; }
        }
        .unread-highlight {
            animation: unreadPulse 5s ease-out forwards !important;
        }

        .dashboard-settings-overlay {
		    display: none;
		    position: fixed;
		    inset: 0;
		    background: rgba(0,0,0,0.8);
		    backdrop-filter: blur(8px);
		    -webkit-backdrop-filter: blur(8px);
		    z-index: 1000;
		    align-items: center;
		    justify-content: center;
		    padding: 24px;
		    animation: fadeIn 0.2s ease;
		}
		.dashboard-settings-overlay.open {
		    display: flex;
		}
		@keyframes fadeIn {
		    from { opacity: 0; }
		    to { opacity: 1; }
		}
		.dashboard-settings-panel {
		    background: linear-gradient(135deg, #1a1a1a 0%, #0E0E0E 100%);
		    border: 1px solid #2a2a2a;
		    border-radius: 16px;
		    width: 100%;
		    max-width: 560px;
		    max-height: 88vh;
		    overflow-y: auto;
		    padding: 32px;
		    display: flex;
		    flex-direction: column;
		    gap: 24px;
		    box-shadow: 0 16px 64px rgba(0,0,0,0.6);
		    position: relative;
		}
		.dashboard-settings-panel::before {
		    content: '';
		    position: absolute;
		    top: 0; left: 0; right: 0;
		    height: 1px;
		    background: linear-gradient(90deg, transparent, rgba(242,202,80,0.4), transparent);
		}

        }
        .dsp-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        .dsp-title {
            font-family: 'Montserrat', sans-serif;
            font-size: 13px;
            font-weight: 700;
            letter-spacing: 0.08em;
            color: #f0c24f;
            text-transform: uppercase;
        }
        .dsp-close {
            background: none;
            border: none;
            color: #666;
            cursor: pointer;
            padding: 4px;
            line-height: 1;
            font-size: 18px;
            transition: color 0.15s;
        }
        .dsp-close:hover { color: #ccc; }
        .dsp-section-label {
            font-family: 'Montserrat', sans-serif;
            font-size: 10px;
            font-weight: 600;
            letter-spacing: 0.1em;
            text-transform: uppercase;
            color: #555;
            margin-bottom: 12px;
        }
        .dsp-sort-list {
            list-style: none;
            margin: 0;
            padding: 0;
            display: flex;
            flex-direction: column;
            gap: 6px;
        }
        /* drag handle */
        .dsp-drag-handle {
            cursor: grab;
            color: #444;
            flex-shrink: 0;
            display: flex;
            align-items: center;
            padding: 2px 2px 2px 0;
            transition: color 0.15s;
        }
        .dsp-drag-handle:active { cursor: grabbing; }
        .dsp-sort-item:hover .dsp-drag-handle { color: #888; }
        .dsp-sort-item.drag-over {
            border-color: #f0c24f;
            background: #2a2700;
            transform: scale(1.01);
        }
        .dsp-sort-item.dragging {
            opacity: 0.35;
            border-style: dashed;
        }
        /* presets */
        .dsp-presets {
            display: flex;
            gap: 6px;
            flex-wrap: wrap;
            margin-bottom: 20px;
        }
        .dsp-preset-btn {
            flex: 1;
            min-width: 0;
            background: #1c1c1c;
            border: 1px solid #333;
            border-radius: 6px;
            color: #888;
            font-family: 'Montserrat', sans-serif;
            font-size: 10px;
            font-weight: 700;
            letter-spacing: 0.07em;
            text-transform: uppercase;
            padding: 8px 6px;
            cursor: pointer;
            transition: border-color 0.15s, color 0.15s, background 0.15s;
            text-align: center;
        }
        .dsp-preset-btn:hover {
            border-color: #f0c24f;
            color: #f0c24f;
            background: #1f1d00;
        }
        .dsp-sort-item {
            display: flex;
            align-items: center;
            gap: 8px;
            background: #242424;
            border: 1px solid #2e2e2e;
            border-radius: 6px;
            padding: 10px 12px;
            font-family: 'Montserrat', sans-serif;
            font-size: 11px;
            font-weight: 600;
            letter-spacing: 0.05em;
            color: #bbb;
            transition: border-color 0.15s, background 0.15s, transform 0.12s;
            user-select: none;
            text-transform: uppercase;
            transition: background 0.15s, border-color 0.15s;
        }
        .dsp-sort-label {
            flex: 1;
        }
        .dsp-move-btn {
            background: none;
            border: none;
            color: #444;
            cursor: pointer;
            padding: 2px 4px;
            line-height: 1;
            border-radius: 3px;
            transition: color 0.15s, background 0.15s;
            display: flex;
            align-items: center;
        }
        .dsp-move-btn:hover {
            color: #f0c24f;
            background: rgba(240,194,79,0.08);
        }
        .dsp-move-btn:disabled {
            opacity: 0.2;
            cursor: default;
        }
        .dsp-move-btn:disabled:hover {
            color: #444;
            background: none;
        }
        .dsp-actions {
            display: flex;
            gap: 8px;
            margin-top: auto;
            padding-top: 8px;
            border-top: 1px solid #222;
        }
        .dsp-btn {
            flex: 1;
            padding: 9px 12px;
            border-radius: 5px;
            font-family: 'Montserrat', sans-serif;
            font-size: 10px;
            font-weight: 700;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            border: none;
            cursor: pointer;
            transition: background 0.15s, color 0.15s;
        }
        .dsp-btn-primary {
            background: #f0c24f;
            color: #0d0d0d;
        }
        .dsp-btn-primary:hover { background: #ffd166; }
        .dsp-btn-ghost {
            background: transparent;
            color: #666;
            border: 1px solid #2e2e2e;
        }
        .dsp-btn-ghost:hover { color: #aaa; border-color: #444; }

        /* ── Welcome section with settings btn ─────────────────── */
        .welcome-section {
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 12px;
        }

<style>
/* ── MT5 Live Feed Panel ───────────────────────────────────────── */
.mt5-panel {
  background: #111;
  border: 1px solid #1e1e1e;
  border-radius: 12px;
  overflow: hidden;
  font-family: "Montserrat", sans-serif;
}
.mt5-panel-header {
  display: flex; align-items: center; justify-content: space-between;
  padding: 14px 20px;
  border-bottom: 1px solid #1a1a1a;
}
.mt5-panel-title {
  font-size: 11px; font-weight: 700; letter-spacing: 0.1em;
  color: #F2CA50; text-transform: uppercase;
  display: flex; align-items: center; gap: 8px;
}
.mt5-live-dot {
  width: 7px; height: 7px; border-radius: 50%;
  background: #22c55e;
  box-shadow: 0 0 8px rgba(34,197,94,0.6);
  animation: mt5-pulse 2s ease-in-out infinite;
}
@keyframes mt5-pulse {
  0%,100% { opacity: 1; } 50% { opacity: 0.3; }
}
.mt5-sync-time {
  font-size: 10px; color: #444; font-weight: 500;
}
.mt5-account-bar {
  display: flex; gap: 24px; padding: 10px 20px;
  border-bottom: 1px solid #1a1a1a;
  background: #0e0e0e;
}
.mt5-account-stat { display: flex; flex-direction: column; gap: 2px; }
.mt5-account-stat .label {
  font-size: 9px; font-weight: 700; letter-spacing: 0.08em;
  color: #444; text-transform: uppercase;
}
.mt5-account-stat .val {
  font-size: 13px; font-weight: 700; color: #ccc;
  font-variant-numeric: tabular-nums;
}
.mt5-account-stat .val.pos { color: #22c55e; }
.mt5-account-stat .val.neg { color: #ef4444; }

/* ── Tabs ── */
.mt5-tabs { display: flex; border-bottom: 1px solid #1a1a1a; }
.mt5-tab {
  flex: 1; padding: 10px; text-align: center;
  font-size: 10px; font-weight: 700; letter-spacing: 0.06em;
  color: #444; cursor: pointer; text-transform: uppercase;
  border-bottom: 2px solid transparent;
  transition: color 0.2s, border-color 0.2s;
}
.mt5-tab.active { color: #F2CA50; border-bottom-color: #F2CA50; }
.mt5-tab:hover:not(.active) { color: #888; }

/* ── Table ── */
.mt5-table-wrap {
  overflow-x: auto; overflow-y: auto;
  max-height: 420px;
  scrollbar-width: thin; scrollbar-color: #1a1a1a transparent;
}
.mt5-table-wrap::-webkit-scrollbar { width: 4px; height: 4px; }
.mt5-table-wrap::-webkit-scrollbar-thumb { background: #1a1a1a; border-radius: 4px; }

.mt5-table { width: 100%; border-collapse: collapse; }
.mt5-table th {
  font-size: 9px; font-weight: 700; letter-spacing: 0.08em;
  color: #444; text-transform: uppercase;
  padding: 8px 16px; text-align: left;
  border-bottom: 1px solid #1a1a1a; white-space: nowrap;
  position: sticky; top: 0; background: #111; z-index: 1;
}
.mt5-table td {
  font-size: 12px; color: #bbb;
  padding: 9px 16px; border-bottom: 1px solid #111;
  white-space: nowrap; font-variant-numeric: tabular-nums;
}
.mt5-table tr:hover td { background: rgba(255,255,255,0.02); }

.mt5-badge {
  display: inline-flex; align-items: center; justify-content: center;
  font-size: 9px; font-weight: 800; letter-spacing: 0.06em;
  padding: 2px 7px; border-radius: 4px;
}
.mt5-badge.buy  { background: rgba(34,197,94,0.12);  color: #22c55e; }
.mt5-badge.sell { background: rgba(239,68,68,0.12);  color: #ef4444; }
.mt5-badge.win  { background: rgba(34,197,94,0.12);  color: #22c55e; }
.mt5-badge.loss { background: rgba(239,68,68,0.12);  color: #ef4444; }
.mt5-badge.be   { background: rgba(156,163,175,0.12);color: #9ca3af; }

.mt5-profit.pos { color: #22c55e; font-weight: 700; }
.mt5-profit.neg { color: #ef4444; font-weight: 700; }

.trade-status-row {
    display: flex;
    align-items: center;
    gap: 8px;
    margin-bottom: 10px;
    font-size: 11px;
    color: #888;
    font-family: 'Montserrat', sans-serif;
}

.trade-status-row--secondary {
    margin-bottom: 6px;
    font-size: 10px;
    opacity: 0.8;
}

.status-dot {
    width: 8px;
    height: 8px;
    border-radius: 50%;
    background: #444;
    box-shadow: 0 0 0 0 rgba(0,0,0,0);
}

.status-dot--mini {
    width: 6px;
    height: 6px;
}

.status-label--secondary {
    font-size: 10px;
}

.status-online {
    background: #22c55e;
    box-shadow: 0 0 8px rgba(34,197,94,0.6);
}

.status-stale {
    background: #facc15;
    box-shadow: 0 0 8px rgba(250,204,21,0.5);
}

.status-offline {
    background: #f97373;
    box-shadow: 0 0 8px rgba(249,115,115,0.5);
}
	
.mt5-empty {
  text-align: center; padding: 40px 20px;
  color: #333; font-size: 11px; font-weight: 600;
  letter-spacing: 0.06em; text-transform: uppercase;
}

.trade-kpis {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 10px;
    margin-bottom: 14px;
}

.trade-kpi {
    background: rgba(255,255,255,0.02);
    border: 1px solid #232323;
    border-radius: 10px;
    padding: 10px 12px;
    display: flex;
    flex-direction: column;
    gap: 4px;
}

.trade-kpi-label {
    font-size: 10px;
    letter-spacing: 0.08em;
    text-transform: uppercase;
    color: #666;
    font-family: 'Montserrat', sans-serif;
    font-weight: 600;
}

.trade-kpi-value {
    font-size: 15px;
    font-weight: 700;
    color: #f3f3f3;
    font-family: 'Montserrat', sans-serif;
}

.trade-mini-list {
    display: flex;
    flex-direction: column;
    gap: 8px;
    margin-bottom: 14px;
    max-height: 210px;          /* ~2.5 rows visible, rest scrolls */
    overflow-y: auto;
    overflow-x: hidden;
    padding-right: 4px;
    scrollbar-width: thin;
    scrollbar-color: #333 transparent;
}
.trade-mini-list::-webkit-scrollbar { width: 4px; }
.trade-mini-list::-webkit-scrollbar-thumb { background: #2a2a2a; border-radius: 4px; }
.trade-mini-list::-webkit-scrollbar-track { background: transparent; }

.trade-row {
    background: #161616;
    border: 1px solid #232323;
    border-radius: 10px;
    padding: 10px 12px;
    display: flex;
    flex-direction: column;
    gap: 5px;
}

.trade-row-main {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 10px;
}

.trade-row-meta {
    display: flex;
    flex-wrap: wrap;
    gap: 8px 14px;
    font-size: 11px;
    color: #8b8b8b;
    font-family: 'Montserrat', sans-serif;
}

.trade-row-time {
    font-size: 10px;
    color: #666;
    font-family: 'Montserrat', sans-serif;
}

.trade-symbol {
    font-size: 12px;
    font-weight: 700;
    color: #f1f1f1;
    font-family: 'Montserrat', sans-serif;
    letter-spacing: 0.04em;
}

.trade-badge {
    font-size: 10px;
    font-weight: 700;
    letter-spacing: 0.08em;
    text-transform: uppercase;
    border-radius: 999px;
    padding: 4px 8px;
    font-family: 'Montserrat', sans-serif;
    border: 1px solid transparent;
}

.trade-badge.buy {
    color: #63d391;
    background: rgba(24, 122, 66, 0.16);
    border-color: rgba(99, 211, 145, 0.22);
}

.trade-badge.sell {
    color: #ff7b7b;
    background: rgba(140, 36, 36, 0.16);
    border-color: rgba(255, 123, 123, 0.22);
}

.trade-empty {
    background: #141414;
    border: 1px dashed #2c2c2c;
    border-radius: 10px;
    padding: 16px 12px;
    text-align: center;
    color: #777;
    font-size: 11px;
    line-height: 1.5;
    font-family: 'Montserrat', sans-serif;
}


#live-pane, #closed-pane, #planned-pane {
    display: flex;
    flex-direction: column;
    min-height: 400px;   /* tune to whatever height fits your grid row */
}

.widget-trades .widget-action {
    margin-top: auto;    /* keeps it pinned to the bottom of the pane */
}

.pos { color: #63d391; }
.neg { color: #ff7b7b; }

    </style>
</head>
<body>

    <div class="dashboard-background"></div>

<?php include __DIR__ . '/../components/promo-bar.php'; ?>

    <nav class="top-nav">
        <div class="nav-container">
            <div class="nav-brand" style="display: flex; flex-direction: column; justify-content: center;">
                <div class="nav-brand-flipper" style="perspective: 1600px; line-height: 1;">
                    <div class="nav-brand-inner" id="brandFlipperInner" style="display: grid; transform-style: preserve-3d; transition: transform 1.2s cubic-bezier(0.16, 1, 0.3, 1);">
                        <div class="nav-brand-face nav-brand-front" style="grid-area: 1 / 1; backface-visibility: hidden;">
                            <h1>2RICH CAPITAL</h1>
                        </div>
                        <div class="nav-brand-face nav-brand-back" style="grid-area: 1 / 1; backface-visibility: hidden; transform: rotateX(180deg);">
                            <h1 style="text-transform: none; background: none; -webkit-text-fill-color: #fff; color: #fff; margin-bottom: 0;">
                                <span style="background: linear-gradient(to right, #D4AF37 0%, #FFF5C3 50%, #F2CA50 100%); -webkit-background-clip: text; -webkit-text-fill-color: transparent; filter: drop-shadow(0 0 10px rgba(242,202,80,0.2));">Welcome</span> back, <?php echo htmlspecialchars(explode(' ', $user_name)[0]); ?>
                            </h1>
                        </div>
                    </div>
                </div>
                <span class="nav-tagline" style="margin-top: 4px;">INSTITUTIONAL GRADE TRADING</span>
            </div>
            <div class="nav-right">
                <div class="tf-topbar-avatar" onclick="window.location.href='/trading-floor/?user_id=<?php echo $user_id; ?>'" title="Account"><?php echo strtoupper(substr($_SESSION['user_name'] ?? 'M', 0, 1)); ?></div>
                <div style="display:flex; gap:8px;">
                    <button type="button" class="logout-btn" onclick="openGlobalSettingsModal('dashboard')">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="margin-right:6px;vertical-align:middle;margin-bottom:2px;"><circle cx="12" cy="12" r="3"></circle><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"></path></svg><span class="settings-text-mobile">SETTINGS</span>
                    </button>
                    <a href="/auth/logout.php" class="logout-btn icon-only" title="Logout">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path><polyline points="16 17 21 12 16 7"></polyline><line x1="21" y1="12" x2="9" y2="12"></line></svg>
                    </a>
                </div>
            </div>
        </div>
    </nav>

    <div class="dashboard-container">

        <aside class="sidebar">
            <ul class="sidebar-menu">
                <li class="menu-item" <?php echo !rich_feature_enabled('dashboard', true, $user_id) ? 'style="opacity: 0.5;"' : ''; ?> onclick="window.location.href='/dashboard'">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <rect x="3" y="3" width="7" height="7"></rect>
                        <rect x="14" y="3" width="7" height="7"></rect>
                        <rect x="14" y="14" width="7" height="7"></rect>
                        <rect x="3" y="14" width="7" height="7"></rect>
                    </svg>
                    <span>Dashboard</span>
                    <?php if (!rich_feature_enabled('dashboard', true, $user_id)): ?><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#f2ca50" stroke-width="2" style="margin-left:auto;"><path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z"></path></svg><?php endif; ?>
                </li>
                <li class="menu-item" <?php echo !rich_feature_enabled('journal', true, $user_id) ? 'style="opacity: 0.5;"' : ''; ?> onclick="window.location.href='/journal'">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                        <polyline points="14 2 14 8 20 8"></polyline>
                        <line x1="16" y1="13" x2="8" y2="13"></line>
                        <line x1="16" y1="17" x2="8" y2="17"></line>
                        <polyline points="10 9 9 9 8 9"></polyline>
                    </svg>
                    <span>Trading Journal</span>
                    <?php if (!rich_feature_enabled('journal', true, $user_id)): ?><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#f2ca50" stroke-width="2" style="margin-left:auto;"><path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z"></path></svg><?php endif; ?>
                </li>
                <li class="menu-item" <?php echo !rich_feature_enabled('trading-floor', true, $user_id) ? 'style="opacity: 0.5;"' : ''; ?> onclick="window.location.href='/trading-floor'">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <line x1="12" y1="1" x2="12" y2="23"></line>
                        <path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"></path>
                    </svg>
                    <span>Trading Floor</span>
                    <?php if (!rich_feature_enabled('trading-floor', true, $user_id)): ?><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#f2ca50" stroke-width="2" style="margin-left:auto;"><path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z"></path></svg><?php endif; ?>
                </li>
                <li class="menu-item" <?php echo !rich_feature_enabled('market-data', true, $user_id) ? 'style="opacity: 0.5;"' : ''; ?> onclick="window.location.href='/market-data'">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <rect x="2" y="3" width="20" height="14" rx="2" ry="2"></rect>
                        <line x1="8" y1="21" x2="16" y2="21"></line>
                        <line x1="12" y1="17" x2="12" y2="21"></line>
                    </svg>
                    <span>Market Data</span>
                    <?php if (!rich_feature_enabled('market-data', true, $user_id)): ?><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#f2ca50" stroke-width="2" style="margin-left:auto;"><path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z"></path></svg><?php endif; ?>
                </li>
                <li class="menu-item" onclick="window.location.href='/account'">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                        <circle cx="12" cy="7" r="4"></circle>
                    </svg>
                    <span>Account</span>
                </li>
                
            <li class="menu-item" onclick="window.location.href='/referrals'">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M23 21v-2a4 4 0 0 0-3-3.87"></path><path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
                </svg>
                <span>Referrals</span>
            </li>
            <li class="menu-item" onclick="window.location.href='/partners'">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"></polygon>
                </svg>
                <span>Partners</span>
            </li>
            <?php if (rich_is_staff()): ?>
                <li class="menu-item" onclick="window.location.href='/admin'">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M12 3l7 4v5c0 5-3.5 8-7 9-3.5-1-7-4-7-9V7l7-4z"></path>
                        <path d="M9.5 12l1.5 1.5 3.5-3.5"></path>
                    </svg>
                    <span>Admin</span>
                </li>
                <?php endif; ?>
            </ul>
        </aside>

        <main class="main-content">
        <div style="padding: 32px; max-width: 1200px; margin: 0 auto; width: 100%; box-sizing: border-box;">
            <div style="margin-bottom: 32px;">
                <h1 style="font-size:28px; font-weight:800; color:#fff; margin:0 0 8px 0; text-transform:uppercase; letter-spacing:0.05em;">Referrals</h1>
                <p style="color:#aaa; font-size:14px; margin:0;">Invite traders to 2RICH CAPITAL and earn 20% recurring commissions.</p>
            </div>

            <!-- Stats Grid -->
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 24px; margin-bottom: 32px;">
                <div style="background:#111; border:1px solid #1e1e1e; border-radius:12px; padding:24px; transition: transform 0.2s;" onmouseover="this.style.transform='translateY(-2px)'" onmouseout="this.style.transform='none'">
                    <div style="font-size:11px; color:#888; text-transform:uppercase; letter-spacing:0.1em; font-weight:700; margin-bottom:8px;">Total Referrals</div>
                    <div style="font-size:32px; font-weight:800; color:#fff;">12</div>
                </div>
                <div style="background:#111; border:1px solid #1e1e1e; border-radius:12px; padding:24px; transition: transform 0.2s;" onmouseover="this.style.transform='translateY(-2px)'" onmouseout="this.style.transform='none'">
                    <div style="font-size:11px; color:#888; text-transform:uppercase; letter-spacing:0.1em; font-weight:700; margin-bottom:8px;">Active Members</div>
                    <div style="font-size:32px; font-weight:800; color:#6ee7b7;">8</div>
                </div>
                <div style="background:#111; border:1px solid #1e1e1e; border-radius:12px; padding:24px; transition: transform 0.2s;" onmouseover="this.style.transform='translateY(-2px)'" onmouseout="this.style.transform='none'">
                    <div style="font-size:11px; color:#888; text-transform:uppercase; letter-spacing:0.1em; font-weight:700; margin-bottom:8px;">Total Earnings</div>
                    <div style="font-size:32px; font-weight:800; color:#f2ca50;">$450.00</div>
                </div>
            </div>

            <!-- Referral Link Box -->
            <div style="background:rgba(242,202,80,0.03); border:1px solid rgba(242,202,80,0.15); border-radius:16px; padding:32px; margin-bottom:32px; display:flex; align-items:center; gap:24px; flex-wrap:wrap;">
                <div style="flex:1; min-width:280px;">
                    <h2 style="font-size:18px; font-weight:700; color:#fff; margin:0 0 8px 0;">Your Unique Invite Link</h2>
                    <p style="font-size:13px; color:#aaa; margin:0; line-height: 1.5;">Share this link with your network. When they sign up and subscribe to any paid plan, you will receive a 20% recurring commission for the lifetime of their membership.</p>
                </div>
                <div style="display:flex; flex:2; min-width:300px; background:#000; border:1px solid #222; border-radius:8px; overflow:hidden;">
                    <input type="text" id="refLinkInput" readonly value="<?php echo htmlspecialchars($ref_link); ?>" style="flex:1; background:transparent; border:none; color:#f5f5f5; padding:16px; font-family:monospace; font-size:14px; outline:none;">
                    <button onclick="navigator.clipboard.writeText(document.getElementById('refLinkInput').value); this.innerText='Copied!'; setTimeout(() => this.innerText='Copy', 2000);" style="background:#f2ca50; color:#000; border:none; padding:0 24px; font-weight:700; font-size:12px; letter-spacing:0.05em; text-transform:uppercase; cursor:pointer; transition: background 0.2s;" onmouseover="this.style.background='#e0b83e'" onmouseout="this.style.background='#f2ca50'">Copy</button>
                </div>
            </div>

            <!-- Referrals Table -->
            <div style="background:#111; border:1px solid #1e1e1e; border-radius:16px; overflow:hidden;">
                <div style="padding:20px 24px; border-bottom:1px solid #1e1e1e; background:rgba(255,255,255,0.01);">
                    <h3 style="margin:0; font-size:14px; font-weight:700; color:#fff; text-transform:uppercase; letter-spacing:0.05em;">Referred Users</h3>
                </div>
                <div style="overflow-x:auto;">
                    <table style="width:100%; border-collapse:collapse; text-align:left; min-width:600px;">
                        <thead>
                            <tr>
                                <th style="padding:16px 24px; font-size:11px; color:#666; text-transform:uppercase; letter-spacing:0.1em; font-weight:600; border-bottom:1px solid #1e1e1e;">User</th>
                                <th style="padding:16px 24px; font-size:11px; color:#666; text-transform:uppercase; letter-spacing:0.1em; font-weight:600; border-bottom:1px solid #1e1e1e;">Joined Date</th>
                                <th style="padding:16px 24px; font-size:11px; color:#666; text-transform:uppercase; letter-spacing:0.1em; font-weight:600; border-bottom:1px solid #1e1e1e;">Status</th>
                                <th style="padding:16px 24px; font-size:11px; color:#666; text-transform:uppercase; letter-spacing:0.1em; font-weight:600; border-bottom:1px solid #1e1e1e;">Commission Earned</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr style="transition: background 0.2s;" onmouseover="this.style.background='rgba(255,255,255,0.02)'" onmouseout="this.style.background='transparent'">
                                <td style="padding:16px 24px; border-bottom:1px solid #1e1e1e;">
                                    <div style="font-size:14px; font-weight:600; color:#fff;">John Doe</div>
                                    <div style="font-size:12px; color:#888;">john@example.com</div>
                                </td>
                                <td style="padding:16px 24px; font-size:13px; color:#ccc; border-bottom:1px solid #1e1e1e;">Oct 1, 2026</td>
                                <td style="padding:16px 24px; border-bottom:1px solid #1e1e1e;">
                                    <span style="background:rgba(110,231,183,0.1); color:#6ee7b7; padding:4px 10px; border-radius:99px; font-size:10px; font-weight:700; text-transform:uppercase; letter-spacing:0.05em;">Active</span>
                                </td>
                                <td style="padding:16px 24px; font-size:14px; font-weight:600; color:#f2ca50; border-bottom:1px solid #1e1e1e;">$50.00</td>
                            </tr>
                            <tr style="transition: background 0.2s;" onmouseover="this.style.background='rgba(255,255,255,0.02)'" onmouseout="this.style.background='transparent'">
                                <td style="padding:16px 24px; border-bottom:1px solid #1e1e1e;">
                                    <div style="font-size:14px; font-weight:600; color:#fff;">Alex Smith</div>
                                    <div style="font-size:12px; color:#888;">alex@example.com</div>
                                </td>
                                <td style="padding:16px 24px; font-size:13px; color:#ccc; border-bottom:1px solid #1e1e1e;">Oct 5, 2026</td>
                                <td style="padding:16px 24px; border-bottom:1px solid #1e1e1e;">
                                    <span style="background:rgba(252,211,77,0.1); color:#fcd34d; padding:4px 10px; border-radius:99px; font-size:10px; font-weight:700; text-transform:uppercase; letter-spacing:0.05em;">Pending</span>
                                </td>
                                <td style="padding:16px 24px; font-size:14px; font-weight:600; color:#666; border-bottom:1px solid #1e1e1e;">$0.00</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

    </main>
</div>
<?php include_once dirname(__DIR__) . '/components/general-settings-modal.php'; ?>
<?php include_once dirname(__DIR__) . '/components/upgrade-modal.php'; ?>
<script>
    // Remove active class from dashboard
    document.querySelectorAll('.menu-item').forEach(el => el.classList.remove('active'));
    // We will highlight referrals later
</script>
</body>
</html>
