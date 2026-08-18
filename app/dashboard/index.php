<?php
require_once '../auth/session-config.php';
require_once '../auth/db.php'; // provides $pdo

if (!defined('WP_USE_THEMES')) {
    define('WP_USE_THEMES', false);
}
require_once dirname(__DIR__, 2) . '/wp-load.php';
require_once '../auth/feature-flags.php';

rich_feature_bootstrap();

if (!isset($_SESSION['user_id']) || !isset($_SESSION['authenticated'])) {
    header('Location: https://app.2rich.capital/login/');
    exit;
}

$user_name  = $_SESSION['user_name']  ?? 'Member';
$user_email = $_SESSION['user_email'] ?? '';
$user_id    = $_SESSION['user_id']    ?? 0;

// ── Load saved dashboard layout for this user (server-side, no flash) ──
$_dashboard_default_order = ['market','signals','news','classroom','strategies','trades','mentors','ai','chat','journal'];
$_dashboard_initial_order = $_dashboard_default_order;

try {
    $__uid = (int)($_SESSION['user_id'] ?? 0);
    if ($__uid > 0) {
        $__stmt = $pdo->prepare("SELECT layout_order FROM user_dashboard_layouts WHERE user_id = ? LIMIT 1");
        $__stmt->execute([$__uid]);
        $__row = $__stmt->fetch(PDO::FETCH_ASSOC);

        if ($__row && !empty($__row['layout_order'])) {
            $__saved = json_decode($__row['layout_order'], true);
            if (is_array($__saved) && count($__saved) > 0) {
                $__filtered = array_values(array_filter($__saved, fn($id) => in_array($id, $_dashboard_default_order)));
                $__missing  = array_values(array_filter($_dashboard_default_order, fn($id) => !in_array($id, $__filtered)));
                $_dashboard_initial_order = array_merge($__filtered, $__missing);
            }
        }
    }
} catch (Throwable $__e) {
    $_dashboard_initial_order = $_dashboard_default_order;
}

// ── Load MT5 connection token separately so it never breaks layout loading ──
$mt5_connection_token = '';

try {
    global $wpdb;
    $__uid = (int)($_SESSION['user_id'] ?? 0);

    if ($__uid > 0) {
        $__table_mt5 = $wpdb->prefix . 'rich_mt5_connections';

        $__mt5Row = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT connection_token
                 FROM {$__table_mt5}
                 WHERE user_id = %d
                   AND is_active = 1
                 ORDER BY id DESC
                 LIMIT 1",
                $__uid
            ),
            ARRAY_A
        );

        if ($__mt5Row && !empty($__mt5Row['connection_token'])) {
            $mt5_connection_token = (string)$__mt5Row['connection_token'];
        }
    }
} catch (Throwable $__e) {
    $mt5_connection_token = '';
}

// ── Filter visible cards based on feature flags ──
$visible_cards = [];
foreach ($_dashboard_initial_order as $card_id) {
    if (rich_card_visible($card_id, $user_id)) {
        $visible_cards[] = $card_id;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - 2RICH CAPITAL</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/dashboard.css?v=<?php echo filemtime(__DIR__ . '/../assets/css/dashboard.css'); ?>">
    <link rel="stylesheet" href="../assets/css/column-manager.css">
    <style>
        /* ── Dashboard Settings Panel ───────────────────────────── */
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

    <div class="dashboard-container">

        <aside class="sidebar">
            <ul class="sidebar-menu">
                <li class="menu-item active" onclick="window.location.href='/dashboard'">
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
                <li class="menu-item" onclick="window.location.href='/account'">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                        <circle cx="12" cy="7" r="4"></circle>
                    </svg>
                    <span>Account</span>
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

            <div class="welcome-section">
                <div>
                    <h2 class="welcome-title">Welcome back, <?php echo htmlspecialchars($user_name); ?></h2>
                    <p class="welcome-subtitle">Your institutional trading platform</p>
                </div>
                <button class="btn-secondary" onclick="openDashboardSettings()">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="12" cy="12" r="3"></circle>
                        <path d="M12 1v6m0 10v6m11-11h-6M7 12H1"></path>
                        <path d="M19.78 4.22l-4.24 4.24M8.46 15.54l-4.24 4.24M19.78 19.78l-4.24-4.24M8.46 8.46L4.22 4.22"></path>
                    </svg>
                    Settings
                </button>
            </div>

            <div class="widget-grid" id="widgetGrid">

                <?php if (in_array('market', $visible_cards)): ?>
                <div class="widget widget-market" data-card-id="market" style="position:relative;">
                    <?php
                    $overlay = rich_feature_overlay('card-market', $user_id);
                    if ($overlay): ?>
                    <div class="card-overlay" style="position:absolute;inset:0;background:rgba(14,14,14,0.42);backdrop-filter:blur(4px) saturate(135%);-webkit-backdrop-filter:blur(4px) saturate(135%);display:flex;align-items:center;justify-content:center;z-index:10;border-radius:inherit;">
                        <div style="text-align:center;padding:24px;max-width:280px;">
                            <div style="font-size:14px;font-weight:700;color:#f2ca50;margin-bottom:8px;"><?php echo esc_html($overlay['message']); ?></div>
                        </div>
                    </div>
                    <?php endif; ?>
                    <div class="widget-tabs">
                        <button class="wtab active">Market</button>
                    </div>
                    <div class="widget-body market-pane-body">
					    <div class="tech-engine">
					        <div class="tech-engine-header">
					            <div class="tech-engine-title">Technical Engine</div>
					            <div class="tech-engine-realtime">Real-Time</div>
					        </div>
					
					        <div class="tech-engine-chart">
					            <div class="tech-engine-stats">
					                <div>
					                    <span class="tech-engine-stat-label" id="techStatPrevLabel">Prev Week O/C</span>
					                    <span class="tech-engine-stat-value" id="techStatPrevOC">– / –</span>
					                </div>
					                <div>
					                    <span class="tech-engine-stat-label">This Week %</span>
					                    <span class="tech-engine-stat-value" id="techStatChange">0.00%</span>
					                </div>
					            </div>
					
					            <svg id="techEngineChartSvg" viewBox="0 0 100 60" preserveAspectRatio="none"></svg>
					
					            <div class="tech-engine-price-tag" id="techPriceTag">
					                XAUUSD 0000.00
					            </div>
					        </div>
					
					        <div class="tech-engine-signals">
					            <div class="tech-engine-signal">
					                <span class="tech-engine-signal-label">Moving Average (200)</span>
					                <span class="tech-engine-signal-value" id="signalMA">--</span>
					            </div>
					            <div class="tech-engine-signal">
					                <span class="tech-engine-signal-label">Daily RSI</span>
					                <span class="tech-engine-signal-value" id="signalRSI">--</span>
					            </div>
					            <div class="tech-engine-signal">
					                <span class="tech-engine-signal-label">Market Structure</span>
					                <span class="tech-engine-signal-value" id="signalMS">--</span>
					            </div>
					        </div>
					    </div>
					
					    <button class="widget-action market-pane-action" onclick="window.location.href='/market-data'">
					        View Markets
					        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
					            <line x1="5" y1="12" x2="19" y2="12"></line>
					            <polyline points="12 5 19 12 12 19"></polyline>
					        </svg>
					    </button>
					</div>
                </div>
                <?php endif; ?>

                <?php if (in_array('signals', $visible_cards)): ?>
                <div class="widget widget-signals" data-card-id="signals" style="position:relative;">
                    <?php
                    $overlay = rich_feature_overlay('card-signals', $user_id);
                    if ($overlay): ?>
                    <div class="card-overlay" style="position:absolute;inset:0;background:rgba(14,14,14,0.42);backdrop-filter:blur(4px) saturate(135%);-webkit-backdrop-filter:blur(4px) saturate(135%);display:flex;align-items:center;justify-content:center;z-index:10;border-radius:inherit;">
                        <div style="text-align:center;padding:24px;max-width:280px;">
                            <div style="font-size:14px;font-weight:700;color:#f2ca50;margin-bottom:8px;"><?php echo esc_html($overlay['message']); ?></div>
                        </div>
                    </div>
                    <?php endif; ?>
                    <div class="widget-tabs">
                        <button class="wtab wtab-tooltip" id="signalsTabDiscovery" onclick="switchSignalsTab('discovery')" data-tooltip="Browse verified analyst groups. Join free communities instantly or subscribe to unlock premium desks.">Discovery</button>
                        <button class="wtab" id="signalsTabFeed" onclick="switchSignalsTab('feed')">My Signals</button>
                    </div>
                    <div class="widget-body">
                        <div class="signals-state" id="signalsDiscoveryState">
                            <div class="signals-membership-strip" id="signalsMembershipStrip" hidden>
                                <span class="signals-membership-strip-label" id="signalsMembershipCount">0 active memberships</span>
                            </div>
                            <div class="signals-group-list" id="signalsGroupList">
                                <div class="signals-empty">Loading signal groups…</div>
                            </div>
                            <button class="widget-action" onclick="window.location.href='/trading-floor/'">
                                Explore Trading Floor
                                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="5" y1="12" x2="19" y2="12"></line><polyline points="12 5 19 12 12 19"></polyline></svg>
                            </button>
                        </div>

                        <div class="signals-state" id="signalsJoinedState" hidden>
                            <div class="signals-feed-toolbar">
                                <select class="signals-switcher" id="signalsGroupSwitcher"></select>
                                <span class="signals-feed-count" id="signalsFeedCount"></span>
                            </div>
                            <div class="signals-feed-list" id="signalsFeedList">
                                <div class="signals-empty">Loading live signals…</div>
                            </div>
                            <button class="widget-action" onclick="window.location.href='/trading-floor/'">
                                Open Trading Floor
                                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="5" y1="12" x2="19" y2="12"></line><polyline points="12 5 19 12 12 19"></polyline></svg>
                            </button>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <?php if (in_array('news', $visible_cards)): ?>
                <div class="widget widget-news" data-card-id="news" style="position:relative;">
                    <?php
                    $overlay = rich_feature_overlay('card-news', $user_id);
                    if ($overlay): ?>
                    <div class="card-overlay" style="position:absolute;inset:0;background:rgba(14,14,14,0.42);backdrop-filter:blur(4px) saturate(135%);-webkit-backdrop-filter:blur(4px) saturate(135%);display:flex;align-items:center;justify-content:center;z-index:10;border-radius:inherit;">
                        <div style="text-align:center;padding:24px;max-width:280px;">
                            <div style="font-size:14px;font-weight:700;color:#f2ca50;margin-bottom:8px;"><?php echo esc_html($overlay['message']); ?></div>
                        </div>
                    </div>
                    <?php endif; ?>
                    <div class="widget-tabs">
                        <button class="wtab active">News</button>
                    </div>
                    <div class="widget-body news-widget-body">
					<div class="news-feed-header">
					    <span class="news-feed-title">Live News Feed</span>
					    <div style="display:flex; align-items:center; gap:14px;">
					        <span class="news-feed-status" id="newsFeedStatus">
					            <span class="news-dot disconnected"></span>
					            <span class="news-status-text">Connecting...</span>
					        </span>
					        <button class="news-popout-btn" onclick="openNewsWindow()" title="Open in new window">
					            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
					                <polyline points="15 3 21 3 21 9"></polyline>
					                <polyline points="9 21 3 21 3 15"></polyline>
					                <line x1="21" y1="3" x2="14" y2="10"></line>
					                <line x1="3" y1="21" x2="10" y2="14"></line>
					            </svg>
					        </button>
					    </div>
					</div>
                        <div class="news-feed-list" id="newsFeedList">
                            <div class="news-feed-empty">Connecting to live feed...</div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <?php if (in_array('classroom', $visible_cards)): ?>
                <div class="widget widget-classroom" data-card-id="classroom" style="position:relative;">
                    <?php
                    $overlay = rich_feature_overlay('card-classroom', $user_id);
                    if ($overlay): ?>
                    <div class="card-overlay" style="position:absolute;inset:0;background:rgba(14,14,14,0.42);backdrop-filter:blur(4px) saturate(135%);-webkit-backdrop-filter:blur(4px) saturate(135%);display:flex;align-items:center;justify-content:center;z-index:10;border-radius:inherit;">
                        <div style="text-align:center;padding:24px;max-width:280px;">
                            <div style="font-size:14px;font-weight:700;color:#f2ca50;margin-bottom:8px;"><?php echo esc_html($overlay['message']); ?></div>
                        </div>
                    </div>
                    <?php endif; ?>
                    <div class="widget-tabs">
                        <button class="wtab active" onclick="switchTab(this,'class-pane')">Classroom</button>
                        <button class="wtab" onclick="switchTab(this,'classchat-pane')">Classroom Chat</button>
                        <button class="wtab" onclick="switchTab(this,'library-pane')">Library</button>
                        <button class="wtab" onclick="switchTab(this,'video-pane')">Video Library</button>
                    </div>
                    <div class="widget-body" id="class-pane">
                        <p class="widget-header">Mentors</p>
                        <div class="mentor-row">
                            <div class="mentor-chip">
                                <div class="mentor-avatar" style="background:linear-gradient(135deg,#7c3aed,#9d5cf7)">JD</div>
                                <span class="mentor-name">James D.</span>
                            </div>
                            <div class="mentor-chip">
                                <div class="mentor-avatar" style="background:linear-gradient(135deg,#059669,#10b981)">SK</div>
                                <span class="mentor-name">Sara K.</span>
                            </div>
                            <div class="mentor-chip">
                                <div class="mentor-avatar" style="background:linear-gradient(135deg,#0284c7,#38bdf8)">CL</div>
                                <span class="mentor-name">Chen L.</span>
                            </div>
                        </div>
                        <div class="widget-blank"></div>
                    </div>
                    <div class="widget-body" id="classchat-pane" style="display:none">
                        <p class="widget-header">Classroom Chat</p>
                        <div class="widget-blank"></div>
                    </div>
                    <div class="widget-body" id="library-pane" style="display:none">
                        <p class="widget-header">Library</p>
                        <div class="widget-blank"></div>
                    </div>
                    <div class="widget-body" id="video-pane" style="display:none">
                        <p class="widget-header">Video Library</p>
                        <div class="widget-blank"></div>
                    </div>
                </div>
                <?php endif; ?>

                <?php if (in_array('strategies', $visible_cards)): ?>
                <div class="widget widget-strategies" data-card-id="strategies" style="position:relative;">
                    <?php
                    $overlay = rich_feature_overlay('card-strategies', $user_id);
                    if ($overlay): ?>
                    <div class="card-overlay" style="position:absolute;inset:0;background:rgba(14,14,14,0.42);backdrop-filter:blur(4px) saturate(135%);-webkit-backdrop-filter:blur(4px) saturate(135%);display:flex;align-items:center;justify-content:center;z-index:10;border-radius:inherit;">
                        <div style="text-align:center;padding:24px;max-width:280px;">
                            <div style="font-size:14px;font-weight:700;color:#f2ca50;margin-bottom:8px;"><?php echo esc_html($overlay['message']); ?></div>
                        </div>
                    </div>
                    <?php endif; ?>
                    <div class="widget-tabs">
                        <button class="wtab active" onclick="switchTab(this,'strat-pane')">Strategies</button>
                        <button class="wtab" onclick="switchTab(this,'backtest-pane')">Backtesting</button>
                    </div>
                    <div class="widget-body" id="strat-pane">
                        <p class="widget-header">My Strategies &amp; Backtesting</p>
                        <div class="widget-content-block">
                            <p class="widget-content-text">Build, store, and refine your personal trading strategies. Document entry rules, risk parameters, and session filters.</p>
                        </div>
                        <div class="widget-meta">Saved: <span>3 strategies</span></div>
                        <button class="widget-action">Open Strategy Builder <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg></button>
                    </div>
                    <div class="widget-body" id="backtest-pane" style="display:none">
                        <p class="widget-header">Backtesting</p>
                        <div class="widget-content-block">
                            <p class="widget-content-text">Run historical simulations on your strategies. Review win rate, drawdown, and expectancy across different timeframes.</p>
                        </div>
                        <div class="widget-meta">Last run: <span>2 days ago</span></div>
                        <button class="widget-action">Run Backtest <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg></button>
                    </div>
                </div>
                <?php endif; ?>

                <?php if (in_array('trades', $visible_cards)): ?>
                <div class="widget widget-trades" data-card-id="trades" style="position:relative;">
                    <?php
                    $overlay = rich_feature_overlay('card-trades', $user_id);
                    if ($overlay): ?>
                    <div class="card-overlay" style="position:absolute;inset:0;background:rgba(14,14,14,0.42);backdrop-filter:blur(4px) saturate(135%);-webkit-backdrop-filter:blur(4px) saturate(135%);display:flex;align-items:center;justify-content:center;z-index:10;border-radius:inherit;">
                        <div style="text-align:center;padding:24px;max-width:280px;">
                            <div style="font-size:14px;font-weight:700;color:#f2ca50;margin-bottom:8px;"><?php echo esc_html($overlay['message']); ?></div>
                        </div>
                    </div>
                    <?php endif; ?>
				    <div class="widget-tabs">
				        <button class="wtab active" onclick="switchTab(this,'live-pane')">My Live Trades</button>
				        <button class="wtab" onclick="switchTab(this,'closed-pane')">My Recent Trades</button>
				        <button class="wtab" onclick="switchTab(this,'planned-pane')">My Planned Trades</button>
				    </div>
				
				    <div class="widget-body" id="live-pane">
				        <p class="widget-header">Live Trades</p>
				
				        <!-- MT5 connection status -->
				        <div class="trade-status-row">
				            <span class="status-dot" id="dashMt5StatusDot"></span>
				            <span class="status-label" id="dashMt5StatusLabel">Checking MT5 connection...</span>
				        </div>
				
				        <div class="trade-kpis">
				            <div class="trade-kpi">
				                <span class="trade-kpi-label">Open Positions</span>
				                <span class="trade-kpi-value" id="dashLiveCount">--</span>
				            </div>
				            <div class="trade-kpi">
				                <span class="trade-kpi-label">Open P&L</span>
				                <span class="trade-kpi-value" id="dashOpenPL">--</span>
				            </div>
				        </div>
				
				        <div class="trade-mini-list" id="dashLiveList">
				            <div class="trade-empty">Loading live positions...</div>
				        </div>
				
				        <button class="widget-action" id="dashMt5LinkCta" onclick="window.location.href='/account/#mt5'" style="display:none;">
				            Link MetaTrader 5
				            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
				                <line x1="5" y1="12" x2="19" y2="12"></line>
				                <polyline points="12 5 19 12 12 19"></polyline>
				            </svg>
				        </button>
				    </div>
				
				    <div class="widget-body" id="closed-pane" style="display:none;">
					    <p class="widget-header">Recent Trades</p>
					
					    <div class="trade-status-row trade-status-row--secondary">
					        <span class="status-dot status-dot--mini" id="dashMt5StatusDotClosed"></span>
					        <span class="status-label status-label--secondary" id="dashMt5StatusLabelClosed">
					            MT5 connection status will appear here.
					        </span>
					    </div>
					
					    <div class="trade-kpis">
					        <div class="trade-kpi">
					            <span class="trade-kpi-label">Recent Trades</span>
					            <span class="trade-kpi-value" id="dashClosedCount">--</span>
					        </div>
					        <div class="trade-kpi">
					            <span class="trade-kpi-label">Latest Result</span>
					            <span class="trade-kpi-value" id="dashClosedPL">--</span>
					        </div>
					    </div>
					
					    <div class="trade-mini-list" id="dashClosedList">
					        <div class="trade-empty">Loading recent trades...</div>
					    </div>
					
						<button class="widget-action" onclick="window.location.href='/journal'">
						    View All In Journal
						    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
						        <line x1="5" y1="12" x2="19" y2="12"></line>
						        <polyline points="12 5 19 12 12 19"></polyline>
						    </svg>
						</button>
					</div>
				
				    <div class="widget-body" id="planned-pane" style="display:none;">
				        <p class="widget-header">Planned Trades</p>
				
				        <div class="trade-mini-list" id="dashPlannedList">
				            <div class="trade-empty">
				                No linked planning feed yet.<br>
				                Use the journal to prepare setups, bias, levels, and execution notes.
				            </div>
				        </div>
				
				        <button class="widget-action" onclick="window.location.href='journal'">
				            Open Planner
				            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
				                <line x1="5" y1="12" x2="19" y2="12"></line>
				                <polyline points="12 5 19 12 12 19"></polyline>
				            </svg>
				        </button>
				    </div>
				</div>
                <?php endif; ?>

                <?php if (in_array('mentors', $visible_cards)): ?>
                <div class="widget widget-mentors" data-card-id="mentors" style="position:relative;">
                    <?php
                    $overlay = rich_feature_overlay('card-mentors', $user_id);
                    if ($overlay): ?>
                    <div class="card-overlay" style="position:absolute;inset:0;background:rgba(14,14,14,0.42);backdrop-filter:blur(4px) saturate(135%);-webkit-backdrop-filter:blur(4px) saturate(135%);display:flex;align-items:center;justify-content:center;z-index:10;border-radius:inherit;">
                        <div style="text-align:center;padding:24px;max-width:280px;">
                            <div style="font-size:14px;font-weight:700;color:#f2ca50;margin-bottom:8px;"><?php echo esc_html($overlay['message']); ?></div>
                        </div>
                    </div>
                    <?php endif; ?>
                    <div class="widget-tabs">
                        <button class="wtab active" onclick="switchTab(this,'ment-pane')">Mentors</button>
                        <button class="wtab" onclick="switchTab(this,'ment1-pane')">Mentor 1</button>
                        <button class="wtab" onclick="switchTab(this,'ment2-pane')">Mentor 2</button>
                    </div>
                    <div class="widget-body" id="ment-pane">
                        <p class="widget-header">Your Mentors</p>
                        <div class="widget-content-block">
                            <p class="widget-content-text">Connect with your assigned mentors, review their latest analysis, and schedule 1-on-1 review sessions.</p>
                        </div>
                        <div class="widget-meta">Next session: <span>Not scheduled</span></div>
                        <button class="widget-action">Book a Session <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg></button>
                    </div>
                    <div class="widget-body" id="ment1-pane" style="display:none">
                        <p class="widget-header">Mentor 1</p>
                        <div class="widget-content-block">
                            <p class="widget-content-text">Specialises in Smart Money Concepts and institutional order flow. 8 years of live trading experience.</p>
                        </div>
                        <div class="widget-meta">Availability: <span>Mon&ndash;Fri</span></div>
                        <button class="widget-action">Book a Session <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg></button>
                    </div>
                    <div class="widget-body" id="ment2-pane" style="display:none">
                        <p class="widget-header">Mentor 2</p>
                        <div class="widget-content-block">
                            <p class="widget-content-text">Focuses on Forex macro fundamentals and central bank policy. Expertise in EURUSD and Gold correlations.</p>
                        </div>
                        <div class="widget-meta">Availability: <span>Tue&ndash;Thu</span></div>
                        <button class="widget-action">Book a Session <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg></button>
                    </div>
                </div>
                <?php endif; ?>

                <?php if (in_array('ai', $visible_cards)): ?>
                <div class="widget widget-ai" data-card-id="ai" style="position:relative;">
                    <?php
                    $overlay = rich_feature_overlay('card-ai', $user_id);
                    if ($overlay): ?>
                    <div class="card-overlay" style="position:absolute;inset:0;background:rgba(14,14,14,0.42);backdrop-filter:blur(4px) saturate(135%);-webkit-backdrop-filter:blur(4px) saturate(135%);display:flex;align-items:center;justify-content:center;z-index:10;border-radius:inherit;">
                        <div style="text-align:center;padding:24px;max-width:280px;">
                            <div style="font-size:14px;font-weight:700;color:#f2ca50;margin-bottom:8px;"><?php echo esc_html($overlay['message']); ?></div>
                        </div>
                    </div>
                    <?php endif; ?>
                    <div class="widget-tabs">
                        <button class="wtab active">AI Chat</button>
                    </div>
                    <div class="widget-body">
                        <div class="ai-header-badge">
                            <div class="ai-dot"></div>
                            AI Online
                        </div>
                        <div class="widget-blank"></div>
                    </div>
                </div>
                <?php endif; ?>

                <?php if (in_array('chat', $visible_cards)): ?>
                <div class="widget widget-chat" data-card-id="chat" style="position:relative;">
                    <?php
                    $overlay = rich_feature_overlay('card-chat', $user_id);
                    if ($overlay): ?>
                    <div class="card-overlay" style="position:absolute;inset:0;background:rgba(14,14,14,0.42);backdrop-filter:blur(4px) saturate(135%);-webkit-backdrop-filter:blur(4px) saturate(135%);display:flex;align-items:center;justify-content:center;z-index:10;border-radius:inherit;">
                        <div style="text-align:center;padding:24px;max-width:280px;">
                            <div style="font-size:14px;font-weight:700;color:#f2ca50;margin-bottom:8px;"><?php echo esc_html($overlay['message']); ?></div>
                        </div>
                    </div>
                    <?php endif; ?>
                    <div class="widget-tabs">
                        <button class="wtab active" onclick="switchTab(this,'chat-pane')">Chat</button>
                        <button class="wtab" onclick="switchTab(this,'private-pane')">Private Chats</button>
                    </div>
                    <div class="widget-body" id="chat-pane">
                        <p class="widget-header">Joined Group Chat</p>
                        <div id="dashboardGroupChatState" class="dashboard-group-chat-state">
                            <div class="widget-content-block"><p class="widget-content-text">Loading your joined trading group...</p></div>
                        </div>
                        <div id="dashboardGroupChatMessages" class="dashboard-group-chat-messages" aria-live="polite"></div>
                        <div id="dashboardGroupChatComposer" class="dashboard-group-chat-composer" hidden>
                            <input id="dashboardGroupChatInput" type="text" maxlength="1000" placeholder="Write a message..." aria-label="Write a group chat message">
                            <button id="dashboardGroupChatSend" class="widget-action" type="button">Send <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg></button>
                        </div>
                        <div id="dashboardGroupChatMeta" class="widget-meta" hidden></div>
                        <button id="dashboardGroupChatCta" class="widget-action" type="button" onclick="window.location.href='/trading-floor'" hidden>Choose a Group <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg></button>
                    </div>
                    <div class="widget-body" id="private-pane" style="display:none">
                        <p class="widget-header">Private Chats</p>
                        <div class="widget-content-block">
                            <p class="widget-content-text">Direct messages with your mentors and fellow traders. All conversations are private and encrypted.</p>
                        </div>
                        <div class="widget-meta">Unread: <span>0 messages</span></div>
                        <button class="widget-action">Open Messages <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg></button>
                    </div>
                </div>
                <?php endif; ?>

                <?php if (in_array('journal', $visible_cards)): ?>
                <div class="widget widget-journal" data-card-id="journal" style="position:relative;">
                    <?php
                    $overlay = rich_feature_overlay('card-journal', $user_id);
                    if ($overlay): ?>
                    <div class="card-overlay" style="position:absolute;inset:0;background:rgba(14,14,14,0.42);backdrop-filter:blur(4px) saturate(135%);-webkit-backdrop-filter:blur(4px) saturate(135%);display:flex;align-items:center;justify-content:center;z-index:10;border-radius:inherit;">
                        <div style="text-align:center;padding:24px;max-width:280px;">
                            <div style="font-size:14px;font-weight:700;color:#f2ca50;margin-bottom:8px;"><?php echo esc_html($overlay['message']); ?></div>
                        </div>
                    </div>
                    <?php endif; ?>
                    <div class="widget-tabs">
                        <button class="wtab active" onclick="switchTab(this,'journal-pane')">Journal</button>
                        <button class="wtab" onclick="switchTab(this,'planner-pane')">Planner</button>
                    </div>
                    <div class="widget-body" id="journal-pane">
                        <p class="widget-header">Journal</p>
                        <div class="widget-blank"></div>
                    </div>
                    <div class="widget-body" id="planner-pane" style="display:none">
                        <p class="widget-header">Planner</p>
                        <div class="widget-blank"></div>
                    </div>
                </div>
                <?php endif; ?>

            </div>

        </main>
    </div>

    <!-- ── Dashboard Settings Panel ────────────────────────────── -->
    <div class="dashboard-settings-overlay" id="dashboardSettingsOverlay" onclick="handleOverlayClick(event)">
        <div class="dashboard-settings-panel">
            <div class="dsp-header">
                <span class="dsp-title">Dashboard Settings</span>
                <button class="dsp-close" onclick="closeDashboardSettings()" aria-label="Close settings">✕</button>
            </div>

            <div>
                <p class="dsp-section-label">Section Order</p>
                <ul class="dsp-sort-list" id="dspSortList">
                    <!-- populated by JS -->
                </ul>
            </div>

            <div class="dsp-actions">
                <button class="dsp-btn dsp-btn-ghost" onclick="resetDashboardOrder()">Reset Default</button>
                <button class="dsp-btn dsp-btn-primary" onclick="applyDashboardOrder()">Apply</button>
            </div>
        </div>
    </div>

    <!-- ── Column Manager Modal ──────────────────────────────────── -->
    <div
        id="columnManagerModal"
        class="modal-overlay"
        style="display: none;"
        role="dialog"
        aria-modal="true"
        aria-labelledby="dashboardSettingsModalTitle"
    >
        <div class="column-manager-modal settings-modal-shell">
            <div class="column-manager-header">
                <div class="modal-header" style="margin-bottom: 0;">
                    <div>
                        <h3 class="modal-title" id="dashboardSettingsModalTitle">Settings</h3>
                        <p class="settings-subtitle">Customize your dashboard layout.</p>
                    </div>
                    <button class="modal-close" onclick="closeColumnManager()" aria-label="Close settings">&times;</button>
                </div>
            </div>

            <div class="settings-tabs" role="tablist" aria-label="Dashboard settings sections">
                <button
                    type="button"
                    class="settings-tab active"
                    data-tab="columns"
                    role="tab"
                    aria-selected="true"
                    aria-controls="settings-panel-columns"
                    id="settings-tab-columns"
                    onclick="showSettingsTab('columns')"
                >
                    Layout
                </button>
            </div>

            <div class="column-manager-body settings-modal-body">
                <section
                    id="settings-panel-columns"
                    class="settings-panel"
                    role="tabpanel"
                    aria-labelledby="settings-tab-columns"
                >
                    <div class="column-manager-section">
                        <div class="section-header">
                            <div class="section-icon">📋</div>
                            <h4 class="section-title">Edit Dashboard Layout</h4>
                        </div>

                        <p class="section-description">
                            Reorder and manage your dashboard layout here.
                        </p>

                        <div class="columns-list" id="columnsList">
                            <div class="column-item">
                                <span class="column-name">Loading layout options...</span>
                            </div>
                        </div>
                    </div>
                </section>
            </div>

            <div class="column-manager-footer">
                <button class="btn-reset" type="button" onclick="closeColumnManager()">Close</button>
            </div>
        </div>
    </div>

	<script>
	    function switchTab(btn, paneId) {
	        const widget = btn.closest('.widget');
	        widget.querySelectorAll('.wtab').forEach(t => t.classList.remove('active'));
	        btn.classList.add('active');
	        widget.querySelectorAll('.widget-body').forEach(p => p.style.display = 'none');
	        const target = document.getElementById(paneId);
	        if (target) target.style.display = 'flex';
	    }
	
    (function () {
        const membershipsUrl = '/api/signals/my-memberships.php';
        const messagesUrl = '/api/signals/messages.php';
        let memberships = [];
        let selectedGroupId = 0;

        const state = document.getElementById('dashboardGroupChatState');
        const messages = document.getElementById('dashboardGroupChatMessages');
        const composer = document.getElementById('dashboardGroupChatComposer');
        const input = document.getElementById('dashboardGroupChatInput');
        const send = document.getElementById('dashboardGroupChatSend');
        const meta = document.getElementById('dashboardGroupChatMeta');
        const cta = document.getElementById('dashboardGroupChatCta');
        if (!state || !messages) return;

        const escapeHtml = value => String(value ?? '').replace(/[&<>'"]/g, ch => ({'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#039;','"':'&quot;'}[ch]));
        const time = value => { const d = new Date(String(value).replace(' ', 'T') + (String(value).includes('Z') ? '' : 'Z')); return Number.isNaN(d.getTime()) ? '' : d.toLocaleTimeString([], {hour:'2-digit', minute:'2-digit'}); };

        function showState(html) { state.innerHTML = html; state.hidden = false; }
        function renderMessages(items) {
            messages.innerHTML = items.length ? items.map(item => `<div class="dashboard-group-chat-message"><div class="dashboard-group-chat-message-meta"><span class="dashboard-group-chat-message-author">${escapeHtml(item.author_name || 'Member')}</span><span>${escapeHtml(time(item.created_at))}</span></div><div class="dashboard-group-chat-message-text">${escapeHtml(item.message)}</div></div>`).join('') : '<div class="dashboard-group-chat-empty">No messages yet. Start the conversation.</div>';
            messages.scrollTop = messages.scrollHeight;
        }
        async function loadMessages() {
            if (!selectedGroupId) return;
            try { const r = await fetch(`${messagesUrl}?group_id=${encodeURIComponent(selectedGroupId)}`, {credentials:'same-origin'}); const data = await r.json(); if (!r.ok || !data.success) throw new Error(data.message || 'Unable to load messages'); renderMessages(data.messages || []); } catch (e) { showState(`<div class="widget-content-block"><p class="widget-content-text">${escapeHtml(e.message)}</p></div>`); }
        }
        async function selectGroup(id) {
            selectedGroupId = Number(id);
            const group = memberships.find(item => Number(item.id) === selectedGroupId);
            if (!group) return;
            state.innerHTML = `<select class="dashboard-group-chat-switcher" aria-label="Select joined group">${memberships.map(item => `<option value="${item.id}" ${Number(item.id) === selectedGroupId ? 'selected' : ''}>${escapeHtml(item.name)}</option>`).join('')}</select>`;
            state.hidden = false;
            state.querySelector('select').addEventListener('change', e => selectGroup(e.target.value));
            composer.hidden = false; cta.hidden = true; meta.hidden = false; meta.innerHTML = `Members: <span>${escapeHtml(group.member_count || 0)}</span>`;
            await loadMessages();
        }
        async function init() {
            try { const r = await fetch(membershipsUrl, {credentials:'same-origin'}); const data = await r.json(); if (!r.ok || !data.success) throw new Error(data.message || 'Unable to load memberships'); memberships = data.memberships || []; if (!memberships.length) { showState('<div class="widget-content-block"><p class="widget-content-text dashboard-group-chat-empty">You have not joined a trading group yet. Choose a group on the Trading Floor to start chatting.</p></div>'); cta.hidden = false; meta.hidden = true; return; } await selectGroup(memberships[0].id); } catch (e) { showState(`<div class="widget-content-block"><p class="widget-content-text">${escapeHtml(e.message)}</p></div>`); }
        }
        async function sendMessage() { const value = input.value.trim(); if (!value || !selectedGroupId) return; send.disabled = true; try { const r = await fetch(messagesUrl, {method:'POST', credentials:'same-origin', headers:{'Content-Type':'application/json'}, body:JSON.stringify({group_id:selectedGroupId, message:value})}); const data = await r.json(); if (!r.ok || !data.success) throw new Error(data.message || 'Unable to send message'); input.value = ''; await loadMessages(); } catch(e) { showState(`<div class="widget-content-block"><p class="widget-content-text">${escapeHtml(e.message)}</p></div>`); } finally { send.disabled = false; } }
        send.addEventListener('click', sendMessage); input.addEventListener('keydown', e => { if (e.key === 'Enter') sendMessage(); });
        init();
        setInterval(loadMessages, 15000);
    })();

	    function openNewsWindow() {
	        window.open(
	            '/dashboard/news-window.php',
	            'newsWindow',
	            'width=480,height=800,resizable=yes,scrollbars=yes'
	        );
	    }
	
	    (function () {
	        let es = null;
	        let lastId = 0;
	
	        function formatTime(datetimeStr) {
	            const d = new Date(datetimeStr + 'Z');
	            return d.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
	        }
	
	        function setStatus(connected) {
	            const dot = document.getElementById('newsFeedStatus')?.querySelector('.news-dot');
	            const text = document.getElementById('newsFeedStatus')?.querySelector('.news-status-text');
	            if (!dot || !text) return;
	
	            dot.className = 'news-dot ' + (connected ? 'connected' : 'disconnected');
	            text.textContent = connected ? 'Live' : 'Reconnecting...';
	        }
	
	        function appendItem(item, isNew = false) {
	            const list = document.getElementById('newsFeedList');
	            if (!list) return;
	
	            const empty = list.querySelector('.news-feed-empty');
	            if (empty) empty.remove();
	
	            const div = document.createElement('div');
	            div.className = 'news-item' + (isNew ? ' new-item' : '');
	            div.innerHTML = `
	                <div class="news-item-text">${item.message}</div>
	                <div class="news-item-meta">
	                    <span class="news-item-time">${formatTime(item.created_at || item.createdat)}</span>
	                    ${item.author ? `<span class="news-item-author">${item.author}</span>` : ''}
	                </div>
	            `;
	
	            if (isNew) {
	                list.prepend(div);
	                setTimeout(() => div.classList.remove('new-item'), 4000);
	            } else {
	                list.appendChild(div);
	            }
	
	            const items = list.querySelectorAll('.news-item');
	            if (items.length > 100) {
	                items[items.length - 1].remove();
	            }
	        }
	
	        function connect() {
	            if (es) es.close();
	
	            const url = `/api/news/stream.php${lastId ? '?since=' + lastId : ''}`;
	            es = new EventSource(url);
	
	            es.onopen = () => setStatus(true);
	
	            es.onmessage = (e) => {
	                try {
	                    const item = JSON.parse(e.data);
	                    if (item.id) lastId = Math.max(lastId, item.id);
	                    appendItem(item, !item.initial);
	                } catch (err) {}
	            };
	
	            es.addEventListener('reconnect', () => {
	                es.close();
	                setTimeout(connect, 500);
	            });
	
	            es.onerror = () => {
	                setStatus(false);
	                es.close();
	                setTimeout(connect, 5000);
	            };
	        }
	
	        const newsTabs = document.querySelectorAll('.wtab');
	        newsTabs.forEach(btn => {
	            btn.addEventListener('click', () => {
	                if (btn.textContent.trim() === 'News' && !es) {
	                    connect();
	                }
	            });
	        });
	
	        connect();
	    })();
	</script>
	
	<script>
	(function() {
	    var elTag        = document.getElementById("techPriceTag");
	    var svg          = document.getElementById("techEngineChartSvg");
	    var elPrevLabel  = document.getElementById("techStatPrevLabel");
	    var elPrevOC     = document.getElementById("techStatPrevOC");
	    var elChangeStat = document.getElementById("techStatChange");
	
	    if (!elTag || !svg || !elPrevLabel || !elPrevOC || !elChangeStat) return;
	
	    var symbolLabel = "XAUUSD";
	    var latestPrice = null;
	    var weeklyPoints = [];
	    var latestWeeklyOpen = null;
	    var sma200 = null;
	    var rsiDaily = null;
	
	    var defaultPrevLabel   = "Prev Week O/C";
	    var defaultPrevOCText  = "– / –";
	    var defaultPctText     = "0.00%";
	
	    var WP_AJAX_BASE = <?php echo json_encode(rtrim(site_url(), "/") . "/wp-admin/admin-ajax.php"); ?>;
	
	    function updateLivePrice() {
	        var url = WP_AJAX_BASE + "?action=tworich_research_quote";
	
	        fetch(url)
	            .then(function(res) { if (!res.ok) throw new Error('HTTP ' + res.status + ' for ' + url); return res.json(); })
	            .then(function(data) {
	                if (!data) return;
	                if (data.error) return;
	                if (data.code) return;
	                if (data.status === "error") return;
	
	                var value = data.price;
	                if (!value) value = data.close;
	                if (!value) return;
	
	                var p = parseFloat(value);
	                if (isNaN(p)) return;
	
	                latestPrice = p;
	                elTag.textContent = symbolLabel + " " + p.toFixed(2);
	
	                updateChangeStat();
	                updateMARow();
	            })
	            .catch(function(err) {
	                console.log("Quote error:", err);
	            });
	    }
	
	    function initWeeklyChart() {
	        var url = WP_AJAX_BASE + "?action=tworich_research_timeseries";

	
	        fetch(url)
	            .then(function(res) { if (!res.ok) throw new Error('HTTP ' + res.status + ' for ' + url); return res.json(); })
	            .then(function(data) {
	                if (!data) return;
	                if (data.error) return;
	                if (!data.values) return;
	                if (!Array.isArray(data.values)) return;
	
	                var values = data.values.slice(0, 9).reverse();
	
	                weeklyPoints = [];
	                var closesOnly = [];
	
	                values.forEach(function(v) {
	                    var close = parseFloat(v.close);
	                    closesOnly.push(isNaN(close) ? null : close);
	                });
	
	                var validCloses = closesOnly.filter(function(n) { return n !== null; });
	                if (!validCloses.length) return;
	
	                var min = Math.min.apply(null, validCloses);
	                var max = Math.max.apply(null, validCloses);
	
	                if (min === max) {
	                    min = min - 1;
	                    max = max + 1;
	                }
	
	                values.forEach(function(v, idx) {
	                    var close = parseFloat(v.close);
	                    var open  = parseFloat(v.open);
	                    if (isNaN(close)) return;
	
	                    var ratio = (close - min) / (max - min);
	                    var x = values.length === 1 ? 50 : (idx / (values.length - 1)) * 100;
	                    var y = 60 - ratio * 50;
	
	                    var pctChange = null;
	                    if (!isNaN(open) && open !== 0) {
	                        pctChange = ((close - open) / open) * 100;
	                    }
	
	                    weeklyPoints.push({
	                        index: idx,
	                        x: x,
	                        y: y,
	                        open: open,
	                        close: close,
	                        datetime: v.datetime,
	                        pctChange: pctChange
	                    });
	                });
	
	                if (!weeklyPoints.length) return;
	
	                if (values.length >= 2) {
	                    var prev = values[values.length - 2];
	                    var curr = values[values.length - 1];
	
	                    var prevOpen = parseFloat(prev.open);
	                    var prevClose = parseFloat(prev.close);
	
	                    if (!isNaN(prevOpen) && !isNaN(prevClose)) {
	                        defaultPrevOCText = "O " + prevOpen.toFixed(1) + " / C " + prevClose.toFixed(1);
	                        elPrevOC.textContent = defaultPrevOCText;
	                    }
	
	                    defaultPrevLabel = "Prev Week O/C";
	                    elPrevLabel.textContent = defaultPrevLabel;
	
	                    var currOpen = parseFloat(curr.open);
	                    if (!isNaN(currOpen)) {
	                        latestWeeklyOpen = currOpen;
	                    }
	
	                    var prevPoint = weeklyPoints[weeklyPoints.length - 2];
	                    if (prevPoint && prevPoint.pctChange !== null) {
	                        defaultPctText = formatPct(prevPoint.pctChange);
	                        elChangeStat.textContent = defaultPctText;
	                    }
	                }
	
	                updateChangeStat();
	                drawChart();
	                attachHover();
	                classifyMarketStructure();
	            })
	            .catch(function(err) {
	                console.log("Weekly chart error:", err);
	            });
	    }
	
	    function loadSma200() {
	        var url = WP_AJAX_BASE + "?action=tworich_research_sma200";
	
	        fetch(url)
	            .then(function(res) { if (!res.ok) throw new Error('HTTP ' + res.status + ' for ' + url); return res.json(); })
	            .then(function(data) {
	                if (!data) return;
	                if (data.error) return;
	                if (typeof data.sma === "undefined") return;
	
	                var smaValue = parseFloat(data.sma);
	                if (isNaN(smaValue)) return;
	
	                sma200 = smaValue;
	                updateMARow();
	            })
	            .catch(function(err) {
	                console.log("SMA200 error:", err);
	            });
	    }
	
	    function loadRsiDaily() {
	        var url = WP_AJAX_BASE + "?action=tworich_research_rsi";
	
	        fetch(url)
	            .then(function(res) { if (!res.ok) throw new Error('HTTP ' + res.status + ' for ' + url); return res.json(); })
	            .then(function(data) {
	                if (!data) return;
	                if (data.error) return;
	                if (typeof data.rsi === "undefined") return;
	
	                var rsiValue = parseFloat(data.rsi);
	                if (isNaN(rsiValue)) return;
	
	                rsiDaily = rsiValue;
	                updateRSIRow();
	            })
	            .catch(function(err) {
	                console.log("RSI error:", err);
	            });
	    }
	
	    function updateMARow() {
	        var elMA = document.getElementById("signalMA");
	        if (!elMA) return;
	
	        if (sma200 === null) {
	            elMA.textContent = "--";
	            return;
	        }
	
	        var text = sma200.toFixed(1);
	
	        if (latestPrice !== null) {
	            if (latestPrice > sma200) text += " · Above";
	            else if (latestPrice < sma200) text += " · Below";
	            else text += " · At";
	        }
	
	        elMA.textContent = text;
	    }
	
	    function classifyRsi(rsi) {
	        if (rsi < 30) return "Oversold";
	        if (rsi < 50) return "Bearish";
	        if (rsi < 70) return "Bullish";
	        return "Overbought";
	    }
	
	    function updateRSIRow() {
	        var elRSI = document.getElementById("signalRSI");
	        if (!elRSI) return;
	
	        if (rsiDaily === null) {
	            elRSI.textContent = "--";
	            return;
	        }
	
	        elRSI.textContent = rsiDaily.toFixed(1) + " · " + classifyRsi(rsiDaily);
	    }
	
	    function classifyMarketStructure() {
	        var elMS = document.getElementById("signalMS");
	        if (!elMS) return;
	
	        if (weeklyPoints.length < 3) {
	            elMS.textContent = "--";
	            return;
	        }
	
	        var closes = [];
	        for (var i = 0; i < weeklyPoints.length; i++) {
	            closes.push(weeklyPoints[i].close);
	        }
	
	        var n = closes.length;
	        var swingHighs = [];
	        var swingLows = [];
	
	        if (n >= 2) {
	            if (closes[0] > closes[1]) swingHighs.push(closes[0]);
	            if (closes[0] < closes[1]) swingLows.push(closes[0]);
	        }
	
	        for (var j = 1; j < n - 1; j++) {
	            if (closes[j] > closes[j - 1] && closes[j] > closes[j + 1]) swingHighs.push(closes[j]);
	            if (closes[j] < closes[j - 1] && closes[j] < closes[j + 1]) swingLows.push(closes[j]);
	        }
	
	        if (n >= 2) {
	            if (closes[n - 1] > closes[n - 2]) swingHighs.push(closes[n - 1]);
	            if (closes[n - 1] < closes[n - 2]) swingLows.push(closes[n - 1]);
	        }
	
	        var highTrend = "neutral";
	        var lowTrend = "neutral";
	
	        if (swingHighs.length >= 2) {
	            var lastHigh = swingHighs[swingHighs.length - 1];
	            var prevHigh = swingHighs[swingHighs.length - 2];
	            if (lastHigh > prevHigh) highTrend = "higher";
	            else if (lastHigh < prevHigh) highTrend = "lower";
	        }
	
	        if (swingLows.length >= 2) {
	            var lastLow = swingLows[swingLows.length - 1];
	            var prevLow = swingLows[swingLows.length - 2];
	            if (lastLow > prevLow) lowTrend = "higher";
	            else if (lastLow < prevLow) lowTrend = "lower";
	        }
	
	        var text = "Ranging";
	
	        if (highTrend === "higher") {
	            if (lowTrend === "higher") text = "Uptrend · HH HL";
	            else if (lowTrend === "lower") text = "Expanding · HH LL";
	            else text = "Bullish Bias · HH";
	        } else if (highTrend === "lower") {
	            if (lowTrend === "lower") text = "Downtrend · LH LL";
	            else if (lowTrend === "higher") text = "Contracting · LH HL";
	            else text = "Bearish Bias · LH";
	        } else {
	            if (lowTrend === "higher") text = "Recovery · HL";
	            else if (lowTrend === "lower") text = "Weakening · LL";
	        }
	
	        elMS.textContent = text;
	    }
	
	    function formatPct(pct) {
	        var sign = pct >= 0 ? "+" : "";
	        return sign + pct.toFixed(2) + "%";
	    }
	
	    function updateChangeStat() {
	        if (!elChangeStat) return;
	
	        if (defaultPctText !== "0.00%") {
	            elChangeStat.textContent = defaultPctText;
	        }
	
	        if (latestWeeklyOpen === null || latestPrice === null || latestWeeklyOpen === 0) return;
	
	        var changePct = ((latestPrice - latestWeeklyOpen) / latestWeeklyOpen) * 100;
	        defaultPctText = formatPct(changePct);
	        elChangeStat.textContent = defaultPctText;
	    }
	
	    function drawChart() {
	        if (!weeklyPoints.length) return;
	
	        var linePoints = weeklyPoints.map(function(p) {
	            return p.x + "," + p.y;
	        }).join(" ");
	
	        var baselineY = weeklyPoints[0].y;
	        var baseline = "0," + baselineY + " 100," + baselineY;
	
	        svg.innerHTML =
	            '<polyline points="' + baseline + '" stroke="rgba(240,194,79,0.22)" stroke-width="0.4" stroke-dasharray="1.5 2.2" fill="none" />' +
	            '<polyline id="techEngineMainLine" points="' + linePoints + '" stroke="#e7c36a" stroke-width="0.9" fill="none" stroke-linecap="round" stroke-linejoin="round" />' +
	            '<line id="techEngineHoverLine" x1="0" y1="0" x2="0" y2="60" stroke="rgba(240,194,79,0.55)" stroke-width="0.4" style="display:none;" />' +
	            '<circle id="techEngineHoverDot" cx="0" cy="0" r="0.9" fill="#f0c24f" style="display:none;" />';
	    }
	
	    function formatLabelForPoint(p, lastIndex) {
	        if (!p || !p.datetime || p.index === lastIndex) return "Prev Week O/C";
	
	        var d = new Date(p.datetime);
	        if (isNaN(d.getTime())) return "Prev Week O/C";
	
	        var months = ["JAN","FEB","MAR","APR","MAY","JUN","JUL","AUG","SEP","OCT","NOV","DEC"];
	        return months[d.getUTCMonth()] + " " + d.getUTCDate() + " O/C";
	    }
	
	    function attachHover() {
	        var hoverLine = svg.querySelector("#techEngineHoverLine");
	        var hoverDot  = svg.querySelector("#techEngineHoverDot");
	        if (!hoverLine || !hoverDot || !weeklyPoints.length) return;
	
	        function getSvgXPercent(evt) {
	            var rect = svg.getBoundingClientRect();
	            return ((evt.clientX - rect.left) / rect.width) * 100;
	        }
	
	        function findNearestPoint(xPercent) {
	            var nearest = weeklyPoints[0];
	            var minDiff = Math.abs(xPercent - nearest.x);
	
	            weeklyPoints.forEach(function(p) {
	                var diff = Math.abs(xPercent - p.x);
	                if (diff < minDiff) {
	                    minDiff = diff;
	                    nearest = p;
	                }
	            });
	
	            return nearest;
	        }
	
	        svg.addEventListener("mousemove", function(evt) {
	            var p = findNearestPoint(getSvgXPercent(evt));
	
	            hoverLine.style.display = "block";
	            hoverDot.style.display = "block";
	
	            hoverLine.setAttribute("x1", p.x);
	            hoverLine.setAttribute("x2", p.x);
	            hoverLine.setAttribute("y1", 0);
	            hoverLine.setAttribute("y2", 60);
	
	            hoverDot.setAttribute("cx", p.x);
	            hoverDot.setAttribute("cy", p.y);
	
	            elTag.textContent = symbolLabel + " " + p.close.toFixed(2);
	            elPrevLabel.textContent = formatLabelForPoint(p, weeklyPoints.length - 1);
	
	            if (!isNaN(p.open) && !isNaN(p.close)) {
	                elPrevOC.textContent = "O " + p.open.toFixed(1) + " / C " + p.close.toFixed(1);
	            }
	
	            if (p.pctChange !== null) {
	                elChangeStat.textContent = formatPct(p.pctChange);
	            }
	        });
	
	        svg.addEventListener("mouseleave", function() {
	            hoverLine.style.display = "none";
	            hoverDot.style.display = "none";
	
	            if (latestPrice !== null) {
	                elTag.textContent = symbolLabel + " " + latestPrice.toFixed(2);
	            }
	
	            elPrevLabel.textContent = defaultPrevLabel;
	            elPrevOC.textContent = defaultPrevOCText;
	            elChangeStat.textContent = defaultPctText;
	        });
	    }
	
	    updateLivePrice();
	    initWeeklyChart();
	    loadSma200();
	    loadRsiDaily();
	    setInterval(updateLivePrice, 5000);
	})();
	</script>

    <script>
    (function () {
        const SIGNALS_CSRF = <?php echo json_encode($_SESSION['csrf_token'] ?? ''); ?>;
        const discoveryEl = document.getElementById('signalsDiscoveryState');
        const joinedEl = document.getElementById('signalsJoinedState');
        const groupListEl = document.getElementById('signalsGroupList');
        const membershipStripEl = document.getElementById('signalsMembershipStrip');
        const membershipCountEl = document.getElementById('signalsMembershipCount');
        const switcherEl = document.getElementById('signalsGroupSwitcher');
        const feedListEl = document.getElementById('signalsFeedList');
        const feedCountEl = document.getElementById('signalsFeedCount');
        const tabDiscoveryEl = document.getElementById('signalsTabDiscovery');
        const tabFeedEl = document.getElementById('signalsTabFeed');

        if (!discoveryEl || !joinedEl || !groupListEl || !switcherEl || !feedListEl) return;

        const state = {
            groups: [],
            memberships: [],
            activeGroupId: null,
            activeTab: null,
            userChoseTab: false,
        };

        function formatPrice(group) {
            return group.pricing_type === 'free' ? 'Join Free' : 'Subscribe';
        }

        function formatMoney(value) {
            const n = Number(value || 0);
            return '$' + n.toFixed(0);
        }

        function priceBadge(group) {
            if (group.pricing_type === 'free') {
                return '<span class="signals-badge free">Free</span>';
            }
            return '<span class="signals-badge paid">' + formatMoney(group.price) + '/mo</span>';
        }

        function switchSignalsTab(tab, userInitiated) {
            state.activeTab = tab;
            if (userInitiated) state.userChoseTab = true;

            const showFeed = tab === 'feed';
            discoveryEl.hidden = showFeed;
            joinedEl.hidden = !showFeed;
            tabDiscoveryEl.classList.toggle('active', !showFeed);
            tabFeedEl.classList.toggle('active', showFeed);

            if (showFeed && state.activeGroupId) {
                fetchFeed(state.activeGroupId);
            }
        }
        window.switchSignalsTab = function (tab) { switchSignalsTab(tab, true); };

        function renderMembershipSummary() {
            const count = state.memberships.length;
            membershipCountEl.textContent = count + ' active membership' + (count === 1 ? '' : 's');
            membershipStripEl.hidden = count === 0;
        }

        function renderGroups() {
            if (!state.groups.length) {
                groupListEl.innerHTML = '<div class="signals-empty">No groups available yet.</div>';
                return;
            }

            groupListEl.innerHTML = state.groups.map(group => {
                const joined = !!group.is_joined;
                const actionLabel = joined ? 'Joined' : formatPrice(group);
                return `
                    <div class="signals-group-item ${joined ? 'is-joined' : ''}">
                        <div class="signals-group-head">
                            <div>
                                <div class="signals-group-title">${group.name}</div>
                                <div class="signals-group-team">${group.team_name || ''}</div>
                            </div>
                            <div class="signals-group-badges">
                                ${priceBadge(group)}
                                ${joined ? '<span class="signals-badge joined">✓ Active</span>' : ''}
                            </div>
                        </div>
                        <div class="signals-group-desc">${group.description || ''}</div>
                        <div class="signals-group-footer">
                            <div class="signals-group-stat">
                                <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                                ${group.member_count || 0} members
                            </div>
                            <button class="signals-join-btn ${joined ? 'is-joined' : ''}" data-group-id="${group.id}" ${joined ? 'disabled' : ''}>${actionLabel}</button>
                        </div>
                    </div>
                `;
            }).join('');

            groupListEl.querySelectorAll('.signals-join-btn[data-group-id]').forEach(btn => {
                btn.addEventListener('click', () => joinGroup(btn.dataset.groupId, btn));
            });
        }

        function renderSwitcher() {
            switcherEl.innerHTML = state.memberships.map(m =>
                `<option value="${m.id}">${m.name}${m.pricing_type === 'paid' ? ' · Pro' : ''}</option>`
            ).join('');
            if (state.activeGroupId) switcherEl.value = String(state.activeGroupId);
        }

        function renderFeed(signals) {
            feedCountEl.textContent = signals.length ? signals.length + ' signal' + (signals.length === 1 ? '' : 's') : '';

            if (!signals.length) {
                feedListEl.innerHTML = '<div class="signals-empty">No signals posted in this group yet. Check back soon.</div>';
                return;
            }

            feedListEl.innerHTML = signals.map(signal => `
                <div class="signals-feed-item">
                    <div class="signals-feed-head">
                        <div>
                            <div class="signals-feed-symbol">${signal.symbol}</div>
                            <div class="signals-feed-time">${signal.posted_at || ''}</div>
                        </div>
                        <div class="signals-feed-badges">
                            <span class="signals-badge ${String(signal.direction || '').toLowerCase()}">${signal.direction}</span>
                            <span class="signals-badge signals-feed-result ${String(signal.result || 'pending').toLowerCase()}">${signal.result}</span>
                        </div>
                    </div>
                    <div class="signals-feed-levels">
                        <div class="signals-level"><span class="signals-level-label">Entry</span><span class="signals-level-value">${signal.entry_price ?? '—'}</span></div>
                        <div class="signals-level"><span class="signals-level-label">Stop</span><span class="signals-level-value">${signal.stop_loss ?? '—'}</span></div>
                        <div class="signals-level"><span class="signals-level-label">Take Profit</span><span class="signals-level-value">${signal.take_profit ?? '—'}</span></div>
                    </div>
                    ${signal.notes ? `<div class="signals-feed-notes">${signal.notes}</div>` : ''}
                </div>
            `).join('');
        }

        function syncStateViews() {
            const hasMemberships = state.memberships.length > 0;

            // Decide default tab only once, the first time we know membership status,
            // unless the user has already clicked a tab themselves.
            if (state.activeTab === null && !state.userChoseTab) {
                switchSignalsTab(hasMemberships ? 'feed' : 'discovery', false);
            }

            tabFeedEl.classList.toggle('is-disabled', !hasMemberships);

            renderMembershipSummary();
            renderGroups();
            if (hasMemberships) renderSwitcher();
        }

        async function fetchGroups() {
            const res = await fetch('/api/signals/list-groups.php', { credentials: 'include' });
            const data = await res.json();
            state.groups = Array.isArray(data.groups) ? data.groups : [];
        }

        async function fetchMemberships() {
            const res = await fetch('/api/signals/my-memberships.php', { credentials: 'include' });
            const data = await res.json();
            state.memberships = Array.isArray(data.memberships) ? data.memberships : [];
            if (!state.activeGroupId && state.memberships.length) {
                state.activeGroupId = state.memberships[0].id;
            }
        }

        async function fetchFeed(groupId) {
            feedListEl.innerHTML = '<div class="signals-empty">Loading live signals…</div>';
            const res = await fetch('/api/signals/feed.php?group_id=' + encodeURIComponent(groupId), { credentials: 'include' });
            const data = await res.json();
            renderFeed(Array.isArray(data.signals) ? data.signals : []);
        }

        async function joinGroup(groupId, btn) {
            btn.disabled = true;
            btn.textContent = 'Joining...';
            const res = await fetch('/api/signals/join.php', {
                method: 'POST',
                credentials: 'include',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': SIGNALS_CSRF,
                },
                body: JSON.stringify({ group_id: Number(groupId) })
            });
            const data = await res.json();

            if (data.requires_pay) {
                btn.disabled = false;
                btn.textContent = 'Subscribe';
                alert(data.message || 'This group requires subscription.');
                window.location.href = '/trading-floor/';
                return;
            }

            if (!data.success) {
                btn.disabled = false;
                btn.textContent = 'Try Again';
                alert(data.message || 'Unable to join group right now.');
                return;
            }

            state.activeGroupId = Number(groupId);
            // Auto-jump to the feed so the join feels immediately rewarding.
            state.userChoseTab = false;
            state.activeTab = null;
            await boot(true);
            switchSignalsTab('feed', true);
        }

        async function boot(fetchFeedAfter = false) {
            await Promise.all([fetchGroups(), fetchMemberships()]);
            syncStateViews();
            if (state.memberships.length && state.activeGroupId) {
                if (fetchFeedAfter || !joinedEl.hidden) {
                    await fetchFeed(state.activeGroupId);
                }
            }
        }

        switcherEl.addEventListener('change', async function () {
            state.activeGroupId = Number(this.value || 0);
            if (state.activeGroupId) {
                await fetchFeed(state.activeGroupId);
            }
        });

        boot(true);
    })();
    </script>

    <!-- ── Dashboard Settings + Card Sort Logic ─────────────────── -->
    <script>
    // ── Embed CSRF token from session (server-side) ──
    const CSRF_TOKEN = <?php echo json_encode($_SESSION['csrf_token'] ?? ''); ?>;
    function csrfHeaders() {
        return CSRF_TOKEN ? { 'X-CSRF-Token': CSRF_TOKEN } : {};
    }

    (function () {
        const STORAGE_KEY = '2rich_dashboard_card_order';

        const DEFAULT_ORDER = [
            'market', 'signals', 'news', 'classroom',
            'strategies', 'trades', 'mentors',
            'ai', 'chat', 'journal'
        ];

        const CARD_LABELS = {
            market:     'Market',
            signals:    'Signals',
            news:       'News',
            classroom:  'Classroom',
            strategies: 'Strategies',
            trades:     'My Trades',
            mentors:    'Mentors',
            ai:         'AI Chat',
            chat:       'Chat',
            journal:    'Journal'
        };

        function normalizeOrder(order) {
            const unique = [...new Set((Array.isArray(order) ? order : []).filter(id => DEFAULT_ORDER.includes(id)))];
            return [...unique, ...DEFAULT_ORDER.filter(id => !unique.includes(id))];
        }

        async function saveOrder(order) {
            const normalized = normalizeOrder(order);
            try {
                const res = await fetch('../api/dashboard/save-layout.php', {
                    method: 'POST',
                    credentials: 'include',
                    headers: { 'Content-Type': 'application/json', ...csrfHeaders() },
                    body: JSON.stringify({ order: normalized })
                });
                const data = await res.json().catch(() => ({}));
                if (!res.ok || data.success === false) throw new Error(data.message || 'Save failed');
                return true;
            } catch(e) {
                console.error('Failed to save dashboard layout', e);
                return false;
            }
        }
		
		async function loadOrder() {
		    try {
		        const res  = await fetch('../api/dashboard/load-layout.php', { credentials: 'include' });
		        const data = await res.json();
		        if (data.success && Array.isArray(data.order)) {
		            const saved   = data.order.filter(id => DEFAULT_ORDER.includes(id));
		            const missing = DEFAULT_ORDER.filter(id => !saved.includes(id));
		            return [...saved, ...missing];
		        }
		    } catch(e) {}
		    return [...DEFAULT_ORDER];
		}

        function applyOrderToGrid(order) {
		    const grid = document.getElementById('widgetGrid');
		    if (!grid) return;
		
		    // Widgets that span 2 columns in the original layout
		    const DOUBLE = ['classroom', 'strategies', 'journal'];
		
		    // Build rows: fill 4 columns per row, doubles take 2 slots
		    const rows = [];
		    let row = [], slots = 0;
		
		    order.forEach(id => {
		        const span = DOUBLE.includes(id) ? 2 : 1;
		        if (slots + span > 4) {
		            // pad remaining slots with last item repeated (grid needs full rows)
		            while (slots < 4) { row.push(row[row.length - 1] || id); slots++; }
		            rows.push(row);
		            row = []; slots = 0;
		        }
		        if (span === 2) {
		            row.push(id); row.push(id);
		        } else {
		            row.push(id);
		        }
		        slots += span;
		    });
		    // flush last row
		    while (slots < 4) { row.push(row[row.length - 1] || '.'); slots++; }
		    rows.push(row);
		
		    const areas = rows.map(r => `"${r.join(' ')}"`).join('\n    ');
		    grid.style.gridTemplateAreas = areas;
		}


        // ── Preset layouts ──────────────────────────────────────
        const PRESETS = {
            default:  ['market','signals','news','classroom','strategies','trades','mentors','ai','chat','journal'],
            trading:  ['market','signals','trades','strategies','mentors','chat','news','classroom','ai','journal'],
            research: ['market','news','signals','classroom','ai','strategies','research','mentors','trades','chat','journal']
                       .filter(id => DEFAULT_ORDER.includes(id)),
        };
        const PRESET_LABELS = {
            default:  'Default',
            trading:  '📈 Trading',
            research: '🔬 Research',
        };

        // ── Build list with drag-and-drop + up/down buttons ──────
        function buildSettingsList(order) {
            const list = document.getElementById('dspSortList');
            if (!list) return;

            // ── inject preset buttons above list (once) ──
            let presetsEl = document.getElementById('dspPresets');
            if (!presetsEl) {
                presetsEl = document.createElement('div');
                presetsEl.id = 'dspPresets';
                presetsEl.className = 'dsp-presets';
                list.parentElement.insertBefore(presetsEl, list);

                Object.entries(PRESET_LABELS).forEach(([key, label]) => {
                    const btn = document.createElement('button');
                    btn.className = 'dsp-preset-btn';
                    btn.textContent = label;
                    btn.addEventListener('click', () => buildSettingsList([...PRESETS[key]]));
                    presetsEl.appendChild(btn);
                });
            }

            list.innerHTML = '';
            let dragSrc = null;

            order.forEach((id, idx) => {
                const li = document.createElement('li');
                li.className = 'dsp-sort-item';
                li.dataset.cardId = id;
                li.draggable = true;

                const upDisabled   = idx === 0 ? 'disabled' : '';
                const downDisabled = idx === order.length - 1 ? 'disabled' : '';

                li.innerHTML = `
                    <span class="dsp-drag-handle" aria-hidden="true">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <circle cx="9" cy="5" r="1" fill="currentColor"/><circle cx="15" cy="5" r="1" fill="currentColor"/>
                            <circle cx="9" cy="12" r="1" fill="currentColor"/><circle cx="15" cy="12" r="1" fill="currentColor"/>
                            <circle cx="9" cy="19" r="1" fill="currentColor"/><circle cx="15" cy="19" r="1" fill="currentColor"/>
                        </svg>
                    </span>
                    <span class="dsp-sort-label" style="flex:1">${CARD_LABELS[id] || id}</span>
                    <button class="dsp-move-btn" data-dir="up" aria-label="Move up" ${upDisabled}>
                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                            <polyline points="18 15 12 9 6 15"/>
                        </svg>
                    </button>
                    <button class="dsp-move-btn" data-dir="down" aria-label="Move down" ${downDisabled}>
                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                            <polyline points="6 9 12 15 18 9"/>
                        </svg>
                    </button>
                `;

                li.querySelectorAll('.dsp-move-btn').forEach(btn => {
                    btn.addEventListener('click', () => {
                        if (btn.disabled) return;
                        moveItem(btn.dataset.dir === 'up' ? -1 : 1, li);
                    });
                });

                // ── Drag & drop events ──
                li.addEventListener('dragstart', e => {
                    dragSrc = li;
                    li.classList.add('dragging');
                    e.dataTransfer.effectAllowed = 'move';
                    e.dataTransfer.setData('text/plain', id);
                });
                li.addEventListener('dragend', () => {
                    li.classList.remove('dragging');
                    list.querySelectorAll('.dsp-sort-item').forEach(el => el.classList.remove('drag-over'));
                });
                li.addEventListener('dragover', e => {
                    e.preventDefault();
                    e.dataTransfer.dropEffect = 'move';
                    if (dragSrc && dragSrc !== li) li.classList.add('drag-over');
                });
                li.addEventListener('dragleave', () => li.classList.remove('drag-over'));
                li.addEventListener('drop', e => {
                    e.preventDefault();
                    li.classList.remove('drag-over');
                    if (!dragSrc || dragSrc === li) return;
                    const items = Array.from(list.children);
                    const fromIdx = items.indexOf(dragSrc);
                    const toIdx   = items.indexOf(li);
                    if (fromIdx < toIdx) list.insertBefore(dragSrc, li.nextSibling);
                    else                 list.insertBefore(dragSrc, li);
                    // rebuild to refresh disabled states on arrow buttons
                    const newOrder = Array.from(list.children).map(el => el.dataset.cardId);
                    buildSettingsList(newOrder);
                });

                list.appendChild(li);
            });
        }

        function moveItem(direction, li) {
            const list = li.parentElement;
            const items = Array.from(list.children);
            const idx = items.indexOf(li);
            const targetIdx = idx + direction;
            if (targetIdx < 0 || targetIdx >= items.length) return;

            if (direction === -1) {
                list.insertBefore(li, items[targetIdx]);
            } else {
                list.insertBefore(items[targetIdx], li);
            }

            // Rebuild to refresh disabled states
            const newOrder = Array.from(list.children).map(el => el.dataset.cardId);
            buildSettingsList(newOrder);
        }

        function getSettingsOrder() {
            return Array.from(document.querySelectorAll('#dspSortList .dsp-sort-item'))
                        .map(li => li.dataset.cardId);
        }

        // ── Public API ────────────────────────────────────────────
        // openDashboardSettings — was: buildSettingsList(loadOrder());
		window.openDashboardSettings = async function () {
		    const order = await loadOrder();
		    buildSettingsList(order);
		    document.getElementById('dashboardSettingsOverlay').classList.add('open');
		};

        window.closeDashboardSettings = function () {
            document.getElementById('dashboardSettingsOverlay').classList.remove('open');
        };

        window.handleOverlayClick = function (e) {
            if (e.target === document.getElementById('dashboardSettingsOverlay')) {
                closeDashboardSettings();
            }
        };

        // applyDashboardOrder — was: saveOrder(newOrder); applyOrderToGrid(newOrder);
        window.applyDashboardOrder = async function () {
            const newOrder = normalizeOrder(getSettingsOrder());
            const saved = await saveOrder(newOrder);
            if (!saved) {
                alert('Could not save your dashboard layout. Please try again.');
                return;
            }
            applyOrderToGrid(newOrder);
            closeDashboardSettings();
        };

        window.resetDashboardOrder = function () {
            buildSettingsList([...DEFAULT_ORDER]);
        };

        // Init on page load — server-injected order applied instantly (no async flash)
        // Each user's saved layout is fetched in PHP above and embedded here.
        (function () {
            const __serverOrder = <?php echo json_encode($_dashboard_initial_order); ?>;
            applyOrderToGrid(__serverOrder);
        })();

        // Async load is ONLY used to populate the settings panel UI, not the grid.

    })();
    </script>

<script>
(function () {
    const API = '/api/trades/mt5-live.php';

    const elLiveCount = document.getElementById('dashLiveCount');
    const elOpenPL = document.getElementById('dashOpenPL');
    const elLiveList = document.getElementById('dashLiveList');
	const elMt5LinkCta = document.getElementById('dashMt5LinkCta');

    const elClosedCount = document.getElementById('dashClosedCount');
    const elClosedPL = document.getElementById('dashClosedPL');
    const elClosedList = document.getElementById('dashClosedList');

    const elStatusDotLive = document.getElementById('dashMt5StatusDot');
    const elStatusLabelLive = document.getElementById('dashMt5StatusLabel');
    const elStatusDotClosed = document.getElementById('dashMt5StatusDotClosed');
    const elStatusLabelClosed = document.getElementById('dashMt5StatusLabelClosed');

    if (!elLiveCount || !elOpenPL || !elLiveList || !elClosedCount || !elClosedPL || !elClosedList) return;

    function escapeHtml(str) {
        return String(str ?? '').replace(/[&<>"']/g, m => ({
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        })[m]);
    }

    function fmt(n, dec = 2) {
        const num = parseFloat(n || 0);
        return num.toLocaleString('en-US', { minimumFractionDigits: dec, maximumFractionDigits: dec });
    }

    function fmtPrice(n) {
        const num = parseFloat(n || 0);
        return num.toLocaleString('en-US', { minimumFractionDigits: 5, maximumFractionDigits: 5 });
    }

    function fmtTime(dt) {
        if (!dt) return '—';
        const d = new Date(String(dt).replace(' ', 'T'));
        if (isNaN(d.getTime())) return escapeHtml(dt);
        return d.toLocaleDateString('en-GB', { day:'2-digit', month:'short' }) + ' ' +
               d.toLocaleTimeString('en-GB', { hour:'2-digit', minute:'2-digit' });
    }

    function plClass(v) {
        return parseFloat(v || 0) >= 0 ? 'pos' : 'neg';
    }

    function renderConnectionStatus(status, lastSync) {
	    const labelOnline = 'EA Online (MT5 syncing)';
	    const labelStale = 'EA recently synced — check MT5 EA';
	    const labelOffline = 'EA not running — please start MT5 EA';
	
	    [elStatusDotLive, elStatusDotClosed].forEach(dot => {
	        if (!dot) return;
	        dot.classList.remove('status-online', 'status-stale', 'status-offline');
	    });
	
	    let label = labelOffline;
	    let cssClass = 'status-offline';
	
	    if (status === 'online') {
	        label = labelOnline;
	        cssClass = 'status-online';
	    } else if (status === 'stale') {
	        label = labelStale;
	        cssClass = 'status-stale';
	    }
	
	    [elStatusDotLive, elStatusDotClosed].forEach(dot => {
	        if (!dot) return;
	        dot.classList.add(cssClass);
	    });
	
	    if (elMt5LinkCta) {
	        elMt5LinkCta.style.display = (status === 'offline') ? 'inline-flex' : 'none';
	    }
	
	    if (lastSync) label += ' • Last sync: ' + lastSync;
	
	    if (elStatusLabelLive) elStatusLabelLive.textContent = label;
	    if (elStatusLabelClosed) elStatusLabelClosed.textContent = label;
	}

    function computeOpenPL(live, currency) {
        const total = live.reduce((sum, t) => {
            const val =
                t.profit_loss !== null && t.profit_loss !== '' && typeof t.profit_loss !== 'undefined'
                    ? parseFloat(t.profit_loss || 0)
                    : parseFloat(t.profit || 0);
            return sum + (Number.isFinite(val) ? val : 0);
        }, 0);
        elOpenPL.textContent = (total >= 0 ? '+' : '') + fmt(total) + ' ' + currency;
        elOpenPL.className = 'trade-kpi-value ' + plClass(total);
    }

    function renderLive(live, account) {
        const currency = account?.currency || 'USD';
        elLiveCount.textContent = live.length;
        computeOpenPL(live, currency);

        if (!live.length) {
            elLiveList.innerHTML = '<div class="trade-empty">No open positions right now.</div>';
            return;
        }

        elLiveList.innerHTML = live.slice(0, 5).map(t => `
            <div class="trade-row">
                <div class="trade-row-main">
                    <span class="trade-symbol">${escapeHtml(t.symbol || '—')}</span>
                    <span class="trade-badge ${(String(t.direction || '').toLowerCase() === 'sell') ? 'sell' : 'buy'}">${escapeHtml(t.direction || '—')}</span>
                </div>
                <div class="trade-row-meta">
                    <span>Volume: ${fmt(t.volume, 2)} lot</span>
                    <span>Price: ${fmtPrice(t.open_price)} → ${fmtPrice(t.current_price)}</span>
                </div>
                <div class="trade-row-time">Opened: ${fmtTime(t.open_time)}</div>
            </div>
        `).join('');
    }

    function renderRecent(recent, account) {
	    const currency = account?.currency || 'USD';
	    const limitedRecent = Array.isArray(recent) ? recent.slice(0, 10) : [];
	
	    elClosedCount.textContent = limitedRecent.length;
	
	    const sumPL = limitedRecent.reduce((sum, t) => {
	        const val = t.profit_loss !== null && t.profit_loss !== ''
	            ? parseFloat(t.profit_loss || 0)
	            : 0;
	        return sum + (Number.isFinite(val) ? val : 0);
	    }, 0);
	
	    if (limitedRecent.length) {
	        elClosedPL.textContent = (sumPL >= 0 ? '+' : '') + fmt(sumPL) + ' ' + currency;
	        elClosedPL.className = 'trade-kpi-value ' + plClass(sumPL);
	    } else {
	        elClosedPL.textContent = '--';
	        elClosedPL.className = 'trade-kpi-value';
	    }
	
	    if (!limitedRecent.length) {
	        elClosedList.innerHTML = '<div class="trade-empty">No recent MT5 trades found yet.</div>';
	        return;
	    }
	
	    elClosedList.innerHTML = limitedRecent.map(t => {
	        const val = t.profit_loss !== null && t.profit_loss !== ''
	            ? parseFloat(t.profit_loss || 0)
	            : parseFloat(t.profit_loss_pct || 0);
	        const suffix = t.profit_loss !== null && t.profit_loss !== '' ? ' ' + currency : '%';
	        const direction = String(t.direction || '').toLowerCase();
	
	        return `
	            <div class="trade-row">
	                <div class="trade-row-main">
	                    <span class="trade-symbol">${escapeHtml(t.symbol || '—')}</span>
	                    <span class="trade-badge ${(direction.includes('short') || direction === 'sell') ? 'sell' : 'buy'}">${escapeHtml(t.direction || '—')}</span>
	                </div>
	                <div class="trade-row-meta">
	                    <span>${escapeHtml((t.status || '—').toUpperCase())}</span>
	                    <span>${fmtPrice(t.entry_price)} → ${t.exit_price ? fmtPrice(t.exit_price) : '—'}</span>
	                    <span class="${plClass(val)}">${(val >= 0 ? '+' : '') + fmt(val) + suffix}</span>
	                </div>
	                <div class="trade-row-time">
	                    ${t.exit_date ? 'Closed' : 'Opened'}: ${fmtTime(t.exit_date || t.entry_date)}
	                </div>
	            </div>
	        `;
	    }).join('');
	}


    async function refreshTradesCard() {
        try {
            const res = await fetch(API, { credentials: 'include' });
            const data = await res.json();

            if (!data || data.success !== true) throw new Error(data?.message || 'Invalid MT5 live response');

            const account = data.account || {};
            const live = Array.isArray(data.live) ? data.live : [];
            const recent = Array.isArray(data.recent) ? data.recent : [];
            const status = data.connection_status || 'offline';
            const lastSync = data.last_sync_at || account.synced_at || '';

            renderConnectionStatus(status, lastSync);
            renderLive(live, account);
            renderRecent(recent, account);
        } catch (e) {
            const msg = e?.message || 'Unable to load MT5 trades';

            elLiveList.innerHTML = `<div class="trade-empty">${escapeHtml(msg)}</div>`;
            elClosedList.innerHTML = `<div class="trade-empty">${escapeHtml(msg)}</div>`;
            elLiveCount.textContent = '--';
            elClosedCount.textContent = '--';
            elOpenPL.textContent = '--';
            elClosedPL.textContent = '--';
            elOpenPL.className = 'trade-kpi-value';
            elClosedPL.className = 'trade-kpi-value';
            renderConnectionStatus('offline', '');
        }
    }

    refreshTradesCard();
    setInterval(refreshTradesCard, 30000);
})();
</script>
	
</body>
</html>