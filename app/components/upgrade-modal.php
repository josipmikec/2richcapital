<?php
// Upgrade Modal
$uid = $_SESSION['user_id'] ?? 0;
$current_plan = get_user_meta($uid, '2rich_plan', true);
if (empty($current_plan)) {
    $current_plan = 'Observer';
}
// Normalize
$current_plan = strtolower(trim((string)$current_plan));
if ($current_plan === 'starter' || $current_plan === 'desk access') {
    $current_plan = 'desk access';
} else if ($current_plan === 'pro' || $current_plan === 'elite desk' || $current_plan === 'elite') {
    $current_plan = 'elite desk';
} else if ($current_plan === 'capital') {
    $current_plan = 'capital';
} else {
    $current_plan = 'observer';
}
?>
<style>
.upgrade-modal-overlay {
    display: none;
    position: fixed;
    top: 0; left: 0; width: 100%; height: 100%;
    background: rgba(0, 0, 0, 0.7);
    backdrop-filter: blur(8px);
    -webkit-backdrop-filter: blur(8px);
    z-index: 2000;
    align-items: center;
    justify-content: center;
    padding: 24px;
    opacity: 0;
    transition: opacity 0.3s ease;
}
.upgrade-modal-overlay.open {
    display: flex;
    opacity: 1;
}
.upgrade-modal-panel {
    background: #0E0E0E;
    border: 1px solid #2a2a2a;
    border-radius: 12px;
    width: 100%;
    max-width: 1200px;
    max-height: 90vh;
    overflow-y: auto;
    position: relative;
    box-shadow: 0 16px 64px rgba(0,0,0,0.6);
    display: flex;
    flex-direction: column;
}
.upgrade-modal-panel::-webkit-scrollbar { width: 4px; }
.upgrade-modal-panel::-webkit-scrollbar-thumb { background: #1a1a1a; border-radius: 4px; }

.upgrade-modal-header {
    padding: 40px 40px 24px;
    text-align: center;
    position: relative;
}
.upgrade-modal-close {
    position: absolute;
    top: 24px;
    right: 24px;
    background: transparent;
    border: none;
    color: #888;
    cursor: pointer;
    padding: 8px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.2s;
    z-index: 10;
}
.upgrade-modal-close:hover {
    color: #fff;
    transform: rotate(90deg);
}
.upgrade-modal-title {
    font-size: 28px;
    font-weight: 800;
    color: #F2CA50;
    margin-bottom: 8px;
    letter-spacing: 0.05em;
    font-family: 'Montserrat', sans-serif;
    text-transform: uppercase;
}
.upgrade-modal-subtitle {
    font-size: 14px;
    color: #aaa;
    line-height: 1.6;
    max-width: 600px;
    margin: 0 auto;
    font-family: 'Montserrat', sans-serif;
}

.upgrade-cards {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 20px;
    padding: 0 40px 40px;
}
@media (max-width: 1100px) {
    .upgrade-cards { grid-template-columns: repeat(2, 1fr); }
}
@media (max-width: 700px) {
    .upgrade-cards { 
        display: flex;
        flex-direction: row;
        overflow-x: auto;
        scroll-snap-type: x mandatory;
        padding: 0 20px 40px;
        -webkit-overflow-scrolling: touch;
        gap: 16px;
    }
    .upgrade-cards::-webkit-scrollbar { display: none; }
    .upgrade-card {
        min-width: 280px;
        width: 75vw;
        max-width: 320px;
        scroll-snap-align: center;
        flex-shrink: 0;
    }
}

.upgrade-card {
    background: #111;
    border: 1px solid #222;
    padding: 32px 24px;
    display: flex;
    flex-direction: column;
    position: relative;
    overflow: hidden;
    transition: transform 0.2s, border-color 0.2s;
    text-decoration: none;
    cursor: pointer;
}
.upgrade-card:hover {
    border-color: #333;
    transform: translateY(-4px);
}
.upgrade-card.featured {
    border-color: #F2CA50;
}
.upgrade-card.featured:hover {
    border-color: #FFDB70;
}

.uc-tier-label {
    font-size: 9px;
    font-weight: 600;
    letter-spacing: 0.1em;
    color: #666;
    margin-bottom: 16px;
    font-family: 'Montserrat', sans-serif;
}
.upgrade-card.featured .uc-tier-label {
    color: #F2CA50;
}

.uc-title {
    font-size: 22px;
    font-weight: 700;
    color: #fff;
    margin-bottom: 16px;
    font-family: 'Montserrat', sans-serif;
}
.uc-desc {
    font-size: 11px;
    color: #888;
    line-height: 1.6;
    margin-bottom: 32px;
    min-height: 54px;
    font-family: 'Montserrat', sans-serif;
}

.uc-features {
    list-style: none;
    margin: 0 0 40px;
    padding: 0;
    flex: 1;
}
.uc-features li {
    font-size: 9px;
    font-weight: 600;
    color: #ccc;
    display: flex;
    align-items: flex-start;
    gap: 10px;
    margin-bottom: 14px;
    line-height: 1.4;
    font-family: 'Montserrat', sans-serif;
    text-transform: uppercase;
    letter-spacing: 0.05em;
}
.uc-features li svg {
    color: #666;
    flex-shrink: 0;
    margin-top: 1px;
}
.upgrade-card.featured .uc-features li svg {
    color: #F2CA50;
}

.uc-footer {
    display: flex;
    flex-direction: column;
    align-items: flex-start;
    margin-top: auto;
}
.uc-price {
    font-size: 16px;
    font-weight: 700;
    color: #fff;
    margin-bottom: 16px;
    display: flex;
    align-items: baseline;
    gap: 4px;
    font-family: 'Montserrat', sans-serif;
}
.uc-price span {
    font-size: 10px;
    font-weight: 500;
    color: #666;
    text-transform: uppercase;
    letter-spacing: 0.05em;
}

.uc-btn {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    font-size: 10px;
    font-weight: 700;
    letter-spacing: 0.1em;
    text-transform: uppercase;
    text-decoration: none;
    font-family: 'Montserrat', sans-serif;
    color: #fff;
    transition: color 0.2s;
    background: none;
    border: none;
    padding: 0;
}
.upgrade-card:hover .uc-btn {
    color: #F2CA50;
}

.current-plan-badge {
    position: absolute;
    top: 24px;
    right: 24px;
    background: rgba(242,202,80,0.15);
    color: #F2CA50;
    border: 1px solid rgba(242,202,80,0.3);
    padding: 4px 10px;
    font-size: 9px;
    font-weight: 700;
    letter-spacing: 0.1em;
    text-transform: uppercase;
    border-radius: 4px;
}
</style>

<div class="upgrade-modal-overlay" id="upgradeModalOverlay" onclick="closeUpgradeModal(event)">
    <div class="upgrade-modal-panel" onclick="event.stopPropagation()">
        <div class="upgrade-modal-header">
            <button class="upgrade-modal-close" onclick="closeUpgradeModal(event)">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>
            </button>
            <h2 class="upgrade-modal-title">The Membership Hierarchy</h2>
            <p class="upgrade-modal-subtitle">Choose your level of access. Upgrade anytime.</p>
        </div>

        <div class="upgrade-cards">
            <!-- Observer -->
            <div class="upgrade-card" style="cursor: default;">
                <?php if ($current_plan === 'observer'): ?>
                    <div class="current-plan-badge">Current Plan</div>
                <?php endif; ?>
                <div class="uc-tier-label">Tier 04</div>
                <div class="uc-title">Observer</div>
                <div class="uc-desc">Passive insight into the 2RICH core governance and public capital flows.</div>
                <ul class="uc-features">
                    <li><svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"></polyline></svg> MACRO MARKET DATA</li>
                    <li><svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"></polyline></svg> 1 TRADE IDEA PER WEEK</li>
                    <li><svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"></polyline></svg> LIVE TRADING FLOOR ACCESS</li>
                    <li><svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"></polyline></svg> 2RICH TRADING JOURNAL</li>
                    <li><svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"></polyline></svg> REAL-TIME NEWS FEED</li>
                    <li><svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"></polyline></svg> TRADINGVIEW CHARTING</li>
                    <li><svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"></polyline></svg> ECONOMIC CALENDAR ALERTS</li>
                </ul>
                <div class="uc-footer">
                    <?php if ($current_plan !== 'observer'): ?>
                        <span class="uc-btn" style="cursor: default; opacity: 0.5;">START FOR FREE &rarr;</span>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Desk Access -->
            <a href="https://buy.stripe.com/6oUdR84aJ121ey17HW0Ba00" target="_blank" class="upgrade-card" <?php if($current_plan === 'desk access') echo 'onclick="event.preventDefault();" style="cursor:default;"'; ?>>
                <?php if ($current_plan === 'desk access'): ?>
                    <div class="current-plan-badge">Current Plan</div>
                <?php endif; ?>
                <div class="uc-tier-label">Tier 03</div>
                <div class="uc-title">Desk Access</div>
                <div class="uc-desc">Access to the desk's daily context, reports, and trade setups.</div>
                <ul class="uc-features">
                    <li><svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"></polyline></svg> EVERYTHING IN OBSERVER</li>
                    <li><svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"></polyline></svg> 2RICH WEEKLY PLAYBOOK</li>
                    <li><svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"></polyline></svg> DAILY SESSION MARKET REPORTS</li>
                    <li><svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"></polyline></svg> ADVANCED TRADING JOURNAL</li>
                    <li><svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"></polyline></svg> PRIVATE CHANNELS</li>
                    <li><svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"></polyline></svg> DESK RESOURCES</li>
                </ul>
                <div class="uc-footer">
                    <div class="uc-price">$49<span>/ MONTH</span></div>
                    <?php if ($current_plan !== 'desk access'): ?>
                        <span class="uc-btn">UPGRADE &rarr;</span>
                    <?php endif; ?>
                </div>
            </a>

            <!-- Elite Desk -->
            <a href="https://buy.stripe.com/4gM4gy36FbGFgG9aU80Ba01" target="_blank" class="upgrade-card featured" <?php if($current_plan === 'elite desk') echo 'onclick="event.preventDefault();" style="cursor:default;"'; ?>>
                <?php if ($current_plan === 'elite desk'): ?>
                    <div class="current-plan-badge">Current Plan</div>
                <?php endif; ?>
                <div class="uc-tier-label">Tier 02 / Recommended</div>
                <div class="uc-title">Elite Desk</div>
                <div class="uc-desc">Full access to the desk, trade rationale, curriculum, and private channels.</div>
                <ul class="uc-features">
                    <li><svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"></polyline></svg> EVERYTHING IN DESK ACCESS</li>
                    <li><svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"></polyline></svg> ADVANCED MARKET DATA</li>
                    <li><svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"></polyline></svg> 5 TRADE IDEAS PER WEEK</li>
                    <li><svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"></polyline></svg> TRADE RATIONALE (THE WHY)</li>
                    <li><svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"></polyline></svg> FULL TRADING JOURNAL</li>
                    <li><svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"></polyline></svg> 12-WEEK FUNDED CURRICULUM</li>
                    <li><svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"></polyline></svg> PROP FIRM CHALLENGE COACHING</li>
                    <li><svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"></polyline></svg> SUNDAY MARKET PREP</li>
                    <li><svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"></polyline></svg> PRIVATE DISCORD ELITE CHANNELS</li>
                </ul>
                <div class="uc-footer">
                    <div class="uc-price">$97<span>/ MONTH</span></div>
                    <?php if ($current_plan !== 'elite desk'): ?>
                        <span class="uc-btn">UPGRADE &rarr;</span>
                    <?php endif; ?>
                </div>
            </a>

            <!-- Capital -->
            <a href="https://2rich.capital/capital" target="_blank" class="upgrade-card" <?php if($current_plan === 'capital') echo 'onclick="event.preventDefault();" style="cursor:default;"'; ?>>
                <?php if ($current_plan === 'capital'): ?>
                    <div class="current-plan-badge">Current Plan</div>
                <?php endif; ?>
                <div class="uc-tier-label">Tier 01</div>
                <div class="uc-title">Capital</div>
                <div class="uc-desc">Long-term track for deeper capital access, analyst and risk-management development, inside the desk.</div>
                <ul class="uc-features">
                    <li><svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"></polyline></svg> EVERYTHING IN ELITE DESK</li>
                    <li><svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"></polyline></svg> DIRECT CONNECTION WITH THE DESK</li>
                    <li><svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"></polyline></svg> CAPITAL TRACK ACCESS</li>
                    <li><svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"></polyline></svg> FULL TRADE IDEAS (NO LIMITS)</li>
                    <li><svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"></polyline></svg> THIS IS NOT A PUBLIC TIER</li>
                </ul>
                <div class="uc-footer">
                    <div class="uc-price">UPON INQUIRY</div>
                    <?php if ($current_plan !== 'capital'): ?>
                        <span class="uc-btn">CONTACT US &rarr;</span>
                    <?php endif; ?>
                </div>
            </a>
        </div>
    </div>
</div>

<script>
function openUpgradeModal() {
    // slight timeout to allow CSS transition
    document.getElementById('upgradeModalOverlay').style.display = 'flex';
    setTimeout(() => {
        document.getElementById('upgradeModalOverlay').classList.add('open');
    }, 10);
}

function closeUpgradeModal(e) {
    if (e && e.target === document.getElementById('upgradeModalOverlay')) {
        document.getElementById('upgradeModalOverlay').classList.remove('open');
        setTimeout(() => {
            document.getElementById('upgradeModalOverlay').style.display = 'none';
        }, 300);
    } else if (e && e.currentTarget && e.currentTarget.classList.contains('upgrade-modal-close')) {
        document.getElementById('upgradeModalOverlay').classList.remove('open');
        setTimeout(() => {
            document.getElementById('upgradeModalOverlay').style.display = 'none';
        }, 300);
    }
}

document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('.upgrade-card').forEach(card => {
        card.addEventListener('click', function(e) {
            if (this.style.cursor === 'default') return; // Ignore non-clickable cards
            document.querySelectorAll('.upgrade-card').forEach(c => c.classList.remove('featured'));
            this.classList.add('featured');
        });
    });
});
</script>
