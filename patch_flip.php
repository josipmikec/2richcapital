<?php
$file = '/Users/josipmikec/Documents/2rich/2rich.capital/app/components/general-settings-modal.php';
$content = file_get_contents($file);

$old_js = <<<'JS'
// Global Brand Flip Animation Logic
document.addEventListener('DOMContentLoaded', () => {
    const flipperInner = document.getElementById('brandFlipperInner');
    if (!flipperInner) return;

    const LAST_FLIP_KEY = '2rich_last_brand_flip';
    const SIX_HOURS_MS = 6 * 60 * 60 * 1000;
    const now = Date.now();
    const lastFlip = localStorage.getItem(LAST_FLIP_KEY);

    if (!lastFlip || (now - parseInt(lastFlip, 10) > SIX_HOURS_MS)) {
        
        const tryFlip = () => {
            const settingsOverlay = document.getElementById('globalGeneralSettingsOverlay');
            const welcomeTour = document.getElementById('welcomeTourModal');
            const isSettingsOpen = settingsOverlay && settingsOverlay.classList.contains('open');
            const isWelcomeOpen = welcomeTour && welcomeTour.style.display !== 'none';
            
            if (isSettingsOpen || isWelcomeOpen) {
                setTimeout(tryFlip, 500); // Check again in 500ms
                return;
            }

            // Record the flip
            localStorage.setItem(LAST_FLIP_KEY, Date.now().toString());

            // Start animation sequence after a brief delay
            setTimeout(() => {
                // Flip to "Welcome back"
                flipperInner.style.transform = 'rotateX(180deg)';
                
                // Stay flipped for 5 seconds, then flip back
                setTimeout(() => {
                    flipperInner.style.transform = 'rotateX(0deg)';
                }, 5000);
            }, 500); // 500ms delay gives the UI time to settle after modal closes
        };

        // Start attempting to flip
        tryFlip();
    }
});
JS;

$new_js = <<<'JS'
// Global Brand Flip Animation Logic
window.triggerBrandFlip = function(customText, durationMs = 3000) {
    const flipperInner = document.getElementById('brandFlipperInner');
    if (!flipperInner) return;
    
    if (customText) {
        const backFace = flipperInner.querySelector('.nav-brand-back');
        if (backFace) {
            backFace.innerHTML = `<h1 style="text-transform: uppercase; background: none; -webkit-text-fill-color: #fff; color: #fff; margin-bottom: 0; font-size: 18px; letter-spacing: 0.1em;">
                <span style="background: linear-gradient(to right, #D4AF37 0%, #FFF5C3 50%, #F2CA50 100%); -webkit-background-clip: text; -webkit-text-fill-color: transparent; filter: drop-shadow(0 0 10px rgba(242,202,80,0.2));">${customText}</span>
            </h1>`;
        }
    }
    
    // Clear any existing flip back timeout if triggered rapidly
    if (window._brandFlipTimeout) clearTimeout(window._brandFlipTimeout);

    flipperInner.style.transform = 'rotateX(180deg)';
    
    window._brandFlipTimeout = setTimeout(() => {
        flipperInner.style.transform = 'rotateX(0deg)';
    }, durationMs);
};

document.addEventListener('DOMContentLoaded', () => {
    const flipperInner = document.getElementById('brandFlipperInner');
    if (!flipperInner) return;

    const LAST_FLIP_KEY = '2rich_last_brand_flip';
    const SIX_HOURS_MS = 6 * 60 * 60 * 1000;
    const now = Date.now();
    const lastFlip = localStorage.getItem(LAST_FLIP_KEY);
    const isWelcome = !lastFlip || (now - parseInt(lastFlip, 10) > SIX_HOURS_MS);

    let flipText = null;

    if (isWelcome) {
        localStorage.setItem(LAST_FLIP_KEY, Date.now().toString());
    } else {
        const path = window.location.pathname;
        if (path.includes('/dashboard')) flipText = 'Dashboard';
        else if (path.includes('/trading-floor')) flipText = 'Trading Floor';
        else if (path.includes('/market-data')) flipText = 'Market Data';
        else if (path.includes('/account')) flipText = 'Account';
        else if (path.includes('/journal')) flipText = 'Trading Journal';
    }

    if (isWelcome || flipText) {
        const tryFlip = () => {
            const settingsOverlay = document.getElementById('globalGeneralSettingsOverlay');
            const welcomeTour = document.getElementById('welcomeTourModal');
            const isSettingsOpen = settingsOverlay && settingsOverlay.classList.contains('open');
            const isWelcomeOpen = welcomeTour && welcomeTour.style.display !== 'none';
            
            if (isSettingsOpen || isWelcomeOpen) {
                setTimeout(tryFlip, 500); // Check again in 500ms
                return;
            }

            setTimeout(() => {
                window.triggerBrandFlip(isWelcome ? null : flipText, isWelcome ? 5000 : 3000);
            }, 400); 
        };

        tryFlip();
    }
});
JS;

$content = str_replace($old_js, $new_js, $content);
file_put_contents($file, $content);
echo "Patched.";
?>
