<?php
define('WP_USE_THEMES', false);
require_once dirname(__DIR__, 3) . '/wp-load.php';

if (!is_user_logged_in() || !current_user_can('manage_options')) {
    wp_redirect(site_url('/app/login/'));
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>2Rich Capital - Market Sync Monitor</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg-dark: #0a0a0a;
            --bg-card: #141414;
            --text-main: #f5f5f5;
            --text-muted: #888;
            --gold-primary: #f2ca50;
            --gold-gradient: linear-gradient(135deg, #f2ca50, #e7c36a);
            --border-color: rgba(255, 255, 255, 0.05);
            --success: #22c55e;
            --warning: #eab308;
            --danger: #ef4444;
        }

        body {
            background-color: var(--bg-dark);
            color: var(--text-main);
            font-family: 'Inter', sans-serif;
            margin: 0;
            padding: 40px 20px;
            -webkit-font-smoothing: antialiased;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
        }

        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 1px solid var(--border-color);
        }

        .header h1 {
            margin: 0;
            font-size: 24px;
            font-weight: 600;
            background: var(--gold-gradient);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .refresh-status {
            font-size: 13px;
            color: var(--text-muted);
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .live-dot {
            width: 8px;
            height: 8px;
            background-color: var(--success);
            border-radius: 50%;
            box-shadow: 0 0 10px var(--success);
            animation: pulse 2s infinite;
        }

        @keyframes pulse {
            0% { transform: scale(0.95); box-shadow: 0 0 0 0 rgba(34, 197, 94, 0.7); }
            70% { transform: scale(1); box-shadow: 0 0 0 6px rgba(34, 197, 94, 0); }
            100% { transform: scale(0.95); box-shadow: 0 0 0 0 rgba(34, 197, 94, 0); }
        }

        .grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 20px;
        }

        .card {
            background-color: var(--bg-card);
            border: 1px solid var(--border-color);
            border-radius: 12px;
            padding: 20px;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }
        
        .card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 24px rgba(0,0,0,0.4);
            border-color: rgba(242, 202, 80, 0.2);
        }

        .symbol-title {
            font-size: 18px;
            font-weight: 600;
            margin: 0 0 15px 0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .timeframe-badge {
            font-size: 11px;
            background: rgba(255,255,255,0.05);
            padding: 4px 8px;
            border-radius: 4px;
            color: var(--text-muted);
        }

        .stat-row {
            display: flex;
            justify-content: space-between;
            font-size: 13px;
            margin-bottom: 8px;
        }

        .stat-label { color: var(--text-muted); }
        
        .stat-value.healthy { color: var(--success); }
        .stat-value.syncing { color: var(--warning); }
        .stat-value.stale { color: var(--danger); }

        .progress-bar-container {
            width: 100%;
            height: 6px;
            background: rgba(255,255,255,0.05);
            border-radius: 3px;
            margin-top: 15px;
            overflow: hidden;
            position: relative;
        }

        .progress-bar {
            height: 100%;
            background: var(--gold-gradient);
            border-radius: 3px;
            transition: width 0.5s ease;
        }
        
        .progress-text {
            font-size: 11px;
            color: var(--text-muted);
            text-align: right;
            margin-top: 5px;
        }

        .seed-badge {
            display: inline-block;
            font-size: 10px;
            text-transform: uppercase;
            font-weight: 700;
            padding: 3px 6px;
            border-radius: 4px;
            margin-left: 10px;
        }

        .seed-badge.done {
            background: rgba(34, 197, 94, 0.1);
            color: var(--success);
            border: 1px solid rgba(34, 197, 94, 0.2);
        }
        
        .seed-badge.active {
            background: rgba(234, 179, 8, 0.1);
            color: var(--warning);
            border: 1px solid rgba(234, 179, 8, 0.2);
        }
    </style>
</head>
<body>

<div class="container">
    <div class="header">
        <h1>Market Sync Monitor</h1>
        <div class="refresh-status">
            <div class="live-dot"></div>
            <span>Auto-refreshing every 5s</span>
        </div>
    </div>
    
    <div class="grid" id="sync-grid">
        <!-- Cards injected via JS -->
    </div>
</div>

<script>
    async function fetchSyncStatus() {
        try {
            const res = await fetch('../api/market/sync-status.php');
            if (!res.ok) return;
            const data = await res.json();
            
            // Group by symbol, sort by timeframe
            const grid = document.getElementById('sync-grid');
            grid.innerHTML = '';
            
            data.forEach(item => {
                const targetBars = item.timeframe.includes('M15') ? 10000 : 8000;
                let currentRows = parseInt(item.rows_synced) || 0;
                let pct = Math.min(100, Math.round((currentRows / targetBars) * 100));
                
                // If it's very close to target, consider it seeded
                const isSeeded = currentRows >= (targetBars - 500); 
                if (isSeeded) pct = 100;
                
                const lastAttempt = new Date(item.last_attempt_at + ' UTC');
                const secondsAgo = Math.round((new Date() - lastAttempt) / 1000);
                
                let statusClass = 'healthy';
                let statusText = 'Live';
                
                if (secondsAgo > 300) {
                    statusClass = 'stale';
                    statusText = 'Stale (> 5m)';
                } else if (!isSeeded) {
                    statusClass = 'syncing';
                    statusText = 'Backfilling...';
                }

                grid.innerHTML += `
                    <div class="card">
                        <div class="symbol-title">
                            <div>
                                ${item.mt5_symbol} 
                                <span class="seed-badge ${isSeeded ? 'done' : 'active'}">${isSeeded ? 'SEEDED' : 'SEEDING'}</span>
                            </div>
                            <span class="timeframe-badge">${item.timeframe}</span>
                        </div>
                        
                        <div class="stat-row">
                            <span class="stat-label">Last Attempt:</span>
                            <span class="stat-value ${statusClass}">${secondsAgo}s ago</span>
                        </div>
                        
                        <div class="stat-row">
                            <span class="stat-label">Status:</span>
                            <span class="stat-value ${statusClass}">${statusText}</span>
                        </div>
                        
                        <div class="progress-bar-container">
                            <div class="progress-bar" style="width: ${pct}%"></div>
                        </div>
                        <div class="progress-text">${currentRows.toLocaleString()} / ${targetBars.toLocaleString()} bars</div>
                    </div>
                `;
            });
            
        } catch(e) {
            console.error(e);
        }
    }

    fetchSyncStatus();
    setInterval(fetchSyncStatus, 5000);
</script>

</body>
</html>
