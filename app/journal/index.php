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
    <link rel="stylesheet" href="../assets/css/dashboard.css">
    <link rel="stylesheet" href="../assets/css/journal.css">
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
                <span class="user-email"><?php echo htmlspecialchars($user_email); ?></span>
                <a href="../auth/logout.php" class="logout-btn">LOGOUT</a>
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

                    <button class="btn-secondary" onclick="openColumnManager()">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <circle cx="12" cy="12" r="3"></circle>
                            <path d="M12 1v6m0 10v6m11-11h-6M7 12H1"></path>
                            <path d="M19.78 4.22l-4.24 4.24M8.46 15.54l-4.24 4.24M19.78 19.78l-4.24-4.24M8.46 8.46L4.22 4.22"></path>
                        </svg>
                        Settings
                    </button>

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

    <!-- ── Settings Modal (Journals / Columns / Preferences) ──────── -->
    <div
        id="columnManagerModal"
        class="modal-overlay"
        style="display: none;"
        role="dialog"
        aria-modal="true"
        aria-labelledby="settingsModalTitle"
    >
        <div class="column-manager-modal settings-modal-shell">
            <div class="column-manager-header">
                <div class="modal-header" style="margin-bottom: 0;">
                    <div>
                        <h3 class="modal-title" id="settingsModalTitle">Settings</h3>
                        <p class="settings-subtitle">Manage journals and customize your journal layout.</p>
                    </div>
                    <button class="modal-close" onclick="closeColumnManager()" aria-label="Close settings">&times;</button>
                </div>
            </div>

            <!-- Tab buttons -->
            <div class="settings-tabs" role="tablist" aria-label="Settings sections">
                <button
                    type="button"
                    class="settings-tab active"
                    data-tab="journals"
                    role="tab"
                    aria-selected="true"
                    aria-controls="settings-panel-journals"
                    id="settings-tab-journals"
                    onclick="showSettingsTab('journals')"
                >
                    Journals
                </button>

                <button
                    type="button"
                    class="settings-tab"
                    data-tab="columns"
                    role="tab"
                    aria-selected="false"
                    aria-controls="settings-panel-columns"
                    id="settings-tab-columns"
                    onclick="showSettingsTab('columns')"
                >
                    Columns
                </button>

                <button
                    type="button"
                    class="settings-tab"
                    data-tab="preferences"
                    role="tab"
                    aria-selected="false"
                    aria-controls="settings-panel-preferences"
                    id="settings-tab-preferences"
                    onclick="showSettingsTab('preferences')"
                >
                    Preferences
                </button>
            </div>

            <div class="column-manager-body settings-modal-body">

                <!-- Journals Panel -->
                <section
                    id="settings-panel-journals"
                    class="settings-panel"
                    role="tabpanel"
                    aria-labelledby="settings-tab-journals"
                >
                    <div class="column-manager-section">
                        <div class="section-header">
                            <div class="section-icon">📚</div>
                            <h4 class="section-title">Journal Manager</h4>
                        </div>

                        <div class="journal-manager-form">
                            <div class="form-field">
                                <label for="newJournalName">New Journal Name</label>
                                <input type="text" id="newJournalName" placeholder="e.g. Futures Journal">
                            </div>

                            <button type="button" class="btn-create-column" onclick="createJournal()">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <line x1="12" y1="5" x2="12" y2="19"></line>
                                    <line x1="5" y1="12" x2="19" y2="12"></line>
                                </svg>
                                Create Journal
                            </button>
                        </div>

                        <div class="journals-list" id="journalsList">
                            <div class="column-item">
                                <span class="column-name">Loading journals...</span>
                            </div>
                        </div>
                    </div>
                </section>

                <!-- Columns Panel -->
                <section
                    id="settings-panel-columns"
                    class="settings-panel"
                    role="tabpanel"
                    aria-labelledby="settings-tab-columns"
                    style="display: none;"
                >
                    <div class="column-manager-section">
                        <div class="section-header">
                            <div class="section-icon">📋</div>
                            <h4 class="section-title">Visible Columns</h4>
                        </div>

                        <div class="columns-list" id="columnsList">
                            <div class="column-item">
                                <span class="column-name">Loading columns...</span>
                            </div>
                        </div>
                    </div>

                    <div class="column-manager-section">
                        <div class="section-header">
                            <div class="section-icon">➕</div>
                            <h4 class="section-title">Add Custom Column</h4>
                        </div>

                        <div class="add-column-form">
                            <div class="form-field">
                                <label for="customColumnName">Column Name</label>
                                <input type="text" id="customColumnName" placeholder="e.g. Emotion, RR, News Day">
                            </div>

                            <div class="form-field">
                                <label for="customColumnType">Field Type</label>
                                <select id="customColumnType" onchange="handleColumnTypeChange()">
                                    <option value="text">Text</option>
                                    <option value="number">Number</option>
                                    <option value="boolean">Yes / No</option>
                                    <option value="select">Dropdown</option>
                                </select>
                            </div>

                            <div class="form-field" id="selectOptionsWrap" style="display: none;">
                                <label>Dropdown Options</label>
                                <div class="options-input" id="selectOptionsList">
                                    <div class="option-item">
                                        <label for="selectOption1" class="sr-only">Option 1</label>
                                        <input type="text" id="selectOption1" name="select_options[]" placeholder="Option 1">
                                    </div>
                                    <div class="option-item">
                                        <label for="selectOption2" class="sr-only">Option 2</label>
                                        <input type="text" id="selectOption2" name="select_options[]" placeholder="Option 2">
                                    </div>
                                </div>
                                <button type="button" class="add-option-btn" onclick="addSelectOption()">
                                    + Add Option
                                </button>
                            </div>

                            <button type="button" class="btn-create-column" onclick="createCustomColumn()">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <line x1="12" y1="5" x2="12" y2="19"></line>
                                    <line x1="5" y1="12" x2="19" y2="12"></line>
                                </svg>
                                Create Column
                            </button>
                        </div>
                    </div>
                </section>

                <!-- Preferences Panel -->
                <section
                    id="settings-panel-preferences"
                    class="settings-panel"
                    role="tabpanel"
                    aria-labelledby="settings-tab-preferences"
                    style="display: none;"
                >
                    <div class="column-manager-section">
                        <div class="section-header">
                            <div class="section-icon">⚙️</div>
                            <h4 class="section-title">Trading Defaults</h4>
                        </div>

                        <p style="color: var(--text-muted, #aaa); font-size: 13px; margin-bottom: 20px;">
                            These values are pre-filled each time you log a new manual trade.
                        </p>

                        <div class="form-field" style="max-width: 320px;">
                            <label for="pref_default_stop_distance">Default Stop Distance (%)</label>
                            <div style="display: flex; gap: 10px; align-items: center; margin-top: 6px;">
                                <input
                                    type="number"
                                    id="pref_default_stop_distance"
                                    step="0.01"
                                    min="0.01"
                                    max="100"
                                    placeholder="1.00"
                                    style="flex: 1;"
                                >
                                <button
                                    type="button"
                                    id="savePreferencesBtn"
                                    class="btn-create-column"
                                    onclick="savePreferences()"
                                    style="white-space: nowrap;"
                                >
                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <polyline points="20 6 9 17 4 12"></polyline>
                                    </svg>
                                    Save
                                </button>
                            </div>
                            <small style="color: var(--text-muted, #888); margin-top: 6px; display: block;">
                                Applied automatically to Stop Distance (%) on the new trade form.
                            </small>
                        </div>

                        <div id="prefMessage" class="form-message" style="display: none; margin-top: 16px; max-width: 320px;"></div>
                    </div>
                </section>

            </div>

            <div class="column-manager-footer">
                <button class="btn-reset" type="button" onclick="closeColumnManager()">Close</button>
                <button class="btn-save-columns" type="button" onclick="saveColumnVisibility()">Save Columns</button>
            </div>
        </div>
    </div>

    <script src="../assets/js/journal.js"></script>
    <script src="../assets/js/column-manager.js"></script>
    <script src="../assets/js/import.js" defer></script>

    <!-- ── Preferences JS (inline, no extra file needed) ───────────── -->
    <script>
    async function loadPreferences() {
        try {
            const res  = await fetch('/api/preferences/get.php', { credentials: 'include' });
            const data = await res.json();
            if (data.success && data.preferences) {
                const stopInput = document.getElementById('pref_default_stop_distance');
                if (stopInput && data.preferences.default_stop_distance) {
                    stopInput.value = parseFloat(data.preferences.default_stop_distance).toFixed(2);
                }
            }
        } catch (e) {
            console.warn('Could not load preferences:', e);
        }
    }

    async function savePreferences() {
        const stopInput = document.getElementById('pref_default_stop_distance');
        const msgDiv    = document.getElementById('prefMessage');
        const btn       = document.getElementById('savePreferencesBtn');
        const value     = parseFloat(stopInput.value);

        if (!value || value <= 0) {
            msgDiv.textContent = 'Please enter a valid stop distance greater than 0.';
            msgDiv.className = 'form-message error';
            msgDiv.style.display = 'block';
            return;
        }

        btn.disabled = true;
        btn.innerHTML = 'Saving...';

        try {
            const csrfResp = await fetch('/api/csrf-token.php', { credentials: 'include' });
            const csrfData = await csrfResp.json();
            const res = await fetch('/api/preferences/set.php', {
                method: 'POST',
                credentials: 'include',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': csrfData.token
                },
                body: JSON.stringify({ key: 'default_stop_distance', value: value.toFixed(2) })
            });
            const result = await res.json();

            if (result.success) {
                msgDiv.textContent = '✓ Saved! This will apply next time you open the trade form.';
                msgDiv.className = 'form-message success';
            } else {
                msgDiv.textContent = result.message || 'Failed to save preferences.';
                msgDiv.className = 'form-message error';
            }
            msgDiv.style.display = 'block';
            setTimeout(() => { msgDiv.style.display = 'none'; }, 3500);
        } catch (e) {
            msgDiv.textContent = 'Connection error. Please try again.';
            msgDiv.className = 'form-message error';
            msgDiv.style.display = 'block';
        }

        btn.disabled = false;
        btn.innerHTML = '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"></polyline></svg> Save';
    }


    // Load preferences when page loads (populates Preferences tab input)
    loadPreferences();
    </script>

</body>
</html>