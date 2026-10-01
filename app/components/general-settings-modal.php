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

/* Journal Settings CSS */
.journal-manager-form {
  display: flex;
  gap: 16px;
  align-items: end;
  margin-bottom: 20px;
  flex-wrap: wrap;
}
.journals-list {
  display: flex;
  flex-direction: column;
  gap: 12px;
}
.journal-item {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 16px;
  padding: 16px 18px;
  border: 1px solid #252525;
  border-radius: 12px;
  background: rgba(255,255,255,0.02);
}
.journal-item.active {
  border-color: rgba(242,202,80,0.35);
  background: rgba(242,202,80,0.05);
}
.journal-item-title {
  display: flex;
  align-items: center;
  flex-wrap: wrap;
  gap: 8px;
  color: #f4f4f4;
  font-size: 14px;
  font-weight: 700;
  margin-bottom: 6px;
}
.journal-item-meta {
  color: #888;
  font-size: 12px;
}
.journal-item-actions {
  display: flex;
  gap: 8px;
  flex-wrap: wrap;
}
.column-badge {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  min-height: 22px;
  padding: 4px 10px;
  background: rgba(242,202,80,0.12);
  border: 1px solid rgba(242,202,80,0.22);
  border-radius: 999px;
  color: #F2CA50;
  font-size: 10px;
  font-weight: 700;
  letter-spacing: 0.08em;
  text-transform: uppercase;
}
.form-field {
  display: flex;
  flex-direction: column;
  gap: 8px;
  flex: 1;
  min-width: 220px;
}
.form-field label {
  font-size: 12px;
  font-weight: 600;
  color: #999;
  letter-spacing: 0.04em;
  text-transform: uppercase;
}
.form-field input,
.form-field select {
  min-height: 48px;
  padding: 12px 14px;
  background: #111;
  border: 1px solid #333;
  border-radius: 8px;
  color: #f4f4f4;
  font-size: 13px;
  font-weight: 500;
  font-family: "Montserrat", sans-serif;
}
.form-field input:focus,
.form-field select:focus {
  outline: none;
  border-color: #F2CA50;
  box-shadow: 0 0 0 3px rgba(242,202,80,0.12);
}
.btn-action {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  min-height: 32px;
  padding: 6px 12px;
  background: transparent;
  border: 1px solid #333;
  border-radius: 6px;
  color: #aaa;
  font-size: 11px;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.2s ease;
  margin-right: 6px;
  margin-bottom: 4px;
  white-space: nowrap;
}
.btn-action:hover {
  border-color: #F2CA50;
  color: #F2CA50;
  background: rgba(242,202,80,0.05);
}
.column-manager-section {
    margin-bottom: 32px;
}
.section-header {
    display: flex;
    align-items: center;
    gap: 12px;
    margin-bottom: 16px;
}
.section-title {
    color: #fff;
    margin: 0;
    font-size: 18px;
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
            <button class="settings-tab active" onclick="switchGlobalSettingsTab('dashboard')">Dashboard</button>
            <button class="settings-tab" onclick="switchGlobalSettingsTab('journal')">Journal</button>
            <button class="settings-tab" onclick="switchGlobalSettingsTab('preferences')">Preferences</button>
            <button class="settings-tab" onclick="switchGlobalSettingsTab('notifications')">Notifications</button>
        </div>

        <div class="settings-modal-body">
            <!-- DASHBOARD TAB -->
            <div class="settings-panel active" id="gs-pane-dashboard">
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
                        <button type="button" class="gs-btn-primary" onclick="createJournal()">Create Journal</button>
                    </div>

                    <div class="journals-list" id="journalsList">
                        <div class="column-item">
                            <span class="column-name">Loading journals...</span>
                        </div>
                    </div>
                </div>

                <div class="column-manager-section" style="margin-top:40px; border-top:1px solid #333; padding-top:40px;">
                    <div class="section-header">
                        <div class="section-icon">📋</div>
                        <h4 class="section-title">Visible Columns</h4>
                    </div>
                    <div class="columns-list" id="columnsList" style="margin-bottom:20px;">
                        <div class="column-item">
                            <span class="column-name">Loading columns...</span>
                        </div>
                    </div>
                    <button class="gs-btn-primary" type="button" onclick="saveColumnVisibility()">Save Columns</button>
                </div>

            </div>

            <!-- PREFERENCES TAB -->
            <div class="settings-panel" id="gs-pane-preferences">
                <h3 style="color:#fff;margin-top:0;">Preferences</h3>
                <p style="color:#a9afb8;font-size:14px;margin-bottom:24px;">Customise your trading defaults</p>
                
                <form id="gsFormGeneral" onsubmit="saveGlobalSettingsGeneral(event)">
                    <div class="column-manager-section">
                        <h4 class="section-title" style="margin-bottom:20px; font-size:11px; letter-spacing:0.05em; text-transform:uppercase; color:#8f95a3;">Localization</h4>
                        <div style="display:flex; gap:20px; flex-wrap:wrap; margin-bottom:20px;">
                            <div class="gs-form-group" style="flex:1; min-width:200px; margin-bottom:0;">
                                <label class="gs-form-label" style="font-size:10px; font-weight:700; letter-spacing:0.05em; text-transform:uppercase; color:#8f95a3;">Timezone</label>
                                <select class="gs-form-control" name="timezone" id="gsTimezone" style="background:#1c1c1c; border:1px solid #333; color:#f4f4f4; border-radius:6px; padding:10px; width:100%;">
                                    <!-- Populated via JS -->
                                </select>
                                <div style="font-size:12px;color:#8f95a3;margin-top:8px;">All times across the platform will be displayed in this timezone.</div>
                            </div>
                            <div class="gs-form-group" style="flex:1; min-width:200px; margin-bottom:0;">
                                <label class="gs-form-label" style="font-size:10px; font-weight:700; letter-spacing:0.05em; text-transform:uppercase; color:#8f95a3;">Date Format</label>
                                <select class="gs-form-control" name="date_format" id="gsDateFormat" style="background:#1c1c1c; border:1px solid #333; color:#f4f4f4; border-radius:6px; padding:10px; width:100%;">
                                    <option value="Y-m-d">YYYY-MM-DD (2026-10-01)</option>
                                    <option value="d/m/Y">DD/MM/YYYY (01/10/2026)</option>
                                    <option value="m/d/Y">MM/DD/YYYY (10/01/2026)</option>
                                    <option value="F j, Y">Month D, YYYY (October 1, 2026)</option>
                                </select>
                            </div>
                            <div class="gs-form-group" style="flex:1; min-width:200px; margin-bottom:0;">
                                <label class="gs-form-label" style="font-size:10px; font-weight:700; letter-spacing:0.05em; text-transform:uppercase; color:#8f95a3;">Time Format</label>
                                <select class="gs-form-control" name="time_format" id="gsTimeFormat" style="background:#1c1c1c; border:1px solid #333; color:#f4f4f4; border-radius:6px; padding:10px; width:100%;">
                                    <option value="H:i">24-hour (14:30)</option>
                                    <option value="h:i A">12-hour (02:30 PM)</option>
                                </select>
                            </div>
                        </div>
                        <button type="submit" class="gs-btn-primary" id="gsBtnGeneral" style="color:#111; font-weight:700;">Save Localization</button>
                        <span id="gsStatusGeneral" style="margin-left:12px;font-size:13px;"></span>
                    </div>
                </form>

                <div class="column-manager-section" style="margin-top:40px; border-top:1px solid #2e2e2e; padding-top:40px;">
                    <h4 class="section-title" style="margin-bottom:20px; font-size:11px; letter-spacing:0.05em; text-transform:uppercase; color:#8f95a3;">Trade Defaults</h4>
                    <div style="display:flex; gap:20px; flex-wrap:wrap;">
                        <div class="form-field" style="flex:1; min-width:200px;">
                            <label for="pref_default_stop_distance">Default Stop Distance (%)</label>
                            <input type="number" id="pref_default_stop_distance" step="0.01" min="0.01" max="100" placeholder="0.60">
                            <small style="color: #8f95a3; margin-top: 6px; display: block;">Pre-fills the stop % field when logging a new trade.</small>
                        </div>
                        <div class="form-field" style="flex:1; min-width:200px;">
                            <label for="pref_default_direction">Default Direction</label>
                            <select id="pref_default_direction">
                                <option value="">No default</option>
                                <option value="long">Long</option>
                                <option value="short">Short</option>
                            </select>
                        </div>
                    </div>
                    <div class="form-field" style="margin-top:20px;">
                        <label for="pref_default_session">Default Session</label>
                        <select id="pref_default_session">
                            <option value="">No default</option>
                            <option value="London">London</option>
                            <option value="New York">New York</option>
                            <option value="Asian">Asian</option>
                        </select>
                    </div>
                    
                    <button type="button" class="gs-btn-primary" onclick="savePreferences()" style="margin-top:20px; color:#111; font-weight:700;">Save Preferences</button>
                    <div id="prefMessage" class="form-message" style="display: none; margin-top: 16px; max-width: 320px; color:#f2ca50; font-size:13px;"></div>
                </div>

                <div class="column-manager-section" style="margin-top:40px; border-top:1px solid #2e2e2e; padding-top:40px;">
                    <h4 class="section-title" style="margin-bottom:20px; font-size:11px; letter-spacing:0.05em; text-transform:uppercase; color:#8f95a3;">Display</h4>
                    
                    <div style="display:flex; flex-direction:column; gap:20px;">
                        <label style="display:flex; flex-direction:column; gap:4px; cursor:pointer;">
                            <div style="display:flex; align-items:center; gap:10px;">
                                <input type="checkbox" id="pref_show_pl_currency" style="accent-color:#F2CA50;">
                                <span style="color:#f4f4f4; font-size:14px; font-weight:600;">Show P&L in currency</span>
                            </div>
                            <span style="color:#8f95a3; font-size:12px; margin-left:24px;">Display dollar amounts alongside percentages</span>
                        </label>
                        
                        <label style="display:flex; flex-direction:column; gap:4px; cursor:pointer;">
                            <div style="display:flex; align-items:center; gap:10px;">
                                <input type="checkbox" id="pref_compact_rows" style="accent-color:#F2CA50;">
                                <span style="color:#f4f4f4; font-size:14px; font-weight:600;">Compact trade rows</span>
                            </div>
                            <span style="color:#8f95a3; font-size:12px; margin-left:24px;">Show more trades per screen in the journal</span>
                        </label>

                        <label style="display:flex; flex-direction:column; gap:4px; cursor:pointer;">
                            <div style="display:flex; align-items:center; gap:10px;">
                                <input type="checkbox" id="pref_auto_calc_pl" style="accent-color:#F2CA50;">
                                <span style="color:#f4f4f4; font-size:14px; font-weight:600;">Auto-calculate P&L</span>
                            </div>
                            <span style="color:#8f95a3; font-size:12px; margin-left:24px;">Automatically fill P&L when entry and exit are set</span>
                        </label>
                    </div>
                </div>
            </div>

            <!-- NOTIFICATIONS TAB -->
            <div class="settings-panel" id="gs-pane-notifications">
                <h3 style="color:#fff;margin-top:0;">Notifications</h3>
                <p style="color:#a9afb8;font-size:14px;margin-bottom:24px;">Control what alerts you receive</p>
                
                <style>
                    .notif-section { margin-top: 20px; padding: 24px; border: 1px solid #2e2e2e; border-radius: 12px; background: #1a1a1a; }
                    .notif-section h4 { font-size: 11px; letter-spacing: 0.05em; text-transform: uppercase; color: #8f95a3; margin: 0 0 20px 0; }
                    .notif-item { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
                    .notif-item:last-child { margin-bottom: 0; }
                    .notif-item-text { display: flex; flex-direction: column; gap: 4px; }
                    .notif-item-title { color: #f4f4f4; font-size: 14px; font-weight: 600; }
                    .notif-item-desc { color: #8f95a3; font-size: 12px; }
                    .notif-toggle { position: relative; display: inline-block; width: 40px; height: 20px; }
                    .notif-toggle input { opacity: 0; width: 0; height: 0; }
                    .notif-slider { position: absolute; cursor: pointer; top: 0; left: 0; right: 0; bottom: 0; background-color: #333; transition: .4s; border-radius: 20px; }
                    .notif-slider:before { position: absolute; content: ""; height: 14px; width: 14px; left: 3px; bottom: 3px; background-color: #888; transition: .4s; border-radius: 50%; }
                    .notif-toggle input:checked + .notif-slider { background-color: #3a3215; }
                    .notif-toggle input:checked + .notif-slider:before { transform: translateX(20px); background-color: #F2CA50; }
                </style>

                <!-- TRADING FLOOR -->
                <div class="notif-section">
                    <h4>Trading Floor</h4>
                    <label class="notif-item">
                        <div class="notif-item-text">
                            <span class="notif-item-title">New followers</span>
                            <span class="notif-item-desc">When someone follows you on the Trading Floor</span>
                        </div>
                        <div class="notif-toggle"><input type="checkbox" id="gsNotifFollowers"><span class="notif-slider"></span></div>
                    </label>
                    <label class="notif-item">
                        <div class="notif-item-text">
                            <span class="notif-item-title">Post likes</span>
                            <span class="notif-item-desc">When someone likes your trade post</span>
                        </div>
                        <div class="notif-toggle"><input type="checkbox" id="gsNotifLikes"><span class="notif-slider"></span></div>
                    </label>
                    <label class="notif-item">
                        <div class="notif-item-text">
                            <span class="notif-item-title">Comments</span>
                            <span class="notif-item-desc">When someone comments on your post</span>
                        </div>
                        <div class="notif-toggle"><input type="checkbox" id="gsNotifComments"><span class="notif-slider"></span></div>
                    </label>
                    <label class="notif-item">
                        <div class="notif-item-text">
                            <span class="notif-item-title">Direct messages</span>
                            <span class="notif-item-desc">When you receive a new DM</span>
                        </div>
                        <div class="notif-toggle"><input type="checkbox" id="gsNotifDMs"><span class="notif-slider"></span></div>
                    </label>
                    <label class="notif-item">
                        <div class="notif-item-text">
                            <span class="notif-item-title">Story views</span>
                            <span class="notif-item-desc">When someone views your 24h story</span>
                        </div>
                        <div class="notif-toggle"><input type="checkbox" id="gsNotifStory"><span class="notif-slider"></span></div>
                    </label>
                    <label class="notif-item">
                        <div class="notif-item-text">
                            <span class="notif-item-title">Suggested traders</span>
                            <span class="notif-item-desc">Weekly curated trader suggestions</span>
                        </div>
                        <div class="notif-toggle"><input type="checkbox" id="gsNotifSuggested"><span class="notif-slider"></span></div>
                    </label>
                </div>

                <!-- GROUP WORKSPACES -->
                <div class="notif-section">
                    <h4>Group Workspaces</h4>
                    <label class="notif-item">
                        <div class="notif-item-text">
                            <span class="notif-item-title">New messages</span>
                            <span class="notif-item-desc">When someone sends a message in a joined group</span>
                        </div>
                        <div class="notif-toggle"><input type="checkbox" id="gsNotifGroupMessages"><span class="notif-slider"></span></div>
                    </label>
                    <label class="notif-item">
                        <div class="notif-item-text">
                            <span class="notif-item-title">Mentions</span>
                            <span class="notif-item-desc">When someone @mentions you in a group</span>
                        </div>
                        <div class="notif-toggle"><input type="checkbox" id="gsNotifGroupMentions"><span class="notif-slider"></span></div>
                    </label>
                    <label class="notif-item">
                        <div class="notif-item-text">
                            <span class="notif-item-title">New signals</span>
                            <span class="notif-item-desc">When a group admin posts a new trade signal</span>
                        </div>
                        <div class="notif-toggle"><input type="checkbox" id="gsNotifGroupSignals"><span class="notif-slider"></span></div>
                    </label>
                    <label class="notif-item">
                        <div class="notif-item-text">
                            <span class="notif-item-title">Call starting</span>
                            <span class="notif-item-desc">When an admin starts a live video/audio call</span>
                        </div>
                        <div class="notif-toggle"><input type="checkbox" id="gsNotifGroupCalls"><span class="notif-slider"></span></div>
                    </label>
                    <label class="notif-item">
                        <div class="notif-item-text">
                            <span class="notif-item-title">Following posts</span>
                            <span class="notif-item-desc">When a user you follow posts (max 2/day)</span>
                        </div>
                        <div class="notif-toggle"><input type="checkbox" id="gsNotifFollowing"><span class="notif-slider"></span></div>
                    </label>
                </div>

                <!-- EMAIL DIGEST -->
                <div class="notif-section">
                    <h4>Email Digest</h4>
                    <label class="notif-item">
                        <div class="notif-item-text">
                            <span class="notif-item-title">Weekly performance summary</span>
                            <span class="notif-item-desc">Your win rate and P&L overview every Monday</span>
                        </div>
                        <div class="notif-toggle"><input type="checkbox" id="gsNotifWeekly"><span class="notif-slider"></span></div>
                    </label>
                    <label class="notif-item">
                        <div class="notif-item-text">
                            <span class="notif-item-title">Trade streak alerts</span>
                            <span class="notif-item-desc">When you hit 3+ wins or losses in a row</span>
                        </div>
                        <div class="notif-toggle"><input type="checkbox" id="gsNotifStreak"><span class="notif-slider"></span></div>
                    </label>
                    <label class="notif-item">
                        <div class="notif-item-text">
                            <span class="notif-item-title">Platform updates</span>
                            <span class="notif-item-desc">New features and announcements</span>
                        </div>
                        <div class="notif-toggle"><input type="checkbox" id="gsNotifPlatformUpdates"><span class="notif-slider"></span></div>
                    </label>
                </div>
                
                <div style="margin-top:20px; text-align:right;">
                    <span id="gsStatusNotifs" style="font-size:13px; margin-right:16px;"></span>
                    <button type="button" class="gs-btn-primary" style="color:#111; font-weight:700;" onclick="gsSaveNotifs()">Save Preferences</button>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
const allTimezones = Intl.supportedValuesOf ? Intl.supportedValuesOf('timeZone') : ['UTC'];

function openGlobalSettingsModal(tab = 'dashboard') {
    document.getElementById('globalGeneralSettingsOverlay').classList.add('open');
    switchGlobalSettingsTab(tab);
    loadGlobalSettingsGeneral();
    
    // Load dashboard order
    gsLoadOrder().then(order => {
        gsBuildSettingsList(order);
    });

    // Load Journal settings if functions exist
    if (typeof loadSettingsManager === 'function') {
        if (typeof getCmCsrfToken === 'function') getCmCsrfToken();
        loadSettingsManager();
    }
    if (typeof loadPreferencesTab === 'function') {
        loadPreferencesTab();
    }
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
        if (data.success) {
            if (data.settings) {
                populateTimezones(data.settings.timezone);
                document.getElementById('gsDateFormat').value = data.settings.date_format;
                document.getElementById('gsTimeFormat').value = data.settings.time_format;
            }
            if (data.notifs) {
                const n = data.notifs;
                // Trading Floor
                const setCheck = (id, val, def) => { const el = document.getElementById(id); if (el) el.checked = val !== undefined ? !!val : def; };
                setCheck('gsNotifFollowers', n.tf_new_followers, true);
                setCheck('gsNotifLikes', n.tf_post_likes, true);
                setCheck('gsNotifComments', n.tf_comments, true);
                setCheck('gsNotifDMs', n.tf_direct_messages, true);
                setCheck('gsNotifStory', n.tf_story_views, false);
                setCheck('gsNotifSuggested', n.tf_suggested_traders, false);
                // Group Workspaces
                setCheck('gsNotifGroupMessages', n.group_new_message, false);
                setCheck('gsNotifGroupMentions', n.group_mention, true);
                setCheck('gsNotifGroupSignals', n.group_new_signal, true);
                setCheck('gsNotifGroupCalls', n.group_call_starting, true);
                setCheck('gsNotifFollowing', n.following_posted, true);
                // Email Digest
                setCheck('gsNotifWeekly', n.email_weekly_summary, true);
                setCheck('gsNotifStreak', n.email_trade_streak, true);
                setCheck('gsNotifPlatformUpdates', n.email_platform_updates, false);
            }
        } else {
            populateTimezones('UTC'); // Fallback
        }
    } catch(e) {
        populateTimezones('UTC');
    }
}

async function gsSaveNotifs() {
    const status = document.getElementById('gsStatusNotifs');
    status.style.color = '#fff';
    status.textContent = 'Saving...';
    try {
        const gc = id => { const el = document.getElementById(id); return el ? (el.checked ? 1 : 0) : 0; };
        const csrfResp = await fetch('/api/csrf-token.php');
        const csrfData = await csrfResp.json();
        const res = await fetch('/api/account/save-notifs.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({
                csrf_token: csrfData.token,
                prefs: {
                    // Trading Floor
                    tf_new_followers: gc('gsNotifFollowers'),
                    tf_post_likes: gc('gsNotifLikes'),
                    tf_comments: gc('gsNotifComments'),
                    tf_direct_messages: gc('gsNotifDMs'),
                    tf_story_views: gc('gsNotifStory'),
                    tf_suggested_traders: gc('gsNotifSuggested'),
                    // Group Workspaces
                    group_new_message: gc('gsNotifGroupMessages'),
                    group_mention: gc('gsNotifGroupMentions'),
                    group_new_signal: gc('gsNotifGroupSignals'),
                    group_call_starting: gc('gsNotifGroupCalls'),
                    following_posted: gc('gsNotifFollowing'),
                    // Email Digest
                    email_weekly_summary: gc('gsNotifWeekly'),
                    email_trade_streak: gc('gsNotifStreak'),
                    email_platform_updates: gc('gsNotifPlatformUpdates')
                }
            })
        });
        const data = await res.json();
        if (data.success) {
            status.style.color = '#28a745';
            status.textContent = 'Notifications saved!';
        } else {
            status.style.color = '#ff5b5b';
            status.textContent = data.message || 'Error saving notifications.';
        }
    } catch (e) {
        status.style.color = '#ff5b5b';
        status.textContent = 'Network error.';
    }
    setTimeout(() => status.textContent = '', 3000);
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
<?php
$user_id_for_modal = (int) ($_SESSION['userid'] ?? $_SESSION['user_id'] ?? 0);
$all_cards = ['market','signals','news','classroom','strategies','trades','mentors','ai','chat','journal'];
$visible_cards_js = [];
if (function_exists('rich_card_visible') && $user_id_for_modal > 0) {
    foreach ($all_cards as $c) {
        if (rich_card_visible($c, $user_id_for_modal)) $visible_cards_js[] = $c;
    }
} else {
    $visible_cards_js = $all_cards;
}
?>
const GS_DEFAULT_ORDER = <?php echo json_encode($visible_cards_js); ?>;
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

function gsApplyOrderToGrid(order) {
    const grid = document.getElementById('widgetGrid');
    if (!grid) return;
    const normalized = gsNormalizeOrder(order);
    const nodesById = new Map(Array.from(grid.children).map(node => [node.dataset.cardId, node]));
    normalized.forEach(id => {
        const node = nodesById.get(id);
        if (node) grid.appendChild(node);
    });
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
    gsApplyOrderToGrid(newOrder);
}

function gsResetDashboardOrder() {
    gsBuildSettingsList([...GS_DEFAULT_ORDER]);
}
</script>

<!-- Journal Settings Script -->
<script src="/app/assets/js/column-manager.js?v=<?= time() ?>"></script>
<script>
async function loadPreferencesTab() {
    try {
        const res  = await fetch('/api/preferences/get.php', { credentials: 'include' });
        const data = await res.json();
        if (!data.success) return;

        const prefs = data.preferences || {};
        
        const stopInput = document.getElementById('pref_default_stop_distance');
        if (stopInput && prefs.default_stop_distance) {
            stopInput.value = parseFloat(prefs.default_stop_distance).toFixed(2);
        }

        const dirSelect = document.getElementById('pref_default_direction');
        if (dirSelect && prefs.default_direction) dirSelect.value = prefs.default_direction;

        const sesSelect = document.getElementById('pref_default_session');
        if (sesSelect && prefs.default_session) sesSelect.value = prefs.default_session;

        const plCheck = document.getElementById('pref_show_pl_currency');
        if (plCheck) plCheck.checked = (prefs.show_pl_currency === '1');

        const compactCheck = document.getElementById('pref_compact_rows');
        if (compactCheck) compactCheck.checked = (prefs.compact_rows === '1');

        const autoCalcCheck = document.getElementById('pref_auto_calc_pl');
        if (autoCalcCheck) autoCalcCheck.checked = (prefs.auto_calc_pl === '1');

    } catch (e) {
        console.warn('Could not load preferences:', e);
    }
}

async function savePreferences() {
    const msgDiv = document.getElementById('prefMessage');
    const btn    = document.querySelector('#gs-pane-preferences .gs-btn-primary');
    
    const prefs = {
        default_stop_distance: document.getElementById('pref_default_stop_distance')?.value || '',
        default_direction: document.getElementById('pref_default_direction')?.value || '',
        default_session: document.getElementById('pref_default_session')?.value || '',
        show_pl_currency: document.getElementById('pref_show_pl_currency')?.checked ? '1' : '0',
        compact_rows: document.getElementById('pref_compact_rows')?.checked ? '1' : '0',
        auto_calc_pl: document.getElementById('pref_auto_calc_pl')?.checked ? '1' : '0'
    };

    if (btn) {
        btn.disabled = true;
        btn.textContent = 'Saving...';
    }

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
            body: JSON.stringify({ prefs: prefs })
        });
        const result = await res.json();

        if (result.success) {
            msgDiv.textContent = 'Saved! Applied next time you open the trade form.';
            msgDiv.style.color = '#28a745';
        } else {
            msgDiv.textContent = result.message || 'Failed to save preferences.';
            msgDiv.style.color = '#ff5b5b';
        }
        msgDiv.style.display = 'block';
        setTimeout(() => { msgDiv.style.display = 'none'; }, 3500);
    } catch (e) {
        msgDiv.textContent = 'Connection error. Please try again.';
        msgDiv.style.color = '#ff5b5b';
        msgDiv.style.display = 'block';
    }

    if (btn) {
        btn.disabled = false;
        btn.textContent = 'Save Preferences';
    }
}
</script>
