let journalColumns = [];
let journalVisibility = {};
let _cmCsrfToken = null;
async function getCmCsrfToken() {
    if (_cmCsrfToken) return _cmCsrfToken;
    try {
        const r = await fetch('/api/csrf-token.php', { credentials: 'include' });
        const data = await r.json();
        if (data.success) _cmCsrfToken = data.token;
    } catch (e) { console.error('Could not fetch CSRF token', e); }
    return _cmCsrfToken;
}
function cmCsrfHeaders() {
    return _cmCsrfToken
        ? { 'Content-Type': 'application/json', 'X-CSRF-Token': _cmCsrfToken }
        : { 'Content-Type': 'application/json' };
}

if (typeof globalThis.availableJournals === 'undefined') {
    globalThis.availableJournals = [];
}

function openColumnManager() {
    const modal = document.getElementById('columnManagerModal');
    if (!modal) return;
    modal.style.display = 'flex';
    showSettingsTab('journals');
    getCmCsrfToken();
    loadSettingsManager();
}

function closeColumnManager() {
    const modal = document.getElementById('columnManagerModal');
    if (!modal) return;
    modal.style.display = 'none';
}

function showSettingsTab(tab) {
    document.querySelectorAll('.settings-tab').forEach(btn => {
        const isActive = btn.dataset.tab === tab;
        btn.classList.toggle('active', isActive);
        btn.setAttribute('aria-selected', isActive ? 'true' : 'false');
        btn.setAttribute('tabindex', isActive ? '0' : '-1');
    });
    document.querySelectorAll('.settings-panel').forEach(panel => {
        const isActive = panel.id === `settings-panel-${tab}`;
        panel.style.display = isActive ? 'block' : 'none';
    });

    // Populate preferences tab whenever it becomes visible
    if (tab === 'preferences') loadPreferencesTab();
}

async function loadSettingsManager() {
    await Promise.all([
        loadJournalManager(),
        loadColumnManager()
    ]);
}

async function loadJournalManager() {
    try {
        const response = await fetch('/api/journals/list.php', { credentials: 'include' });
        const data = await response.json();
        if (data.success) {
            globalThis.availableJournals = data.journals || [];
            renderJournalsList();
        }
    } catch (error) {
        console.error('Error loading journals:', error);
    }
}

function renderJournalsList() {
    const container = document.getElementById('journalsList');
    if (!container) return;

    const journals = globalThis.availableJournals || [];

    if (!journals.length) {
        container.innerHTML = '<div class="journal-item"><div class="journal-item-title">No journals found.</div></div>';
        return;
    }

    container.innerHTML = journals.map(journal => {
        const safeName = String(journal.name || '').replace(/'/g, "\\'");
        return `
            <div class="journal-item ${Number(journal.id) === Number(selectedJournalId) ? 'active' : ''}">
                <div class="journal-item-header">
                    <div class="journal-item-copy">
                        <div class="journal-item-title-row">
                            <div class="journal-item-title">${escapeHtml(journal.name || 'Untitled Journal')}</div>
                            ${Number(journal.is_default) === 1 ? '<span class="column-badge">Default</span>' : ''}
                        </div>
                        <div class="journal-item-meta">
                            <span>${escapeHtml(journal.broker || 'No broker')}</span>
                            <span class="journal-meta-separator">·</span>
                            <span>${escapeHtml(journal.platform || 'No platform')}</span>
                        </div>
                    </div>
                </div>
                <div class="journal-item-actions">
                    <button type="button" class="btn-action" onclick="renameJournalPrompt(${journal.id}, '${safeName}')">Rename</button>
                    <button type="button" class="btn-action" onclick="switchToJournal(${journal.id})">Open</button>
                    <button type="button" class="btn-action" onclick="setDefaultJournal(${journal.id})">Set Default</button>
                    <button type="button" class="btn-action" onclick="deleteJournal(${journal.id})">Delete</button>
                </div>
            </div>
        `;
    }).join('');
}

async function createJournal() {
    const input = document.getElementById('newJournalName');
    if (!input) return;

    const name = input.value.trim();
    if (!name) {
        alert('Please enter a journal name.');
        return;
    }

    try {
        await getCmCsrfToken();
        const response = await fetch('/api/journals/create.php', {
            method: 'POST',
            credentials: 'include',
            headers: cmCsrfHeaders(),
            body: JSON.stringify({ name })
        });

        const data = await response.json();
        if (data.success) {
            input.value = '';
            if (typeof loadJournals === 'function') await loadJournals();
            await loadJournalManager();
        } else {
            alert(data.message || 'Failed to create journal.');
        }
    } catch (error) {
        console.error('Error creating journal:', error);
        alert('Error creating journal.');
    }
}

async function renameJournalPrompt(id, currentName) {
    const nextName = prompt('Enter new journal name:', currentName);
    if (!nextName) return;
    const trimmed = nextName.trim();
    if (!trimmed) return;

    try {
        await getCmCsrfToken();
        const response = await fetch('/api/journals/rename.php', {
            method: 'POST',
            credentials: 'include',
            headers: cmCsrfHeaders(),
            body: JSON.stringify({ journal_id: id, name: trimmed })
        });
        const data = await response.json();
        if (data.success) {
            if (typeof loadJournals === 'function') await loadJournals();
            await loadJournalManager();
        } else {
            alert(data.message || 'Failed to rename journal.');
        }
    } catch (error) {
        console.error('Error renaming journal:', error);
        alert('Error renaming journal.');
    }
}

async function setDefaultJournal(id) {
    try {
        await getCmCsrfToken();
        const response = await fetch('/api/journals/set-default.php', {
            method: 'POST',
            credentials: 'include',
            headers: cmCsrfHeaders(),
            body: JSON.stringify({ journal_id: id })
        });
        const data = await response.json();
        if (data.success) {
            if (typeof loadJournals === 'function') await loadJournals();
            await loadJournalManager();
        } else {
            alert(data.message || 'Failed to set default journal.');
        }
    } catch (error) {
        console.error('Error setting default journal:', error);
        alert('Error setting default journal.');
    }
}

async function switchToJournal(id) {
    selectedJournalId = Number(id);
    globalThis.selectedJournalId = selectedJournalId;

    const select = document.getElementById('journalProfileSelect');
    if (select) select.value = String(selectedJournalId);

    if (typeof updateJournalUrl   === 'function') updateJournalUrl(selectedJournalId);
    if (typeof refreshJournalData === 'function') await refreshJournalData();

    await loadJournalManager();
}

async function deleteJournal(id) {
    const confirmed = confirm('Delete this journal? You must keep at least one journal.');
    if (!confirmed) return;

    try {
        await getCmCsrfToken();
        const response = await fetch('/api/journals/delete.php', {
            method: 'POST',
            credentials: 'include',
            headers: cmCsrfHeaders(),
            body: JSON.stringify({ journal_id: id })
        });
        const data = await response.json();
        if (data.success) {
            if (typeof loadJournals       === 'function') await loadJournals();
            if (typeof refreshJournalData === 'function') await refreshJournalData();
            await loadJournalManager();
        } else {
            alert(data.message || 'Failed to delete journal.');
        }
    } catch (error) {
        console.error('Error deleting journal:', error);
        alert('Error deleting journal.');
    }
}

async function loadColumnManager() {
    try {
        const response = await fetch('/api/columns/list.php', { credentials: 'include' });
        const data = await response.json();
        if (data.success) {
            journalColumns = data.columns || [];
            renderColumnsList();
        }
    } catch (error) {
        console.error('Error loading columns:', error);
    }
}

function renderColumnsList() {
    const container = document.getElementById('columnsList');
    if (!container) return;

    container.innerHTML = journalColumns.map((col, index) => {
        const safeKey    = String(col.key).replace(/[^a-zA-Z0-9_-]/g, '');
        const checkboxId = `column-visible-${index}-${safeKey}`;
        const colId      = col.id ? Number(col.id) : 0;

        return `
            <div class="column-item ${col.locked ? 'locked' : ''}">
                <input
                    type="checkbox"
                    id="${checkboxId}"
                    name="column_visibility[]"
                    class="column-checkbox"
                    ${col.visible ? 'checked'  : ''}
                    ${col.locked  ? 'disabled' : ''}
                    onchange="toggleColumnVisibility('${col.key}', this.checked)"
                >
                <label class="column-name" for="${checkboxId}">${escapeHtml(col.name)}</label>
                <span class="column-badge">${escapeHtml(col.type)}</span>
                ${col.type === 'custom' && colId > 0 ? `
                    <div class="column-actions">
                        <button
                            type="button"
                            class="icon-btn delete"
                            onclick="deleteCustomColumn(${colId})"
                            title="Delete column"
                            aria-label="Delete ${escapeHtml(col.name)} column"
                        >×</button>
                    </div>
                ` : ''}
            </div>
        `;
    }).join('');
}

function toggleColumnVisibility(key, visible) {
    journalColumns = journalColumns.map(col => {
        if (col.key === key) col.visible = visible;
        return col;
    });
}

async function saveColumnVisibility() {
    try {
        await getCmCsrfToken();
        const response = await fetch('/api/columns/update-visibility.php', {
            method: 'POST',
            credentials: 'include',
            headers: cmCsrfHeaders(),
            body: JSON.stringify({
                visibility: journalColumns.reduce((acc, col) => {
                    acc[col.key] = !!col.visible;
                    return acc;
                }, {})
            })
        });

        const raw = await response.text();
        console.log('update-visibility raw response:', raw);

        let data;
        try {
            data = JSON.parse(raw);
        } catch (e) {
            throw new Error(`Invalid JSON response: ${raw}`);
        }

        if (data.success) {
            closeColumnManager();
            if (typeof loadColumnsAndRenderTable === 'function') {
                await loadColumnsAndRenderTable();
            }
        } else {
            alert(data.message || 'Failed to save columns.');
        }
    } catch (error) {
        console.error('Error saving columns:', error);
        alert('Error saving columns.');
    }
}


function handleColumnTypeChange() {
    const type = document.getElementById('customColumnType').value;
    const wrap = document.getElementById('selectOptionsWrap');
    if (wrap) {
        wrap.style.display = (type === 'select' || type === 'dropdown') ? 'block' : 'none';
    }
}

function addSelectOption() {
    const container = document.getElementById('selectOptionsList');
    if (!container) return;

    const optionCount = container.querySelectorAll('input').length + 1;
    const inputId     = `selectOption${optionCount}`;

    const option      = document.createElement('div');
    option.className  = 'option-item';
    option.innerHTML  = `
        <label for="${inputId}" class="sr-only">Option ${optionCount}</label>
        <input type="text" id="${inputId}" name="select_options[]" placeholder="Option ${optionCount}">
    `;
    container.appendChild(option);
}

function encodeOptionsForPost(optionsArray) {
    try {
        return btoa(unescape(encodeURIComponent(JSON.stringify(optionsArray))));
    } catch (e) {
        return null;
    }
}

async function createCustomColumn() {
    const nameInput = document.getElementById('customColumnName');
    const typeInput = document.getElementById('customColumnType');
    if (!nameInput || !typeInput) return;

    const name = nameInput.value.trim();
    const type = typeInput.value;

    if (!name) {
        alert('Please enter a column name');
        return;
    }

    let selectOptions = null;

    if (type === 'select' || type === 'dropdown') {
        const rawOptions = Array.from(document.querySelectorAll('#selectOptionsList input'))
            .map(input => input.value.trim())
            .filter(Boolean);

        if (rawOptions.length < 2) {
            alert('Please add at least 2 dropdown options');
            return;
        }
        selectOptions = encodeOptionsForPost(rawOptions);
    }

    try {
        await getCmCsrfToken();
        const response = await fetch('/api/columns/create-custom.php', {
            method: 'POST',
            credentials: 'include',
            headers: cmCsrfHeaders(),
            body: JSON.stringify({
                column_name:    name,
                data_type:      type,
                select_options: selectOptions
            })
        });


        const raw = await response.text();
        console.log('create-custom raw response:', raw);

        let data;
        try {
            data = JSON.parse(raw);
        } catch (e) {
            throw new Error(`Invalid JSON response: ${raw}`);
        }

        if (data.success) {
            nameInput.value = '';
            typeInput.value = 'text';

            const wrap = document.getElementById('selectOptionsWrap');
            if (wrap) wrap.style.display = 'none';

            const list = document.getElementById('selectOptionsList');
            if (list) {
                list.innerHTML = `
                    <div class="option-item">
                        <label for="selectOption1" class="sr-only">Option 1</label>
                        <input type="text" id="selectOption1" name="select_options[]" placeholder="Option 1">
                    </div>
                    <div class="option-item">
                        <label for="selectOption2" class="sr-only">Option 2</label>
                        <input type="text" id="selectOption2" name="select_options[]" placeholder="Option 2">
                    </div>
                `;
            }

            await loadColumnManager();
            if (typeof loadColumnsAndRenderTable === 'function') {
                await loadColumnsAndRenderTable();
            }
        } else {
            console.error('Create custom column failed:', data);
            alert(data.message || 'Failed to create custom column.');
        }
    } catch (error) {
        console.error('Error creating custom column:', error);
        alert('Error creating custom column.');
    }
}

async function deleteCustomColumn(id) {
    if (!confirm('Delete this custom column? This cannot be undone.')) return;

    try {
        await getCmCsrfToken();
        const response = await fetch('/api/columns/delete-custom.php', {
            method: 'POST',
            credentials: 'include',
            headers: cmCsrfHeaders(),
            body: JSON.stringify({ column_id: id })
        });

        const raw = await response.text();
        console.log('delete-custom raw response:', raw);

        let data;
        try {
            data = JSON.parse(raw);
        } catch (e) {
            throw new Error(`Invalid JSON response: ${raw}`);
        }

        if (data.success) {
            await loadColumnManager();
            if (typeof loadColumnsAndRenderTable === 'function') {
                await loadColumnsAndRenderTable();
            }
        } else {
            alert(data.message || 'Failed to delete column.');
        }
    } catch (error) {
        console.error('Error deleting column:', error);
        alert('Error deleting column.');
    }
}

// ── Preferences tab ─────────────────────────────────────────────────────────

async function loadPreferencesTab() {
    try {
        const res  = await fetch('/api/preferences/get.php', { credentials: 'include' });
        const data = await res.json();
        if (!data.success) return;

        const stopInput = document.getElementById('pref_default_stop_distance');
        if (stopInput) {
            let val = null;
            if (data.preferences && data.preferences.default_stop_distance !== undefined) {
                val = parseFloat(data.preferences.default_stop_distance);
            } else if (data.value !== undefined) {
                val = parseFloat(data.value);
            }
            if (val && val > 0) stopInput.value = val.toFixed(2);
        }
    } catch (e) {
        console.warn('Could not load preferences:', e);
    }
}

async function savePreferences() {
    const stopInput = document.getElementById('pref_default_stop_distance');
    const msgDiv    = document.getElementById('prefMessage');
    const btn       = document.getElementById('savePreferencesBtn');
    const value     = parseFloat(stopInput ? stopInput.value : '');

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
            msgDiv.textContent = '✓ Saved! Applied next time you open the trade form.';
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

// ── Shared utility ───────────────────────────────────────────────────────────

// Single shared definition — used by both column-manager.js and journal.js
if (typeof escapeHtml === 'undefined') {
    function escapeHtml(value) {
        return String(value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }
}
