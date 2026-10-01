<?php
require_once '../auth/session-config.php';
require_once '../auth/feature-flags.php';

if (!isset($_SESSION['user_id']) || !isset($_SESSION['authenticated'])) {
    header('Location: https://app.2rich.capital/login/');
    exit;
}

rich_feature_guard('journal', 'Trading Journal');

$user_name  = $_SESSION['user_name']  ?? 'Member';
$user_email = $_SESSION['user_email'] ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Trading Journal - 2RICH CAPITAL</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/dashboard.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="../assets/css/journal.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="../assets/css/column-manager.css">
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
                <div class="tf-topbar-avatar" onclick="openGlobalSettingsModal('journal')" title="Account"><?php echo strtoupper(substr($_SESSION['user_name'] ?? 'M', 0, 1)); ?></div>
                <div style="display:flex; gap:8px;">
                    <button type="button" class="logout-btn" onclick="openGlobalSettingsModal('journal')">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="margin-right:6px;vertical-align:middle;margin-bottom:2px;"><circle cx="12" cy="12" r="3"></circle><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"></path></svg>SETTINGS
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
                <li class="menu-item active" <?php echo !rich_feature_enabled('journal', true, $user_id) ? 'style="opacity: 0.5;"' : ''; ?>>
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

            <div class="journal-header">
                <div>
                    <h2 class="page-title">Trading Journal</h2>
                    <p class="page-subtitle">Track and analyze your trading performance</p>
                </div>

                <div class="header-actions">
                    <div class="journal-switcher">
                        <label for="journalProfileSelect" class="journal-switcher-label">Journal Profile</label>
                        <select id="journalProfileSelect" class="journal-profile-select" onchange="handleJournalChange()">
                            <option value="">Loading journals...</option>
                        </select>
                    </div>

                    <button class="btn-primary" onclick="openNewTradeModal()">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <line x1="12" y1="5" x2="12" y2="19"></line>
                            <line x1="5" y1="12" x2="19" y2="12"></line>
                        </svg>
                        Log New Trade
                    </button>
                </div>
            </div>

            <div class="stats-grid" id="statsGrid">
                <div class="stat-card">
                    <div class="stat-icon">📊</div>
                    <div class="stat-content">
                        <div class="stat-label">Total Trades</div>
                        <div class="stat-value" id="totalTrades">-</div>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon">✅</div>
                    <div class="stat-content">
                        <div class="stat-label">Win Rate</div>
                        <div class="stat-value" id="winRate">-</div>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon">📈</div>
                    <div class="stat-content">
                        <div class="stat-label">Avg P&L</div>
                        <div class="stat-value" id="avgPL">-</div>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon">🎯</div>
                    <div class="stat-content">
                        <div class="stat-label">Open Trades</div>
                        <div class="stat-value" id="openTrades">-</div>
                    </div>
                </div>
            </div>

            <div class="table-container">
                <table class="trades-table">
                    <thead>
                        <tr id="tradesTableHead">
                            <th>Loading columns...</th>
                        </tr>
                    </thead>
                    <tbody id="tradesTableBody">
                        <tr>
                            <td colspan="1" class="loading-cell">Loading trades...</td>
                        </tr>
                    </tbody>
                </table>
            </div>

        </main>
    </div>

    <!-- ── New Trade Modal ──────────────────────────────────────────── -->
    <div id="newTradeModal" class="modal-overlay" style="display: none;">
        <div class="method-modal">
            <div class="modal-header">
                <h3 class="modal-title">Choose Entry Method</h3>
                <button class="modal-close" onclick="closeModal('newTradeModal')">&times;</button>
            </div>

            <div class="method-grid">
                <div class="method-card" onclick="closeModal('newTradeModal'); window.location.href='/account/#mt5';">
				    <div class="method-icon">
				        <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
				            <path d="M4 14h4v6H4z"></path>
				            <path d="M10 10h4v10h-4z"></path>
				            <path d="M16 4h4v16h-4z"></path>
				            <path d="M3 20h18"></path>
				        </svg>
				    </div>
				    <h4 class="method-title">MT5 Sync</h4>
				    <p class="method-description">Connect your MT5 account and sync trades automatically from the account settings page</p>
				    <div class="method-badge">Recommended</div>
				</div>

                <div class="method-card" onclick="goToNewTradeForCurrentJournal()">
                    <div class="method-icon">
                        <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                            <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path>
                            <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path>
                        </svg>
                    </div>
                    <h4 class="method-title">Manual Entry</h4>
                    <p class="method-description">Fill out the complete trade form with all your analysis details</p>
                </div>

                <div class="method-card" onclick="closeModal('newTradeModal'); importMT5()">
                    <div class="method-icon">
                        <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                            <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                            <polyline points="14 2 14 8 20 8"></polyline>
                            <line x1="9" y1="15" x2="15" y2="15"></line>
                            <line x1="9" y1="11" x2="15" y2="11"></line>
                        </svg>
                    </div>
                    <h4 class="method-title">Import MT5 CSV / Custom CSV</h4>
                    <p class="method-description">Upload an MT5 export or your own custom CSV file with trade data</p>
                </div>
            </div>
        </div>
    </div>

    <!-- ── MT5 Import Modal ─────────────────────────────────────────── -->
    <div id="mt5Modal" class="modal-overlay" style="display: none;">
        <div class="import-modal">
            <div class="modal-header">
                <h3 class="modal-title">Import MT5 History</h3>
                <button class="modal-close" onclick="closeModal('mt5Modal')">&times;</button>
            </div>

            <div class="import-instructions">
                <h4>How to Export from MT5:</h4>
                <ol>
                    <li>Open MetaTrader 5</li>
                    <li>Go to "Account History" tab</li>
                    <li>Right-click → "Save as Report"</li>
                    <li>Choose "Excel" or "CSV" format</li>
                    <li>Upload the file below</li>
                </ol>
            </div>

            <div class="upload-zone" id="mt5UploadZone" onclick="document.getElementById('mt5FileInput').click()">
                <div class="upload-icon">📊</div>
                <div class="upload-text">Click to upload or drag & drop</div>
                <div class="upload-hint">MT5 CSV or Excel file</div>
            </div>

            <input type="file" id="mt5FileInput" class="file-input" accept=".csv,.xlsx,.xls" onchange="handleMT5File(event)">

            <div id="mt5Preview" style="display: none; margin-bottom: 20px;">
                <p style="color: #aaa; font-size: 13px;">File loaded: <strong id="mt5FileName"></strong></p>
                <p style="color: #F2CA50; font-size: 13px;">Trades found: <strong id="mt5TradeCount">0</strong></p>
            </div>

            <button id="mt5ImportBtn" class="btn-import" disabled onclick="processMT5Import()">
                Import Trades
            </button>
        </div>
    </div>

    <!-- ── Excel Import Modal ──────────────────────────────────────── -->
    <div id="excelModal" class="modal-overlay" style="display: none;">
        <div class="import-modal">
            <div class="modal-header">
                <h3 class="modal-title">Import Excel/CSV</h3>
                <button class="modal-close" onclick="closeModal('excelModal')">&times;</button>
            </div>

            <div class="import-instructions">
                <h4>Required Columns:</h4>
                <ol>
                    <li><strong>entry_date</strong> - Trade date (YYYY-MM-DD)</li>
                    <li><strong>symbol</strong> - e.g., XAUUSD, EURUSD</li>
                    <li><strong>direction</strong> - LONG or SHORT</li>
                    <li><strong>session</strong> - NY, ASIA, or LONDON</li>
                    <li><strong>entry_price</strong> - Entry price</li>
                    <li>Optional: exit_price, profit_loss_pct, outcome, etc.</li>
                </ol>
            </div>

            <div class="upload-zone" id="excelUploadZone" onclick="document.getElementById('excelFileInput').click()">
                <div class="upload-icon">📄</div>
                <div class="upload-text">Click to upload or drag & drop</div>
                <div class="upload-hint">CSV or Excel file with your trade data</div>
            </div>

            <input type="file" id="excelFileInput" class="file-input" accept=".csv,.xlsx,.xls" onchange="handleExcelFile(event)">

            <div id="excelPreview" style="display: none; margin-bottom: 20px;">
                <p style="color: #aaa; font-size: 13px;">File loaded: <strong id="excelFileName"></strong></p>
                <p style="color: #F2CA50; font-size: 13px;">Trades found: <strong id="excelTradeCount">0</strong></p>
            </div>

            <button id="excelImportBtn" class="btn-import" disabled onclick="processExcelImport()">
                Import Trades
            </button>
        </div>
    </div>



    <script src="../assets/js/journal.js"></script>
    <script src="../assets/js/import.js" defer></script>

    <?php require_once __DIR__ . '/../components/general-settings-modal.php'; ?>

</body>
</html>