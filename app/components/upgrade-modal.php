<?php
// Upgrade Modal
?>
<style>
.upgrade-modal-overlay {
    display: none;
    position: fixed;
    inset: 0;
    background: rgba(0,0,0,0.85);
    backdrop-filter: blur(12px);
    -webkit-backdrop-filter: blur(12px);
    z-index: 2000;
    align-items: center;
    justify-content: center;
    padding: 24px;
    animation: fadeIn 0.2s ease;
}
.upgrade-modal-overlay.open {
    display: flex;
}
.upgrade-modal-panel {
    background: #0A0A0A;
    border: 1px solid #222;
    border-radius: 16px;
    width: 100%;
    max-width: 900px;
    max-height: 90vh;
    overflow-y: auto;
    position: relative;
    box-shadow: 0 24px 64px rgba(0,0,0,0.8);
    display: flex;
    flex-direction: column;
}
.upgrade-modal-panel::-webkit-scrollbar { width: 4px; }
.upgrade-modal-panel::-webkit-scrollbar-thumb { background: #1a1a1a; border-radius: 4px; }

.upgrade-modal-header {
    padding: 32px 32px 24px;
    text-align: center;
    border-bottom: 1px solid #1a1a1a;
    position: relative;
}
.upgrade-modal-close {
    position: absolute;
    top: 24px;
    right: 24px;
    background: rgba(255,255,255,0.05);
    border: none;
    color: #888;
    cursor: pointer;
    padding: 8px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.2s;
}
.upgrade-modal-close:hover {
    background: rgba(255,255,255,0.1);
    color: #fff;
    transform: rotate(90deg);
}
.upgrade-modal-title {
    font-size: 24px;
    font-weight: 800;
    color: #f5f5f5;
    margin-bottom: 12px;
    letter-spacing: -0.02em;
    font-family: 'Montserrat', sans-serif;
}
.upgrade-modal-subtitle {
    font-size: 14px;
    color: #888;
    line-height: 1.6;
    max-width: 600px;
    margin: 0 auto;
    font-family: 'Montserrat', sans-serif;
}

.upgrade-cards {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
    gap: 24px;
    padding: 32px;
}

.upgrade-card {
    background: #111;
    border: 1px solid #222;
    border-radius: 12px;
    padding: 24px;
    display: flex;
    flex-direction: column;
    transition: transform 0.2s, border-color 0.2s;
    position: relative;
    overflow: hidden;
}
.upgrade-card:hover {
    transform: translateY(-4px);
    border-color: #333;
}
.upgrade-card.featured {
    border-color: #F2CA50;
    box-shadow: 0 0 32px rgba(242,202,80,0.1);
}
.upgrade-card.featured::before {
    content: 'MOST POPULAR';
    position: absolute;
    top: 12px;
    right: -24px;
    background: #F2CA50;
    color: #0E0E0E;
    font-size: 9px;
    font-weight: 800;
    letter-spacing: 0.1em;
    padding: 4px 24px;
    transform: rotate(45deg);
    font-family: 'Montserrat', sans-serif;
}

.uc-tier {
    font-size: 11px;
    font-weight: 700;
    letter-spacing: 0.15em;
    text-transform: uppercase;
    color: #888;
    margin-bottom: 8px;
    font-family: 'Montserrat', sans-serif;
}
.upgrade-card.featured .uc-tier {
    color: #F2CA50;
}
.uc-price {
    font-size: 32px;
    font-weight: 800;
    color: #fff;
    margin-bottom: 4px;
    display: flex;
    align-items: baseline;
    gap: 4px;
    font-family: 'Montserrat', sans-serif;
}
.uc-price span {
    font-size: 14px;
    font-weight: 600;
    color: #666;
}
.uc-desc {
    font-size: 12px;
    color: #777;
    line-height: 1.5;
    margin-bottom: 24px;
    min-height: 36px;
    font-family: 'Montserrat', sans-serif;
}
.uc-features {
    list-style: none;
    margin: 0 0 24px;
    padding: 0;
    flex: 1;
}
.uc-features li {
    font-size: 12px;
    color: #ccc;
    display: flex;
    align-items: flex-start;
    gap: 10px;
    margin-bottom: 12px;
    line-height: 1.5;
    font-family: 'Montserrat', sans-serif;
}
.uc-features li svg {
    color: #F2CA50;
    flex-shrink: 0;
    margin-top: 2px;
}
.uc-features li.disabled {
    color: #555;
}
.uc-features li.disabled svg {
    color: #444;
}
.uc-btn {
    display: block;
    width: 100%;
    padding: 14px;
    text-align: center;
    border-radius: 8px;
    font-size: 11px;
    font-weight: 800;
    letter-spacing: 0.1em;
    text-transform: uppercase;
    text-decoration: none;
    transition: all 0.2s;
    font-family: 'Montserrat', sans-serif;
}
.uc-btn.primary {
    background: #F2CA50;
    color: #0E0E0E;
}
.uc-btn.primary:hover {
    background: #FFDB70;
}
.uc-btn.secondary {
    background: rgba(255,255,255,0.05);
    color: #fff;
}
.uc-btn.secondary:hover {
    background: rgba(255,255,255,0.1);
}
</style>

<div class="upgrade-modal-overlay" id="upgradeModalOverlay" onclick="closeUpgradeModal(event)">
    <div class="upgrade-modal-panel" onclick="event.stopPropagation()">
        <div class="upgrade-modal-header">
            <button class="upgrade-modal-close" onclick="closeUpgradeModal(event)">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>
            </button>
            <h2 class="upgrade-modal-title">Upgrade Your Access</h2>
            <p class="upgrade-modal-subtitle">Join the inner circle. Get high-conviction trade setups, live market analytics, and connect directly with elite traders.</p>
        </div>

        <div class="upgrade-cards">
            <!-- Starter -->
            <div class="upgrade-card">
                <div class="uc-tier">Starter</div>
                <div class="uc-price">$29<span>/mo</span></div>
                <div class="uc-desc">Essential market data and tools for independent traders.</div>
                <ul class="uc-features">
                    <li><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"></polyline></svg> Real-time Market Data</li>
                    <li><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"></polyline></svg> Advanced Charting</li>
                    <li><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"></polyline></svg> Community Forum Access</li>
                    <li class="disabled"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg> Live Trade Signals</li>
                    <li class="disabled"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg> Premium Groups</li>
                </ul>
                <a href="https://2rich.capital/access/" class="uc-btn secondary">Select Starter</a>
            </div>

            <!-- Pro -->
            <div class="upgrade-card featured">
                <div class="uc-tier">Pro</div>
                <div class="uc-price">$99<span>/mo</span></div>
                <div class="uc-desc">Full suite of intelligence and signals for serious traders.</div>
                <ul class="uc-features">
                    <li><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"></polyline></svg> Everything in Starter</li>
                    <li><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"></polyline></svg> Live Trade Signals</li>
                    <li><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"></polyline></svg> Premium Group Access</li>
                    <li><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"></polyline></svg> Trade Copier (MT5)</li>
                    <li class="disabled"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg> Direct 1-on-1 Mentorship</li>
                </ul>
                <a href="https://2rich.capital/access/" class="uc-btn primary">Select Pro</a>
            </div>

            <!-- Elite -->
            <div class="upgrade-card">
                <div class="uc-tier">Elite</div>
                <div class="uc-price">$199<span>/mo</span></div>
                <div class="uc-desc">Institutional level access, direct mentoring and inner circle.</div>
                <ul class="uc-features">
                    <li><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"></polyline></svg> Everything in Pro</li>
                    <li><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"></polyline></svg> Direct 1-on-1 Mentorship</li>
                    <li><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"></polyline></svg> Inner Circle Mastermind</li>
                    <li><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"></polyline></svg> Custom Trading Plan</li>
                    <li><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"></polyline></svg> VIP Support Line</li>
                </ul>
                <a href="https://2rich.capital/access/" class="uc-btn secondary">Select Elite</a>
            </div>
        </div>
    </div>
</div>

<script>
function openUpgradeModal() {
    document.getElementById('upgradeModalOverlay').classList.add('open');
}

function closeUpgradeModal(e) {
    if (e && e.target === document.getElementById('upgradeModalOverlay')) {
        document.getElementById('upgradeModalOverlay').classList.remove('open');
    } else if (e && e.currentTarget && e.currentTarget.classList.contains('upgrade-modal-close')) {
        document.getElementById('upgradeModalOverlay').classList.remove('open');
    }
}
</script>
