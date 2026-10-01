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
.general-settings-modal {
    background: #1e2025;
    border: 1px solid #333;
    border-radius: 16px;
    width: 900px;
    max-width: 95%;
    height: 600px;
    max-height: 90vh;
    display: flex;
    box-shadow: 0 20px 50px rgba(0,0,0,0.5);
    overflow: hidden;
    transform: scale(0.95);
    transition: transform 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275);
}
.general-settings-overlay.open .general-settings-modal {
    transform: scale(1);
}
.general-settings-sidebar {
    width: 250px;
    background: rgba(0,0,0,0.2);
    border-right: 1px solid rgba(255,255,255,0.05);
    padding: 24px 16px;
    display: flex;
    flex-direction: column;
}
.general-settings-title {
    font-size: 20px;
    font-weight: 800;
    color: #fff;
    margin-bottom: 24px;
    padding-left: 12px;
}
.general-settings-tab {
    background: none;
    border: none;
    color: #8f95a3;
    font-size: 14px;
    font-weight: 600;
    text-align: left;
    padding: 12px;
    border-radius: 8px;
    cursor: pointer;
    transition: all 0.2s;
    margin-bottom: 4px;
    display: flex;
    align-items: center;
    gap: 12px;
}
.general-settings-tab:hover {
    background: rgba(255,255,255,0.05);
    color: #fff;
}
.general-settings-tab.active {
    background: rgba(242, 202, 80, 0.1);
    color: #f2ca50;
}
.general-settings-content {
    flex: 1;
    padding: 32px;
    overflow-y: auto;
    position: relative;
}
.general-settings-close {
    position: absolute;
    top: 24px;
    right: 24px;
    background: rgba(255,255,255,0.05);
    border: none;
    color: #a9afb8;
    width: 32px;
    height: 32px;
    border-radius: 50%;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.2s;
}
.general-settings-close:hover {
    background: rgba(255,255,255,0.1);
    color: #fff;
}
.general-settings-pane {
    display: none;
    animation: fadeIn 0.3s ease;
}
.general-settings-pane.active {
    display: block;
}
@keyframes fadeIn {
    from { opacity: 0; transform: translateY(10px); }
    to { opacity: 1; transform: translateY(0); }
}

/* Form Styles */
.gs-form-group {
    margin-bottom: 24px;
}
.gs-form-label {
    display: block;
    font-size: 13px;
    font-weight: 600;
    color: #a9afb8;
    margin-bottom: 8px;
}
.gs-form-control {
    width: 100%;
    background: rgba(0,0,0,0.2);
    border: 1px solid rgba(255,255,255,0.1);
    padding: 12px;
    border-radius: 8px;
    color: #fff;
    font-size: 14px;
    transition: border-color 0.2s;
}
.gs-form-control:focus {
    outline: none;
    border-color: #f2ca50;
}
.gs-btn-primary {
    background: #f2ca50;
    color: #000;
    border: none;
    padding: 10px 20px;
    border-radius: 8px;
    font-weight: 700;
    cursor: pointer;
    transition: opacity 0.2s;
}
.gs-btn-primary:hover {
    opacity: 0.9;
}
</style>

<div class="general-settings-overlay" id="globalGeneralSettingsOverlay">
    <div class="general-settings-modal" onclick="event.stopPropagation()">
        <div class="general-settings-sidebar">
            <div class="general-settings-title">Settings</div>
            <button class="general-settings-tab active" onclick="switchGlobalSettingsTab('general')">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="3"></circle><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"></path></svg>
                General
            </button>
            <button class="general-settings-tab" onclick="switchGlobalSettingsTab('dashboard')">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="9"></rect><rect x="14" y="3" width="7" height="5"></rect><rect x="14" y="12" width="7" height="9"></rect><rect x="3" y="16" width="7" height="5"></rect></svg>
                Dashboard
            </button>
            <button class="general-settings-tab" onclick="switchGlobalSettingsTab('journal')">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"></path><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"></path></svg>
                Journal
            </button>
            <button class="general-settings-tab" onclick="switchGlobalSettingsTab('account')">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle></svg>
                Account
            </button>
        </div>
        <div class="general-settings-content">
            <button class="general-settings-close" onclick="closeGlobalSettingsModal()">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>
            </button>

            <!-- GENERAL TAB -->
            <div class="general-settings-pane active" id="gs-pane-general">
                <h2 style="margin-top:0;margin-bottom:24px;font-size:24px;color:#fff;">General Preferences</h2>
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
            <div class="general-settings-pane" id="gs-pane-dashboard">
                <h2 style="margin-top:0;margin-bottom:24px;font-size:24px;color:#fff;">Dashboard Layout</h2>
                <p style="color:#a9afb8;font-size:14px;">We are migrating the dashboard card re-ordering logic here soon.</p>
                <!-- This will be populated from dashboard/index.php later -->
            </div>

            <!-- JOURNAL TAB -->
            <div class="general-settings-pane" id="gs-pane-journal">
                <h2 style="margin-top:0;margin-bottom:24px;font-size:24px;color:#fff;">Journal Settings</h2>
                <p style="color:#a9afb8;font-size:14px;">Journal layout and preferences will be migrated here.</p>
            </div>

            <!-- ACCOUNT TAB -->
            <div class="general-settings-pane" id="gs-pane-account">
                <h2 style="margin-top:0;margin-bottom:24px;font-size:24px;color:#fff;">Account Settings</h2>
                <p style="color:#a9afb8;font-size:14px;">Profile details, notifications, and security settings will be migrated here.</p>
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
    document.querySelectorAll('.general-settings-tab').forEach(el => {
        el.classList.remove('active');
        if (el.getAttribute('onclick').includes(tabId)) el.classList.add('active');
    });
    document.querySelectorAll('.general-settings-pane').forEach(el => el.classList.remove('active'));
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
            status.textContent = 'Settings saved successfully!';
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
