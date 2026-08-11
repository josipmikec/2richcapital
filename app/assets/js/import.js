let mt5Data = null;
let excelData = null;
let _xlsxLoader = null;

function ensureXLSXLoaded() {
    if (window.XLSX) return Promise.resolve(window.XLSX);
    if (_xlsxLoader) return _xlsxLoader;

    _xlsxLoader = new Promise((resolve, reject) => {
        const script = document.createElement('script');
        script.src = '../assets/vendor/xlsx.full.min.js';
        script.onload = () => resolve(window.XLSX);
        script.onerror = () => reject(new Error('Failed to load XLSX library'));
        document.head.appendChild(script);
    });

    return _xlsxLoader;
}

function getSelectedJournalId() {
    const globalId = Number(globalThis.selectedJournalId);

    if (Number.isInteger(globalId) && globalId > 0) {
        return globalId;
    }

    const params = new URLSearchParams(window.location.search);
    const urlId = Number(params.get('journal_id'));

    if (Number.isInteger(urlId) && urlId > 0) {
        return urlId;
    }

    const select = document.getElementById('journalProfileSelect');
    if (select) {
        const selectId = Number(select.value);
        if (Number.isInteger(selectId) && selectId > 0) {
            return selectId;
        }
    }

    return null;
}

function syncJournalIdFromPage() {
    const jid = getSelectedJournalId();
    if (jid) {
        globalThis.selectedJournalId = jid;
    }
    return jid;
}

function openNewTradeModal() {
    const modal = document.getElementById('newTradeModal');
    if (!modal) return;
    syncJournalIdFromPage();
    modal.style.display = 'flex';
}

function goToNewTradeForCurrentJournal() {
    const jid = syncJournalIdFromPage();
    const target = jid ? `/journal/new/?journal_id=${jid}` : '/journal/new/';
    window.location.href = target;
}

function importMT5() {
    const jid = syncJournalIdFromPage();
    if (!jid) {
        alert('Please select a journal first before importing.');
        return;
    }
    const modal = document.getElementById('mt5Modal');
    if (!modal) return;
    modal.style.display = 'flex';
}

function importExcel() {
    const jid = syncJournalIdFromPage();
    if (!jid) {
        alert('Please select a journal first before importing.');
        return;
    }
    const modal = document.getElementById('excelModal');
    if (!modal) return;
    modal.style.display = 'flex';
}

function closeModal(modalId) {
    const modal = document.getElementById(modalId);
    if (!modal) return;
    modal.style.display = 'none';

    if (modalId === 'mt5Modal') {
        const input   = document.getElementById('mt5FileInput');
        const preview = document.getElementById('mt5Preview');
        const button  = document.getElementById('mt5ImportBtn');
        if (input)   input.value = '';
        if (preview) preview.style.display = 'none';
        if (button)  button.disabled = true;
        mt5Data = null;
    } else if (modalId === 'excelModal') {
        const input   = document.getElementById('excelFileInput');
        const preview = document.getElementById('excelPreview');
        const button  = document.getElementById('excelImportBtn');
        if (input)   input.value = '';
        if (preview) preview.style.display = 'none';
        if (button)  button.disabled = true;
        excelData = null;
    }
}

document.addEventListener('click', function (event) {
    if (event.target.classList.contains('modal-overlay')) {
        event.target.style.display = 'none';
    }
});

async function handleMT5File(event) {
    const file = event.target.files[0];
    if (!file) return;

    const fileName = file.name.toLowerCase();
    const isExcel  = fileName.endsWith('.xlsx') || fileName.endsWith('.xls');
	console.log('Selected MT5 file:', file.name, 'isExcel:', isExcel);
    const reader   = new FileReader();

    reader.onload = async function (e) {
        console.log('FileReader loaded MT5 file');
		try {
            if (isExcel) {
                await ensureXLSXLoaded();
                const data = new Uint8Array(e.target.result);
                mt5Data = parseMT5Excel(data);
            } else {
                mt5Data = parseMT5CSV(e.target.result);
            }
			console.log('Parsed MT5 trades:', mt5Data);
			
            document.getElementById('mt5FileName').textContent  = file.name;
            document.getElementById('mt5TradeCount').textContent = mt5Data.length;
            document.getElementById('mt5Preview').style.display  = 'block';
            document.getElementById('mt5ImportBtn').disabled      = mt5Data.length === 0;

            if (mt5Data.length === 0) {
                alert('No valid MT5 trades found in this file.');
            }
        } catch (error) {
            console.error('MT5 parse error:', error);
            alert('Failed to parse MT5 file: ' + error.message);
            mt5Data = null;
            document.getElementById('mt5Preview').style.display = 'none';
            document.getElementById('mt5ImportBtn').disabled    = true;
        }
    };

    if (isExcel) {
        reader.readAsArrayBuffer(file);
    } else {
        reader.readAsText(file);
    }
}

function handleExcelFile(event) {
    const file = event.target.files[0];
    if (!file) return;

    const reader = new FileReader();
    reader.onload = function (e) {
        try {
            excelData = parseCustomCSV(e.target.result);

            document.getElementById('excelFileName').textContent  = file.name;
            document.getElementById('excelTradeCount').textContent = excelData.length;
            document.getElementById('excelPreview').style.display  = 'block';
            document.getElementById('excelImportBtn').disabled      = excelData.length === 0;

            if (excelData.length === 0) {
                alert('No valid trades found in this file.');
            }
        } catch (error) {
            console.error('Excel parse error:', error);
            alert('Failed to parse file: ' + error.message);
            excelData = null;
            document.getElementById('excelPreview').style.display = 'none';
            document.getElementById('excelImportBtn').disabled    = true;
        }
    };

    reader.readAsText(file);
}

function parseMT5Excel(arrayBuffer) {
    if (typeof XLSX === 'undefined') {
        throw new Error('SheetJS library is missing. Please include XLSX before using Excel import.');
    }
    const workbook   = XLSX.read(arrayBuffer, { type: 'array' });
    const firstSheet = workbook.Sheets[workbook.SheetNames[0]];
    const rows       = XLSX.utils.sheet_to_json(firstSheet, { header: 1, raw: false });
    return parseMT5Rows(rows);
}

function parseMT5CSV(csvText) {
    const rows = csvText
        .split(/\r?\n/)
        .filter(line => line.trim() !== '')
        .map(line => splitCSVLine(line));

    return parseMT5Rows(rows);
}

function parseMT5Rows(rows) {
    const trades = [];
    let positionsRowIndex = -1;
    let headerRowIndex    = -1;

    for (let i = 0; i < rows.length; i++) {
        const firstCell = String(rows[i]?.[0] || '').trim().toLowerCase();
        if (firstCell === 'positions') {
            positionsRowIndex = i;
            headerRowIndex    = i + 1;
            break;
        }
    }

    if (positionsRowIndex === -1 || !rows[headerRowIndex]) return [];

    const headers = rows[headerRowIndex].map(h => String(h || '').trim());

    // MT5 has duplicate "Time" and "Price" columns (open + close).
    // Find the FIRST and LAST index of each.
    function firstIdx(name) {
        return headers.findIndex(h => h.toLowerCase() === name.toLowerCase());
    }
    function lastIdx(name) {
        let idx = -1;
        headers.forEach((h, i) => { if (h.toLowerCase() === name.toLowerCase()) idx = i; });
        return idx;
    }

    for (let i = headerRowIndex + 1; i < rows.length; i++) {
        const row = rows[i];
        if (!row || row.length === 0) continue;

        const firstCell = String(row[0] || '').trim().toLowerCase();
        const rowText = row.map(cell => String(cell || '').trim().toLowerCase()).join(' | ');
        const isEmptyRow = row.every(cell => String(cell || '').trim() === '');

        if (isEmptyRow) continue;

        if (
            firstCell === 'orders' ||
            firstCell === 'deals' ||
            firstCell === 'open positions' ||
            firstCell === 'working orders' ||
            firstCell === 'summary' ||
            rowText === 'orders' ||
            rowText === 'deals' ||
            rowText.includes('open positions') ||
            rowText.includes('working orders') ||
            rowText.includes('summary')
        ) {
            break;
        }


        // Helper to get cell by header name (first match)
        const col = (name) => {
            const idx = firstIdx(name);
            return idx >= 0 ? (row[idx] ?? '') : '';
        };

        const openTime   = col('Time');
        const closeTime  = lastIdx('Time')  !== firstIdx('Time')  ? (row[lastIdx('Time')]  ?? '') : null;
        const entryPrice = col('Price');
        const closePrice = lastIdx('Price') !== firstIdx('Price') ? (row[lastIdx('Price')] ?? '') : null;
        const ticket     = col('Position');
        const symbol     = col('Symbol');
        const type       = String(col('Type') || '').toLowerCase();
        const volume     = col('Volume');
        const stopLoss   = col('S / L');
        const takeProfit = col('T / P');
        const commission = col('Commission');
        const swap       = col('Swap');
        const profit     = col('Profit');

        if (!openTime || !symbol || (!type.includes('buy') && !type.includes('sell'))) continue;

        const entryNum = parseNullableNumber(entryPrice);
        const exitNum  = parseNullableNumber(closePrice);
        const profitNum = parseNullableNumber(profit);
        const slNum    = parseNullableNumber(stopLoss);
        const direction = type.includes('buy') ? 'LONG' : 'SHORT';

        // Calculate P&L %
        let profit_loss_pct = null;
        if (entryNum && exitNum && entryNum > 0) {
            const diff = direction === 'LONG'
                ? exitNum - entryNum
                : entryNum - exitNum;
            profit_loss_pct = parseFloat(((diff / entryNum) * 100).toFixed(2));
        }

        // Detect stop hit: exit price within 0.05% of stop loss level
        let stop_triggered = 0;
        if (exitNum && slNum && slNum > 0) {
            const tolerance = entryNum * 0.0005; // 0.05% buffer
            if (direction === 'LONG'  && exitNum <= slNum + tolerance) stop_triggered = 1;
            if (direction === 'SHORT' && exitNum >= slNum - tolerance) stop_triggered = 1;
        }

        const trade = {
            ticket,
            entry_date:      formatMT5Date(openTime),
            exit_date:       formatMT5Date(closeTime),
            symbol:          String(symbol).trim(),
            direction,
            volume:          parseNullableNumber(volume),
            entry_price:     entryNum,
            exit_price:      exitNum,
            stop_loss:       slNum,
            take_profit:     parseNullableNumber(takeProfit),
            commission:      parseNullableNumber(commission) || 0,
            swap:            parseNullableNumber(swap) || 0,
            profit_loss:     profitNum || 0,
            profit_loss_pct,
            stop_triggered,
            session:         detectSession(openTime),
            strategy_type:   'MT5 IMPORT',
            outcome:         getOutcomeFromProfit(profitNum)
        };

        if (trade.entry_date && trade.symbol && trade.direction && trade.entry_price) {
            trades.push(trade);
        }
    }

    return trades;
}


function parseCustomCSV(csvText) {
    const lines = csvText.split('\n');
    if (!lines.length) return [];

    const headers = splitCSVLine(lines[0]).map(h => h.trim().toLowerCase());
    const trades  = [];

    for (let i = 1; i < lines.length; i++) {
        const line = lines[i].trim();
        if (!line) continue;

        const cols = splitCSVLine(line);
        const row  = {};
        headers.forEach((header, index) => { row[header] = cols[index]?.trim(); });

        const trade = { ...row };

        if (!trade.profit_loss_pct && trade.entry_price && trade.exit_price) {
            const entry     = parseNullableNumber(trade.entry_price);
            const exit      = parseNullableNumber(trade.exit_price);
            const direction = String(trade.direction || '').toUpperCase();
            if (entry && exit) {
                const priceDiff = direction === 'LONG' ? exit - entry : entry - exit;
                trade.profit_loss_pct = ((priceDiff / entry) * 100).toFixed(2);
            }
        }

        if (trade.entry_date && trade.symbol && trade.direction && trade.entry_price) {
            trades.push(trade);
        }
    }

    return trades;
}

// ── Process MT5 import ────────────────────────────────────────────────────────
async function processMT5Import() {
    if (!mt5Data || mt5Data.length === 0) return;

    const btn = document.getElementById('mt5ImportBtn');
    btn.disabled    = true;
    btn.textContent = 'Importing...';

    try {
        const jid = getSelectedJournalId();
        console.log('MT5 import → journal_id:', jid);

        if (!jid) {
            alert('Please select a journal first before importing.');
            return;
        }

        globalThis.selectedJournalId = jid;

        const response = await fetch('../api/trades/bulk-import.php', {
            method:  'POST',
            headers: { 'Content-Type': 'application/json' },
            credentials: 'include',   // ← sends the session cookie
            body: JSON.stringify({ trades: mt5Data, journal_id: jid })
        });

        // Read raw text first — prevents the "string did not match" SyntaxError
        const text = await response.text();
        console.log('Server raw response (MT5):', text);

        let result;
        try {
            result = JSON.parse(text);
        } catch (parseErr) {
            alert('Server error — raw response:\n\n' + text.substring(0, 600));
            return;
        }

        if (result.success) {
            alert(`Successfully imported ${result.imported} of ${result.total} trades!`);
            closeModal('mt5Modal');
            if (typeof loadStats  === 'function') await loadStats();
            if (typeof loadTrades === 'function') await loadTrades();
        } else {
            const errDetail = result.errors && result.errors.length
                ? '\n\nRow errors:\n' + result.errors.slice(0, 5).join('\n')
                : '';
            alert('Import failed: ' + (result.message || 'Unknown error') + errDetail);
        }
    } catch (error) {
        console.error('MT5 import error:', error);
        alert('Error during import: ' + error.message);
    } finally {
        btn.disabled    = false;
        btn.textContent = 'Import Trades';
    }
}

// ── Process Excel import ──────────────────────────────────────────────────────
async function processExcelImport() {
    if (!excelData || excelData.length === 0) return;

    const btn = document.getElementById('excelImportBtn');
    btn.disabled    = true;
    btn.textContent = 'Importing...';

    try {
        const jid = getSelectedJournalId();
        console.log('Excel import → journal_id:', jid);

        if (!jid) {
            alert('Please select a journal first before importing.');
            return;
        }

        globalThis.selectedJournalId = jid;

        const response = await fetch('../api/trades/bulk-import.php', {
            method:  'POST',
            headers: { 'Content-Type': 'application/json' },
            credentials: 'include',   // ← sends the session cookie
            body: JSON.stringify({ trades: excelData, journal_id: jid })
        });

        // Read raw text first — prevents the "string did not match" SyntaxError
        const text = await response.text();
        console.log('Server raw response (Excel):', text);

        let result;
        try {
            result = JSON.parse(text);
        } catch (parseErr) {
            alert('Server error — raw response:\n\n' + text.substring(0, 600));
            return;
        }

        if (result.success) {
            alert(`Successfully imported ${result.imported} of ${result.total} trades!`);
            closeModal('excelModal');
            if (typeof loadStats  === 'function') await loadStats();
            if (typeof loadTrades === 'function') await loadTrades();
        } else {
            const errDetail = result.errors && result.errors.length
                ? '\n\nRow errors:\n' + result.errors.slice(0, 5).join('\n')
                : '';
            alert('Import failed: ' + (result.message || 'Unknown error') + errDetail);
        }
    } catch (error) {
        console.error('Excel import error:', error);
        alert('Error during import: ' + error.message);
    } finally {
        btn.disabled    = false;
        btn.textContent = 'Import Trades';
    }
}

// ── Helper functions ──────────────────────────────────────────────────────────

function formatMT5Date(dateStr) {
    if (!dateStr) return null;

    const str   = String(dateStr).trim();
    const match = str.match(/^(\d{4})\.(\d{2})\.(\d{2})(?:\s+(\d{2}):(\d{2}):(\d{2}))?$/);

    if (match) {
        const [, y, m, d, hh = '00', mm = '00', ss = '00'] = match;
        return `${y}-${m}-${d} ${hh}:${mm}:${ss}`;
    }

    const fallback = new Date(str);
    if (!Number.isNaN(fallback.getTime())) {
        const y  = fallback.getFullYear();
        const m  = String(fallback.getMonth() + 1).padStart(2, '0');
        const d  = String(fallback.getDate()).padStart(2, '0');
        const hh = String(fallback.getHours()).padStart(2, '0');
        const mm = String(fallback.getMinutes()).padStart(2, '0');
        const ss = String(fallback.getSeconds()).padStart(2, '0');
        return `${y}-${m}-${d} ${hh}:${mm}:${ss}`;
    }

    return null;
}

function detectSession(timeStr) {
    const formatted = formatMT5Date(timeStr);
    if (!formatted) return 'LONDON';

    const hour = parseInt(formatted.slice(11, 13), 10);
    if (hour >= 0  && hour < 8)  return 'ASIA';
    if (hour >= 8  && hour < 13) return 'LONDON';
    if (hour >= 13 && hour < 22) return 'NY';
    return 'ASIA';
}

function splitCSVLine(line) {
    const result = [];
    let current  = '';
    let inQuotes = false;

    for (let i = 0; i < line.length; i++) {
        const char = line[i];
        const next = line[i + 1];

        if (char === '"' && inQuotes && next === '"') {
            current += '"';
            i++;
        } else if (char === '"') {
            inQuotes = !inQuotes;
        } else if (char === ',' && !inQuotes) {
            result.push(current.trim());
            current = '';
        } else {
            current += char;
        }
    }

    result.push(current.trim());
    return result;
}

function parseNullableNumber(value) {
    if (value === null || value === undefined || value === '') return null;

    const cleaned = String(value)
        .replace(/\s+/g, '')
        .replace(/,/g, '');

    const num = parseFloat(cleaned);
    return Number.isNaN(num) ? null : num;
}

function getOutcomeFromProfit(profit) {
    if (profit === null || profit === undefined) return 'OPEN';
    if (profit > 0) return 'WIN';
    if (profit < 0) return 'LOSS';
    return 'OPEN';
}

// ── Drag & drop support ───────────────────────────────────────────────────────
window.addEventListener('DOMContentLoaded', function () {
    syncJournalIdFromPage();

    const zones = ['mt5UploadZone', 'excelUploadZone'];

    zones.forEach(zoneId => {
        const zone = document.getElementById(zoneId);
        if (!zone) return;

        zone.addEventListener('dragover', (e) => {
            e.preventDefault();
            zone.classList.add('dragover');
        });

        zone.addEventListener('dragleave', () => {
            zone.classList.remove('dragover');
        });

        zone.addEventListener('drop', (e) => {
            e.preventDefault();
            zone.classList.remove('dragover');

            const file = e.dataTransfer.files[0];
            if (file) {
                const inputId = zoneId === 'mt5UploadZone' ? 'mt5FileInput' : 'excelFileInput';
                const input   = document.getElementById(inputId);
                if (!input) return;

                const dataTransfer = new DataTransfer();
                dataTransfer.items.add(file);
                input.files = dataTransfer.files;
                input.dispatchEvent(new Event('change'));
            }
        });
    });
});
