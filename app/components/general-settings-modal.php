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
                <p style="color:#a9afb8;font-size:14px;">Dashboard card re-ordering logic will be migrated here.</p>
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
</script>
