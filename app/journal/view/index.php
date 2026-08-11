<?php
// Load session configuration
require_once '../../auth/session-config.php';

// Check if user is logged in
if (!isset($_SESSION['user_id']) || !isset($_SESSION['authenticated'])) {
    header('Location: https://app.2rich.capital/login/');
    exit;
}

// Get trade ID and journal ID from URL
$trade_id   = isset($_GET['id'])         ? intval($_GET['id'])         : 0;
$journal_id = isset($_GET['journal_id']) ? intval($_GET['journal_id']) : 0;

if ($trade_id <= 0) {
    header('Location: /journal');
    exit;
}

// Get user data from session
$user_name  = $_SESSION['user_name']  ?? 'Member';
$user_email = $_SESSION['user_email'] ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Trade - 2RICH CAPITAL</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../../assets/css/dashboard.css">
    <link rel="stylesheet" href="../../assets/css/journal.css">
    <link rel="stylesheet" href="../../assets/css/trade-view.css">
</head>
<body>

    <!-- Animated Background -->
    <div class="dashboard-background"></div>

    <!-- Top Navigation -->
    <nav class="top-nav">
        <div class="nav-container">
            <div class="nav-brand">
                <h1>2RICH CAPITAL</h1>
                <span class="nav-tagline">INSTITUTIONAL GRADE TRADING</span>
            </div>
            <div class="nav-right">
                <span class="user-email"><?php echo htmlspecialchars($user_email); ?></span>
                <a href="../../auth/logout.php" class="logout-btn">LOGOUT</a>
            </div>
        </div>
    </nav>

    <!-- Main Container -->
    <div class="dashboard-container">

        <!-- Sidebar Navigation -->
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
                <li class="menu-item active">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                        <polyline points="14 2 14 8 20 8"></polyline>
                        <line x1="16" y1="13" x2="8" y2="13"></line>
                        <line x1="16" y1="17" x2="8" y2="17"></line>
                        <polyline points="10 9 9 9 8 9"></polyline>
                    </svg>
                    <span>Trading Journal</span>
                </li>
                <li class="menu-item">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <line x1="12" y1="1" x2="12" y2="23"></line>
                        <path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"></path>
                    </svg>
                    <span>Trading Floor</span>
                </li>
                <li class="menu-item">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <rect x="2" y="3" width="20" height="14" rx="2" ry="2"></rect>
                        <line x1="8" y1="21" x2="16" y2="21"></line>
                        <line x1="12" y1="17" x2="12" y2="21"></line>
                    </svg>
                    <span>Market Data</span>
                </li>
                <li class="menu-item">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                        <circle cx="12" cy="7" r="4"></circle>
                    </svg>
                    <span>Account</span>
                </li>
            </ul>
        </aside>

        <!-- Main Content Area -->
        <main class="main-content">

            <!-- Header Section -->
            <div class="view-header">
                <div class="view-header-left">
                    <button class="back-btn" onclick="window.location.href='/journal<?php echo $journal_id > 0 ? '?journal_id=' . $journal_id : ''; ?>'">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <line x1="19" y1="12" x2="5" y2="12"></line>
                            <polyline points="12 19 5 12 12 5"></polyline>
                        </svg>
                        Back to Journal
                    </button>
                    <div class="view-title-block">
                        <h2 class="page-title">Trade Details</h2>
                        <p class="page-subtitle">Trade #<span id="tradeNumber"><?php echo $trade_id; ?></span></p>
                    </div>
                </div>
                <button class="btn-primary" onclick="window.location.href='/journal/edit?id=<?php echo $trade_id; ?>&journal_id=<?php echo $journal_id; ?>'">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path>
                        <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path>
                    </svg>
                    Edit Trade
                </button>
            </div>

            <!-- Loading State -->
            <div id="loadingState" class="loading-container">
                <div class="loading-spinner"></div>
                <p>Loading trade details...</p>
            </div>

            <!-- Trade Details Container -->
            <div id="tradeDetails" style="display: none;">

                <!-- Quick Stats -->
                <div class="trade-stats-grid">
                    <div class="trade-stat-card">
                        <div class="stat-label">Entry Date</div>
                        <div class="stat-value" id="entryDate">-</div>
                    </div>
                    <div class="trade-stat-card">
                        <div class="stat-label">Symbol</div>
                        <div class="stat-value" id="symbol">-</div>
                    </div>
                    <div class="trade-stat-card">
                        <div class="stat-label">Direction</div>
                        <div class="stat-value" id="direction">-</div>
                    </div>
                    <div class="trade-stat-card">
                        <div class="stat-label">Outcome</div>
                        <div class="stat-value" id="outcome">-</div>
                    </div>
                </div>

                <!-- Trade Information -->
                <div class="detail-section">
                    <h3 class="detail-title">Trade Information</h3>
                    <div class="detail-grid">
                        <div class="detail-item">
                            <span class="detail-label">Session</span>
                            <span class="detail-value" id="session">-</span>
                        </div>
                        <div class="detail-item">
                            <span class="detail-label">Strategy Type</span>
                            <span class="detail-value" id="strategyType">-</span>
                        </div>
                        <div class="detail-item">
                            <span class="detail-label">Entry Price</span>
                            <span class="detail-value" id="entryPrice">-</span>
                        </div>
                        <div class="detail-item">
                            <span class="detail-label">Exit Price</span>
                            <span class="detail-value" id="exitPrice">-</span>
                        </div>
                        <div class="detail-item">
                            <span class="detail-label">Exit Date</span>
                            <span class="detail-value" id="exitDate">-</span>
                        </div>
                        <div class="detail-item">
                            <span class="detail-label">P&amp;L %</span>
                            <span class="detail-value" id="profitLoss">$-</span>
							 <span class="detail-value" id="profitLossPct">-%</span>
                        </div>
                    </div>
                </div>

                <!-- Price Movement -->
                <div class="detail-section">
                    <h3 class="detail-title">Price Movement</h3>
                    <div class="detail-grid">
                        <div class="detail-item">
                            <span class="detail-label">Lowest from Entry</span>
                            <span class="detail-value" id="lowestFromEntry">-</span>
                        </div>
                        <div class="detail-item">
                            <span class="detail-label">Highest from Entry</span>
                            <span class="detail-value" id="highestFromEntry">-</span>
                        </div>
                        <div class="detail-item">
                            <span class="detail-label">Trade Duration (Bars)</span>
                            <span class="detail-value" id="tradeDuration">-</span>
                        </div>
                    </div>
                </div>

                <!-- Imbalance Analysis -->
                <div class="detail-section">
                    <h3 class="detail-title">Imbalance Analysis</h3>
                    <div class="detail-grid">
                        <div class="detail-item">
                            <span class="detail-label">Imbalance Size</span>
                            <span class="detail-value" id="imbalanceSize">-</span>
                        </div>
                        <div class="detail-item">
                            <span class="detail-label">Fill Time (Bars)</span>
                            <span class="detail-value" id="fillTime">-</span>
                        </div>
                        <div class="detail-item">
                            <span class="detail-label">Nearby Imbalance</span>
                            <span class="detail-value" id="nearbyImbalance">-</span>
                        </div>
                    </div>
                </div>

                <!-- Histogram Analysis -->
                <div class="detail-section">
                    <h3 class="detail-title">Histogram Analysis</h3>
                    <div class="detail-grid">
                        <div class="detail-item">
                            <span class="detail-label">W Histogram</span>
                            <span class="detail-value" id="wHistogram">-</span>
                        </div>
                        <div class="detail-item">
                            <span class="detail-label">M Histogram</span>
                            <span class="detail-value" id="mHistogram">-</span>
                        </div>
                        <div class="detail-item">
                            <span class="detail-label">All 8H Bars Bullish</span>
                            <span class="detail-value" id="all8hBars">-</span>
                        </div>
                    </div>
                </div>

                <!-- VIX Conditions -->
                <div class="detail-section">
                    <h3 class="detail-title">VIX Conditions</h3>
                    <div class="detail-grid">
                        <div class="detail-item">
                            <span class="detail-label">M VIX Open</span>
                            <span class="detail-value" id="mVixOpen">-</span>
                        </div>
                        <div class="detail-item">
                            <span class="detail-label">W VIX Open</span>
                            <span class="detail-value" id="wVixOpen">-</span>
                        </div>
                        <div class="detail-item">
                            <span class="detail-label">D VIX Open</span>
                            <span class="detail-value" id="dVixOpen">-</span>
                        </div>
                        <div class="detail-item">
                            <span class="detail-label">VIX at Entry</span>
                            <span class="detail-value" id="vixMoment">-</span>
                        </div>
                    </div>
                </div>

                <!-- Risk Management -->
                <div class="detail-section">
                    <h3 class="detail-title">Risk Management</h3>
                    <div class="detail-grid">
                        <div class="detail-item">
                            <span class="detail-label">Stop Level</span>
                            <span class="detail-value" id="stopLevel">-</span>
                        </div>
                        <div class="detail-item">
                            <span class="detail-label">Stop Triggered</span>
                            <span class="detail-value" id="stopTriggered">-</span>
                        </div>
                    </div>
                </div>

                <!-- Notes -->
                <div class="detail-section" id="notesSection" style="display: none;">
                    <h3 class="detail-title">Notes</h3>
                    <div class="notes-content" id="tradeNotes"></div>
                </div>

            </div>

        </main>
    </div>

    <script>
        const tradeId   = <?php echo $trade_id; ?>;
        const journalId = <?php echo $journal_id > 0 ? $journal_id : 'null'; ?>;

        async function loadTradeDetails() {
            try {
                // Always send journal_id if we have it so get.php ownership check passes
                const url = journalId
                    ? `../../api/trades/get.php?id=${tradeId}&journal_id=${journalId}`
                    : `../../api/trades/get.php?id=${tradeId}`;

                const response = await fetch(url);
                const data     = await response.json();

                if (data.success && data.trade) {
                    const trade = data.trade;

                    // Quick stats
                    document.getElementById('entryDate').textContent = formatDate(trade.entry_date);
                    document.getElementById('symbol').textContent    = trade.symbol || '-';
                    document.getElementById('direction').innerHTML   = trade.direction
                        ? `<span class="badge badge-${trade.direction.toLowerCase()}">${trade.direction}</span>`
                        : '-';
                    document.getElementById('outcome').innerHTML     = trade.outcome
                        ? `<span class="badge badge-${trade.outcome.toLowerCase()}">${trade.outcome}</span>`
                        : '-';

                    // Trade info
                    document.getElementById('session').textContent      = trade.session      || '-';
                    document.getElementById('strategyType').textContent = trade.strategy_type || '-';
                    document.getElementById('entryPrice').textContent   = trade.entry_price
                        ? parseFloat(trade.entry_price).toFixed(5) : '-';
                    document.getElementById('exitPrice').textContent    = trade.exit_price
                        ? parseFloat(trade.exit_price).toFixed(5)  : '-';
                    document.getElementById('exitDate').textContent     = trade.exit_date
                        ? formatDate(trade.exit_date) : '-';

                    // P&L ($)
                    const plAmount        = trade.profit_loss;
                    const plAmountElement = document.getElementById('profitLoss');
                    if (plAmount !== null && plAmount !== '' && plAmount !== undefined) {
                        const plAmountNum = parseFloat(plAmount);
                        plAmountElement.textContent = (plAmountNum >= 0 ? '+' : '') + plAmountNum.toFixed(2) + ' $';
                        plAmountElement.style.color = plAmountNum >= 0 ? '#22c55e' : '#ef4444';
                    } else {
                        plAmountElement.textContent = '-';
                    }

                    // P&L (%)
                    const plPct        = trade.profit_loss_pct;
                    const plPctElement = document.getElementById('profitLossPct');
                    if (plPct !== null && plPct !== '' && plPct !== undefined) {
                        const plPctNum = parseFloat(plPct);
                        plPctElement.textContent = (plPctNum >= 0 ? '+' : '') + plPctNum.toFixed(2) + '%';
                        plPctElement.style.color = plPctNum >= 0 ? '#22c55e' : '#ef4444';
                    } else {
                        plPctElement.textContent = '-';
                    }

                    // Price movement
                    document.getElementById('lowestFromEntry').textContent  = trade.lowest_price_from_entry_pct
                        ? parseFloat(trade.lowest_price_from_entry_pct).toFixed(2)  + '%' : '-';
                    document.getElementById('highestFromEntry').textContent = trade.highest_price_from_entry_pct
                        ? parseFloat(trade.highest_price_from_entry_pct).toFixed(2) + '%' : '-';
                    document.getElementById('tradeDuration').textContent    = trade.trade_time_bars || '-';

                    // Imbalance
                    document.getElementById('imbalanceSize').textContent   = trade.imbalance_size_pct
                        ? parseFloat(trade.imbalance_size_pct).toFixed(2) + '%' : '-';
                    document.getElementById('fillTime').textContent        = trade.fill_time_bars   || '-';
                    document.getElementById('nearbyImbalance').textContent = trade.nearby_imbalance == 1 ? 'Yes' : 'No';

                    // Histogram
                    document.getElementById('wHistogram').textContent  = trade.w_histogram        || '-';
                    document.getElementById('mHistogram').textContent  = trade.m_histogram        || '-';
                    document.getElementById('all8hBars').textContent   = trade.all_8h_bars_bullish == 1 ? 'Yes' : 'No';

                    // VIX
                    document.getElementById('mVixOpen').textContent  = trade.m_vix_open  || '-';
                    document.getElementById('wVixOpen').textContent  = trade.w_vix_open  || '-';
                    document.getElementById('dVixOpen').textContent  = trade.d_vix_open  || '-';
                    document.getElementById('vixMoment').textContent = trade.vix_moment  || '-';

                    // Risk
                    document.getElementById('stopLevel').textContent     = trade.stop_percentage
                        ? parseFloat(trade.stop_percentage).toFixed(2) + '%' : '-';
                    document.getElementById('stopTriggered').textContent = trade.stop_triggered == 1 ? 'Yes' : 'No';

                    // Notes
                    if (trade.note) {
                        document.getElementById('notesSection').style.display = 'block';
                        document.getElementById('tradeNotes').textContent      = trade.note;
                    }

                    document.getElementById('loadingState').style.display  = 'none';
                    document.getElementById('tradeDetails').style.display  = 'block';

                } else {
                    alert(data.message || 'Trade not found or access denied');
                    window.location.href = journalId ? `/journal?journal_id=${journalId}` : '/journal';
                }
            } catch (error) {
                console.error('Error loading trade:', error);
                alert('Error loading trade details');
                window.location.href = journalId ? `/journal?journal_id=${journalId}` : '/journal';
            }
        }

        function formatDate(dateString) {
            if (!dateString) return '-';
            const date = new Date(dateString);
            if (isNaN(date.getTime())) return '-';
            return date.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
        }

        document.addEventListener('DOMContentLoaded', loadTradeDetails);
    </script>
</body>
</html>
