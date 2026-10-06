<?php
// Included globally via promo-bar.php to ring the NY Market Bell
?>
<script>
(function() {
    function getNYTime() {
        const d = new Date();
        const nyString = d.toLocaleString("en-US", { timeZone: "America/New_York" });
        return new Date(nyString);
    }

    function getEaster(year) {
        const f = Math.floor,
            G = year % 19,
            C = f(year / 100),
            H = (C - f(C / 4) - f((8 * C + 13)/25) + 19 * G + 15) % 30,
            I = H - f(H/28) * (1 - f(29/(H + 1)) * f((21-G)/11)),
            J = (year + f(year / 4) + I + 2 - C + f(C / 4)) % 7,
            L = I - J,
            month = 3 + f((L + 40)/44),
            day = L + 28 - 31 * f(month / 4);
        return new Date(year, month - 1, day);
    }

    function isUSMarketHoliday(date) {
        const year = date.getFullYear();
        const month = date.getMonth(); // 0-11
        const day = date.getDate();
        const dayOfWeek = date.getDay(); // 0=Sun, 1=Mon

        // Helper for Nth day of week in a month
        const isNthDayOfWeek = (n, dOfWeek) => {
            if (dayOfWeek !== dOfWeek) return false;
            return Math.ceil(day / 7) === n;
        };

        // Helper for last day of week in a month
        const isLastDayOfWeek = (dOfWeek) => {
            if (dayOfWeek !== dOfWeek) return false;
            const daysInMonth = new Date(year, month + 1, 0).getDate();
            return day + 7 > daysInMonth;
        };

        // 1. New Year's Day (Jan 1, observed Mon if Sun)
        if (month === 0 && day === 1 && dayOfWeek !== 0 && dayOfWeek !== 6) return true;
        if (month === 0 && day === 2 && dayOfWeek === 1) return true;

        // 2. MLK Jr. Day (3rd Mon in Jan)
        if (month === 0 && isNthDayOfWeek(3, 1)) return true;

        // 3. Washington's Birthday (3rd Mon in Feb)
        if (month === 1 && isNthDayOfWeek(3, 1)) return true;

        // 4. Good Friday
        const easter = getEaster(year);
        const goodFriday = new Date(year, easter.getMonth(), easter.getDate() - 2);
        if (month === goodFriday.getMonth() && day === goodFriday.getDate()) return true;

        // 5. Memorial Day (Last Mon in May)
        if (month === 4 && isLastDayOfWeek(1)) return true;

        // 6. Juneteenth (June 19, observed Mon if Sun)
        if (month === 5 && day === 19 && dayOfWeek !== 0 && dayOfWeek !== 6) return true;
        if (month === 5 && day === 20 && dayOfWeek === 1) return true;

        // 7. Independence Day (July 4, observed Mon if Sun)
        if (month === 6 && day === 4 && dayOfWeek !== 0 && dayOfWeek !== 6) return true;
        if (month === 6 && day === 5 && dayOfWeek === 1) return true;

        // 8. Labor Day (1st Mon in Sep)
        if (month === 8 && isNthDayOfWeek(1, 1)) return true;

        // 9. Thanksgiving (4th Thu in Nov)
        if (month === 10 && isNthDayOfWeek(4, 4)) return true;

        // 10. Christmas (Dec 25, observed Mon if Sun)
        if (month === 11 && day === 25 && dayOfWeek !== 0 && dayOfWeek !== 6) return true;
        if (month === 11 && day === 26 && dayOfWeek === 1) return true;

        return false;
    }

    function playBell() {
        // We need user interaction first in modern browsers for AudioContext, 
        // but since this is an active dashboard, it's likely they have clicked something by 9:30.
        try {
            const AudioContext = window.AudioContext || window.webkitAudioContext;
            if (!AudioContext) return;
            const audioCtx = new AudioContext();
            
            function playChime(freq, startTime, delay) {
                const osc = audioCtx.createOscillator();
                const gain = audioCtx.createGain();
                osc.type = 'sine';
                osc.frequency.setValueAtTime(freq, audioCtx.currentTime);
                
                gain.gain.setValueAtTime(0, startTime + delay);
                gain.gain.linearRampToValueAtTime(0.6, startTime + delay + 0.05);
                gain.gain.exponentialRampToValueAtTime(0.001, startTime + delay + 3);
                
                osc.connect(gain);
                gain.connect(audioCtx.destination);
                
                osc.start(startTime + delay);
                osc.stop(startTime + delay + 3);
            }
            
            const now = audioCtx.currentTime;
            // NYSE opening bell style ring sequence
            for (let i = 0; i < 5; i++) {
                playChime(880, now, i * 0.4); // A5
                playChime(1108.73, now, i * 0.4 + 0.05); // C#6
            }
        } catch (e) {
            console.error('Failed to play bell:', e);
        }

        // Show a nice visual notification
        const notif = document.createElement('div');
        notif.style.cssText = `
            position: fixed;
            bottom: -100px;
            right: 24px;
            background: linear-gradient(135deg, #1A1A1A, #0E0E0E);
            border: 1px solid #d4a92c;
            color: #fff;
            padding: 16px 24px;
            border-radius: 8px;
            z-index: 999999;
            display: flex;
            align-items: center;
            gap: 16px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.5);
            font-family: 'Montserrat', sans-serif;
            transition: bottom 0.5s cubic-bezier(0.175, 0.885, 0.32, 1.275);
        `;
        notif.innerHTML = `
            <div style="font-size: 24px; animation: ring 2s ease infinite;">🔔</div>
            <div>
                <div style="font-size: 11px; color: #F2CA50; font-weight: 700; letter-spacing: 0.1em; text-transform: uppercase; margin-bottom: 4px;">Market Open</div>
                <div style="font-size: 15px; font-weight: 600;">The New York market is now open.</div>
            </div>
            <style>
                @keyframes ring {
                    0%, 100% { transform: rotate(0deg); }
                    10% { transform: rotate(15deg); }
                    20% { transform: rotate(-10deg); }
                    30% { transform: rotate(5deg); }
                    40% { transform: rotate(-5deg); }
                    50% { transform: rotate(0deg); }
                }
            </style>
        `;
        document.body.appendChild(notif);
        
        // animate in
        setTimeout(() => { notif.style.bottom = '24px'; }, 100);
        // remove after 10s
        setTimeout(() => { 
            notif.style.bottom = '-100px'; 
            setTimeout(() => notif.remove(), 500);
        }, 10000);
    }

    function checkBell() {
        const nyNow = getNYTime();
        
        // 1. Check Weekend
        const day = nyNow.getDay();
        if (day === 0 || day === 6) return;

        // 2. Check Holiday
        if (isUSMarketHoliday(nyNow)) return;

        // 3. Check Time (9:30 AM EST)
        const h = nyNow.getHours();
        const m = nyNow.getMinutes();
        
        if (h === 9 && m === 30) {
            const todayStr = `${nyNow.getFullYear()}-${nyNow.getMonth()+1}-${nyNow.getDate()}`;
            const key = `ny_bell_rung_${todayStr}`;
            
            if (!localStorage.getItem(key)) {
                localStorage.setItem(key, "1");
                playBell();
            }
        }
    }

    // Check every 10 seconds
    setInterval(checkBell, 10000);
    // Initial check
    checkBell();

})();
</script>
