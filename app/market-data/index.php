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
    <title>Market Data - 2RICH CAPITAL</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/dashboard.css">
    <link rel="stylesheet" href="../assets/css/market-data.css">
    <!-- TradingView Charting Library -->
    <script src="../assets/charting_library/charting_library.standalone.js"></script>
    <style>
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

            <!-- ── Feed controls (symbol + timeframe + live) live here ── -->
            <div class="md-feed-controls">
                <!-- Symbol selector -->
                <div class="md-symbol-select-wrap">
                    <select id="symbolSelect" class="md-symbol-select" onchange="changeSymbol(this.value)">
                        <option value="FX:EURUSD">EURUSD</option>
                        <option value="FX:GBPUSD">GBPUSD</option>
                        <option value="FX:USDJPY">USDJPY</option>
                        <option value="FX:AUDUSD">AUDUSD</option>
                        <option value="FX:USDCAD">USDCAD</option>
                        <option value="FX:USDCHF">USDCHF</option>
                        <option value="FX:NZDUSD">NZDUSD</option>
                        <option value="TVC:GOLD">Gold (XAUUSD)</option>
                        <option value="TVC:SILVER">Silver (XAGUSD)</option>
                        <option value="OANDA:WTIUSD">WTI Crude Oil</option>
                        <option value="NASDAQ:NDX">NAS100</option>
                        <option value="DJ:DJI">US30</option>
                        <option value="SP:SPX">SPX500</option>
                    </select>
                </div>

                <!-- Interval selector -->
                <div class="md-interval-wrap">
                    <button class="md-interval-btn" data-interval="1"   onclick="changeInterval(1)">1m</button>
                    <button class="md-interval-btn" data-interval="5"   onclick="changeInterval(5)">5m</button>
                    <button class="md-interval-btn active" data-interval="15"  onclick="changeInterval(15)">15m</button>
                    <button class="md-interval-btn" data-interval="60"  onclick="changeInterval(60)">1H</button>
                    <button class="md-interval-btn" data-interval="240" onclick="changeInterval(240)">4H</button>
                    <button class="md-interval-btn" data-interval="D"   onclick="changeInterval('D')">D</button>
                </div>

                <!-- Live Feed badge -->
                <div class="md-live-badge">
                    <div class="md-live-dot"></div>
                    Live Feed
                </div>
            </div>

            <!-- Chart container -->
            <div class="md-chart-wrap">
                <div id="tv_chart_container"></div>
            </div>

            <!-- Feature cards below chart -->
            <div class="md-features">
                <div class="md-feature-card">
                    <div class="md-feature-label">Forex</div>
                    <div class="md-feature-title">Major &amp; Minor Pairs</div>
                    <div class="md-feature-desc">Live bid/ask spreads, pip movement, and session volume for all major Forex pairs.</div>
                </div>
                <div class="md-feature-card">
                    <div class="md-feature-label">Commodities</div>
                    <div class="md-feature-title">Gold, Silver &amp; Oil</div>
                    <div class="md-feature-desc">Spot prices and futures data for XAUUSD, XAGUSD, WTI Crude, and Brent.</div>
                </div>
                <div class="md-feature-card">
                    <div class="md-feature-label">Indices</div>
                    <div class="md-feature-title">Global Stock Indices</div>
                    <div class="md-feature-desc">Real-time data for NAS100, US30, SPX500, DAX40, and UK100.</div>
                </div>
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
let currentSymbol  = 'FX:EURUSD';
let currentInterval= 15;

function initChart() {
    if (tvWidget) { tvWidget.remove(); tvWidget = null; }
    tvWidget = new TradingView.widget({
        container:       'tv_chart_container',
        locale:          'en',
        library_path:    '../assets/charting_library/',
        datafeed:        new Datafeeds.UDFCompatibleDatafeed('https://demo_feed.tradingview.com'),
        symbol:          currentSymbol,
        interval:        currentInterval,
        fullscreen:      false,
        autosize:        true,
        theme:           'dark',
        timezone:        'Europe/London',
        toolbar_bg:      '#0d0d0d',
        overrides: {
            'paneProperties.background':                '#0d0d0d',
            'paneProperties.backgroundType':            'solid',
            'paneProperties.vertGridProperties.color':  '#111',
            'paneProperties.horzGridProperties.color':  '#111',
            'scalesProperties.textColor':               '#555',
            'scalesProperties.lineColor':               '#1a1a1a',
            'mainSeriesProperties.candleStyle.upColor':          '#22c55e',
            'mainSeriesProperties.candleStyle.downColor':        '#ef4444',
            'mainSeriesProperties.candleStyle.borderUpColor':    '#22c55e',
            'mainSeriesProperties.candleStyle.borderDownColor':  '#ef4444',
            'mainSeriesProperties.candleStyle.wickUpColor':      '#22c55e',
            'mainSeriesProperties.candleStyle.wickDownColor':    '#ef4444',
        },
        disabled_features: ['use_localstorage_for_settings','header_symbol_search','header_interval_dialog_button'],
        enabled_features:  ['study_templates','hide_left_toolbar_by_default'],
    });
}

function changeSymbol(symbol) {
    currentSymbol = symbol;
    if (tvWidget) tvWidget.onChartReady(() => tvWidget.activeChart().setSymbol(symbol));
}

function changeInterval(interval) {
    currentInterval = interval;
    document.querySelectorAll('.md-interval-btn').forEach(b =>
        b.classList.toggle('active', b.dataset.interval == interval)
    );
    if (tvWidget) tvWidget.onChartReady(() => tvWidget.activeChart().setResolution(interval));
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

    // Load TradingView UDF + init chart
    const udfScript = document.createElement('script');
    udfScript.src   = '../assets/datafeeds/udf/dist/bundle.js';
    udfScript.onload = initChart;
    document.head.appendChild(udfScript);
});
</script>
</body>
</html>