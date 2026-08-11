// trade-form.js — CSRF-aware

function getJournalId() {
    const params = new URLSearchParams(window.location.search);
    return parseInt(params.get('journal_id') || '1', 10);
}

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

document.addEventListener('DOMContentLoaded', async function () {
    await getCsrfToken();

    const form = document.getElementById('tradeForm');

    document.getElementById('entry_date').valueAsDate = new Date();

    // ── Stop Price ↔ Stop Distance auto-calculation ──────────────────
    const entryPriceField = document.getElementById('entry_price');
    const stopPriceField  = document.getElementById('stop_price');
    const stopPctField    = document.getElementById('stop_percentage');
    const directionField  = document.getElementById('direction');

    // Load saved default stop distance from user preferences
    fetch('/api/preferences/get.php', { credentials: 'include' })
        .then(r => r.json())
        .then(data => {
            let saved = null;
            if (data.value !== undefined) {
                saved = parseFloat(data.value);
            } else if (data.preferences && data.preferences.default_stop_distance !== undefined) {
                saved = parseFloat(data.preferences.default_stop_distance);
            }
            if (!stopPctField.value) {
                stopPctField.value = (saved && saved > 0) ? saved.toFixed(2) : '1.00';
            }
        })
        .catch(() => {
            if (!stopPctField.value) stopPctField.value = '1.00';
        });

    // Load custom columns and render them
    fetch('/api/columns/list.php', { credentials: 'include' })
        .then(r => r.json())
        .then(data => {
            if (!data.success || !data.columns || data.columns.length === 0) return;
            renderCustomColumns(data.columns, {});
        })
        .catch(() => {/* no custom columns — silently ignore */});

    // ── Render custom column inputs ───────────────────────────────────────
    function renderCustomColumns(columns, existingValues) {
        const container = document.getElementById('customColumnsContainer');
        const section   = document.getElementById('customColumnsSection');
        if (!container || !section) return;

        const customCols = columns.filter(col => col.type === 'custom');
        if (customCols.length === 0) return;

        section.innerHTML = '';

        customCols.forEach(col => {
            const group    = document.createElement('div');
            group.className = 'form-group';

            const colType  = (col.data_type || 'text').toLowerCase();
            const fieldKey = col.key;
            const savedVal = existingValues[fieldKey] !== undefined ? existingValues[fieldKey] : '';

            if (colType === 'checkbox' || colType === 'boolean' || colType === 'yes_no') {
                const wrapper = document.createElement('label');
                wrapper.className = 'checkbox-label';
                const input = document.createElement('input');
                input.type    = 'checkbox';
                input.id      = 'custom_col_' + col.id;
                input.name    = fieldKey;
                input.checked = savedVal == 1 || savedVal === 'true' || savedVal === true;
                const span = document.createElement('span');
                span.textContent = col.column_name || col.name;
                wrapper.appendChild(input);
                wrapper.appendChild(span);
                group.appendChild(wrapper);
                section.appendChild(group);
                return;
            }

            const label = document.createElement('label');
            label.setAttribute('for', 'custom_col_' + col.id);
            label.textContent = col.column_name || col.name;
            group.appendChild(label);

            let input;

            if (colType === 'number') {
                input = document.createElement('input');
                input.type        = 'number';
                input.step        = '0.01';
                input.placeholder = '0.00';
            } else if (colType === 'select' || colType === 'dropdown') {
                input = document.createElement('select');
                const blank = document.createElement('option');
                blank.value       = '';
                blank.textContent = '— Select —';
                input.appendChild(blank);
                const opts = Array.isArray(col.select_options)
                    ? col.select_options
                    : (col.select_options || '').split(',').map(o => o.trim()).filter(Boolean);
                opts.forEach(opt => {
                    const o = document.createElement('option');
                    o.value       = opt;
                    o.textContent = opt;
                    if (o.value === savedVal) o.selected = true;
                    input.appendChild(o);
                });
            } else {
                input = document.createElement('input');
                input.type        = 'text';
                input.placeholder = '';
            }

            input.id   = 'custom_col_' + col.id;
            input.name = fieldKey;
            if (savedVal && input.tagName !== 'SELECT') input.value = savedVal;

            group.appendChild(input);
            section.appendChild(group);
        });

        container.style.display = 'block';
    }

    let lastStopEdited = null;

    function calcStopFromPrice() {
        const entry = parseFloat(entryPriceField.value);
        const stop  = parseFloat(stopPriceField.value);
        if (!entry || !stop || entry <= 0) return;
        stopPctField.value = (Math.abs(entry - stop) / entry * 100).toFixed(4);
    }

    function calcStopFromPct() {
        const entry = parseFloat(entryPriceField.value);
        const pct   = parseFloat(stopPctField.value);
        const dir   = directionField.value;
        if (!entry || !pct || entry <= 0) return;
        const stopPrice = dir === 'SHORT'
            ? entry * (1 + pct / 100)
            : entry * (1 - pct / 100);
        stopPriceField.value = stopPrice.toFixed(5);
    }

    stopPriceField.addEventListener('input', function () {
        lastStopEdited = 'price';
        calcStopFromPrice();
    });

    stopPctField.addEventListener('input', function () {
        lastStopEdited = 'pct';
        calcStopFromPct();
    });

    // ── P&L auto-calculation ─────────────────────────────────────────
    const exitPriceField  = document.getElementById('exit_price');
    const plPercentField  = document.getElementById('profit_loss_pct');
    const outcomeField    = document.getElementById('outcome');

    function clearPLFields() {
        if (plPercentField) plPercentField.value = '';
        if (outcomeField && outcomeField.value !== 'OPEN') {
            outcomeField.value = 'OPEN';
        }
    }

    function calculatePLPct() {
        const entry = parseFloat(entryPriceField.value);
        const exit  = parseFloat(exitPriceField.value);
        const dir   = directionField.value;

        if (!entry || !exit || !dir) {
            clearPLFields();
            return;
        }

        let plPct;
        if (dir === 'LONG') {
            plPct = ((exit - entry) / entry) * 100;
        } else {
            plPct = ((entry - exit) / entry) * 100;
        }

        if (plPercentField) plPercentField.value = plPct.toFixed(2);

        if (outcomeField) {
            if (plPct > 0.1)       outcomeField.value = 'WIN';
            else if (plPct < -0.1) outcomeField.value = 'LOSS';
            else                   outcomeField.value = 'BREAKEVEN';
        }
    }

    entryPriceField.addEventListener('input', function () {
        if (lastStopEdited === 'price') {
            calcStopFromPrice();
        } else {
            calcStopFromPct();
        }
        calculatePLPct();
    });

    directionField.addEventListener('change', function () {
        if (lastStopEdited !== 'price') calcStopFromPct();
        calculatePLPct();
    });

    if (exitPriceField) {
        exitPriceField.addEventListener('input', calculatePLPct);
    }


    // ── Form submission ──────────────────────────────────────────────
    form.addEventListener('submit', async function (e) {
        e.preventDefault();

        const token      = await getCsrfToken();
        const formData   = new FormData(form);
        formData.set('journal_id', getJournalId());

        const submitBtn  = form.querySelector('button[type="submit"]');
        const messageDiv = document.getElementById('formMessage');

        submitBtn.disabled = true;
        submitBtn.innerHTML = '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle></svg> Saving...';

        try {
            const response = await fetch('../../api/trades/create.php', {
                method: 'POST',
                headers: token ? { 'X-CSRF-Token': token } : {},
                body: formData,
            });

            const result = await response.json();

            if (result.success) {
                messageDiv.textContent  = 'Trade logged successfully! Redirecting...';
                messageDiv.className    = 'form-message success';
                messageDiv.style.display = 'block';
                setTimeout(() => { window.location.href = '../'; }, 1500);
            } else {
                messageDiv.textContent  = result.message || 'Failed to log trade. Please try again.';
                messageDiv.className    = 'form-message error';
                messageDiv.style.display = 'block';
                submitBtn.disabled = false;
                submitBtn.innerHTML = '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"></polyline></svg> Save Trade';
                messageDiv.scrollIntoView({ behavior: 'smooth', block: 'center' });
            }
        } catch (error) {
            console.error('Error:', error);
            messageDiv.textContent  = 'Connection error. Please try again.';
            messageDiv.className    = 'form-message error';
            messageDiv.style.display = 'block';
            submitBtn.disabled = false;
            submitBtn.innerHTML = '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"></polyline></svg> Save Trade';
        }
    });
});