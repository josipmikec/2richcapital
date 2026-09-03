<?php
require_once '../auth/session-config.php';
require_once '../auth/feature-flags.php';

if (!isset($_SESSION['user_id']) || !isset($_SESSION['authenticated'])) {
    header('Location: https://app.2rich.capital/login');
    exit;
}

rich_feature_guard('market-data', 'Market Data');

$username   = $_SESSION['username']   ?? 'Member';
$useremail  = $_SESSION['user_email'] ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?php echo htmlspecialchars($_SESSION['csrf_token'] ?? '', ENT_QUOTES); ?>">
    <title>Market Data - 2RICH CAPITAL</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/dashboard.css">
    <link rel="stylesheet" href="../assets/css/market-data.css">
    <!-- TradingView Charting Library -->
    <script src="../assets/charting_library/charting_library.standalone.js"></script>
    <style>

        .md-watchlist-btn, .md-watchlist-add {
            border: 1px solid #1e1e1e; background: rgba(255,255,255,0.03); color: #888; border-radius: 8px;
            min-height: 30px; padding: 6px 10px; font: 700 9px/1 "Montserrat", sans-serif; letter-spacing: .08em; text-transform: uppercase;
        }
        .md-watchlist-btn:hover, .md-watchlist-add:hover, .md-watchlist-btn[aria-expanded="true"] { color: #F2CA50; border-color: rgba(242,202,80,.35); }
        .md-watchlist-add { padding-inline: 9px; font-size: 16px; line-height: 16px; }
        .md-watchlist-panel { position: absolute; z-index: 20; margin-top: 38px; min-width: 230px; background: #151515; border: 1px solid #292929; border-radius: 10px; box-shadow: 0 12px 35px rgba(0,0,0,.45); }
        .md-watchlist-heading { display:flex; align-items:center; justify-content:space-between; padding: 10px 12px; border-bottom:1px solid #252525; color:#ccc; font:700 10px/1 "Montserrat",sans-serif; text-transform:uppercase; letter-spacing:.08em; }
        .md-watchlist-heading button { color:#777; font-size:18px; padding:0 3px; }
        .md-watchlist-items { padding: 6px; }
        .md-watchlist-item { display:flex; align-items:center; gap:8px; width:100%; padding:8px; color:#aaa; border-radius:6px; text-align:left; font:600 10px/1.2 "Montserrat",sans-serif; }
        .md-watchlist-item:hover { background:rgba(242,202,80,.08); color:#F2CA50; }
        .md-watchlist-remove { margin-left:auto; color:#555; font-size:14px; }
        .md-watchlist-empty { display:block; padding:12px 8px; color:#555; font:400 10px/1.4 "Montserrat",sans-serif; }

        /* ── Feed controls bar (inside Market Feeds pane) ─────────────── */
        .md-feed-controls {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 10px 16px;
            background: #111;
            border-bottom: 1px solid #1e1e1e;
            flex-wrap: wrap;
        }

        .md-feed-controls .md-symbol-select-wrap { margin: 0; }
        .md-symbol-native-btn { display:inline-flex; align-items:center; gap:8px; min-height:36px; padding:0 12px; border:1px solid #2a2a2a; border-radius:8px; background:#151515; color:#d8d8d8; font:600 11px/1 "Montserrat",sans-serif; letter-spacing:.04em; text-transform:uppercase; cursor:pointer; }
        .md-symbol-native-btn:hover, .md-symbol-native-btn:focus-visible { background:#1b1b1b; border-color:#3a3a3a; color:#fff; }
        .md-symbol-native-btn span[aria-hidden="true"] { font-size:13px; color:#F2CA50; }
        .md-feed-controls .md-interval-wrap      { margin: 0; }
        .md-feed-controls .md-live-badge         { margin: 0; }

        /* ── Calendar filter panel: hidden by default ──────────────────── */
        .md-cal-filters {
            display: none;
        }
        .md-cal-filters.is-open {
            display: flex;
        }

        /* ── Calendar header — single row layout ───────────────────────── */
        .md-calendar-header {
            display: flex;
            flex-direction: column;
            gap: 6px;
        }

        /* Row 1: range pills flush left, controls flush far right */
        .md-cal-header-row1 {
            display: flex;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
        }

        /* Event count */
        .md-cal-event-count {
            font-size: 11px;
            font-weight: 700;
            line-height: 1;
            letter-spacing: 0.06em;
            color: #666;
            text-transform: uppercase;
            white-space: nowrap;
        }

        /* Updated timestamp */
        .md-cal-meta {
            font-size: 11px;
            font-weight: 400;
            line-height: 1;
            color: #444;
            white-space: nowrap;
        }

        /* Right-side controls — pushed to far right via margin-left: auto */
        .md-calendar-header-right {
            display: flex;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
            margin-left: auto;
        }

        /* ── Inline Range pills in header ───────────────────────────────── */
        .md-cal-range-pills {
            display: flex;
            gap: 2px;
            align-items: center;
        }
        .md-cal-range-pills .md-cal-filter-btn {
            padding: 4px 10px;
            font-size: 11px;
        }

        /* Thin vertical divider between range pills and refresh controls */
        .md-cal-header-divider {
            width: 1px;
            height: 16px;
            background: #2a2a2a;
            flex-shrink: 0;
        }

        /* ── Cal error state badge ───────────────────────────────────────── */
        #calRefreshBadge.cal-error .md-live-dot {
            background: #ef4444 !important;
            box-shadow: 0 0 0 3px rgba(239,68,68,0.25) !important;
            animation: none;
        }

        /* ── Filter toggle button ────────────────────────────────────────── */
        .md-cal-filter-toggle {
            display: flex;
            align-items: center;
            gap: 5px;
            padding: 5px 10px;
            font-size: 11px;
            font-weight: 600;
            letter-spacing: 0.05em;
            border-radius: 6px;
            border: 1px solid #2a2a2a;
            background: transparent;
            color: #666;
            cursor: pointer;
            transition: background 0.15s, color 0.15s, border-color 0.15s;
        }
        .md-cal-filter-toggle.is-active,
        .md-cal-filter-toggle:hover {
            background: #1a1a1a;
            border-color: #3a3a3a;
            color: #ccc;
        }
        .md-cal-filter-toggle svg { flex-shrink: 0; }

        /* ── Multi-select currency: active pill style ────────────────────── */
        #currencyFilter .md-cal-filter-btn.active {
            background: #c9a84c22;
            border-color: #c9a84c88;
            color: #c9a84c;
        }

        /* ── Currency filter hint text ───────────────────────────────────── */
        .md-cal-filter-hint {
            font-size: 10px;
            color: #444;
            font-style: italic;
            margin-left: 4px;
            align-self: center;
        }

        /* 2RICH chart toolbar — independent from TradingView's internal header */
        #rich-chart-toolbar button,
        #rich-chart-toolbar select,
        .rich-native-timeframe-host button {
            min-height:30px;
            border:1px solid transparent;
            background:transparent;
            color:#b8bac2;
            border-radius:4px;
            padding:0 9px;
            font:500 11px/1 "Montserrat",sans-serif;
            cursor:pointer;
        }
        #rich-chart-toolbar button:hover,
        #rich-chart-toolbar select:hover,
        .rich-native-timeframe-host button:hover {
            background:#1a1a1a;
            color:#f1f1f1;
        }
        #rich-chart-toolbar button:focus-visible,
        #rich-chart-toolbar select:focus-visible,
        .rich-native-timeframe-host button:focus-visible {
            outline:2px solid rgba(242,202,80,.78);
            outline-offset:1px;
            background:#1a1a1a !important;
            color:#ffffff !important;
        }
        #rich-chart-toolbar button:active,
        .rich-native-timeframe-host button:active {
            background:#242424;
            color:#ffffff;
            transform:translateY(1px);
        }
        #rich-chart-toolbar .rich-toolbar-spacer { flex:1; }
        #rich-chart-toolbar .rich-toolbar-divider { width:1px; height:20px; background:#303030; margin:0 4px; }
        #rich-chart-toolbar select { min-width:150px; appearance:none; }
        #rich-chart-toolbar .rich-toolbar-status { color:#777; font-size:10px; white-space:nowrap; }
        .rich-toolbar-timeframes { display:inline-flex; align-items:center; gap:0; }
        .rich-toolbar-timeframes button { min-width:36px; padding:0 6px; }
        .rich-native-timeframe-host,
        .rich-native-timeframe-host:hover,
        .rich-native-timeframe-host:focus,
        .rich-native-timeframe-host:active {
            min-height:42px !important;
            padding:0 !important;
            margin:0 !important;
            background:transparent !important;
            border:0 !important;
            box-shadow:none !important;
            pointer-events:none !important;
        }
        /* Neutralise TradingView's parent wrapper hover on timeframe host */
        div:has(> .rich-native-timeframe-host),
        div:has(> .rich-native-timeframe-host):hover,
        div:has(> .rich-native-timeframe-host):focus-within,
        div:has(> .rich-native-timeframe-host):active {
            background:transparent !important;
            background-color:transparent !important;
            border:0 !important;
            box-shadow:none !important;
        }
        .rich-native-timeframe-host > div,
        .rich-native-timeframe-host > div > div,
        .rich-native-timeframe-host > div > div > button {
            background:transparent !important;
            border:0 !important;
            box-shadow:none !important;
        }
        .rich-native-timeframe-host button {
            min-height:30px !important;
            min-width:36px !important;
            padding:0 6px !important;
            border:1px solid transparent !important;
            border-radius:4px !important;
            background:transparent !important;
            background-color:transparent !important;
            color:#b8bac2 !important;
            box-shadow:none !important;
            pointer-events:auto !important;
            transition:background .16s ease,color .16s ease,transform .08s ease,outline .16s ease !important;
        }
        .rich-native-timeframe-host button:hover {
            background:#1a1a1a !important;
            color:#f1f1f1 !important;
        }
        .rich-native-timeframe-host button:focus-visible {
            outline:2px solid rgba(242,202,80,.78) !important;
            outline-offset:1px;
            background:#1a1a1a !important;
            color:#ffffff !important;
        }
        .rich-native-timeframe-host button:active {
            background:#242424 !important;
            color:#ffffff !important;
            transform:translateY(1px);
        }
        .rich-native-timeframe-host button.is-active {
            color:#f1f1f1 !important;
            border-bottom:2px solid #f2ca50 !important;
        }
        #rich-chart-toolbar .rich-icon { display:inline-flex; align-items:center; justify-content:center; min-width:16px; font-size:16px; line-height:1; }
        #rich-chart-toolbar .rich-icon-indicator { font-size:14px; font-weight:600; }
        
        /* Hide Search and Compare buttons */
        #richSearchBtn,
        #richCompareBtn {
            display: none !important;
        }
    </style>
</head>
<body>
<div class="dashboard-background"></div>

<!-- Top Navigation -->
<nav class="top-nav">
    <div class="nav-container">
        <div class="nav-brand">
            <h1>2RICH CAPITAL</h1>
            <span class="nav-tagline">INSTITUTIONAL GRADE TRADING</span>
        </div>
        <div class="nav-right">
            <span class="user-email"><?php echo htmlspecialchars($useremail); ?></span>
            <a href="../auth/logout.php" class="logout-btn">LOGOUT</a>
        </div>
    </div>
</nav>

<!-- Main Container -->
<div class="dashboard-container">

    <!-- Sidebar -->
    <aside class="sidebar">
        <ul class="sidebar-menu">
            <li class="menu-item" onclick="window.location.href='../dashboard'">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/>
                    <rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/>
                </svg>
                <span>Dashboard</span>
            </li>
            <li class="menu-item" onclick="window.location.href='../journal'">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
                    <polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/>
                    <line x1="16" y1="17" x2="8" y2="17"/><polyline points="10 9 9 9 8 9"/>
                </svg>
                <span>Trading Journal</span>
            </li>
            <li class="menu-item" onclick="window.location.href='../trading-floor'">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <line x1="12" y1="1" x2="12" y2="23"/>
                    <path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/>
                </svg>
                <span>Trading Floor</span>
            </li>
            <li class="menu-item active" onclick="window.location.href='../market-data'">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <rect x="2" y="3" width="20" height="14" rx="2" ry="2"/>
                    <line x1="8" y1="21" x2="16" y2="21"/><line x1="12" y1="17" x2="12" y2="21"/>
                </svg>
                <span>Market Data</span>
            </li>
            <li class="menu-item" onclick="window.location.href='../account'">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/>
                    <circle cx="12" cy="7" r="4"/>
                </svg>
                <span>Account</span>
            </li>
        </ul>
    </aside>

    <!-- Main Content -->
    <main class="main-content">

        <!-- ═══════════════════════════════════════════════════════════════
             PAGE HEADER
        ═══════════════════════════════════════════════════════════════ -->
        <div class="md-page-header">
            <div>
                <h2 class="md-page-title">Market Data</h2>
                <p class="md-page-subtitle">Real-time feeds &amp; institutional intelligence</p>
            </div>
        </div>

        <!-- ═══════════════════════════════════════════════════════════════
             TAB NAVIGATION
        ═══════════════════════════════════════════════════════════════ -->
        <div class="md-tabs">
            <button class="md-tab active"  onclick="switchTab(this,'tab-feeds')">Market Feeds</button>
            <button class="md-tab"         onclick="switchTab(this,'tab-calendar')">Economic Calendar</button>
            <button class="md-tab"         onclick="switchTab(this,'tab-sentiment')">Institutional Sentiment</button>
            <button class="md-tab"         onclick="switchTab(this,'tab-heatmap')">Heatmap</button>
            <button class="md-tab"         onclick="switchTab(this,'tab-screener')">Screener</button>
        </div>

        <!-- ═══════════════════════════════════════════════════════════════
             MARKET FEEDS PANE
        ═══════════════════════════════════════════════════════════════ -->
        <div class="md-pane active" id="tab-feeds">

            <div class="md-feed-controls">
                <button type="button" class="md-watchlist-btn" id="watchlistToggle" onclick="toggleWatchlist()" aria-expanded="false" title="Open watchlist">
                    <span aria-hidden="true">★</span> Watchlist
                </button>
                <button type="button" class="md-watchlist-add" onclick="addCurrentToWatchlist()" title="Add current symbol to watchlist" aria-label="Add current symbol to watchlist">＋</button>
                <div class="md-watchlist-panel" id="watchlistPanel" hidden>
                    <div class="md-watchlist-heading">
                        <span>Favourites</span>
                        <button type="button" onclick="toggleWatchlist()" aria-label="Close watchlist">×</button>
                    </div>
                    <div id="watchlistItems" class="md-watchlist-items"><span class="md-watchlist-empty">No favourites yet</span></div>
                </div>
            </div>

            <!-- Chart container -->
            <div class="md-chart-wrap">
                <div id="tv_chart_container"></div>
            </div>

        </div>

        <!-- ═══════════════════════════════════════════════════════════════
             ECONOMIC CALENDAR PANE
        ═══════════════════════════════════════════════════════════════ -->
        <div class="md-pane" id="tab-calendar">
            <div class="md-calendar-card">

                <!-- Single-row header -->
                <div class="md-calendar-header">

                    <div class="md-cal-header-row1">

                        <!-- Range pills flush left -->
                        <div class="md-cal-range-pills" id="rangeFilter">
                            <button class="md-cal-filter-btn active" data-range="all">All</button>
                            <button class="md-cal-filter-btn" data-range="today">Today</button>
                            <button class="md-cal-filter-btn" data-range="tomorrow">Tomorrow</button>
                            <button class="md-cal-filter-btn" data-range="this_week">This Week</button>
                            <button class="md-cal-filter-btn" data-range="next_week">Next Week</button>
                        </div>

                        <!-- Controls flush far right via margin-left: auto -->
                        <div class="md-calendar-header-right">

                            <!-- Event count + last updated — inline before divider -->
                            <span class="md-cal-event-count" id="calEventCount">Loading...</span>
                            <span class="md-cal-meta" id="calLastUpdated"></span>

                            <div class="md-cal-header-divider"></div>

                            <!-- Auto-refresh badge -->
                            <div class="md-live-badge" id="calRefreshBadge">
                                <div class="md-live-dot"></div>
                                <span id="calRefreshLabel">Refreshing in 1:00</span>
                            </div>

                            <!-- Manual refresh -->
                            <button class="md-cal-refresh-btn" onclick="refreshCalendar()" title="Refresh now">
                                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                                    <polyline points="23 4 23 10 17 10"/><path d="M20.49 15a9 9 0 1 1-2.12-9.36L23 10"/>
                                </svg>
                                Refresh
                            </button>

                            <!-- Filter toggle -->
                            <button class="md-cal-filter-toggle" id="calFilterToggle" onclick="toggleCalFilters()" title="Toggle filters">
                                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                                    <line x1="4" y1="6" x2="20" y2="6"/><line x1="8" y1="12" x2="16" y2="12"/><line x1="12" y1="18" x2="12" y2="18"/>
                                </svg>
                                Filters
                            </button>
                        </div>
                    </div>

                </div>

                <!-- Filters panel — Impact + Currency only (Range is in header) -->
                <div class="md-cal-filters" id="calFilters">

                    <!-- Impact filter (single-select) -->
                    <div class="md-cal-filter-group">
                        <span class="md-cal-filter-label">Impact</span>
                        <div class="md-cal-filter-btns" id="impactFilter">
                            <button class="md-cal-filter-btn active" data-impact="all">All</button>
                            <button class="md-cal-filter-btn" data-impact="High"><span class="md-impact-dot high"></span>High</button>
                            <button class="md-cal-filter-btn" data-impact="Medium"><span class="md-impact-dot medium"></span>Medium</button>
                            <button class="md-cal-filter-btn" data-impact="Low"><span class="md-impact-dot low"></span>Low</button>
                        </div>
                    </div>

                    <!-- Currency filter (MULTI-select — klikni više njih odjednom) -->
                    <div class="md-cal-filter-group">
                        <span class="md-cal-filter-label">Currency</span>
                        <div class="md-cal-filter-btns" id="currencyFilter">
                            <button class="md-cal-filter-btn active" data-currency="all">All</button>
                            <button class="md-cal-filter-btn" data-currency="USD">USD</button>
                            <button class="md-cal-filter-btn" data-currency="EUR">EUR</button>
                            <button class="md-cal-filter-btn" data-currency="GBP">GBP</button>
                            <button class="md-cal-filter-btn" data-currency="JPY">JPY</button>
                            <button class="md-cal-filter-btn" data-currency="AUD">AUD</button>
                            <button class="md-cal-filter-btn" data-currency="CAD">CAD</button>
                            <button class="md-cal-filter-btn" data-currency="CHF">CHF</button>
                            <button class="md-cal-filter-btn" data-currency="NZD">NZD</button>
                        </div>
                        <span class="md-cal-filter-hint">Možeš odabrati više valuta</span>
                    </div>

                </div>

                <div class="md-calendar-list" id="economicCalendar">
                    <div class="md-calendar-loading">Loading upcoming events...</div>
                </div>
            </div>

            <div class="md-features">
                <div class="md-feature-card">
                    <div class="md-feature-label">Impact Filter</div>
                    <div class="md-feature-title">High / Medium / Low</div>
                    <div class="md-feature-desc">Filter by event impact level to focus on only the releases most likely to move your instruments.</div>
                </div>
                <div class="md-feature-card">
                    <div class="md-feature-label">Auto-Refresh</div>
                    <div class="md-feature-title">Every 60s / 20s Near Events</div>
                    <div class="md-feature-desc">Calendar refreshes every 60 seconds normally, and every 20 seconds when an event is within 5 minutes of release.</div>
                </div>
                <div class="md-feature-card">
                    <div class="md-feature-label">Execution</div>
                    <div class="md-feature-title">News-Aware Trading</div>
                    <div class="md-feature-desc">Use the calendar before entries and around session opens to avoid getting caught in event-driven volatility.</div>
                </div>
            </div>
        </div>

        <!-- ═══════════════════════════════════════════════════════════════
             INSTITUTIONAL SENTIMENT PANE (placeholder)
        ═══════════════════════════════════════════════════════════════ -->
        <div class="md-pane" id="tab-sentiment">
            <div class="md-placeholder">
                <div class="md-placeholder-glow"></div>
                <div class="md-placeholder-inner">
                    <svg class="md-placeholder-icon" viewBox="0 0 64 64" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="32" cy="32" r="26"/>
                        <path d="M20 38 Q32 50 44 38"/>
                        <circle cx="22" cy="26" r="3" fill="currentColor" stroke="none"/>
                        <circle cx="42" cy="26" r="3" fill="currentColor" stroke="none"/>
                    </svg>
                    <h3 class="md-placeholder-title">Institutional Sentiment</h3>
                    <p class="md-placeholder-desc">
                        COT report analysis, smart money positioning, and retail sentiment data to give you an institutional-grade edge on direction bias and confluence.
                    </p>
                </div>
            </div>
            <div class="md-features">
                <div class="md-feature-card">
                    <div class="md-feature-label">COT Reports</div>
                    <div class="md-feature-title">Commitment of Traders</div>
                    <div class="md-feature-desc">Weekly CFTC COT data visualised — see what commercials, non-commercials, and small speculators are doing.</div>
                </div>
                <div class="md-feature-card">
                    <div class="md-feature-label">Smart Money</div>
                    <div class="md-feature-title">Institutional Positioning</div>
                    <div class="md-feature-desc">Track large speculator net positions and identify potential trend reversals before they happen.</div>
                </div>
                <div class="md-feature-card">
                    <div class="md-feature-label">Retail Bias</div>
                    <div class="md-feature-title">Retail vs. Institutional</div>
                    <div class="md-feature-desc">Understand when retail sentiment is at extremes — often the clearest signal of institutional intent.</div>
                </div>
            </div>
        </div>

        <!-- ═══════════════════════════════════════════════════════════════
             HEATMAP PANE (placeholder)
        ═══════════════════════════════════════════════════════════════ -->
        <div class="md-pane" id="tab-heatmap">
            <div class="md-placeholder">
                <div class="md-placeholder-glow"></div>
                <div class="md-placeholder-inner">
                    <svg class="md-placeholder-icon" viewBox="0 0 64 64" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                        <rect x="6"  y="6"  width="14" height="14" rx="3"/>
                        <rect x="25" y="6"  width="14" height="14" rx="3"/>
                        <rect x="44" y="6"  width="14" height="14" rx="3"/>
                        <rect x="6"  y="25" width="14" height="14" rx="3"/>
                        <rect x="25" y="25" width="14" height="14" rx="3"/>
                        <rect x="44" y="25" width="14" height="14" rx="3"/>
                        <rect x="6"  y="44" width="14" height="14" rx="3"/>
                        <rect x="25" y="44" width="14" height="14" rx="3"/>
                        <rect x="44" y="44" width="14" height="14" rx="3"/>
                    </svg>
                    <h3 class="md-placeholder-title">Currency Heatmap</h3>
                    <p class="md-placeholder-desc">
                        Visualise relative currency strength across all major pairs in real time. Instantly identify the strongest and weakest currencies to build high-probability trade bias.
                    </p>
                </div>
            </div>
        </div>

        <!-- ═══════════════════════════════════════════════════════════════
             SCREENER PANE (placeholder)
        ═══════════════════════════════════════════════════════════════ -->
        <div class="md-pane" id="tab-screener">
            <div class="md-placeholder">
                <div class="md-placeholder-glow"></div>
                <div class="md-placeholder-inner">
                    <svg class="md-placeholder-icon" viewBox="0 0 64 64" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="28" cy="28" r="18"/>
                        <line x1="42" y1="42" x2="58" y2="58"/>
                        <line x1="18" y1="28" x2="38" y2="28"/>
                        <line x1="28" y1="18" x2="28" y2="38"/>
                    </svg>
                    <h3 class="md-placeholder-title">Market Screener</h3>
                    <p class="md-placeholder-desc">
                        Scan the market for setups matching your criteria — filter by session, instrument type, ATR, trend direction, and key level proximity to find your next trade faster.
                    </p>
                </div>
            </div>
        </div>

    </main>
</div>

<script>
// ═══════════════════════════════════════════════════════════════════════════
// CHART STATE
// ═══════════════════════════════════════════════════════════════════════════
let tvWidget       = null;
let currentSymbol  = '';
let chartSettings  = {};
let chartSettingsTimer = null;
let chartPermission = { sync: true, multi: false };
let tvUserSettings = {};
let chartState = {};
let chartStateTimer = null;
let tvSettingsTimer = null;
let isApplyingChartState = false;
let isRestoringDrawings = false;
let drawingRestorePromise = Promise.resolve();
let chartRestoreSettlingUntil = 0;
let chartPersistenceArmTimer = null;
const CHART_RESTORE_SETTLE_MS = 1800;
let hasCompletedInitialChartRestore = false;
let hasArmedChartPersistence = false;
let lastAppliedChartStateSignature = '';
let chartStateApplyTimers = [];
const TWO_RICH_TEMPLATES = {
    dark: {
        name: '2RICH Dark',
        theme: 'dark',
        toolbarBg: '#0F1A1B',
        overrides: {
            'paneProperties.background': '#0E1718',
            'paneProperties.backgroundType': 'solid',
            'paneProperties.vertGridProperties.color': '#233638',
            'paneProperties.horzGridProperties.color': '#233638',
            'paneProperties.crossHairProperties.color': '#6F8C89',
            'scalesProperties.textColor': '#D7E4E3',
            'scalesProperties.lineColor': '#2A3C3F',
            'mainSeriesProperties.style': 1,
            'mainSeriesProperties.candleStyle.upColor': '#23B38F',
            'mainSeriesProperties.candleStyle.downColor': '#E07761',
            'mainSeriesProperties.candleStyle.borderUpColor': '#23B38F',
            'mainSeriesProperties.candleStyle.borderDownColor': '#E07761',
            'mainSeriesProperties.candleStyle.wickUpColor': '#23B38F',
            'mainSeriesProperties.candleStyle.wickDownColor': '#E07761',
            'mainSeriesProperties.priceLineColor': '#19A39B',
            'mainSeriesProperties.showPriceLine': true
        },
        studiesOverrides: {
            'volume.volume.visible': false,
            'volume.show ma': false
        }
    },
    light: {
        name: '2RICH Light',
        theme: 'light',
        toolbarBg: '#F4EDE4',
        overrides: {
            'paneProperties.background': '#F7F3EE',
            'paneProperties.backgroundType': 'solid',
            'paneProperties.vertGridProperties.color': '#E6DDD2',
            'paneProperties.horzGridProperties.color': '#E6DDD2',
            'paneProperties.crossHairProperties.color': '#8FA7A5',
            'scalesProperties.textColor': '#1E2A2C',
            'scalesProperties.lineColor': '#C9BFB0',
            'mainSeriesProperties.style': 1,
            'mainSeriesProperties.candleStyle.upColor': '#138A72',
            'mainSeriesProperties.candleStyle.downColor': '#C35B4A',
            'mainSeriesProperties.candleStyle.borderUpColor': '#138A72',
            'mainSeriesProperties.candleStyle.borderDownColor': '#C35B4A',
            'mainSeriesProperties.candleStyle.wickUpColor': '#138A72',
            'mainSeriesProperties.candleStyle.wickDownColor': '#C35B4A',
            'mainSeriesProperties.priceLineColor': '#0E6B68',
            'mainSeriesProperties.showPriceLine': true
        },
        studiesOverrides: {
            'volume.volume.visible': false,
            'volume.show ma': false
        }
    }
};
const DEFAULT_CHART_THEME = {
    theme: 'dark',
    toolbarBg: '#0e0e0e',
    studiesOverrides: {
        'volume.volume.visible': false,
        'volume.show ma': false
    }
};
const CHART_DEBUG = true;
function chartDebug(label, payload) {
    if (!CHART_DEBUG) return;
    console.groupCollapsed('[2RICH chart debug] ' + label);
    if (payload !== undefined) console.log(payload);
    console.log(new Date().toISOString());
    console.groupEnd();
}


function isChartRestoreSettling() {
    return Date.now() < chartRestoreSettlingUntil;
}

function canPersistChartState() {
    return hasCompletedInitialChartRestore && hasArmedChartPersistence && !isChartPersistenceBlocked();
}

function armChartPersistence(reason = 'manual') {
    hasArmedChartPersistence = true;
    chartDebug('chart persistence armed', {
        reason,
        hasCompletedInitialChartRestore,
        hasArmedChartPersistence
    });
}

function isChartPersistenceBlocked() {
    return isApplyingChartState || isRestoringDrawings || isChartRestoreSettling();
}

function getChartPersistenceBlockReason() {
    if (isApplyingChartState) return 'applying-chart-state';
    if (isRestoringDrawings) return 'restoring-drawings';
    if (isChartRestoreSettling()) return 'restore-settling';
    return null;
}

function markChartRestoreSettling(delayMs = CHART_RESTORE_SETTLE_MS) {
    chartRestoreSettlingUntil = Date.now() + Math.max(0, Number(delayMs) || 0);
    chartDebug('chart restore settling window', {
        delayMs,
        settleUntil: new Date(chartRestoreSettlingUntil).toISOString()
    });
}

function resetChartPersistenceArmTimer() {
    if (chartPersistenceArmTimer) {
        clearTimeout(chartPersistenceArmTimer);
        chartPersistenceArmTimer = null;
    }
}

function scheduleChartPersistenceArm(reason = 'restore-settled', delayMs = CHART_RESTORE_SETTLE_MS) {
    resetChartPersistenceArmTimer();
    const armDelay = Math.max(0, Number(delayMs) || 0);
    chartPersistenceArmTimer = setTimeout(() => {
        chartPersistenceArmTimer = null;
        armChartPersistence(reason);
    }, armDelay);
    chartDebug('chart persistence arm scheduled', {
        reason,
        delayMs: armDelay,
        settleUntil: chartRestoreSettlingUntil ? new Date(chartRestoreSettlingUntil).toISOString() : null
    });
}

function resetChartPersistenceState(reason = 'reset') {
    resetChartPersistenceArmTimer();
    hasCompletedInitialChartRestore = false;
    hasArmedChartPersistence = false;
    chartRestoreSettlingUntil = 0;
    chartDebug('chart persistence reset', {
        reason,
        hasCompletedInitialChartRestore,
        hasArmedChartPersistence
    });
}

function csrfHeaders() {
    const token = document.querySelector('meta[name=csrf-token]')?.content || '';
    return token ? { 'X-CSRF-Token': token, 'Content-Type': 'application/json' } : { 'Content-Type': 'application/json' };
}

async function loadChartSettings() {
    try {
        const response = await fetch('../api/preferences/get.php?key=market_data_chart_settings', { credentials: 'same-origin' });
        const data = await response.json();
        if (data.success && data.value) chartSettings = JSON.parse(data.value);
    } catch (error) { console.warn('[2RICH] Chart settings unavailable', error); }
}

async function saveChartSettings(settings) {
    chartSettings = { ...chartSettings, ...settings };
    clearTimeout(chartSettingsTimer);
    chartSettingsTimer = setTimeout(async () => {
        try {
            await fetch('../api/preferences/set.php', { method: 'POST', credentials: 'same-origin', headers: csrfHeaders(), body: JSON.stringify({ key: 'market_data_chart_settings', value: JSON.stringify(chartSettings) }) });
        } catch (error) { console.warn('[2RICH] Chart settings could not be saved', error); }
    }, 500);
}

const CHART_STATE_STORAGE_KEY = 'market_data_chart_state';

function getChartStateSymbol(symbolOverride = null) {
    return String(symbolOverride || currentSymbol || chartSettings.symbol || '').trim() || 'global';
}

async function loadChartStateMap() {
    try {
        const response = await fetch(`../api/preferences/get.php?key=${encodeURIComponent(CHART_STATE_STORAGE_KEY)}`, { credentials: 'same-origin' });
        const data = await response.json();
        chartDebug('chart state load response', { key: CHART_STATE_STORAGE_KEY, status: response.status, data });
        if (data.success && data.value) {
            const parsed = JSON.parse(data.value);
            return parsed && typeof parsed === 'object' ? parsed : {};
        }
    } catch (error) {
        console.warn('[2RICH] Chart state unavailable', error);
    }
    return {};
}

async function loadChartState(symbolOverride = null) {
    const symbolKey = getChartStateSymbol(symbolOverride);
    
    // Load chart state (without drawings) from preferences
    const stateMap = await loadChartStateMap();
    let nextState = stateMap && typeof stateMap === 'object' ? stateMap[symbolKey] : null;
    return nextState && typeof nextState === 'object' ? nextState : {};
}

async function saveChartState(state) {
    const nextState = state && typeof state === 'object' ? state : {};
    const symbolKey = getChartStateSymbol(nextState.symbol || null);
    if (isChartPersistenceBlocked()) {
        chartDebug('chart state save skipped', {
            reason: getChartPersistenceBlockReason(),
            symbolKey,
            snapshot: nextState
        });
        return;
    }
    
    // Extract drawings from state so they don't get saved in preferences
    const stateWithoutDrawings = { ...nextState };
    delete stateWithoutDrawings.drawings;
    
    chartState = { ...chartState, ...stateWithoutDrawings };
    
    clearTimeout(chartStateTimer);
    chartStateTimer = setTimeout(async () => {
        try {
            // Save core chart settings
            const stateMap = await loadChartStateMap();
            const nextMap = { ...(stateMap || {}), [symbolKey]: { ...chartState, symbol: symbolKey } };
            const payload = { key: CHART_STATE_STORAGE_KEY, value: JSON.stringify(nextMap) };
            chartDebug('chart state save request', { key: CHART_STATE_STORAGE_KEY, symbolKey, snapshot: chartState, payload });
            
            const response = await fetch('../api/preferences/set.php', {
                method: 'POST',
                credentials: 'same-origin',
                headers: csrfHeaders(),
                body: JSON.stringify(payload)
            });
            const data = await response.json().catch(() => null);
            chartDebug('chart state save response', { key: CHART_STATE_STORAGE_KEY, symbolKey, status: response.status, data });
        } catch (error) {
            console.warn('[2RICH] Chart state could not be saved', error);
        }
    }, 700);
}

async function applyChartState(symbolOverride = null) {
    if (isApplyingChartState) {
        chartDebug('chart state apply skipped', { reason: 'already-applying', symbolOverride });
        return;
    }

    resetChartPersistenceState('apply-chart-state');

    const nextState = await loadChartState(symbolOverride);
    if (!nextState || typeof nextState !== 'object' || !Object.keys(nextState).length) return;

    const stateSignature = JSON.stringify({
        symbol: nextState.symbol || null,
        interval: nextState.interval || null,
        chart_type: nextState.chart_type ?? null,
        drawings: nextState.drawings || null,
    });
    if (stateSignature === lastAppliedChartStateSignature) {
        chartDebug('chart state apply skipped', { reason: 'same-signature', symbolOverride });
        return;
    }

    chartStateApplyTimers.forEach((timer) => clearTimeout(timer));
    chartStateApplyTimers = [];
    isApplyingChartState = true;
    window._latestTvDrawingState = null;
    chartState = { ...nextState };
    chartDebug('chart state apply start', { symbolOverride, nextState });

    const chart = richChartApi();
    if (!chart) {
        isApplyingChartState = false;
        return;
    }

    try {
        if (nextState.interval && typeof chart.setResolution === 'function') {
            chart.setResolution(String(nextState.interval), () => {}, () => {});
        }
    } catch (error) {
        console.warn('[2RICH] Could not restore interval from chart state', error);
    }

    try {
        if (nextState.chart_type != null && typeof chart.setChartType === 'function') {
            chart.setChartType(nextState.chart_type);
        }
    } catch (error) {
        console.warn('[2RICH] Could not restore chart type', error);
    }

    // We no longer manually restore drawings here.
    // The TradingView save_load_adapter now handles all drawing restoration via loadLineToolsAndGroups.
    isRestoringDrawings = false;
    markChartRestoreSettling();
    hasCompletedInitialChartRestore = true;
    scheduleChartPersistenceArm('restore-settled', CHART_RESTORE_SETTLE_MS);
    chartDebug('chart state restore settled', {
        symbolOverride,
        settleMs: CHART_RESTORE_SETTLE_MS,
        hasCompletedInitialChartRestore
    });

    lastAppliedChartStateSignature = stateSignature;
    chartStateApplyTimers.push(setTimeout(() => {
        isApplyingChartState = false;
        chartStateApplyTimers = [];
        chartDebug('chart state apply complete', { symbolOverride, stateSignature });
    }, 2600));
}

function serializeLineToolsState(value, key = '') {
    if (!value || typeof value !== 'object') return value;

    if (value instanceof Map) {
        const obj = {};
        for (const [k, v] of value.entries()) {
            obj[k] = serializeLineToolsState(v, k);
        }
        return obj;
    }

    if (value instanceof Set) {
        return Array.from(value.values());
    }

    if (Array.isArray(value)) {
        return value.map(item => serializeLineToolsState(item));
    }

    const result = {};
    for (const [k, v] of Object.entries(value)) {
        result[k] = serializeLineToolsState(v, k);
    }
    return result;
}

function normalizeLineToolsState(value, key = '') {
    if (!value || typeof value !== 'object') return value;
    if (value instanceof Map || value instanceof Set) return value;
    
    if (key === 'sources' || key === 'groups') {
        if (Array.isArray(value)) {
            return new Map(value.map(entry => {
                const pair = Array.isArray(entry) ? entry : [entry?.id ?? entry?.name ?? String(Math.random()), entry];
                return [pair[0], normalizeLineToolsState(pair[1], key)];
            }));
        }
        const map = new Map();
        Object.entries(value).forEach(([k, v]) => {
            map.set(k, normalizeLineToolsState(v, k));
        });
        return map;
    }
    
    if (key === 'lineToolsToValidate' || key === 'groupsToValidate') {
        if (value instanceof Set) return value;
        if (Array.isArray(value)) return new Set(value);
        return new Set(Object.values(value));
    }
    
    if (Array.isArray(value)) {
        return value.map(item => normalizeLineToolsState(item));
    }
    
    const result = {};
    Object.entries(value).forEach(([entryKey, entryValue]) => {
        result[entryKey] = normalizeLineToolsState(entryValue, entryKey);
    });
    return result;
}


function snapshotChartState() {
    const chart = richChartApi();
    const state = {
        symbol: currentSymbol,
        interval: currentInterval,
        chart_type: null,
        visible_panes: null,
        studies: null,
        drawings: null,
        snapshot_version: 3,
        capabilities: {},
    };
    try {
        state.capabilities = chart ? {
            getChartType: typeof chart.getChartType === 'function',
            getAllPanesHeight: typeof chart.getAllPanesHeight === 'function',
            getAllStudies: typeof chart.getAllStudies === 'function',
            getLineToolsState: typeof chart.getLineToolsState === 'function',
            applyLineToolsState: typeof chart.applyLineToolsState === 'function',
            save: typeof chart.save === 'function',
            load: typeof chart.load === 'function',
        } : {};
    } catch (e) {}
    try {
        if (chart && typeof chart.getChartType === 'function') state.chart_type = chart.getChartType();
    } catch (e) {}
    try {
        if (chart && typeof chart.getAllPanesHeight === 'function') state.visible_panes = chart.getAllPanesHeight();
    } catch (e) {}
    try {
        if (chart && typeof chart.getAllStudies === 'function') {
            const studies = chart.getAllStudies();
            state.studies = Array.isArray(studies) ? studies.map(s => ({ name: s.name?.() || null, id: s.id?.() || null })) : null;
        }
    } catch (e) {}
    try {
        if (chart && typeof chart.getLineToolsState === 'function') {
            let raw = chart.getLineToolsState();
            
            // Fallback to intercepted drawing state from save_load_adapter if empty or null
            if ((!raw || (raw.sources instanceof Map && raw.sources.size === 0)) && window._latestTvDrawingState) {
                raw = window._latestTvDrawingState;
            }

            const serialized = serializeLineToolsState(raw);
            state.drawings = serialized ?? null;

            chartDebug('chart drawing snapshot', {
                hasDrawings: !!raw,
                type: typeof raw,
                rawKeys: raw && typeof raw === 'object' ? Object.keys(raw) : [],
                sourcesCount: raw?.sources instanceof Map ? raw.sources.size : (raw?.sources && typeof raw.sources === 'object' ? Object.keys(raw.sources).length : null),
                groupsCount: raw?.groups instanceof Map ? raw.groups.size : (raw?.groups && typeof raw.groups === 'object' ? Object.keys(raw.groups).length : null),
                serializedSourcesCount: serialized?.sources && typeof serialized.sources === 'object' ? Object.keys(serialized.sources).length : null,
                serializedGroupsCount: serialized?.groups && typeof serialized.groups === 'object' ? Object.keys(serialized.groups).length : null,
            });
        }
    } catch (e) {
        chartDebug('chart drawing snapshot error', { message: e?.message || String(e) });
    }
    return state;
}

async function loadTvUserSettings() {
    try {
        const response = await fetch('../api/preferences/chart-settings.php', { credentials: 'same-origin' });
        const data = await response.json();
        chartDebug('settings load response', { status: response.status, data });
        tvUserSettings = data && data.success && data.settings && typeof data.settings === 'object' ? data.settings : {};
    } catch (error) {
        tvUserSettings = {};
        console.warn('[2RICH] TV user settings unavailable', error);
    }
}

function persistTvUserSettings() {
    clearTimeout(tvSettingsTimer);
    tvSettingsTimer = setTimeout(async () => {
        try {
            const settingsSnapshot = Object.fromEntries(Object.entries(tvUserSettings).map(([k, v]) => [String(k), v]));
            const payload = { settings: settingsSnapshot };
            chartDebug('settings save request', { keyCount: Object.keys(settingsSnapshot).length, keys: Object.keys(settingsSnapshot), sampleEntries: Object.entries(settingsSnapshot).slice(0, 20), payloadJson: JSON.stringify(payload) });
            const response = await fetch('../api/preferences/chart-settings.php?debug=1', {
                method: 'POST',
                credentials: 'same-origin',
                headers: csrfHeaders(),
                body: JSON.stringify(payload)
            });
            const data = await response.json().catch(() => null);
            chartDebug('settings save response', { status: response.status, data });
        } catch (error) {
            console.warn('[2RICH] TV user settings could not be saved', error);
        }
    }, 700);
}

function resetTvUserSettings() {
    tvUserSettings = {};
    return fetch('../api/preferences/chart-settings.php', {
        method: 'DELETE',
        credentials: 'same-origin',
        headers: csrfHeaders()
    }).catch((error) => {
        console.warn('[2RICH] TV user settings reset failed', error);
    });
}

function getPreferredTwoRichTemplateId() {
    const explicit = String(tvUserSettings['2rich.template'] || chartSettings.template || '').trim().toLowerCase();
    if (explicit === 'light' || explicit === 'dark') return explicit;

    const currentTheme = String(tvUserSettings['currenttheme.name'] || '').trim().toLowerCase();
    if (currentTheme.includes('light')) return 'light';
    if (currentTheme.includes('dark')) return 'dark';

    return 'dark';
}

function hasPersistedTvUserSettings() {
    return !!Object.keys(tvUserSettings || {}).length;
}

function rememberTwoRichTemplate(templateId) {
    const normalized = templateId === 'light' ? 'light' : 'dark';
    tvUserSettings['2rich.template'] = normalized;
    tvUserSettings['currenttheme.name'] = TWO_RICH_TEMPLATES[normalized].name;
    chartSettings = { ...chartSettings, template: normalized };
    persistTvUserSettings();
    saveChartSettings({ template: normalized });
}

function applyTwoRichTemplate(templateId, options = {}) {
    const normalized = templateId === 'light' ? 'light' : 'dark';
    const template = TWO_RICH_TEMPLATES[normalized];
    const shouldPersist = options.persist !== false;

    if (!tvWidget) return;

    try {
        if (typeof tvWidget.changeTheme === 'function') {
            tvWidget.changeTheme(template.theme);
        }
    } catch (error) {
        console.warn('[2RICH] Could not change TradingView theme', error);
    }

    try {
        if (typeof tvWidget.applyOverrides === 'function') {
            tvWidget.applyOverrides(template.overrides);
        }
    } catch (error) {
        console.warn('[2RICH] Could not apply widget overrides', error);
    }

    try {
        const chart = typeof tvWidget.activeChart === 'function' ? tvWidget.activeChart() : null;
        if (chart && typeof chart.applyOverrides === 'function') {
            chart.applyOverrides(template.overrides);
        }
    } catch (error) {
        console.warn('[2RICH] Could not apply chart overrides', error);
    }

    const container = document.getElementById('tv_chart_container');
    if (container) {
        container.style.background = template.overrides['paneProperties.background'] || '';
    }

    if (shouldPersist) {
        rememberTwoRichTemplate(normalized);
    }
}

function injectTwoRichTemplateOptions() {
    chartDebug('2RICH template injection disabled', {});
}

function chartSettingsAdapter() {
    return {
        initialSettings: tvUserSettings,
        setValue(key, value) {
            chartDebug('TradingView settings_adapter.setValue', { key, value, keyType: typeof key });
            tvUserSettings[key] = value;

            if (key === 'currenttheme.name') {
                const nextTheme = String(value || '').toLowerCase();
                if (nextTheme.includes('light')) {
                    tvUserSettings['2rich.template'] = 'light';
                    chartSettings = { ...chartSettings, template: 'light' };
                } else if (nextTheme.includes('dark')) {
                    tvUserSettings['2rich.template'] = 'dark';
                    chartSettings = { ...chartSettings, template: 'dark' };
                }
            }

            persistTvUserSettings();
        },
        removeValue(key) {
            chartDebug('TradingView settings_adapter.removeValue', { key });
            delete tvUserSettings[key];
            persistTvUserSettings();
        },
        save() {
            const state = snapshotChartState();
            saveChartState(state);
            return state;
        },
        load() {
            return loadChartState();
        }
    };
}

async function loadWatchlist() {
    try {
        const response = await fetch('../api/preferences/get.php?key=market_data_watchlist', { credentials: 'same-origin' });
        const data = await response.json();
        const list = data.success && data.value ? JSON.parse(data.value) : [];
        window.marketWatchlist = Array.isArray(list) ? list : [];
    } catch (error) { window.marketWatchlist = []; }
    renderWatchlist();
}

function renderWatchlist() {
    const target = document.getElementById('watchlistItems');
    if (!target) return;
    const list = Array.isArray(window.marketWatchlist) ? window.marketWatchlist : [];
    target.innerHTML = list.length ? list.map(symbol => `<button type="button" class="md-watchlist-item" onclick="changeSymbol('${escapeHtml(symbol)}'); toggleWatchlist();"><span aria-hidden="true">★</span>${escapeHtml(symbol)}<span class="md-watchlist-remove" onclick="event.stopPropagation(); removeFromWatchlist('${escapeHtml(symbol)}')">×</span></button>`).join('') : '<span class="md-watchlist-empty">No favourites yet</span>';
}

function toggleWatchlist() {
    const panel = document.getElementById('watchlistPanel');
    const button = document.getElementById('watchlistToggle');
    if (!panel || !button) return;
    const open = panel.hidden; panel.hidden = !open; button.setAttribute('aria-expanded', String(open));
}

async function persistWatchlist() {
    await fetch('../api/preferences/set.php', { method:'POST', credentials:'same-origin', headers: csrfHeaders(), body: JSON.stringify({ key:'market_data_watchlist', value:JSON.stringify(window.marketWatchlist || []) }) });
}

function addCurrentToWatchlist() {
    const symbol = String(currentSymbol || '').trim();
    if (!symbol) return;
    window.marketWatchlist = Array.isArray(window.marketWatchlist) ? window.marketWatchlist : [];
    if (!window.marketWatchlist.includes(symbol)) { window.marketWatchlist.push(symbol); persistWatchlist(); renderWatchlist(); }
}

function removeFromWatchlist(symbol) {
    window.marketWatchlist = (window.marketWatchlist || []).filter(item => item !== symbol);
    persistWatchlist(); renderWatchlist();
}

function getSymbolSelectElement() {
    return document.getElementById('symbolSelect');
}

function escapeHtml(value) {
    return String(value == null ? '' : value)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#39;');
}

function symbolBrokerLabel(s) {
    const name = String(s?.broker_name || '').trim();
    const server = String(s?.broker_server || '').trim();
    return name || server || 'MT5';
}

function renderSymbolOptions(symbols, preferredSymbol) {
    const select = getSymbolSelectElement();
    if (!select) return;

    const rows = Array.isArray(symbols) ? symbols.filter(Boolean) : [];
    const options = rows.map((s) => {
        const value = String(s.mt5_symbol || s.display_symbol || '').trim();
        const label = String(s.display_symbol || s.mt5_symbol || value).trim();
        return value ? { value, label } : null;
    }).filter(Boolean);

    if (!options.length) {
        select.innerHTML = '<option value="">No symbols available</option>';
        select.disabled = true;
        return;
    }

    select.disabled = false;
    select.innerHTML = options
        .map((opt) => `<option value="${escapeHtml(opt.value)}">${escapeHtml(opt.label)}</option>`)
        .join('');

    const preferred = String(preferredSymbol || currentSymbol || '').trim();
    const fallback = options[0].value;
    const matched = options.find((opt) => opt.value === preferred || opt.label === preferred);
    select.value = matched ? matched.value : fallback;
    currentSymbol = select.value || fallback;
}

function syncSymbolSelectValue(symbol) {
    const select = getSymbolSelectElement();
    if (!select) return;
    const normalized = String(symbol || '').trim();
    if (!normalized) return;
    const hasOption = Array.from(select.options).some((opt) => opt.value === normalized);
    if (hasOption) select.value = normalized;
}

let currentInterval= 'D';
let marketSymbols = [];
let marketDataReady = false;
const BROKER_LABEL = 'MT5';

class TwoRichUDFDatafeed {
    constructor(base) {
        this.base = base;
        this.listeners = {};
        this.symbolsPromise = null;
    }

    normalizeSymbol(symbol) {
        const value = String(symbol || '').trim();
        if (!value) return '';
        const colon = value.lastIndexOf(':');
        return colon >= 0 ? value.slice(colon + 1).trim() : value;
    }

    fetchSymbols() {
        if (!this.symbolsPromise) {
            this.symbolsPromise = fetch(this.base + '/symbols.php', { credentials: 'same-origin' })
                .then(r => {
                    if (!r.ok) throw new Error('symbols.php returned HTTP ' + r.status);
                    return r.json();
                })
                .then(x => {
                    const rows = Array.isArray(x && x.symbols) ? x.symbols.filter(Boolean) : [];
                    console.log('[2RICH symbols.php result]', {
                        ok: !!(x && x.ok),
                        count: rows.length,
                        sample: rows.slice(0, 6)
                    });
                    return rows;
                });
        }
        return this.symbolsPromise;
    }

    normalizeResolution(resolution) {
        const value = String(resolution || '').toUpperCase();
        if (value === 'H8' || value === '480') return { tv: '480', api: 'H8' };
        if (value === 'D' || value === '1D' || value === 'D1') return { tv: 'D', api: 'D1' };
        if (value === 'W' || value === '1W' || value === 'W1') return { tv: 'W', api: 'W1' };
        if (value === 'M' || value === '1M' || value === 'MN' || value === 'MN1') return { tv: 'M', api: 'MN1' };
        return { tv: 'D', api: 'D1' };
    }

    onReady(cb) {
        setTimeout(() => cb({
            supported_resolutions: ['480', 'D', 'W', 'M'],
            exchanges: [{ value: '2RICH', name: BROKER_LABEL, desc: BROKER_LABEL + ' Market Feed' }],
            symbols_types: [{ name: 'Forex', value: 'forex' }],
            supports_search: true,
            supports_group_request: false,
            supports_marks: false,
            supports_timescale_marks: false,
            supports_time: true
        }), 0);
    }

    searchSymbols(userInput, exchange, symbolType, onResultReadyCallback) {
        const query = String(userInput || '').toLowerCase();
        const buildRows = (symbols) => (Array.isArray(symbols) ? symbols : [])
            .filter(s => !query || String(s.display_symbol || '').toLowerCase().includes(query) || String(s.mt5_symbol || '').toLowerCase().includes(query))
            .map(s => ({
                symbol: s.display_symbol || s.mt5_symbol,
                full_name: s.display_symbol || s.mt5_symbol,
                description: s.display_symbol || s.mt5_symbol,
                exchange: symbolBrokerLabel(s),
                ticker: s.mt5_symbol || s.display_symbol,
                type: 'forex'
            }));

        if (Array.isArray(this.symbolsCache) && this.symbolsCache.length) {
            onResultReadyCallback(buildRows(this.symbolsCache));
            return;
        }

        this.fetchSymbols()
            .then(symbols => onResultReadyCallback(buildRows(symbols)))
            .catch(() => onResultReadyCallback([]));
    }

    resolveSymbol(symbolName, onResolve, onError) {
        this.fetchSymbols()
            .then(symbols => {
                const normalizedSymbol = this.normalizeSymbol(symbolName);
                const s = symbols.find(v =>
                    v.display_symbol === normalizedSymbol ||
                    v.mt5_symbol === normalizedSymbol ||
                    v.display_symbol === symbolName ||
                    v.mt5_symbol === symbolName
                );
                if (!s) {
                    onError('Symbol is not available in the MT5 market list');
                    return;
                }
                const digits = Number.isFinite(Number(s.digits)) ? Number(s.digits) : 5;
                onResolve({
                    name: s.display_symbol || s.mt5_symbol,
                    full_name: s.display_symbol || s.mt5_symbol,
                    ticker: s.mt5_symbol || s.display_symbol,
                    description: s.display_symbol || s.mt5_symbol,
                    exchange: symbolBrokerLabel(s),
                    listed_exchange: symbolBrokerLabel(s),
                    type: 'forex',
                    session: '24x7',
                    timezone: 'Etc/UTC',
                    format: 'price',
                    minmov: 1,
                    pricescale: Math.pow(10, digits),
                    has_intraday: true,
                    has_daily: true,
                    has_weekly_and_monthly: true,
                    visible_plots_set: 'ohlcv',
                    supported_resolutions: ['480', 'D', 'W', 'M'],
                    volume_precision: 0,
                    data_status: 'streaming'
                });
            })
            .catch(err => onError(err && err.message ? err.message : 'Failed to resolve symbol'));
    }

    getBars(info, resolution, periodParams, onHistoryCallback, onErrorCallback) {
        const mapped = this.normalizeResolution(resolution);
        const symbol = encodeURIComponent(this.normalizeSymbol(info.ticker || info.name || currentSymbol));
        const requestedCountBack = Number(periodParams?.countBack || 500);
        const countBack = Math.min(10000, Math.max(2000, requestedCountBack));
        const fromSec = Number(periodParams?.from || 0);
        const toSec = Number(periodParams?.to || 0);
        let url = this.base + '/candles.php?symbol=' + symbol + '&timeframe=' + mapped.api + '&limit=' + countBack;
        if (fromSec > 0) url += '&from=' + encodeURIComponent(fromSec);
        if (toSec > 0) url += '&to=' + encodeURIComponent(toSec);

        console.log('[2RICH getBars]', { symbol: info.ticker || info.name || currentSymbol, resolution, mapped, url, periodParams });
        fetch(url, { credentials: 'same-origin' })
            .then(r => {
                if (!r.ok) throw new Error('candles.php returned HTTP ' + r.status);
                return r.json();
            })
            .then(x => {
                if (!x || x.ok === false) throw new Error((x && x.message) ? x.message : 'Invalid candles response');
                const fromMs = fromSec * 1000;
                const toMs = toSec * 1000;
                let bars = (x.candles || [])
                    .map(c => ({
                        time: new Date(c.time).getTime(),
                        open: Number(c.open),
                        high: Number(c.high),
                        low: Number(c.low),
                        close: Number(c.close),
                        volume: Number(c.volume || 0)
                    }))
                    .filter(b => Number.isFinite(b.time) && Number.isFinite(b.open) && Number.isFinite(b.high) && Number.isFinite(b.low) && Number.isFinite(b.close))
                    .sort((a, b) => a.time - b.time);

                const serverAlreadyFiltered = fromSec > 0 || toSec > 0;
                if (!serverAlreadyFiltered && (fromMs || toMs)) {
                    bars = bars.filter(b => (!fromMs || b.time >= fromMs) && (!toMs || b.time < toMs));
                }

                if (!bars.length && Array.isArray(x.candles) && x.candles.length) {
                    console.warn('[2RICH getBars empty after range filter]', { fromMs, toMs, sampleFirst: x.candles[0], sampleLast: x.candles[x.candles.length - 1] });
                }

                const meta = { noData: bars.length === 0 };
                console.log('[2RICH getBars result]', { count: bars.length, fromMs, toMs, first: bars[0], last: bars[bars.length - 1], meta });
                onHistoryCallback(bars, meta);
            })
            .catch(err => onErrorCallback(err && err.message ? err.message : 'Failed to load bars'));
    }

    subscribeBars(info, resolution, onRealtimeCallback, subscriberUID, onResetCacheNeededCallback) {
        console.log('[2RICH subscribeBars]', { symbol: info.ticker || info.name, resolution, subscriberUID });

        const symbol = this.normalizeSymbol(info.ticker || info.name || currentSymbol);
        const mapped = this.normalizeResolution(resolution);
        const pollMs = 15000;

        if (this.listeners[subscriberUID] && this.listeners[subscriberUID].timer) {
            clearInterval(this.listeners[subscriberUID].timer);
        }

        const fetchLatestBar = () => {
            const url = `${this.base}/candles.php?symbol=${encodeURIComponent(symbol)}&timeframe=${mapped.api}&limit=2&_=${Date.now()}`;

            fetch(url, { credentials: 'same-origin', cache: 'no-store' })
                .then(r => {
                    if (!r.ok) throw new Error('candles.php returned HTTP ' + r.status);
                    return r.json();
                })
                .then(x => {
                    if (!x || x.ok !== true || !Array.isArray(x.candles) || !x.candles.length) return;

                    const last = x.candles[x.candles.length - 1];
                    const bar = {
                        time: new Date(last.time).getTime(),
                        open: Number(last.open),
                        high: Number(last.high),
                        low: Number(last.low),
                        close: Number(last.close),
                        volume: Number(last.volume || 0)
                    };

                    if (
                        Number.isFinite(bar.time) &&
                        Number.isFinite(bar.open) &&
                        Number.isFinite(bar.high) &&
                        Number.isFinite(bar.low) &&
                        Number.isFinite(bar.close)
                    ) {
                        onRealtimeCallback(bar);
                    }
                })
                .catch(err => {
                    console.warn('[2RICH realtime poll failed]', {
                        symbol,
                        resolution,
                        error: err && err.message ? err.message : err
                    });
                });
        };

        this.listeners[subscriberUID] = {
            symbol,
            resolution: mapped.tv,
            active: true,
            timer: setInterval(fetchLatestBar, pollMs)
        };

        fetchLatestBar();
    }

    unsubscribeBars(id) {
        if (this.listeners[id] && this.listeners[id].timer) clearInterval(this.listeners[id].timer);
        delete this.listeners[id];
    }

    getServerTime(cb) {
        cb(Math.floor(Date.now() / 1000));
    }
}

function buildTwoRichMacdIndicator(PineJS) {
    return {
        name: '2rich MACD',
        metainfo: {
            _metainfoVersion: 51,
            id: '2richMACD@tv-basicstudies-1',
            description: '2rich MACD',
            shortDescription: '2rich MACD',
            isCustomIndicator: true,
            is_price_study: false,
            linkedToSeries: false,
            format: {
                type: 'price',
                precision: 6,
            },
            plots: [
                { id: 'plot_hist', type: 'line' },
                { id: 'plot_hist_colorer', type: 'colorer', target: 'plot_hist', palette: 'histPalette' },
                { id: 'plot_zero', type: 'line' },
            ],
            palettes: {
                histPalette: {
                    colors: {
                        0: { name: 'Positive Rising' },
                        1: { name: 'Positive Falling' },
                        2: { name: 'Negative Rising' },
                        3: { name: 'Negative Falling' },
                    },
                },
            },
            defaults: {
                styles: {
                    plot_hist: {
                        plottype: 5,
                        linewidth: 2,
                        color: '#90BFF9',
                        trackPrice: false,
                        visible: true,
                    },
                    plot_zero: {
                        plottype: 2,
                        linewidth: 1,
                        color: '#808080',
                        trackPrice: false,
                        visible: true,
                    },
                },
                palettes: {
                    histPalette: {
                        colors: {
                            0: { color: 'rgb(144,191,249)', width: 1, style: 0 },
                            1: { color: 'rgb(110,167,240)', width: 1, style: 0 },
                            2: { color: 'rgb(255,255,255)', width: 1, style: 0 },
                            3: { color: 'rgb(207,207,207)', width: 1, style: 0 },
                        },
                    },
                },
                inputs: {},
            },
            styles: {
                plot_hist: {
                    title: 'Histogram',
                    histogramBase: 0,
                },
                plot_zero: {
                    title: 'Zero Line',
                    histogramBase: 0,
                },
            },
            inputs: [],
        },
        constructor: function () {
            this.init = function (context, inputCallback) {
                this._context = context;
                this._input = inputCallback;
                this._histSeries = null;
            };

            this.main = function (context, inputCallback) {
                this._context = context;
                this._input = inputCallback;

                const fastLength = 1000;
                const slowLength = 500;
                const signalLength = 10;

                const high = PineJS.Std.high(this._context);
                const low = PineJS.Std.low(this._context);
                const close = PineJS.Std.close(this._context);
                const src = (high + low + close + close) / 4.0;

                const srcVar = this._context.new_var(src);
                const fastMa = PineJS.Std.ema(srcVar, fastLength, this._context);
                const slowMa = PineJS.Std.ema(srcVar, slowLength, this._context);
                const macd = fastMa - slowMa;

                const macdVar = this._context.new_var(macd);
                const signal = PineJS.Std.ema(macdVar, signalLength, this._context);
                const hist = -(macd - signal);

                if (!this._histSeries) {
                    this._histSeries = this._context.new_var(hist);
                } else {
                    this._histSeries.set(hist);
                }

                const prevHist = this._histSeries.get(1);
                let colorIndex = 0;

                if (isNaN(hist) || isNaN(prevHist)) {
                    colorIndex = 0;
                } else if (hist >= 0) {
                    colorIndex = prevHist < hist ? 0 : 1;
                } else {
                    colorIndex = prevHist < hist ? 2 : 3;
                }

                return [hist, colorIndex, 0];
            };
        },
    };
}

function getTwoRichCustomIndicatorsGetter() {
    return function (PineJS) {
        return Promise.resolve([
            buildTwoRichMacdIndicator(PineJS)
        ]);
    };
}

let sharedDatafeed = null;

wireRichToolbar();

function bootstrapMarketChart() {
    sharedDatafeed = new TwoRichUDFDatafeed('../api/market');
    sharedDatafeed.fetchSymbols()
        .then((symbols) => {
            marketSymbols = Array.isArray(symbols) ? symbols : [];
            renderSymbolOptions(marketSymbols, currentSymbol);
            if (!marketSymbols.length) {
                throw new Error('The MT5 market list returned no enabled symbols');
            }
            if (!currentSymbol) {
                const first = marketSymbols[0];
                currentSymbol = String(first.mt5_symbol || first.display_symbol || '').trim();
            }
            syncSymbolSelectValue(currentSymbol);
            initChart();
        })
        .catch((err) => {
            console.error('[2RICH market bootstrap failed]', err);
            const select = getSymbolSelectElement();
            if (select) {
                select.innerHTML = '<option value="">Market list unavailable</option>';
                select.disabled = true;
            }
        });
}

function mountNativeTimeframeGroup() {
    if (!tvWidget || typeof tvWidget.headerReady !== 'function' || typeof tvWidget.createButton !== 'function') return;
    tvWidget.headerReady().then(() => {
        if (document.getElementById('rich-native-timeframes')) return;
        const group = document.createElement('div');
        group.id = 'rich-native-timeframes';
        group.className = 'rich-toolbar-timeframes rich-native-timeframes';
        [['15','15m'],['60','1H'],['240','4H'],['480','8H'],['D','D'],['W','W'],['M','MN']].forEach(([value,label]) => {
            const btn = document.createElement('button');
            btn.type = 'button';
            btn.dataset.richInterval = value;
            btn.textContent = label;
            btn.style.cssText = 'background:transparent!important;background-color:transparent!important;border:1px solid transparent!important;box-shadow:none!important;';
            btn.addEventListener('click', () => richSetInterval(value));
            group.appendChild(btn);
        });
        const host = tvWidget.createButton();
        host.className = 'rich-native-timeframe-host';
        host.title = 'Timeframes';
        host.style.cssText = 'display:flex;align-items:center;padding:0!important;margin:0!important;border:0!important;background:transparent!important;box-shadow:none!important;';
        host.appendChild(group);
        host.style.setProperty('background', 'transparent', 'important');
        host.style.setProperty('background-color', 'transparent', 'important');
        host.style.setProperty('border', '0', 'important');
        host.style.setProperty('box-shadow', 'none', 'important');
        syncNativeTimeframeGroup();
    }).catch((err) => console.error('[2RICH native timeframe mount failed]', err));
}
function syncNativeTimeframeGroup() {
    document.querySelectorAll('#rich-native-timeframes [data-rich-interval]').forEach(btn => btn.classList.toggle('is-active', String(btn.dataset.richInterval) === String(currentInterval)));
}

function richChartApi() { return tvWidget && typeof tvWidget.activeChart === 'function' ? tvWidget.activeChart() : null; }
function richToolbarStatus(message) { const el=document.getElementById('richToolbarStatus'); if(el) el.textContent=message; }
function richOpenSymbolModal() {
    const chart = richChartApi();
    try {
        if (chart?.executeActionById) chart.executeActionById('chartDialogSearch');
        else richToolbarStatus('Symbol search API unavailable');
    } catch (e) {
        richToolbarStatus('Symbol search unavailable');
        console.error(e);
    }
}
function richSetInterval(interval) { changeInterval(interval); document.querySelectorAll('[data-rich-interval]').forEach(btn=>btn.classList.toggle('is-active',btn.dataset.richInterval===String(interval))); syncNativeTimeframeGroup(); }
function richSetCandles() { const chart=richChartApi(); try { if(chart && typeof chart.setChartType==='function') { chart.setChartType(1); richToolbarStatus('Candles'); } else if(chart && typeof chart.executeActionById==='function') { chart.executeActionById('chartType'); richToolbarStatus('Chart type'); } else richToolbarStatus('Chart type API unavailable'); } catch(e){ richToolbarStatus('Candles unavailable'); console.error(e); } }
function richOpenIndicators() { const chart=richChartApi(); try { if(chart && typeof chart.executeActionById==='function') chart.executeActionById('insertIndicator'); else richToolbarStatus('Indicators API unavailable'); } catch(e){ richToolbarStatus('Indicators unavailable'); console.error(e); } }
function richUndoRedo(action) { const chart=richChartApi(); try { if(chart && typeof chart.executeActionById==='function') chart.executeActionById(action); else richToolbarStatus(action+' API unavailable'); } catch(e){ richToolbarStatus(action+' unavailable'); console.error(e); } }
function richCapture() { const chart=richChartApi(); try { if(chart && typeof chart.takeClientScreenshot==='function') chart.takeClientScreenshot().then((canvas)=>{ const a=document.createElement('a'); a.download='2rich-chart.png'; a.href=canvas.toDataURL('image/png'); a.click(); }); else richToolbarStatus('Capture API unavailable'); } catch(e){ richToolbarStatus('Capture unavailable'); console.error(e); } }
function wireRichToolbar() {
    document.querySelectorAll('[data-rich-interval]').forEach(btn=>btn.addEventListener('click',()=>richSetInterval(btn.dataset.richInterval)));
    document.getElementById('richCandlesBtn')?.addEventListener('click', richSetCandles);
    document.getElementById('richIndicatorsBtn')?.addEventListener('click', richOpenIndicators);
    document.getElementById('richUndoBtn')?.addEventListener('click', ()=>richUndoRedo('undo'));
    document.getElementById('richRedoBtn')?.addEventListener('click', ()=>richUndoRedo('redo'));
    document.getElementById('richSettingsBtn')?.addEventListener('click', ()=>richToolbarStatus('Settings API pending verification'));
    document.getElementById('richFullscreenBtn')?.addEventListener('click', ()=>{ const el=document.getElementById('tv_chart_container'); if(el?.requestFullscreen) el.requestFullscreen(); });
    document.getElementById('richCaptureBtn')?.addEventListener('click', richCapture);
}

function initChart() {
    if (tvWidget) { tvWidget.remove(); tvWidget = null; }
    chartDebug('widget init input', { symbol: currentSymbol, interval: currentInterval, userSettingKeys: Object.keys(tvUserSettings) });
    const preferredTemplateId = getPreferredTwoRichTemplateId();
    const isFirstTimeUser = !hasPersistedTvUserSettings();
    const initialTemplate = isFirstTimeUser ? TWO_RICH_TEMPLATES[preferredTemplateId] : null;

    tvWidget = new TradingView.widget({
        container:       'tv_chart_container',
        locale:          'en',
        library_path:    '../assets/charting_library/',
        datafeed:        sharedDatafeed,
        custom_indicators_getter: getTwoRichCustomIndicatorsGetter(),
        symbol:          currentSymbol,
        interval:        currentInterval,
        fullscreen:      false,
        autosize:        true,
        theme:           initialTemplate ? initialTemplate.theme : DEFAULT_CHART_THEME.theme,
        timezone:        'Europe/London',
        toolbar_bg:      initialTemplate ? initialTemplate.toolbarBg : DEFAULT_CHART_THEME.toolbarBg,
        overrides:       initialTemplate ? initialTemplate.overrides : undefined,
        studies_overrides: initialTemplate ? initialTemplate.studiesOverrides : DEFAULT_CHART_THEME.studiesOverrides,
        disabled_features: ['use_localstorage_for_settings','header_interval_dialog_button','header_resolutions','create_volume_indicator_by_default'],
        enabled_features:  ['items_favoriting', 'saveload_separate_drawings_storage'],
        settings_adapter: chartSettingsAdapter(),
        save_load_adapter: {
            chartsCount: () => Promise.resolve(0),
            getAllCharts: () => Promise.resolve([]),
            removeChart: () => Promise.resolve(),
            saveChart: () => Promise.resolve(1),
            getChartContent: () => Promise.resolve(''),
            saveLineToolsAndGroups: (layoutId, chartId, state) => {
                chartDebug('save_load_adapter saveLineToolsAndGroups', { layoutId, chartId, stateType: typeof state, sourcesCount: state?.sources?.size });
                window._latestTvDrawingState = state;
                const serialized = serializeLineToolsState(state);
                const symbolKey = getChartStateSymbol(currentSymbol);
                return fetch('../api/drawings/set.php', {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: csrfHeaders(),
                    body: JSON.stringify({ symbol: symbolKey, drawings: JSON.stringify(serialized) })
                }).then(() => {});
            },
            loadLineToolsAndGroups: (layoutId, chartId, requestType, requestContext) => {
                chartDebug('save_load_adapter loadLineToolsAndGroups', { layoutId, chartId, requestType });
                const symbolKey = getChartStateSymbol(currentSymbol);
                return fetch(`../api/drawings/get.php?symbol=${encodeURIComponent(symbolKey)}`, { credentials: 'same-origin' })
                    .then(res => res.json())
                    .then(data => {
                        if (data.success && data.drawings) {
                            const parsed = typeof data.drawings === 'string' ? JSON.parse(data.drawings) : data.drawings;
                            return normalizeLineToolsState(parsed);
                        }
                        return null;
                    })
                    .catch(e => {
                        console.warn('[2RICH] Failed to load drawings for TV adapter', e);
                        return null;
                    });
            }
        }
    });

    tvWidget.onChartReady(() => {
        chartDebug('chart ready state', { symbol: currentSymbol, interval: currentInterval, userSettingKeys: Object.keys(tvUserSettings) });
        mountNativeTimeframeGroup();
        richToolbarStatus('Chart ready');
        injectTwoRichTemplateOptions();
        richToolbarStatus('Restoring saved chart state');
        setTimeout(() => { applyChartState(currentSymbol); }, 250);
        if (isFirstTimeUser) {
            applyTwoRichTemplate(preferredTemplateId, { persist: true });
        }
        if (typeof tvWidget.subscribe === 'function') {
            tvWidget.subscribe('onResetChartPreferences', () => {
                resetTvUserSettings().finally(() => {
                    rememberTwoRichTemplate('dark');
                });
            });
        }

        const chart = tvWidget.activeChart();
        if (chart) {
            try {
                if (typeof chart.onSymbolChanged === 'function') {
                    chart.onSymbolChanged().subscribe(null, (symbolInfo) => {
                        chartDebug('TradingView symbol changed', {
                            symbolInfo,
                            isApplyingChartState,
                            isRestoringDrawings
                        });
                        const nextSymbol = String(symbolInfo?.ticker || symbolInfo?.name || '').trim();
                        if (!nextSymbol) return;
                        currentSymbol = nextSymbol;
                        syncSymbolSelectValue(nextSymbol);
                        saveChartSettings({ symbol: nextSymbol });
                        if (isChartPersistenceBlocked()) {
                            chartDebug('TradingView symbol changed skipped save/apply', {
                                nextSymbol,
                                reason: getChartPersistenceBlockReason()
                            });
                            return;
                        }
                        armChartPersistence('symbol-change');
                        armChartPersistence('interval-change');
                        saveChartState(snapshotChartState());
                        setTimeout(() => { applyChartState(nextSymbol); }, 250);
                    });
                }
                if (typeof chart.onIntervalChanged === 'function') {
                    chart.onIntervalChanged().subscribe(null, (interval) => {
                        chartDebug('TradingView interval changed', {
                            interval,
                            isApplyingChartState,
                            isRestoringDrawings
                        });
                        const nextInterval = String(interval || '').trim();
                        if (!nextInterval) return;
                        currentInterval = nextInterval;
                        document.querySelectorAll('.md-interval-btn').forEach(b =>
                            b.classList.toggle('active', b.dataset.interval == nextInterval)
                        );
                        saveChartSettings({ interval: nextInterval });
                        if (isChartPersistenceBlocked()) {
                            chartDebug('TradingView interval changed skipped save', {
                                nextInterval,
                                reason: getChartPersistenceBlockReason()
                            });
                            return;
                        }
                        saveChartState(snapshotChartState());
                    });
                }
                if (typeof tvWidget.subscribe === 'function') {
                    tvWidget.subscribe('onAutoSaveNeeded', () => {
                        const state = typeof tvWidget.symbolInterval === 'function' ? tvWidget.symbolInterval() : null;
                        const symbol = String(state?.symbol || currentSymbol || '').trim();
                        const interval = String(state?.interval || currentInterval || '').trim();
                        chartDebug('TradingView onAutoSaveNeeded', {
                            ...state,
                            symbol,
                            interval,
                            isApplyingChartState,
                            isRestoringDrawings
                        });
                        if (symbol || interval) {
                            if (symbol) currentSymbol = symbol;
                            if (interval) currentInterval = interval;
                            syncSymbolSelectValue(currentSymbol);
                            document.querySelectorAll('.md-interval-btn').forEach(b =>
                                b.classList.toggle('active', b.dataset.interval == currentInterval)
                            );
                            saveChartSettings({ symbol: currentSymbol, interval: currentInterval });
                        }
                        if (!canPersistChartState()) {
                            chartDebug('TradingView onAutoSaveNeeded skipped', {
                                reason: isChartPersistenceBlocked() ? getChartPersistenceBlockReason() : (!hasCompletedInitialChartRestore ? 'initial-restore-incomplete' : 'persistence-not-armed'),
                                symbol: currentSymbol,
                                interval: currentInterval,
                                hasCompletedInitialChartRestore,
                                hasArmedChartPersistence
                            });
                            return;
                        }
                        Promise.resolve(drawingRestorePromise).finally(() => {
                            if (!canPersistChartState()) return;
                            saveChartState(snapshotChartState());
                        });
                    });
                }
            } catch (error) {
                console.warn('[2RICH] Could not attach chart persistence listeners', error);
            }
        }
        if (chart && chartSettings && chartSettings.interval) {
            try { chart.setResolution(String(chartSettings.interval), () => {}, () => {}); } catch (e) {}
        }
        if (!chart || typeof chart.createStudy !== 'function') return;

        try {
            const allStudies = typeof chart.getAllStudies === 'function' ? chart.getAllStudies() : [];
            allStudies.forEach((study) => {
                const name = String(study.name || '').toLowerCase();
                if (name.includes('volume') && typeof chart.removeEntity === 'function') {
                    chart.removeEntity(study.id);
                }
            });
        } catch (error) {
            console.warn('[2RICH] Could not remove default volume study', error);
        }

        chart.createStudy('2rich MACD', false, false);
    });
}

function changeSymbol(symbol) {
    saveChartSettings({ symbol });
    armChartPersistence('changeSymbol');
    saveChartState(snapshotChartState());
    const normalized = sharedDatafeed ? sharedDatafeed.normalizeSymbol(symbol) : String(symbol || '').trim();
    if (!normalized) return;
    currentSymbol = normalized;
    syncSymbolSelectValue(normalized);
    if (tvWidget) tvWidget.onChartReady(() => tvWidget.activeChart().setSymbol(normalized));
}

function changeInterval(interval) {
    saveChartSettings({ interval });
    armChartPersistence('changeInterval');
    saveChartState(snapshotChartState());
    const mapped = new TwoRichUDFDatafeed('../api/market').normalizeResolution(interval);
    currentInterval = mapped.tv;
    document.querySelectorAll('.md-interval-btn').forEach(b =>
        b.classList.toggle('active', b.dataset.interval == currentInterval)
    );
    if (tvWidget) tvWidget.onChartReady(() => tvWidget.activeChart().setResolution(mapped.tv, () => {}, () => {}));
}


// ═══════════════════════════════════════════════════════════════════════════
// TAB SWITCHING
// ═══════════════════════════════════════════════════════════════════════════
function switchTab(btn, paneId) {
    document.querySelectorAll('.md-tab').forEach(t  => t.classList.remove('active'));
    btn.classList.add('active');
    document.querySelectorAll('.md-pane').forEach(p  => p.classList.remove('active'));
    const target = document.getElementById(paneId);
    if (target) target.classList.add('active');
}

function openCalendarFromHash() {
    if (window.location.hash !== '#calendar') return;
    const calendarTab = document.querySelector('.md-tab[onclick*="tab-calendar"]');
    if (calendarTab) switchTab(calendarTab, 'tab-calendar');
}

// ═══════════════════════════════════════════════════════════════════════════
// ECONOMIC CALENDAR
// ═══════════════════════════════════════════════════════════════════════════
let calAllEvents      = [];
let calRefreshTimer   = null;
let calBurstTimer     = null;
let calCountdownTimer = null;
let calSecsLeft       = 60;
let calBurstMode      = false;
let activeImpact      = 'all';
let activeRange       = 'all';
let activeWeek        = 'this_week';

// ── PROMJENA: activeCurrency je sada Set za multi-select ─────────────────
let activeCurrency = new Set(['all']);

const CAL_NORMAL_INTERVAL = 60;
const CAL_BURST_INTERVAL  = 20;
const CAL_BURST_WINDOW    = 5 * 60;

// ── Week switcher (triggered by Range buttons: this_week / next_week) ───────
function switchWeek(newWeek) {
    if (newWeek === activeWeek) return;
    activeWeek = newWeek;
    const container = document.getElementById('economicCalendar');
    if (container) {
        container.style.opacity = '0.4';
        container.style.pointerEvents = 'none';
    }
    clearInterval(calRefreshTimer);
    clearInterval(calBurstTimer);
    calBurstMode = false;
    loadEconomicCalendar(true).then(() => {
        if (container) { container.style.opacity = ''; container.style.pointerEvents = ''; }
        startNormalRefresh();
        setCountdown(CAL_NORMAL_INTERVAL);
    });
}

// ── Filter panel toggle ─────────────────────────────────────────────────────
function toggleCalFilters() {
    const panel  = document.getElementById('calFilters');
    const toggle = document.getElementById('calFilterToggle');
    const open   = panel.classList.toggle('is-open');
    toggle.classList.toggle('is-active', open);
}

// ── Helpers ────────────────────────────────────────────────────────────────
function impactClass(impact) {
    if (!impact) return 'low';
    const i = impact.toLowerCase();
    if (i === 'high')   return 'high';
    if (i === 'medium') return 'medium';
    return 'low';
}

function actualClass(actual, forecast) {
    if (!actual || actual === '-' || !forecast || forecast === '-') return '';
    const parseVal = v => parseFloat(v.replace(/[^0-9.\-]/g, ''));
    const a = parseVal(actual), f = parseVal(forecast);
    if (isNaN(a) || isNaN(f)) return '';
    if (a > f) return 'actual-beat';
    if (a < f) return 'actual-miss';
    return 'actual-inline';
}

function getNYOffsetString() {
    try {
        const fmt   = new Intl.DateTimeFormat('en-US', { timeZone: 'America/New_York', timeZoneName: 'shortOffset' });
        const parts = fmt.formatToParts(new Date());
        const tz    = parts.find(p => p.type === 'timeZoneName');
        const match = tz && tz.value.match(/GMT([+-]\d+)/);
        if (match) {
            const hours = parseInt(match[1], 10);
            const sign  = hours < 0 ? '-' : '+';
            const abs   = Math.abs(hours);
            return sign + String(abs).padStart(2, '0') + '00';
        }
    } catch (e) {}
    return '-0500';
}
const NY_OFFSET = getNYOffsetString();

function eventDateNY(event) {
    try {
        const year    = new Date().getFullYear();
        const timeStr = (event.time || '').replace(/\s*(EST|EDT|ET)\s*/i, '').trim();
        const t       = /\d/.test(timeStr) ? timeStr : '00:00';
        const d       = new Date(`${event.date} ${year} ${t} GMT${NY_OFFSET}`);
        return isNaN(d.getTime()) ? null : d;
    } catch (e) { return null; }
}

function todayNY() { return new Date(new Date().toLocaleDateString('en-US', { timeZone: 'America/New_York' })); }

function isToday(event) {
    const d = eventDateNY(event);
    if (!d) return false;
    const n = todayNY();
    return d.getFullYear() === n.getFullYear() && d.getMonth() === n.getMonth() && d.getDate() === n.getDate();
}

function isTomorrow(event) {
    const d = eventDateNY(event);
    if (!d) return false;
    const n = todayNY();
    n.setDate(n.getDate() + 1);
    return d.getFullYear() === n.getFullYear() && d.getMonth() === n.getMonth() && d.getDate() === n.getDate();
}

function hasImminentEvent() {
    const now = Date.now();
    return calAllEvents.some(ev => {
        if (!ev.actual || ev.actual === '-') {
            const d    = eventDateNY(ev);
            if (!d) return false;
            const diff = (d.getTime() - now) / 1000;
            return diff > -120 && diff < CAL_BURST_WINDOW;
        }
        return false;
    });
}

// ── PROMJENA: applyFilters koristi Set za multi-select valuta ────────────
function applyFilters() {
    let filtered = calAllEvents;

    // Impact — ostaje single-select
    if (activeImpact !== 'all') {
        filtered = filtered.filter(e => e.impact.toLowerCase() === activeImpact.toLowerCase());
    }

    // Range — single-select
    if      (activeRange === 'today')    filtered = filtered.filter(isToday);
    else if (activeRange === 'tomorrow') filtered = filtered.filter(isTomorrow);

    // Currency — MULTI-select: prikazuje event ako mu je valuta u Setu
    if (!activeCurrency.has('all')) {
        filtered = filtered.filter(e => activeCurrency.has(e.currency.toUpperCase()));
    }

    renderCalendar(filtered);
}

// ── Render ───────────────────────────────────────────────────────────────────
function renderCalendar(events) {
    const container = document.getElementById('economicCalendar');
    const countEl   = document.getElementById('calEventCount');
    if (!container) return;
    countEl.textContent = `${events.length} event${events.length !== 1 ? 's' : ''}`;

    if (events.length === 0) {
        container.innerHTML = `<div class="md-calendar-empty">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" width="28" height="28">
                <rect x="3" y="4" width="18" height="18" rx="2"/>
                <line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/>
                <line x1="3" y1="10" x2="21" y2="10"/>
            </svg>
            <span>No events match your filters.</span>
        </div>`;
        return;
    }

    const groups = {};
    events.forEach(event => {
        const key = event.date || 'Unknown';
        if (!groups[key]) groups[key] = [];
        groups[key].push(event);
    });

    let html = '';
    Object.entries(groups).forEach(([date, dayEvents]) => {
        html += `<div class="md-cal-day-group"><div class="md-cal-day-header">${date}</div>`;
        dayEvents.forEach(event => {
            const impCls    = impactClass(event.impact);
            const actual    = (event.actual   && event.actual   !== '-') ? event.actual   : null;
            const previous  = (event.previous && event.previous !== '-') ? event.previous : null;
            const forecast  = (event.forecast && event.forecast !== '-') ? event.forecast : null;
            const actCls    = actual ? actualClass(actual, forecast) : '';
            const hasActual = !!actual;

            html += `<div class="md-calendar-item">
                <div class="md-cal-item-left">
                    <div class="md-calendar-time">${event.time}</div>
                    <span class="md-impact-pill ${impCls}">${event.impact || 'Low'}</span>
                </div>
                <div class="md-cal-item-body">
                    <div class="md-calendar-title-row">
                        <div class="md-calendar-currency">${event.currency || 'N/A'}</div>
                        <div class="md-calendar-event-title">${event.title || 'Economic Event'}</div>
                    </div>
                    <div class="md-calendar-stats">
                        <span class="md-cal-stat ${actCls}">
                            <span class="md-calendar-label">Act</span>
                            ${hasActual ? actual : '<span class="md-cal-pending">Pending</span>'}
                        </span>
                        <span class="md-cal-stat">
                            <span class="md-calendar-label">Fcst</span>${forecast || '-'}
                        </span>
                        <span class="md-cal-stat">
                            <span class="md-calendar-label">Prev</span>${previous || '-'}
                        </span>
                    </div>
                </div>
            </div>`;
        });
        html += '</div>';
    });

    container.innerHTML = html;
}

// ── Fetch ────────────────────────────────────────────────────────────────────
async function loadEconomicCalendar(forceBust = false) {
    const container = document.getElementById('economicCalendar');
    const lastEl    = document.getElementById('calLastUpdated');
    const badge     = document.getElementById('calRefreshBadge');
    if (!container) return;

    if (calAllEvents.length === 0) {
        container.innerHTML = '<div class="md-calendar-loading"><span class="md-cal-spinner"></span> Loading events...</div>';
    }

    try {
        const bust      = `&bust=${Date.now()}`;
        const weekParam = `&week=${activeWeek}`;
        const controller = new AbortController();
        const timeout    = setTimeout(() => controller.abort(), 12000);

        const res = await fetch(
            `https://2rich.capital/wp-admin/admin-ajax.php?action=tworich_economic_calendar${bust}${weekParam}`,
            { credentials: 'include', signal: controller.signal }
        );
        clearTimeout(timeout);

        if (!res.ok) throw new Error(`HTTP ${res.status}`);
        const raw = await res.text();
        if (raw === '0' || raw === '-1' || raw.trim() === '') throw new Error('WP AJAX returned empty/error response');

        const events = JSON.parse(raw);
        if (!Array.isArray(events)) throw new Error('Invalid response format');

        if (events.length > 0) {
            calAllEvents = events;
        } else if (calAllEvents.length === 0) {
            calAllEvents = [];
        }

        const now = new Date();
        if (lastEl) lastEl.textContent = `Updated ${now.toLocaleTimeString('en-GB', { hour: '2-digit', minute: '2-digit', second: '2-digit' })}`;
        if (badge) badge.classList.remove('cal-error');
        applyFilters();
        manageBurstMode();

    } catch (e) {
        console.error('Calendar load failed:', e.message);
        if (badge) badge.classList.add('cal-error');
        const errLabel = document.getElementById('calRefreshLabel');
        if (errLabel) errLabel.textContent = 'Refresh failed — retrying';
        if (calAllEvents.length === 0) {
            container.innerHTML = `<div class="md-calendar-loading">
                Unable to load calendar.
                <button class="md-cal-retry" onclick="refreshCalendar()">Retry</button>
            </div>`;
        } else {
            applyFilters();
        }
    }
}

// ── Burst / normal mode ──────────────────────────────────────────────────────
function manageBurstMode() {
    const imminent = hasImminentEvent();
    if (imminent && !calBurstMode) {
        calBurstMode = true;
        clearInterval(calRefreshTimer);
        clearInterval(calBurstTimer);
        calBurstTimer = setInterval(() => { loadEconomicCalendar(true); setCountdown(CAL_BURST_INTERVAL); }, CAL_BURST_INTERVAL * 1000);
        setCountdown(CAL_BURST_INTERVAL);
    } else if (!imminent && calBurstMode) {
        calBurstMode = false;
        clearInterval(calBurstTimer);
        startNormalRefresh();
    }
}

function startNormalRefresh() {
    clearInterval(calRefreshTimer);
    calRefreshTimer = setInterval(() => { loadEconomicCalendar(true); setCountdown(CAL_NORMAL_INTERVAL); }, CAL_NORMAL_INTERVAL * 1000);
    setCountdown(CAL_NORMAL_INTERVAL);
}

function refreshCalendar() {
    const interval = calBurstMode ? CAL_BURST_INTERVAL : CAL_NORMAL_INTERVAL;
    setCountdown(interval);
    loadEconomicCalendar(true);
}

function setCountdown(seconds) {
    calSecsLeft = seconds;
    updateCountdownLabel();
}

function updateCountdownLabel() {
    const el = document.getElementById('calRefreshLabel');
    if (!el) return;
    const m = String(Math.floor(calSecsLeft / 60)).padStart(1, '0');
    const s = String(calSecsLeft % 60).padStart(2, '0');
    el.textContent = `Refreshing in ${m}:${s}`;
}

function startCountdownTick() {
    clearInterval(calCountdownTimer);
    calCountdownTimer = setInterval(() => {
        calSecsLeft = Math.max(0, calSecsLeft - 1);
        updateCountdownLabel();
        if (calAllEvents.length > 0) manageBurstMode();
    }, 1000);
}

// ── PROMJENA: wireFilterGroup ostaje za Impact i Range (single-select) ───
function wireFilterGroup(groupId, stateSetter) {
    const group = document.getElementById(groupId);
    if (!group) return;
    group.querySelectorAll('.md-cal-filter-btn').forEach(btn => {
        btn.addEventListener('click', () => {
            group.querySelectorAll('.md-cal-filter-btn').forEach(b => b.classList.remove('active'));
            btn.classList.add('active');
            const key = groupId === 'impactFilter' ? 'impact' : 'range';
            const val = btn.dataset[key];
            stateSetter(val);
            if (groupId === 'rangeFilter' && (val === 'this_week' || val === 'next_week')) {
                switchWeek(val);
            } else {
                applyFilters();
            }
        });
    });
}

// ── NOVA FUNKCIJA: wireCurrencyFilter — multi-select logika ─────────────
function wireCurrencyFilter() {
    const group = document.getElementById('currencyFilter');
    if (!group) return;

    group.querySelectorAll('.md-cal-filter-btn').forEach(btn => {
        btn.addEventListener('click', () => {
            const val = btn.dataset.currency;

            if (val === 'all') {
                // Klik na "All" = reset sve natrag na All
                activeCurrency = new Set(['all']);
                group.querySelectorAll('.md-cal-filter-btn').forEach(b => {
                    b.classList.toggle('active', b.dataset.currency === 'all');
                });
            } else {
                // Ukloni "all" iz Seta i s dugmeta
                activeCurrency.delete('all');
                group.querySelector('[data-currency="all"]').classList.remove('active');

                // Toggle kliknute valute
                if (activeCurrency.has(val)) {
                    activeCurrency.delete(val);
                    btn.classList.remove('active');
                } else {
                    activeCurrency.add(val);
                    btn.classList.add('active');
                }

                // Ako je Set prazan (deselektovao si sve), vrati na "All"
                if (activeCurrency.size === 0) {
                    activeCurrency = new Set(['all']);
                    group.querySelector('[data-currency="all"]').classList.add('active');
                }
            }

            applyFilters();
        });
    });
}

// ═══════════════════════════════════════════════════════════════════════════
// BOOT
// ═══════════════════════════════════════════════════════════════════════════
document.addEventListener('DOMContentLoaded', function () {
    // Single-select filteri (Impact i Range)
    wireFilterGroup('impactFilter', v => activeImpact = v);
    wireFilterGroup('rangeFilter',  v => activeRange  = v);

    // Multi-select filter (Currency)
    wireCurrencyFilter();

    loadEconomicCalendar();
    startNormalRefresh();
    startCountdownTick();

    Promise.all([loadChartSettings(), loadWatchlist(), loadTvUserSettings()]).finally(() => {
        if (chartSettings && typeof chartSettings === 'object') {
            if (chartSettings.symbol) currentSymbol = String(chartSettings.symbol).trim();
            if (chartSettings.interval) {
                const mapped = new TwoRichUDFDatafeed('../api/market').normalizeResolution(chartSettings.interval);
                currentInterval = mapped.tv;
                document.querySelectorAll('.md-interval-btn').forEach(b =>
                    b.classList.toggle('active', b.dataset.interval == currentInterval)
                );
            }
        }

        // Load TradingView UDF + init chart
        const udfScript = document.createElement('script');
        udfScript.src   = '../assets/datafeeds/udf/dist/bundle.js';
        udfScript.onload = bootstrapMarketChart;
        document.head.appendChild(udfScript);
    });
});
</script>


</body>
</html>