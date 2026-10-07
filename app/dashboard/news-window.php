<?php
require_once '../auth/session-config.php';

if (
    (!isset($_SESSION['userid']) && !isset($_SESSION['user_id'])) ||
    !isset($_SESSION['authenticated'])
) {
    header('Location: https://app.2rich.capital/login');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Live News Feed — 2RICH CAPITAL</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: Montserrat, -apple-system, BlinkMacSystemFont, sans-serif;
            background: #0E0E0E;
            color: #f4f4f4;
            height: 100vh;
            overflow: hidden;
            display: flex;
            flex-direction: column;
            background-image:
                radial-gradient(circle at 20% 30%, rgba(242,202,80,0.03) 0%, transparent 50%),
                radial-gradient(circle at 80% 70%, rgba(242,202,80,0.02) 0%, transparent 50%);
        }

        .nw-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 14px 20px;
            background: rgba(14,14,14,0.95);
            border-bottom: 1px solid #1a1a1a;
            position: sticky;
            top: 0;
            z-index: 10;
            backdrop-filter: blur(12px);
            flex-shrink: 0;
        }

        .nw-brand {
            font-size: 13px;
            font-weight: 800;
            letter-spacing: 0.12em;
            text-transform: uppercase;
            background: linear-gradient(135deg, #F2CA50 0%, #FFDB70 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .nw-status {
            display: flex;
            align-items: center;
            gap: 6px;
            font-size: 10px;
            color: #888;
            font-weight: 600;
            letter-spacing: 0.06em;
            text-transform: uppercase;
        }

        .news-dot {
            width: 7px;
            height: 7px;
            border-radius: 50%;
            background: #555;
            transition: background 0.3s;
            flex-shrink: 0;
        }

        .news-dot.connected {
            background: #22c55e;
            box-shadow: 0 0 6px #22c55e88;
            animation: pulse-dot 2s infinite;
        }

        .news-dot.disconnected {
            background: #ef4444;
        }

        @keyframes pulse-dot {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.4; }
        }

        .nw-feed {
            flex: 1;
            overflow-y: auto;
            scrollbar-width: thin;
            scrollbar-color: #1a1a1a transparent;
        }

        .nw-feed::-webkit-scrollbar {
            width: 4px;
        }

        .nw-feed::-webkit-scrollbar-thumb {
            background: #1a1a1a;
            border-radius: 4px;
        }

        .nw-empty {
            padding: 40px 20px;
            color: #555;
            font-size: 12px;
            text-align: center;
        }

        .news-item {
            display: flex;
            flex-direction: column;
            gap: 4px;
            padding: 12px 20px;
            border-bottom: 1px solid rgba(255,255,255,0.04);
            border-left: 2px solid transparent;
            animation: slide-in 0.3s ease;
            transition: background 0.3s ease, border-left-color 0.3s ease;
        }

        .news-item:last-child {
            border-bottom: none;
        }

        .news-item.new-item {
            animation: newsHighlightPulse 5s ease-out forwards;
        }

        @keyframes newsHighlightPulse {
            0% { 
                background: linear-gradient(90deg, rgba(242, 202, 80, 0.15) 0%, transparent 100%);
                border-left-color: rgba(242, 202, 80, 1);
            }
            80% { 
                background: linear-gradient(90deg, rgba(242, 202, 80, 0.02) 0%, transparent 100%);
                border-left-color: rgba(242, 202, 80, 0.5);
            }
            100% { 
                background: transparent;
                border-left-color: transparent;
            }
        }

        @keyframes slide-in {
            from { opacity: 0; transform: translateY(-6px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .news-item-text {
            font-size: 12px;
            color: #ccc;
            line-height: 1.6;
        }

        .news-item-meta {
            display: flex;
            gap: 10px;
            font-size: 10px;
            color: #555;
        }

        .news-item-author {
            color: #F2CA50;
            opacity: 0.7;
        }
    </style>
</head>
<body>

    <div class="nw-header">
        <span class="nw-brand">2RICH — Live News</span>
        <div style="display:flex; align-items:center; gap:16px;">
            <div style="position:relative; display:inline-block;">
                <button class="news-popout-btn nw-filter-btn" onclick="toggleNewsFilterMenu(event)" title="Filter sources" style="background:transparent; border:none; color:#b2b5be; cursor:pointer; padding:6px; display:flex; align-items:center;" onmouseover="this.style.color='#F2CA50'" onmouseout="this.style.color='#b2b5be'">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3"></polygon></svg>
                </button>
                <div id="newsFilterMenu" onclick="event.stopPropagation()" style="display:none; position:absolute; top:calc(100% + 4px); right:0; background:#151515; border:1px solid #2a2a2a; border-radius:8px; padding:8px; z-index:100; min-width:140px; box-shadow:0 12px 35px rgba(0,0,0,0.45);">
                    <label style="display:flex; align-items:center; gap:8px; padding:6px 8px; color:#d8d8d8; font-size:11px; font-weight:600; cursor:pointer; border-radius:4px;"><input type="radio" name="n_src" value="all" checked onchange="window.applyNewsFilter(this.value); toggleNewsFilterMenu(event);" style="margin:0; accent-color:#F2CA50;">All Sources</label>
                    <label style="display:flex; align-items:center; gap:8px; padding:6px 8px; color:#d8d8d8; font-size:11px; font-weight:600; cursor:pointer; border-radius:4px;"><input type="radio" name="n_src" value="financialjuice" onchange="window.applyNewsFilter(this.value); toggleNewsFilterMenu(event);" style="margin:0; accent-color:#F2CA50;">FinancialJuice</label>
                    <label style="display:flex; align-items:center; gap:8px; padding:6px 8px; color:#d8d8d8; font-size:11px; font-weight:600; cursor:pointer; border-radius:4px;"><input type="radio" name="n_src" value="investing.com" onchange="window.applyNewsFilter(this.value); toggleNewsFilterMenu(event);" style="margin:0; accent-color:#F2CA50;">Investing.com</label>
                    <label style="display:flex; align-items:center; gap:8px; padding:6px 8px; color:#d8d8d8; font-size:11px; font-weight:600; cursor:pointer; border-radius:4px;"><input type="radio" name="n_src" value="barchart" onchange="window.applyNewsFilter(this.value); toggleNewsFilterMenu(event);" style="margin:0; accent-color:#F2CA50;">Barchart</label>
                    <label style="display:flex; align-items:center; gap:8px; padding:6px 8px; color:#d8d8d8; font-size:11px; font-weight:600; cursor:pointer; border-radius:4px;"><input type="radio" name="n_src" value="investinglive" onchange="window.applyNewsFilter(this.value); toggleNewsFilterMenu(event);" style="margin:0; accent-color:#F2CA50;">Investinglive</label>
                </div>
            </div>
            <div class="nw-status">
                <span class="news-dot disconnected" id="nwDot"></span>
                <span id="nwStatusText">Connecting...</span>
            </div>
        </div>
    </div>

    <div class="nw-feed" id="nwFeed">
        <div class="nw-empty">Connecting to live feed...</div>
    </div>

    <script>
        let es = null;
        let lastId = 0;

        function formatTime(str) {
            if (!str) return '--:--';
            if (window.opener && window.opener.formatUserDate) return window.opener.formatUserDate(str, 'time');
            const d = new Date(str + 'Z');
            if (isNaN(d.getTime())) return '--:--';
            return d.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
        }

        function setStatus(connected) {
            const dot = document.getElementById('nwDot');
            const text = document.getElementById('nwStatusText');

            dot.className = 'news-dot ' + (connected ? 'connected' : 'disconnected');
            text.textContent = connected ? 'Live' : 'Reconnecting...';
        }

        function appendItem(item, isNew = false) {
            const feed = document.getElementById('nwFeed');
            if (!feed) return;

            const empty = feed.querySelector('.nw-empty');
            if (empty) empty.remove();

            if (feed.querySelector(`[data-news-id="${item.id}"]`)) return;

            const div = document.createElement('div');
            div.setAttribute('data-news-id', item.id);
            const itemAuthor = item.author ? item.author.toLowerCase() : '';
            div.setAttribute('data-author', itemAuthor);
            div.className = 'news-item' + (isNew ? ' new-item' : '');
            
            const currentFilter = feed.getAttribute('data-filter') || 'all';
            if (currentFilter !== 'all' && !itemAuthor.includes(currentFilter)) {
                div.style.display = 'none';
            }
            div.innerHTML = `
                <div class="news-item-text">${item.message || ''}</div>
                <div class="news-item-meta">
                    <span class="news-item-time">${formatTime(item.created_at)}</span>
                    ${item.author ? `<span class="news-item-author">${item.author}</span>` : ''}
                </div>
            `;

            if (isNew) {
                const isScrolledToBottom = feed.scrollHeight - feed.clientHeight <= feed.scrollTop + 10;
                feed.appendChild(div);
                if (isScrolledToBottom) feed.scrollTop = feed.scrollHeight;
            } else {
                feed.prepend(div);
                feed.scrollTop = feed.scrollHeight;
            }

            const items = feed.querySelectorAll('.news-item');
            if (items.length > 200) {
                items[0].remove();
            }
        }

        window.applyNewsFilter = function(filterValue) {
            const filter = filterValue.toLowerCase();
            const feed = document.getElementById('nwFeed');
            if (!feed) return;
            feed.setAttribute('data-filter', filter);
            
            const items = feed.querySelectorAll('.news-item');
            items.forEach(item => {
                const author = item.getAttribute('data-author') || '';
                if (filter === 'all' || author.includes(filter)) {
                    item.style.display = '';
                } else {
                    item.style.display = 'none';
                }
            });
            feed.scrollTop = feed.scrollHeight;
        };

        window.toggleNewsFilterMenu = function(e) {
            const menu = document.getElementById('newsFilterMenu');
            if(menu) menu.style.display = menu.style.display === 'none' ? 'block' : 'none';
            if(e) e.stopPropagation();
        };
        document.addEventListener('click', function(e) {
            const menu = document.getElementById('newsFilterMenu');
            if (menu && e.target.closest('.nw-filter-btn') === null) {
                menu.style.display = 'none';
            }
        });

        function connect() {
            if (es) es.close();

            const url = '/api/news/stream.php' + (lastId ? `?since=${lastId}` : '');
            es = new EventSource(url);

            es.onopen = () => {
                setStatus(true);
            };

            es.onmessage = (e) => {
                try {
                    const item = JSON.parse(e.data);
                    if (item.id) {
                        lastId = Math.max(lastId, item.id);
                    }
                    appendItem(item, !item.initial);
                } catch (err) {
                    console.error('SSE parse error:', err, e.data);
                }
            };

            es.addEventListener('reconnect', () => {
                es.close();
                setStatus(false);
                setTimeout(connect, 500);
            });

            es.onerror = (err) => {
                console.error('SSE connection error:', err);
                setStatus(false);
                es.close();
                setTimeout(connect, 5000);
            };
        }

        connect();
    </script>
</body>
</html>
