<?php
$user_plan = get_user_meta($user_id, '2rich_plan', true);
$is_paying = !empty($user_plan) && strtolower(trim((string)$user_plan)) !== 'observer';
?>
<?php if (!$is_paying): ?>
<div id="promoTopbar" style="background: #F2CA50; border-bottom: 1px solid #d4a92c; padding: 6px 24px; display: none; justify-content: center; align-items: center; position: relative; z-index: 1000;">
    <div style="font-size: 12px; font-weight: 500; color: #111; letter-spacing: 0.02em; display: flex; align-items: center; gap: 12px; flex-wrap: wrap; justify-content: center; font-family: 'Montserrat', sans-serif;">
        <span id="promoTextSpan">Upgrade to Elite Desk today and get <strong>21 DAYS FREE</strong>.</span>
        <span id="promoCountdownSpan" style="font-weight: 700; background: rgba(0,0,0,0.05); padding: 4px 8px; border-radius: 4px; letter-spacing: 0.05em; font-variant-numeric: tabular-nums;"></span>
        <button type="button" onclick="openUpgradeModal()" style="color: #F2CA50; background: #111; border: none; padding: 6px 14px; border-radius: 4px; font-size: 10px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.1em; cursor: pointer; transition: background 0.2s; font-family: 'Montserrat', sans-serif;" onmouseover="this.style.background='#222'" onmouseout="this.style.background='#111'">View Plans &rarr;</button>
    </div>
    <button onclick="closePromoTopbar()" style="position: absolute; right: 24px; background: transparent; border: none; color: #7a5e00; cursor: pointer; padding: 4px; display: flex; align-items: center; justify-content: center; transition: color 0.2s;" onmouseover="this.style.color='#111'" onmouseout="this.style.color='#7a5e00'">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>
    </button>
</div>
<script>
(function() {
    const promoTopbar = document.getElementById('promoTopbar');
    const promoText = document.getElementById('promoTextSpan');
    const promoCountdown = document.getElementById('promoCountdownSpan');
    const now = new Date().getTime();
    const DAY = 24 * 60 * 60 * 1000;
    const CYCLE_LEN = 7 * DAY;
    
    let cycleStart = localStorage.getItem('richPromoStart');
    let cycleCount = parseInt(localStorage.getItem('richPromoCycle') || '1', 10);
    let hideUntil = parseInt(localStorage.getItem('richPromoHideUntil') || '0', 10);
    
    if (!cycleStart) {
        cycleStart = now;
        localStorage.setItem('richPromoStart', cycleStart);
        localStorage.setItem('richPromoCycle', '1');
    } else {
        cycleStart = parseInt(cycleStart, 10);
    }
    
    let timeInCurrentCycle = now - cycleStart;
    
    if (timeInCurrentCycle > CYCLE_LEN) {
        if (timeInCurrentCycle <= CYCLE_LEN * 2) {
            return; // Hide for 7 days
        } else {
            cycleStart = now;
            cycleCount++;
            localStorage.setItem('richPromoStart', cycleStart);
            localStorage.setItem('richPromoCycle', cycleCount.toString());
            timeInCurrentCycle = 0;
        }
    }
    
    if (hideUntil > now) {
        return; // Closed for today
    }
    
    const endTime = cycleStart + CYCLE_LEN;
    
    promoTopbar.style.display = 'flex';
    
    function updateTimer() {
        const left = endTime - new Date().getTime();
        if (left <= 0) {
            promoTopbar.style.display = 'none';
            return;
        }
        const d = Math.floor(left / DAY);
        const h = Math.floor((left % DAY) / (1000 * 60 * 60));
        const m = Math.floor((left % (1000 * 60 * 60)) / (1000 * 60));
        const s = Math.floor((left % (1000 * 60)) / 1000);
        promoCountdown.innerHTML = `${d}d ${h}h ${m}m ${s}s`;
    }
    
    updateTimer();
    setInterval(updateTimer, 1000);
    
    window.closePromoTopbar = function() {
        promoTopbar.style.display = 'none';
        const tomorrow = new Date();
        tomorrow.setHours(24, 0, 0, 0);
        localStorage.setItem('richPromoHideUntil', tomorrow.getTime().toString());
    };
})();
</script>
<?php endif; ?>
<?php include __DIR__ . '/ny-market-bell.php'; ?>
