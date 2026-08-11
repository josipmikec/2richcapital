function getJournalId() {
    const params = new URLSearchParams(window.location.search);
    return parseInt(params.get('journal_id') || '1', 10);
}

const tradeId = new URLSearchParams(window.location.search).get('id');

document.addEventListener('DOMContentLoaded', function () {
    if (!tradeId) {
        alert('Missing trade ID');
        window.location.href = '/journal';
        return;
    }
    loadTradeData();
});

async function loadTradeData() {
    try {
        const [tradeResp, columnsResp] = await Promise.all([
            fetch(`../../api/trades/get.php?id=${tradeId}&journal_id=${getJournalId()}`, { credentials: 'include' }),
            fetch('/api/columns/list.php', { credentials: 'include' }).catch(() => null)
        ]);

        const data = await tradeResp.json();

        let columns = [];
        if (columnsResp) {
            const colData = await columnsResp.json().catch(() => null);
            if (colData && colData.success && colData.columns) {
                columns = colData.columns;
            }
        }

        if (data.success && data.trade) {
            populateForm(data.trade, columns);
            document.getElementById('loadingState').style.display = 'none';
            document.getElementById('tradeForm').style.display = 'block';
        } else {
            alert(data.message || 'Trade not found or access denied');
            window.location.href = '/journal';
        }
    } catch (error) {
        console.error('Error loading trade:', error);
        alert('Error loading trade');
        window.location.href = '/journal';
    }
}

function populateForm(trade, columns) {
    const tradeIdField = document.getElementById('trade_id');
    if (tradeIdField) tradeIdField.value = trade.id;

    setInputValue('entry_date',    formatForDateInput(trade.entry_date));
    setInputValue('session',       trade.session);
    setInputValue('symbol',        trade.symbol);
    setInputValue('direction',     trade.direction);
    setInputValue('entry_price',   trade.entry_price);
    setInputValue('strategy_type', trade.strategy_type || 'BULL IMBALANCE');

    setInputValue('imbalance_size_pct', trade.imbalance_size_pct);
    setInputValue('fill_time_bars',     trade.fill_time_bars);
    setCheckboxValue('nearby_imbalance', trade.nearby_imbalance == 1);

    setInputValue('w_histogram', trade.w_histogram);
    setInputValue('m_histogram', trade.m_histogram);
    setCheckboxValue('all_8h_bars_bullish', trade.all_8h_bars_bullish == 1);

    setInputValue('m_vix_open', trade.m_vix_open);
    setInputValue('w_vix_open', trade.w_vix_open);
    setInputValue('d_vix_open', trade.d_vix_open);
    setInputValue('vix_moment', trade.vix_moment);

    if (trade.stop_price && parseFloat(trade.stop_price) > 0) {
        setInputValue('stop_price', parseFloat(trade.stop_price).toFixed(5));
    }

    const stopPctField = document.getElementById('stop_percentage');
    if (trade.stop_percentage) {
        setInputValue('stop_percentage', trade.stop_percentage);
    } else if (stopPctField && !stopPctField.value) {
        stopPctField.value = '1.00';
    }
    setCheckboxValue('stop_triggered', trade.stop_triggered == 1);

    setInputValue('lowest_price_from_entry_pct',  trade.lowest_price_from_entry_pct);
    setInputValue('highest_price_from_entry_pct', trade.highest_price_from_entry_pct);
    setInputValue('trade_time_bars', trade.trade_time_bars);

    setInputValue('exit_price',      trade.exit_price);
    setInputValue('exit_date',       formatForDateInput(trade.exit_date));
    setInputValue('profit_loss',     trade.profit_loss);
    setInputValue('profit_loss_pct', trade.profit_loss_pct);
    setInputValue('outcome',         trade.outcome);
    setInputValue('note',            trade.note);

    if (columns && columns.length > 0) {
        renderCustomColumns(columns, trade);
    }

    setupFormSubmit();
    setupAutoCalculation();
}

function renderCustomColumns(columns, existingValues) {
    const container = document.getElementById('customColumnsContainer');
    const section   = document.getElementById('customColumnsSection');
    if (!container || !section) return;

    const customCols = columns.filter(function(col) { return col.type === 'custom'; });
    if (customCols.length === 0) return;

    section.innerHTML = '';

    customCols.forEach(function(col) {
        const group = document.createElement('div');
        group.className = 'form-group';

        const colType  = (col.data_type || 'text').toLowerCase();
        const fieldKey = col.key;
        const savedVal = existingValues[fieldKey] !== undefined ? existingValues[fieldKey] : '';

        if (colType === 'checkbox' || colType === 'boolean') {
            const wrapper = document.createElement('label');
            wrapper.className = 'checkbox-label';
            const input = document.createElement('input');
            input.type    = 'checkbox';
            input.id      = 'custom_col_' + col.id;
            input.name    = fieldKey;
            input.checked = savedVal == 1 || savedVal === 'true';
            const span = document.createElement('span');
            span.textContent = col.name;
            wrapper.appendChild(input);
            wrapper.appendChild(span);
            group.appendChild(wrapper);
            section.appendChild(group);
            return;
        }

        const label = document.createElement('label');
        label.setAttribute('for', 'custom_col_' + col.id);
        label.textContent = col.name;
        group.appendChild(label);

        let input;

        if (colType === 'number') {
            input = document.createElement('input');
            input.type        = 'number';
            input.step        = '0.01';
            input.placeholder = '0.00';
        } else if (colType === 'select' && col.select_options) {
            input = document.createElement('select');
            const blank = document.createElement('option');
            blank.value       = '';
            blank.textContent = 'Select';
            input.appendChild(blank);
            col.select_options.split(',').forEach(function(opt) {
                const o = document.createElement('option');
                o.value       = opt.trim();
                o.textContent = opt.trim();
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

function setupFormSubmit() {
    const form = document.getElementById('tradeForm');
    if (!form) return;
    if (form.dataset.submitBound === '1') return;
    form.dataset.submitBound = '1';

    form.addEventListener('submit', async function (e) {
        e.preventDefault();

        const formData = new FormData(form);
        formData.set('journal_id', getJournalId());
        formData.set('trade_id', tradeId);

        const submitBtn  = form.querySelector('button[type="submit"]');
        const messageDiv = document.getElementById('formMessage');

        submitBtn.disabled = true;
        submitBtn.innerHTML = '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle></svg> Updating...';

        try {
            const csrfResp = await fetch('../../api/csrf-token.php', { credentials: 'include' });
            const csrfData = await csrfResp.json();
            if (!csrfData.success || !csrfData.token) {
                throw new Error('Failed to retrieve CSRF token');
            }

            const response = await fetch('../../api/trades/update.php', {
                method: 'POST',
                headers: { 'X-CSRF-Token': csrfData.token },
                body: formData,
                credentials: 'include'
            });

            const result = await response.json();

            if (result.success) {
                messageDiv.textContent = 'Trade updated successfully! Redirecting...';
                messageDiv.className = 'form-message success';
                messageDiv.style.display = 'block';
                setTimeout(() => { window.location.href = '/journal'; }, 1500);
            } else {
                messageDiv.textContent = result.message || 'Failed to update trade.';
                messageDiv.className = 'form-message error';
                messageDiv.style.display = 'block';
                submitBtn.disabled = false;
                submitBtn.innerHTML = '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"></polyline></svg> Update Trade';
                messageDiv.scrollIntoView({ behavior: 'smooth', block: 'center' });
            }
        } catch (error) {
            console.error('Error:', error);
            messageDiv.textContent = 'Connection error. Please try again.';
            messageDiv.className = 'form-message error';
            messageDiv.style.display = 'block';
            submitBtn.disabled = false;
            submitBtn.innerHTML = '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"></polyline></svg> Update Trade';
        }
    });
}

function setupAutoCalculation() {
    const entryPriceField = document.getElementById('entry_price');
    const exitPriceField  = document.getElementById('exit_price');
    const directionField  = document.getElementById('direction');
    const plPercentField  = document.getElementById('profit_loss_pct');
    const outcomeField    = document.getElementById('outcome');
    const stopPriceField  = document.getElementById('stop_price');
    const stopPctField    = document.getElementById('stop_percentage');

    if (!entryPriceField || !exitPriceField || !directionField || !outcomeField) return;

    if (exitPriceField.dataset.calcBound === '1') return;
    exitPriceField.dataset.calcBound  = '1';
    entryPriceField.dataset.calcBound = '1';
    directionField.dataset.calcBound  = '1';

    function clearPLPct() {
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
            clearPLPct();
            return;
        }

        let plPct;
        if (dir === 'LONG') {
            plPct = ((exit - entry) / entry) * 100;
        } else {
            plPct = ((entry - exit) / entry) * 100;
        }

        if (plPercentField) plPercentField.value = plPct.toFixed(2);

        if (plPct > 0.1)       outcomeField.value = 'WIN';
        else if (plPct < -0.1) outcomeField.value = 'LOSS';
        else                   outcomeField.value = 'BREAKEVEN';
    }

    entryPriceField.addEventListener('input', calculatePLPct);
    exitPriceField.addEventListener('input', calculatePLPct);
    directionField.addEventListener('change', calculatePLPct);

    if (!stopPriceField || !stopPctField) return;

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

    entryPriceField.addEventListener('input', function () {
        if (lastStopEdited === 'price') {
            calcStopFromPrice();
        } else {
            calcStopFromPct();
        }
    });

    directionField.addEventListener('change', function () {
        if (lastStopEdited !== 'price') calcStopFromPct();
    });

    const entryOnLoad = parseFloat(entryPriceField.value);
    const pctOnLoad   = parseFloat(stopPctField.value);
    if (entryOnLoad > 0 && pctOnLoad > 0 && !stopPriceField.value) {
        calcStopFromPct();
    }

    if (entryPriceField.value && exitPriceField.value && directionField.value) {
        calculatePLPct();
    }
}

function setInputValue(id, value) {
    const el = document.getElementById(id);
    if (!el) return;
    if (value === null || value === undefined || value === '') return;
    el.value = value;
}

function setCheckboxValue(id, checked) {
    const el = document.getElementById(id);
    if (!el) return;
    el.checked = !!checked;
}

function formatForDateInput(value) {
    if (!value) return '';
    const stringValue = String(value).trim();
    if (/^\d{4}-\d{2}-\d{2}$/.test(stringValue)) return stringValue;
    if (stringValue.includes('T')) return stringValue.slice(0, 10);
    if (stringValue.includes(' ')) return stringValue.split(' ')[0];
    return stringValue;
}
