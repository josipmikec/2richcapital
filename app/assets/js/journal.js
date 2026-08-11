// journal.js — CSRF-aware

let activeColumns     = [];
let journalTrades     = [];
let selectedJournalId = null;
let availableJournals = [];

let _csrfToken = null;
async function getCsrfToken() {
    if (_csrfToken) return _csrfToken;
    try {
        const r    = await fetch('/api/csrf-token.php', { credentials: 'include' });
        const data = await r.json();
        if (data.success) _csrfToken = data.token;
    } catch (e) { console.error('Could not fetch CSRF token', e); }
    return _csrfToken;
}
function csrfHeaders() {
    return _csrfToken
        ? { 'Content-Type': 'application/json', 'X-CSRF-Token': _csrfToken }
        : { 'Content-Type': 'application/json' };
}

document.addEventListener('DOMContentLoaded', async function () {
    await getCsrfToken();
    await loadJournals();
});

function getJournalIdFromUrl() {
    const params = new URLSearchParams(window.location.search);
    const urlId = Number(params.get('journal_id'));
    if (Number.isInteger(urlId) && urlId > 0) return urlId;
    return null;
}

function syncGlobalJournalId() {
    globalThis.selectedJournalId = selectedJournalId;
}

function updateJournalUrl(journalId) {
    if (!journalId) return;
    const url = new URL(window.location.href);
    url.searchParams.set('journal_id', journalId);
    window.history.replaceState({}, '', url);
}

async function loadJournals() {
    try {
        const response = await fetch('../api/journals/list.php');
        const data = await response.json();

        if (!data.success || !Array.isArray(data.journals) || data.journals.length === 0) {
            document.getElementById('journalProfileSelect').innerHTML =
                '<option value="">No journals found</option>';
            return;
        }

        availableJournals = data.journals;
        renderJournalOptions();

        const urlJournalId = getJournalIdFromUrl();
        const matchingUrlJournal = availableJournals.find(j => Number(j.id) === urlJournalId);
        const defaultJournal =
            matchingUrlJournal ||
            availableJournals.find(j => Number(j.is_default) === 1) ||
            availableJournals[0];

        selectedJournalId = Number(defaultJournal.id);
        syncGlobalJournalId();

        document.getElementById('journalProfileSelect').value = String(selectedJournalId);
        updateJournalUrl(selectedJournalId);

        await refreshJournalData();
    } catch (error) {
        console.error('Error loading journals:', error);
        document.getElementById('journalProfileSelect').innerHTML =
            '<option value="">Error loading journals</option>';
    }
}

function renderJournalOptions() {
    const select = document.getElementById('journalProfileSelect');
    if (!select) return;

    select.innerHTML = availableJournals.map(journal => `
        <option value="${journal.id}">
            ${escapeHtml(journal.name)}
        </option>
    `).join('');
}

async function handleJournalChange() {
    const select = document.getElementById('journalProfileSelect');
    if (!select) return;

    selectedJournalId = Number(select.value);
    syncGlobalJournalId();

    if (!selectedJournalId) return;

    updateJournalUrl(selectedJournalId);
    await refreshJournalData();
}

async function refreshJournalData() {
    await loadStats();
    await loadColumnsAndRenderTable();
}

async function loadStats() {
    if (!selectedJournalId) return;

    try {
        const response = await fetch(`../api/trades/stats.php?journal_id=${selectedJournalId}`);
        const data = await response.json();

        if (data.success) {
            const stats = data.stats || {};
            document.getElementById('totalTrades').textContent = stats.total_trades ?? 0;
            document.getElementById('winRate').textContent = (stats.win_rate ?? 0) + '%';
            document.getElementById('avgPL').textContent =
                ((stats.avg_profit_loss_pct ?? 0) >= 0 ? '+' : '') + (stats.avg_profit_loss_pct ?? 0) + '%';
            document.getElementById('openTrades').textContent = stats.open_trades ?? 0;
        }
    } catch (error) {
        console.error('Error loading stats:', error);
    }
}

async function loadColumnsAndRenderTable() {
    try {
        const colResponse = await fetch('../api/columns/list.php');
        const colData = await colResponse.json();

        if (colData.success) {
            activeColumns = colData.columns.filter(col => col.visible);

            const hasActions = activeColumns.some(col => col.key === 'actions');
            if (!hasActions) {
                activeColumns.push({ key: 'actions', name: 'Actions', visible: true });
            }

            renderTableHead();
            await loadTrades();
        }
    } catch (error) {
        console.error('Error loading columns:', error);
    }
}

function renderTableHead() {
    const head = document.getElementById('tradesTableHead');
    if (!head) return;

    if (!activeColumns.length) {
        head.innerHTML = '<th>No columns selected</th>';
        return;
    }

    head.innerHTML = activeColumns.map(col => `<th>${escapeHtml(col.name)}</th>`).join('');
}

async function loadTrades() {
    if (!selectedJournalId) return;

    try {
        const response = await fetch(`../api/trades/list.php?journal_id=${selectedJournalId}`);
        const data = await response.json();

        if (data.success) {
            journalTrades = data.trades || [];
            renderTradesTable();
        }
    } catch (error) {
        console.error('Error loading trades:', error);
        const tbody = document.getElementById('tradesTableBody');
        if (tbody) {
            tbody.innerHTML =
                `<tr><td colspan="${activeColumns.length || 1}" class="loading-cell">Error loading trades</td></tr>`;
        }
    }
}

function renderTradesTable() {
    const tbody = document.getElementById('tradesTableBody');
    if (!tbody) return;

    if (!journalTrades.length) {
        tbody.innerHTML =
            `<tr><td colspan="${activeColumns.length || 1}" class="loading-cell">No trades logged yet. Click "Log New Trade" to get started!</td></tr>`;
        return;
    }

    tbody.innerHTML = journalTrades.map(trade => {
        const outcome = (trade.outcome || '').toLowerCase();
        const rowClass =
            outcome === 'win'  ? 'row-win'  :
            outcome === 'loss' ? 'row-loss' : '';

        const cells = activeColumns.map(col => `<td>${renderColumnValue(col, trade)}</td>`).join('');
        return `<tr class="${rowClass}">${cells}</tr>`;
    }).join('');
}

function renderColumnValue(col, trade) {
    switch (col.key) {
        case 'date':
        case 'entry_date':
            return formatDate(trade.entry_date);

        case 'symbol':
            return `<strong>${escapeHtml(trade.symbol || '-')}</strong>`;

        case 'direction':
            return trade.direction
                ? `<span class="badge badge-${String(trade.direction).toLowerCase()}">${escapeHtml(trade.direction)}</span>`
                : '-';

        case 'session':
            return escapeHtml(trade.session || '-');

        case 'entry_price':
            return trade.entry_price ? parseFloat(trade.entry_price).toFixed(5) : '-';

        case 'exit_price':
            return trade.exit_price ? parseFloat(trade.exit_price).toFixed(5) : '-';

        case 'stop_price':
            return trade.stop_price ? parseFloat(trade.stop_price).toFixed(5) : '-';

        case 'stop_percentage':
            return trade.stop_percentage ? parseFloat(trade.stop_percentage).toFixed(2) + '%' : '-';

        case 'profit_loss': {
            if (trade.profit_loss === null || trade.profit_loss === '' || typeof trade.profit_loss === 'undefined') {
                return '-';
            }
            const pl = parseFloat(trade.profit_loss);
            return `<span style="color: ${pl >= 0 ? '#22c55e' : '#ef4444'}">${pl >= 0 ? '+' : ''}${pl.toFixed(2)} $</span>`;
        }

        case 'profit_loss_pct': {
            if (trade.profit_loss_pct === null || trade.profit_loss_pct === '' || typeof trade.profit_loss_pct === 'undefined') {
                return '-';
            }
            const pl = parseFloat(trade.profit_loss_pct);
            return `<span style="color: ${pl >= 0 ? '#22c55e' : '#ef4444'}">${pl >= 0 ? '+' : ''}${pl.toFixed(2)}%</span>`;
        }

        case 'outcome':
            return trade.outcome
                ? `<span class="badge badge-${String(trade.outcome).toLowerCase()}">${escapeHtml(trade.outcome)}</span>`
                : '-';

        case 'strategy_type':
            return escapeHtml(trade.strategy_type || '-');

        case 'imbalance_size_pct':
            return trade.imbalance_size_pct ? `${parseFloat(trade.imbalance_size_pct).toFixed(2)}%` : '-';

        case 'fill_time_bars':
            return escapeHtml(trade.fill_time_bars || '-');

        case 'w_histogram':
            return escapeHtml(trade.w_histogram || '-');

        case 'm_histogram':
            return escapeHtml(trade.m_histogram || '-');

        case 'vix_moment':
            return escapeHtml(trade.vix_moment || '-');

        case 'stop_triggered':
            return trade.stop_triggered == 1 ? 'Yes' : 'No';

        case 'actions':
            return `
                <button class="btn-action" onclick="editTrade(${trade.id}, ${trade.journal_id})">Edit</button>
                <button class="btn-action" onclick="viewTrade(${trade.id}, ${trade.journal_id})">View</button>
                <button class="btn-action btn-action-delete" onclick="deleteTrade(${trade.id})">Delete</button>
            `;

        default:
            if (col.key && col.key.startsWith('custom_')) {
                const fields = trade.custom_fields;
                if (!fields || typeof fields !== 'object' || Array.isArray(fields)) return '-';
                const value = fields[col.key];
                if (value === null || value === undefined || value === '') return '-';
                if (value === true || value == 1 || value === '1') return 'Yes';
                if (value === false || value == 0 || value === '0') return 'No';
                return escapeHtml(String(value));
            }
            return '-';
    }
}

function viewTrade(tradeId) {
    const params = new URLSearchParams(window.location.search);
    const journalId =
        params.get('journal_id') ||
        (typeof globalThis.selectedJournalId !== 'undefined' ? globalThis.selectedJournalId : '');

    const target = journalId
        ? `/journal/view/?id=${tradeId}&journal_id=${journalId}`
        : `/journal/view/?id=${tradeId}`;

    window.location.href = target;
}

function editTrade(tradeId) {
    const params = new URLSearchParams(window.location.search);
    const journalId =
        params.get('journal_id') ||
        (typeof globalThis.selectedJournalId !== 'undefined' ? globalThis.selectedJournalId : '');

    const target = journalId
        ? `/journal/edit/?id=${tradeId}&journal_id=${journalId}`
        : `/journal/edit/?id=${tradeId}`;

    window.location.href = target;
}

async function deleteTrade(id) {
    const confirmed = confirm('Are you sure you want to delete this trade? This action cannot be undone.');
    if (!confirmed) return;

    await getCsrfToken();
    try {
        const response = await fetch('../api/trades/delete.php', {
            method: 'POST',
            headers: csrfHeaders(),
            body: JSON.stringify({ trade_id: id, journal_id: selectedJournalId })
        });

        const data = await response.json();

        if (data.success) {
            await refreshJournalData();
        } else {
            alert(data.message || 'Failed to delete trade.');
        }
    } catch (error) {
        console.error('Error deleting trade:', error);
        alert('An error occurred while deleting the trade.');
    }
}

function formatDate(dateString) {
    if (!dateString) return '-';
    const date = new Date(dateString);
    if (Number.isNaN(date.getTime())) return '-';
    return date.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
}

function escapeHtml(value) {
    return String(value ?? '')
        .replaceAll('&', '&amp;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;')
        .replaceAll("'", '&#039;');
}
