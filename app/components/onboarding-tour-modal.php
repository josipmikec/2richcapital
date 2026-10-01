<?php
// /app/components/onboarding-tour-modal.php
?>
<style>
.tour-overlay {
    position: fixed;
    top: 0; left: 0; width: 100%; height: 100%;
    background: rgba(0, 0, 0, 0.85);
    backdrop-filter: blur(10px);
    z-index: 9999999;
    display: flex;
    align-items: center;
    justify-content: center;
    opacity: 0;
    visibility: hidden;
    transition: opacity 0.4s ease, visibility 0.4s ease;
}
.tour-overlay.open {
    opacity: 1;
    visibility: visible;
}
.tour-modal {
    background: rgba(18, 18, 20, 0.95);
    border: 1px solid rgba(255, 255, 255, 0.1);
    box-shadow: 0 24px 64px rgba(0,0,0,0.5), inset 0 1px 0 rgba(255,255,255,0.05);
    border-radius: 16px;
    width: 90%;
    max-width: 540px;
    padding: 32px;
    position: relative;
    text-align: center;
    transform: translateY(20px) scale(0.95);
    transition: transform 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
    overflow: hidden;
}
.tour-overlay.open .tour-modal {
    transform: translateY(0) scale(1);
}
.tour-step {
    display: none;
    animation: tourFadeIn 0.4s forwards;
}
.tour-step.active {
    display: block;
}
@keyframes tourFadeIn {
    from { opacity: 0; transform: translateX(20px); }
    to { opacity: 1; transform: translateX(0); }
}
.tour-image {
    width: 100%;
    height: 240px;
    object-fit: contain;
    margin-bottom: 24px;
    border-radius: 8px;
    background: rgba(255,255,255,0.02);
}
.tour-title {
    font-size: 24px;
    font-weight: 700;
    color: #fff;
    margin-bottom: 12px;
    font-family: 'Inter', sans-serif;
    letter-spacing: -0.02em;
}
.tour-desc {
    font-size: 15px;
    color: #8f95a3;
    line-height: 1.6;
    margin-bottom: 32px;
}
.tour-controls {
    display: flex;
    justify-content: space-between;
    align-items: center;
}
.tour-dots {
    display: flex;
    gap: 8px;
}
.tour-dot {
    width: 8px;
    height: 8px;
    border-radius: 50%;
    background: rgba(255,255,255,0.2);
    transition: background 0.3s ease, transform 0.3s ease;
}
.tour-dot.active {
    background: #007aff;
    transform: scale(1.3);
}
.tour-btn {
    background: rgba(255,255,255,0.05);
    border: 1px solid rgba(255,255,255,0.1);
    color: #fff;
    padding: 10px 24px;
    border-radius: 30px;
    font-size: 14px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.2s ease;
}
.tour-btn:hover {
    background: rgba(255,255,255,0.1);
    border-color: rgba(255,255,255,0.2);
}
.tour-btn.primary {
    background: #007aff;
    border-color: #007aff;
}
.tour-btn.primary:hover {
    background: #006ae6;
    box-shadow: 0 0 16px rgba(0,122,255,0.4);
}
.tour-skip {
    position: absolute;
    top: 16px;
    right: 16px;
    color: #666;
    font-size: 12px;
    cursor: pointer;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    font-weight: 600;
}
.tour-skip:hover {
    color: #fff;
}
</style>

<div class="tour-overlay" id="onboardingTour">
    <div class="tour-modal">
        <div class="tour-skip" onclick="closeTour()">Skip</div>
        
        <div class="tour-step active" id="tourStep1">
            <img src="/app/assets/img/tour_1.png" alt="Welcome" class="tour-image" onerror="this.style.display='none'">
            <h2 class="tour-title">Welcome to 2RICH CAPITAL</h2>
            <p class="tour-desc">Your ultimate hub for professional trading. Analyze markets, journal your trades, and build your edge with precision.</p>
        </div>

        <div class="tour-step" id="tourStep2">
            <img src="/app/assets/img/tour_2.png" alt="Journal" class="tour-image" onerror="this.style.display='none'">
            <h2 class="tour-title">Advanced Trade Journal</h2>
            <p class="tour-desc">Log your setups seamlessly. Add custom columns, track your psychology, and review detailed performance metrics instantly.</p>
        </div>

        <div class="tour-step" id="tourStep3">
            <img src="/app/assets/img/tour_3.png" alt="Charts" class="tour-image" onerror="this.style.display='none'">
            <h2 class="tour-title">Live Market Analytics</h2>
            <p class="tour-desc">Connect directly to broker feeds. View real-time charts, monitor sync status, and execute your strategy with confidence.</p>
        </div>

        <div class="tour-controls">
            <div class="tour-dots">
                <div class="tour-dot active" id="dot1"></div>
                <div class="tour-dot" id="dot2"></div>
                <div class="tour-dot" id="dot3"></div>
            </div>
            <div style="display:flex; gap:12px;">
                <button class="tour-btn" id="tourPrevBtn" style="display:none;" onclick="tourNav(-1)">Back</button>
                <button class="tour-btn primary" id="tourNextBtn" onclick="tourNav(1)">Next</button>
            </div>
        </div>
    </div>
</div>

<script>
let currentTourStep = 1;
const maxTourSteps = 3;

function tourNav(dir) {
    document.getElementById('tourStep' + currentTourStep).classList.remove('active');
    document.getElementById('dot' + currentTourStep).classList.remove('active');
    
    currentTourStep += dir;
    
    if (currentTourStep > maxTourSteps) {
        closeTour();
        return;
    }
    
    document.getElementById('tourStep' + currentTourStep).classList.add('active');
    document.getElementById('dot' + currentTourStep).classList.add('active');
    
    document.getElementById('tourPrevBtn').style.display = (currentTourStep === 1) ? 'none' : 'block';
    
    const nextBtn = document.getElementById('tourNextBtn');
    if (currentTourStep === maxTourSteps) {
        nextBtn.textContent = 'Get Started';
    } else {
        nextBtn.textContent = 'Next';
    }
}

function closeTour() {
    document.getElementById('onboardingTour').classList.remove('open');
    localStorage.setItem('hasSeen2RichTour', 'true');
    setTimeout(() => {
        document.getElementById('onboardingTour').style.display = 'none';
    }, 400);
}

document.addEventListener('DOMContentLoaded', () => {
    if (!localStorage.getItem('hasSeen2RichTour')) {
        setTimeout(() => {
            document.getElementById('onboardingTour').classList.add('open');
        }, 800);
    } else {
        document.getElementById('onboardingTour').style.display = 'none';
    }
});
</script>
