<?php
// /app/components/general-settings-modal.php
?>
<style>
.general-settings-overlay {
    position: fixed;
    top: 0; left: 0; width: 100%; height: 100%;
    background: rgba(0, 0, 0, 0.7);
    backdrop-filter: blur(8px);
    z-index: 999999;
    display: none;
    align-items: center;
    justify-content: center;
    opacity: 0;
    transition: opacity 0.3s ease;
}
.general-settings-overlay.open {
    display: flex;
    opacity: 1;
}

.settings-modal-shell {
  background: linear-gradient(135deg, #1a1a1a 0%, #0E0E0E 100%);
  border: 1px solid #2a2a2a;
  border-radius: 16px;
  width: 100%;
  max-width: 960px;
  max-height: 88vh;
  overflow: hidden;
  box-shadow: 0 16px 64px rgba(0,0,0,0.6);
  position: relative;
  display: flex;
  flex-direction: column;
  transform: scale(0.95);
  transition: transform 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275);
}
.general-settings-overlay.open .settings-modal-shell {
    transform: scale(1);
}

.settings-modal-header-top {
    padding: 32px 32px 0 32px;
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
}
.settings-modal-title {
    color: #F2CA50;
    font-size: 24px;
    font-weight: 800;
    margin: 0 0 8px 0;
}
.settings-modal-subtitle {
    color: #8f95a3;
    font-size: 14px;
    margin: 0;
}
.settings-modal-close {
    background: transparent;
    border: none;
    color: #8f95a3;
    font-size: 24px;
    cursor: pointer;
    line-height: 1;
    padding: 0;
    transition: color 0.2s;
}
.settings-modal-close:hover {
    color: #fff;
}

.settings-tabs {
  display: flex;
  gap: 10px;
  padding: 24px 32px 0;
  border-bottom: 1px solid #1f1f1f;
}

.settings-tab {
  background: transparent;
  border: 1px solid #2d2d2d;
  color: #999;
  min-height: 42px;
  padding: 10px 24px;
  border-radius: 10px 10px 0 0;
  font-size: 12px;
  font-weight: 700;
  letter-spacing: 0.08em;
  text-transform: uppercase;
  cursor: pointer;
  transition: all 0.2s ease;
  margin-bottom: -1px;
}

.settings-tab.active,
.settings-tab:hover {
  color: #F2CA50;
  border-color: rgba(242,202,80,0.35);
  background: rgba(242,202,80,0.06);
  border-bottom-color: #1a1a1a;
}

.settings-modal-body {
  padding: 32px;
  max-height: calc(88vh - 180px);
  overflow-y: auto;
  position: relative;
}

.settings-panel {
  display: none;
  animation: fadeIn 0.3s ease;
}
.settings-panel.active {
  display: block;
}

@keyframes fadeIn {
    from { opacity: 0; transform: translateY(10px); }
    to { opacity: 1; transform: translateY(0); }
}

/* Form Styles */
.gs-form-group {
    margin-bottom: 24px;
    max-width: 400px;
}
.gs-form-label {
    display: block;
    font-size: 11px;
    font-weight: 700;
    letter-spacing: 0.05em;
    color: #999;
    margin-bottom: 8px;
    text-transform: uppercase;
}
.gs-form-control {
    width: 100%;
    background: rgba(0,0,0,0.2);
    border: 1px solid #2d2d2d;
    padding: 14px;
    border-radius: 8px;
    color: #fff;
    font-size: 14px;
    transition: all 0.2s;
}
.gs-form-control:focus {
    outline: none;
    border-color: rgba(242,202,80,0.5);
    background: rgba(242,202,80,0.02);
}
.gs-btn-primary {
    background: #f2ca50;
    color: #111;
    border: none;
    padding: 12px 24px;
    border-radius: 8px;
    font-size: 13px;
    font-weight: 700;
    cursor: pointer;
    transition: opacity 0.2s;
    letter-spacing: 0.05em;
    text-transform: uppercase;
}
.gs-btn-primary:hover {
    opacity: 0.9;
}

/* Dashboard Sorting CSS */
.dsp-sort-list {
    list-style: none;
    margin: 0;
    padding: 0;
    display: flex;
    flex-direction: column;
    gap: 6px;
    max-width: 400px;
}
.dsp-drag-handle {
    cursor: grab;
    color: #444;
    flex-shrink: 0;
    display: flex;
    align-items: center;
    padding: 2px 2px 2px 0;
    transition: color 0.15s;
}
.dsp-drag-handle:active { cursor: grabbing; }
.dsp-sort-item:hover .dsp-drag-handle { color: #888; }
.dsp-sort-item.drag-over {
    border-color: #f0c24f;
    background: #2a2700;
    transform: scale(1.01);
}
.dsp-sort-item.dragging {
    opacity: 0.35;
    border-style: dashed;
}
.dsp-presets {
    display: flex;
    gap: 6px;
    flex-wrap: wrap;
    margin-bottom: 20px;
    max-width: 400px;
}
.dsp-preset-btn {
    flex: 1;
    min-width: 0;
    background: #1c1c1c;
    border: 1px solid #333;
    border-radius: 6px;
    color: #888;
    font-family: 'Montserrat', sans-serif;
    font-size: 10px;
    font-weight: 700;
    letter-spacing: 0.07em;
    text-transform: uppercase;
    padding: 8px 6px;
    cursor: pointer;
    transition: border-color 0.15s, color 0.15s, background 0.15s;
    text-align: center;
}
.dsp-preset-btn:hover {
    border-color: #f0c24f;
    color: #f0c24f;
    background: #1f1d00;
}
.dsp-sort-item {
    display: flex;
    align-items: center;
    gap: 8px;
    background: #242424;
    border: 1px solid #2e2e2e;
    border-radius: 6px;
    padding: 10px 12px;
    font-family: 'Montserrat', sans-serif;
    font-size: 11px;
    font-weight: 600;
    letter-spacing: 0.05em;
    color: #bbb;
    transition: border-color 0.15s, background 0.15s, transform 0.12s;
    user-select: none;
    text-transform: uppercase;
}
.dsp-sort-label {
    flex: 1;
}
.dsp-move-btn {
    background: none;
    border: none;
    color: #444;
    cursor: pointer;
    padding: 2px 4px;
    line-height: 1;
    border-radius: 3px;
    transition: color 0.15s, background 0.15s;
    display: flex;
    align-items: center;
}
.dsp-move-btn:hover {
    color: #f0c24f;
    background: rgba(240,194,79,0.08);
}
.dsp-move-btn:disabled {
    opacity: 0.2;
    cursor: not-allowed;
}
</style>

<div class="general-settings-overlay" id="globalGeneralSettingsOverlay">
    <div class="settings-modal-shell" onclick="event.stopPropagation()">
        
        <div class="settings-modal-header-top">
            <div>
                <h3 class="settings-modal-title">Settings</h3>
                <p class="settings-modal-subtitle">Manage preferences and customize your platform experience.</p>
            </div>
            <button class="settings-modal-close" onclick="closeGlobalSettingsModal()">&times;</button>
        </div>

        <div class="settings-tabs" role="tablist">
            <button class="settings-tab active" onclick="switchGlobalSettingsTab('general')">General</button>
            <button class="settings-tab" onclick="switchGlobalSettingsTab('dashboard')">Dashboard</button>
            <button class="settings-tab" onclick="switchGlobalSettingsTab('journal')">Journal</button>
            <button class="settings-tab" onclick="switchGlobalSettingsTab('account')">Account</button>
        </div>

        <div class="settings-modal-body">
            <!-- GENERAL TAB -->
            <div class="settings-panel active" id="gs-pane-general">
                <form id="gsFormGeneral" onsubmit="saveGlobalSettingsGeneral(event)">
                    <div class="gs-form-group">
                        <label class="gs-form-label">Timezone</label>
                        <select class="gs-form-control" name="timezone" id="gsTimezone">
                            <!-- Populated via JS -->
                        </select>
                        <div style="font-size:12px;color:#8f95a3;margin-top:8px;">All times across the platform will be displayed in this timezone.</div>
                    </div>
                    <div class="gs-form-group">
                        <label class="gs-form-label">Date Format</label>
                        <select class="gs-form-control" name="date_format" id="gsDateFormat">
                            <option value="Y-m-d">YYYY-MM-DD (2026-10-01)</option>
                            <option value="d/m/Y">DD/MM/YYYY (01/10/2026)</option>
                            <option value="m/d/Y">MM/DD/YYYY (10/01/2026)</option>
                            <option value="F j, Y">Month D, YYYY (October 1, 2026)</option>
                        </select>
                    </div>
                    <div class="gs-form-group">
                        <label class="gs-form-label">Time Format</label>
                        <select class="gs-form-control" name="time_format" id="gsTimeFormat">
                            <option value="H:i">24-hour (14:30)</option>
                            <option value="h:i A">12-hour (02:30 PM)</option>
                        </select>
                    </div>
                    <button type="submit" class="gs-btn-primary" id="gsBtnGeneral">Save Preferences</button>
                    <span id="gsStatusGeneral" style="margin-left:12px;font-size:13px;"></span>
                </form>
            </div>

            <!-- DASHBOARD TAB -->
            <div class="settings-panel" id="gs-pane-dashboard">
                <h3 style="color:#fff;margin-top:0;">Dashboard Layout</h3>
                <p style="color:#a9afb8;font-size:14px;">Drag and drop to reorder your dashboard cards.</p>
                <div>
                    <ul class="dsp-sort-list" id="gsDspSortList">
                        <!-- populated by JS -->
                    </ul>
                </div>
                <div class="dsp-actions" style="margin-top:20px;">
                    <button class="gs-btn-primary" style="background:transparent;border:1px solid #333;color:#fff;margin-right:12px;" onclick="gsResetDashboardOrder()">Reset Default</button>
                    <button class="gs-btn-primary" onclick="gsApplyDashboardOrder()">Apply Order</button>
                    <span id="gsStatusDashboard" style="margin-left:12px;font-size:13px;"></span>
                </div>
            </div>

            <!-- JOURNAL TAB -->
            <div class="settings-panel" id="gs-pane-journal">
                <h3 style="color:#fff;margin-top:0;">Journal Settings</h3>
                <p style="color:#a9afb8;font-size:14px;">Journal manager and column layouts will be migrated here.</p>
            </div>

            <!-- ACCOUNT TAB -->
            <div class="settings-panel" id="gs-pane-account">
                <h3 style="color:#fff;margin-top:0;">Account Profile</h3>
                <p style="color:#a9afb8;font-size:14px;">Profile and notification settings will be migrated here.</p>
            </div>
        </div>
    </div>
</div>

<script>
const allTimezones = Intl.supportedValuesOf ? Intl.supportedValuesOf('timeZone') : ['UTC'];

function openGlobalSettingsModal(tab = 'general') {
    document.getElementById('globalGeneralSettingsOverlay').classList.add('open');
    switchGlobalSettingsTab(tab);
    loadGlobalSettingsGeneral();
    
    // Load dashboard order
    gsLoadOrder().then(order => {
        gsBuildSettingsList(order);
    });
}

function closeGlobalSettingsModal() {
    document.getElementById('globalGeneralSettingsOverlay').classList.remove('open');
}

document.getElementById('globalGeneralSettingsOverlay').addEventListener('click', function(e) {
    if (e.target === this) {
        closeGlobalSettingsModal();
    }
});

function switchGlobalSettingsTab(tabId) {
    document.querySelectorAll('#globalGeneralSettingsOverlay .settings-tab').forEach(el => {
        el.classList.remove('active');
        if (el.getAttribute('onclick').includes(tabId)) el.classList.add('active');
    });
    document.querySelectorAll('#globalGeneralSettingsOverlay .settings-panel').forEach(el => el.classList.remove('active'));
    const pane = document.getElementById('gs-pane-' + tabId);
    if (pane) pane.classList.add('active');
}

function populateTimezones(selected) {
    const tzSelect = document.getElementById('gsTimezone');
    tzSelect.innerHTML = '';
    
    // Add common defaults at top if desired, or just list all
    allTimezones.forEach(tz => {
        const option = document.createElement('option');
        option.value = tz;
        option.textContent = tz.replace(/_/g, ' ');
        if (tz === selected) option.selected = true;
        tzSelect.appendChild(option);
    });
}

async function loadGlobalSettingsGeneral() {
    try {
        const res = await fetch('/api/user/settings.php');
        const data = await res.json();
        if (data.success && data.settings) {
            populateTimezones(data.settings.timezone);
            document.getElementById('gsDateFormat').value = data.settings.date_format;
            document.getElementById('gsTimeFormat').value = data.settings.time_format;
        } else {
            populateTimezones('UTC'); // Fallback
        }
    } catch(e) {
        populateTimezones('UTC');
    }
}

async function saveGlobalSettingsGeneral(e) {
    e.preventDefault();
    const btn = document.getElementById('gsBtnGeneral');
    const status = document.getElementById('gsStatusGeneral');
    const originalText = btn.textContent;
    btn.textContent = 'Saving...';
    btn.disabled = true;
    status.textContent = '';
    
    try {
        const payload = {
            timezone: document.getElementById('gsTimezone').value,
            date_format: document.getElementById('gsDateFormat').value,
            time_format: document.getElementById('gsTimeFormat').value
        };
        const res = await fetch('/api/user/settings.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify(payload)
        });
        const data = await res.json();
        if (data.success) {
            status.style.color = '#28a745';
            status.textContent = 'Saved successfully!';
        } else {
            throw new Error(data.message || 'Error saving settings');
        }
    } catch(e) {
        status.style.color = '#ff5b5b';
        status.textContent = e.message;
    } finally {
        btn.textContent = originalText;
        btn.disabled = false;
        setTimeout(() => { status.textContent = ''; }, 3000);
    }
}

// ==========================================
// DASHBOARD LOGIC
// ==========================================
const GS_DEFAULT_ORDER = ['market','signals','news','classroom','strategies','trades','mentors','ai','chat','journal'];
const GS_PRESETS = {
    default:  ['market','signals','news','classroom','strategies','trades','mentors','ai','chat','journal'],
    trading:  ['market','signals','trades','strategies','mentors','chat','news','classroom','ai','journal'],
    research: ['market','news','signals','classroom','ai','strategies','research','mentors','trades','chat','journal'].filter(id => GS_DEFAULT_ORDER.includes(id)),
};
const GS_PRESET_LABELS = {
    default:  'Default',
    trading:  '📈 Trading',
    research: '🔬 Research',
};
const GS_CARD_LABELS = {
    market:     'Market',
    signals:    'Signals',
    news:       'News',
    classroom:  'Classroom',
    strategies: 'Strategies',
    trades:     'My Trades',
    mentors:    'Mentors',
    ai:         'AI Chat',
    chat:       'Chat',
    journal:    'Journal'
};

function gsNormalizeOrder(order) {
    const unique = [...new Set((Array.isArray(order) ? order : []).filter(id => GS_DEFAULT_ORDER.includes(id)))];
    return [...unique, ...GS_DEFAULT_ORDER.filter(id => !unique.includes(id))];
}

async function gsLoadOrder() {
    try {
        const res  = await fetch('/api/dashboard/load-layout.php', { credentials: 'include' });
        const data = await res.json();
        if (data.success && Array.isArray(data.order)) {
            const saved   = data.order.filter(id => GS_DEFAULT_ORDER.includes(id));
            const missing = GS_DEFAULT_ORDER.filter(id => !saved.includes(id));
            return [...saved, ...missing];
        }
    } catch(e) {}
    return [...GS_DEFAULT_ORDER];
}

async function gsSaveOrder(order) {
    const normalized = gsNormalizeOrder(order);
    try {
        // Find CSRF token dynamically if present on page
        const metaCsrf = document.querySelector('meta[name="csrf-token"]');
        const csrfToken = metaCsrf ? metaCsrf.getAttribute('content') : (window.CSRF_TOKEN || '');
        const headers = { 'Content-Type': 'application/json' };
        if (csrfToken) headers['X-CSRF-Token'] = csrfToken;

        const res = await fetch('/api/dashboard/save-layout.php', {
            method: 'POST',
            credentials: 'include',
            headers: headers,
            body: JSON.stringify({ order: normalized })
        });
        const data = await res.json().catch(() => ({}));
        if (!res.ok || data.success === false) throw new Error(data.message || 'Save failed');
        return true;
    } catch(e) {
        console.error('Failed to save dashboard layout', e);
        return false;
    }
}

function gsBuildSettingsList(order) {
    const list = document.getElementById('gsDspSortList');
    if (!list) return;

    let presetsEl = document.getElementById('gsDspPresets');
    if (!presetsEl) {
        presetsEl = document.createElement('div');
        presetsEl.id = 'gsDspPresets';
        presetsEl.className = 'dsp-presets';
        list.parentElement.insertBefore(presetsEl, list);

        Object.entries(GS_PRESET_LABELS).forEach(([key, label]) => {
            const btn = document.createElement('button');
            btn.className = 'dsp-preset-btn';
            btn.textContent = label;
            btn.addEventListener('click', (e) => {
                e.preventDefault();
                gsBuildSettingsList([...GS_PRESETS[key]]);
            });
            presetsEl.appendChild(btn);
        });
    }

    list.innerHTML = '';
    let dragSrc = null;

    order.forEach((id, idx) => {
        const li = document.createElement('li');
        li.className = 'dsp-sort-item';
        li.dataset.cardId = id;
        li.draggable = true;

        const upDisabled   = idx === 0 ? 'disabled' : '';
        const downDisabled = idx === order.length - 1 ? 'disabled' : '';

        li.innerHTML = `
            <span class="dsp-drag-handle" aria-hidden="true">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <circle cx="9" cy="5" r="1" fill="currentColor"/><circle cx="15" cy="5" r="1" fill="currentColor"/>
                    <circle cx="9" cy="12" r="1" fill="currentColor"/><circle cx="15" cy="12" r="1" fill="currentColor"/>
                    <circle cx="9" cy="19" r="1" fill="currentColor"/><circle cx="15" cy="19" r="1" fill="currentColor"/>
                </svg>
            </span>
            <span class="dsp-sort-label" style="flex:1">${GS_CARD_LABELS[id] || id}</span>
            <button class="dsp-move-btn" data-dir="up" aria-label="Move up" ${upDisabled} onclick="event.preventDefault()">
                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                    <polyline points="18 15 12 9 6 15"/>
                </svg>
            </button>
            <button class="dsp-move-btn" data-dir="down" aria-label="Move down" ${downDisabled} onclick="event.preventDefault()">
                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                    <polyline points="6 9 12 15 18 9"/>
                </svg>
            </button>
        `;

        li.querySelectorAll('.dsp-move-btn').forEach(btn => {
            btn.addEventListener('click', (e) => {
                e.preventDefault();
                if (btn.disabled) return;
                gsMoveItem(btn.dataset.dir === 'up' ? -1 : 1, li);
            });
        });

        li.addEventListener('dragstart', e => {
            dragSrc = li;
            li.classList.add('dragging');
            e.dataTransfer.effectAllowed = 'move';
            e.dataTransfer.setData('text/plain', id);
        });
        li.addEventListener('dragend', () => {
            li.classList.remove('dragging');
            list.querySelectorAll('.dsp-sort-item').forEach(el => el.classList.remove('drag-over'));
        });
        li.addEventListener('dragover', e => {
            e.preventDefault();
            e.dataTransfer.dropEffect = 'move';
            if (dragSrc && dragSrc !== li) li.classList.add('drag-over');
        });
        li.addEventListener('dragleave', () => li.classList.remove('drag-over'));
        li.addEventListener('drop', e => {
            e.preventDefault();
            li.classList.remove('drag-over');
            if (!dragSrc || dragSrc === li) return;
            const items = Array.from(list.children);
            const fromIdx = items.indexOf(dragSrc);
            const toIdx   = items.indexOf(li);
            if (fromIdx < toIdx) list.insertBefore(dragSrc, li.nextSibling);
            else                 list.insertBefore(dragSrc, li);
            const newOrder = Array.from(list.children).map(el => el.dataset.cardId);
            gsBuildSettingsList(newOrder);
        });

        list.appendChild(li);
    });
}

function gsMoveItem(direction, li) {
    const list = li.parentElement;
    const items = Array.from(list.children);
    const idx = items.indexOf(li);
    const targetIdx = idx + direction;
    if (targetIdx < 0 || targetIdx >= items.length) return;

    if (direction === -1) {
        list.insertBefore(li, items[targetIdx]);
    } else {
        list.insertBefore(items[targetIdx], li);
    }
    const newOrder = Array.from(list.children).map(el => el.dataset.cardId);
    gsBuildSettingsList(newOrder);
}

function gsGetSettingsOrder() {
    return Array.from(document.querySelectorAll('#gsDspSortList .dsp-sort-item'))
                .map(li => li.dataset.cardId);
}

async function gsApplyDashboardOrder() {
    const status = document.getElementById('gsStatusDashboard');
    status.style.color = '#fff';
    status.textContent = 'Saving...';
    const newOrder = gsNormalizeOrder(gsGetSettingsOrder());
    const saved = await gsSaveOrder(newOrder);
    if (!saved) {
        status.style.color = '#ff5b5b';
        status.textContent = 'Error saving layout.';
        return;
    }
    status.style.color = '#28a745';
    status.textContent = 'Layout saved!';
    setTimeout(() => { status.textContent = ''; }, 3000);
    
    // Apply locally if on dashboard
    if (typeof applyOrderToGrid === 'function') {
        applyOrderToGrid(newOrder);
    } else {
        // If not on dashboard, maybe offer to reload or just say saved
        status.textContent = 'Layout saved (will apply on Dashboard page).';
    }
}

function gsResetDashboardOrder() {
    gsBuildSettingsList([...GS_DEFAULT_ORDER]);
}
</script>
