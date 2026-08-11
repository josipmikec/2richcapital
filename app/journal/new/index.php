<?php
require_once '../../auth/session-config.php';

if (!isset($_SESSION['user_id']) || !isset($_SESSION['authenticated'])) {
    header('Location: https://app.2rich.capital/login/');
    exit;
}

$user_name  = $_SESSION['user_name']  ?? 'Member';
$user_email = $_SESSION['user_email'] ?? '';

$journal_id = isset($_GET['journal_id']) ? intval($_GET['journal_id']) : 0;

$back_url = $journal_id > 0 ? '/journal?journal_id=' . $journal_id : '/journal';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Log New Trade - 2RICH CAPITAL</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../../assets/css/dashboard.css">
    <link rel="stylesheet" href="../../assets/css/journal.css">
    <link rel="stylesheet" href="../../assets/css/form.css">
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
                <a href="../../auth/logout.php" class="logout-btn">LOGOUT</a>
            </div>
        </div>
    </nav>

    <div class="dashboard-container">
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

        <main class="main-content">
            <div class="form-header">
                <div>
                    <button class="back-btn" onclick="window.location.href='<?php echo htmlspecialchars($back_url); ?>'">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <line x1="19" y1="12" x2="5" y2="12"></line>
                            <polyline points="12 19 5 12 12 5"></polyline>
                        </svg>
                        Back to Journal
                    </button>
                    <h2 class="page-title">Log New Trade</h2>
                    <p class="page-subtitle">Record your trade details for analysis</p>
                </div>
            </div>

            <div id="formMessage" class="form-message" style="display: none;"></div>

            <form id="tradeForm" class="trade-form">
                <input type="hidden" name="journal_id" id="journal_id" value="<?php echo $journal_id; ?>">

                <!-- SECTION 1: TRADE INFORMATION -->
                <div class="form-section">
                    <h3 class="section-title">Trade Information</h3>
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="entry_date">Entry Date *</label>
                            <input type="date" id="entry_date" name="entry_date" required>
                        </div>

                        <div class="form-group">
                            <label for="session">Session *</label>
                            <select id="session" name="session" required>
                                <option value="">Select session</option>
                                <option value="NY">New York</option>
                                <option value="LONDON">London</option>
                                <option value="ASIA">Asia</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="symbol">Symbol *</label>
                            <input type="text" id="symbol" name="symbol" placeholder="e.g., XAUUSD" required>
                        </div>

                        <div class="form-group">
                            <label for="direction">Direction *</label>
                            <select id="direction" name="direction" required>
                                <option value="">Select direction</option>
                                <option value="LONG">Long</option>
                                <option value="SHORT">Short</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="entry_price">Entry Price *</label>
                            <input type="number" id="entry_price" name="entry_price" step="0.00001" placeholder="0.00000" required>
                        </div>

                        <div class="form-group">
                            <label for="strategy_type">Strategy Type</label>
                            <input type="text" id="strategy_type" name="strategy_type" placeholder="e.g. BULL IMBALANCE, BEAR BREAK">
                        </div>
                    </div>
                </div>

                <div class="section-divider"></div>

                <!-- SECTION 2: EXIT & OUTCOME -->
                <div class="form-section">
                    <h3 class="section-title">Exit &amp; Outcome</h3>
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="exit_price">Exit Price</label>
                            <input type="number" id="exit_price" name="exit_price" step="0.00001" placeholder="0.00000">
                        </div>

                        <div class="form-group">
                            <label for="exit_date">Exit Date</label>
                            <input type="date" id="exit_date" name="exit_date">
                        </div>

						<div class="form-group">
                            <label for="profit_loss">P&amp;L ($)</label>
                            <input type="number" id="profit_loss" name="profit_loss" step="0.01" placeholder="Auto-calculated">
                        </div>
						
                        <div class="form-group">
                            <label for="profit_loss_pct" class="label-with-tooltip">
                                P&amp;L (%)
                                <span class="tooltip-wrap">
                                    <svg class="info-icon" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                                        <circle cx="12" cy="12" r="10"></circle>
                                        <line x1="12" y1="8" x2="12" y2="8"></line>
                                        <line x1="12" y1="12" x2="12" y2="16"></line>
                                    </svg>
                                    <span class="tooltip-popup">Input exit price for auto calculation</span>
                                </span>
                            </label>
                            <input type="number" id="profit_loss_pct" name="profit_loss_pct" step="0.01" placeholder="Auto-calculated">
                        </div>

                        <div class="form-group">
                            <label for="outcome" class="label-with-tooltip">
                                Outcome
                                <span class="tooltip-wrap">
                                    <svg class="info-icon" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                                        <circle cx="12" cy="12" r="10"></circle>
                                        <line x1="12" y1="8" x2="12" y2="8"></line>
                                        <line x1="12" y1="12" x2="12" y2="16"></line>
                                    </svg>
                                    <span class="tooltip-popup">Set to Open by default, update once trade is closed</span>
                                </span>
                            </label>
                            <select id="outcome" name="outcome">
                                <option value="OPEN">Open</option>
                                <option value="WIN">Win</option>
                                <option value="LOSS">Loss</option>
                                <option value="BREAKEVEN">Breakeven</option>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- SECTION 3: RISK MANAGEMENT -->
                <div class="form-section">
                    <h3 class="section-title">Risk Management</h3>
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="stop_price" class="label-with-tooltip">
                                Stop Price
                                <span class="tooltip-wrap">
                                    <svg class="info-icon" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                                        <circle cx="12" cy="12" r="10"></circle>
                                        <line x1="12" y1="8" x2="12" y2="8"></line>
                                        <line x1="12" y1="12" x2="12" y2="16"></line>
                                    </svg>
                                    <span class="tooltip-popup">Price level where stop loss is placed. Auto-calculates Stop Distance.</span>
                                </span>
                            </label>
                            <input type="number" id="stop_price" name="stop_price" step="0.00001" placeholder="0.00000">
                        </div>

                        <div class="form-group">
                            <label for="stop_percentage" class="label-with-tooltip">
                                Stop Distance (%)
                                <span class="tooltip-wrap">
                                    <svg class="info-icon" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                                        <circle cx="12" cy="12" r="10"></circle>
                                        <line x1="12" y1="8" x2="12" y2="8"></line>
                                        <line x1="12" y1="12" x2="12" y2="16"></line>
                                    </svg>
                                    <span class="tooltip-popup">Distance from entry to stop in %. Auto-calculates Stop Price.</span>
                                </span>
                            </label>
                            <input type="number" id="stop_percentage" name="stop_percentage" step="0.01" placeholder="Loading...">
                            <small>Adjustable stop percentage</small>
                        </div>

                        <div class="form-group checkbox-group">
                            <label class="checkbox-label">
                                <input type="checkbox" id="stop_triggered" name="stop_triggered">
                                <span>Stop Triggered?</span>
                            </label>
                            <small>Did price hit the stop level?</small>
                        </div>
                    </div>
                </div>

                <!-- SECTION 4: IMBALANCE ANALYSIS -->
                <div class="form-section">
                    <h3 class="section-title">Imbalance Analysis</h3>
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="imbalance_size_pct">Imbalance Size (%)</label>
                            <input type="number" id="imbalance_size_pct" name="imbalance_size_pct" step="0.01" placeholder="0.00">
                        </div>

                        <div class="form-group">
                            <label for="fill_time_bars">Fill Time (Bars)</label>
                            <input type="number" id="fill_time_bars" name="fill_time_bars" placeholder="Number of bars">
                            <small>How many bars to fill after imbalance</small>
                        </div>

                        <div class="form-group checkbox-group">
                            <label class="checkbox-label">
                                <input type="checkbox" id="nearby_imbalance" name="nearby_imbalance">
                                <span>Nearby Imbalance?</span>
                            </label>
                        </div>
                    </div>
                </div>

                <!-- SECTION 5: HISTOGRAM ANALYSIS -->
                <div class="form-section">
                    <h3 class="section-title">Histogram Analysis</h3>
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="w_histogram">W Histogram</label>
                            <select id="w_histogram" name="w_histogram">
                                <option value="">Select</option>
                                <option value="BLUE">Blue</option>
                                <option value="DARK BLUE">Dark Blue</option>
                                <option value="WHITE">White</option>
                                <option value="DARK WHITE">Dark White</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="m_histogram">M Histogram</label>
                            <select id="m_histogram" name="m_histogram">
                                <option value="">Select</option>
                                <option value="BLUE">Blue</option>
                                <option value="DARK BLUE">Dark Blue</option>
                                <option value="WHITE">White</option>
                                <option value="DARK WHITE">Dark White</option>
                            </select>
                        </div>

                        <div class="form-group checkbox-group">
                            <label class="checkbox-label">
                                <input type="checkbox" id="all_8h_bars_bullish" name="all_8h_bars_bullish">
                                <span>All 8H Bars Bullish?</span>
                            </label>
                        </div>
                    </div>
                </div>

                <!-- SECTION 6: VIX CONDITIONS -->
                <div class="form-section">
                    <h3 class="section-title">VIX Conditions</h3>
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="m_vix_open">M VIX Open</label>
                            <input type="number" id="m_vix_open" name="m_vix_open" step="0.01" placeholder="0.00">
                        </div>

                        <div class="form-group">
                            <label for="w_vix_open">W VIX Open</label>
                            <input type="number" id="w_vix_open" name="w_vix_open" step="0.01" placeholder="0.00">
                        </div>

                        <div class="form-group">
                            <label for="d_vix_open">D VIX Open</label>
                            <input type="number" id="d_vix_open" name="d_vix_open" step="0.01" placeholder="0.00">
                        </div>

                        <div class="form-group">
                            <label for="vix_moment">VIX at Entry Moment</label>
                            <input type="number" id="vix_moment" name="vix_moment" step="0.01" placeholder="0.00">
                            <small>VIX price at exact trade entry</small>
                        </div>
                    </div>
                </div>

                <!-- SECTION 7: PRICE MOVEMENT -->
                <div class="form-section">
                    <h3 class="section-title">Price Movement</h3>
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="lowest_price_from_entry_pct">Lowest from Entry (%)</label>
                            <input type="number" id="lowest_price_from_entry_pct" name="lowest_price_from_entry_pct" step="0.01" placeholder="0.00">
                        </div>

                        <div class="form-group">
                            <label for="highest_price_from_entry_pct">Highest from Entry (%)</label>
                            <input type="number" id="highest_price_from_entry_pct" name="highest_price_from_entry_pct" step="0.01" placeholder="0.00">
                        </div>

                        <div class="form-group">
                            <label for="trade_time_bars">Trade Duration (Bars)</label>
                            <input type="number" id="trade_time_bars" name="trade_time_bars" placeholder="Number of bars">
                            <small>How long was the trade active</small>
                        </div>
                    </div>
                </div>

                <!-- SECTION 8: CUSTOM COLUMNS (dynamic — shown only if user has custom columns) -->
                <div id="customColumnsContainer" class="form-section" style="display: none;">
                    <h3 class="section-title">Custom Fields</h3>
                    <div id="customColumnsSection" class="form-grid"></div>
                </div>

                <!-- SECTION 9: NOTES -->
                <div class="form-section">
                    <h3 class="section-title">Notes</h3>
                    <div class="form-group">
                        <label for="note">Trade Notes</label>
                        <textarea id="note" name="note" rows="4" placeholder="Add any observations, mistakes, or lessons learned..."></textarea>
                    </div>
                </div>

                <div class="form-actions">
                    <button type="button" class="btn-secondary-form" onclick="window.location.href='<?php echo htmlspecialchars($back_url); ?>'">
                        Cancel
                    </button>
                    <button type="submit" class="btn-primary-form">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <polyline points="20 6 9 17 4 12"></polyline>
                        </svg>
                        Save Trade
                    </button>
                </div>
            </form>
        </main>
    </div>

    <script src="../../assets/js/trade-form.js"></script>
</body>
</html>
