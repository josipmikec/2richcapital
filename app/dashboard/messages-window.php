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
    <title>Private Messages — 2RICH CAPITAL</title>
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

        .mw-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 16px 20px;
            background: rgba(14,14,14,0.95);
            border-bottom: 1px solid #1a1a1a;
            position: sticky;
            top: 0;
            z-index: 10;
            backdrop-filter: blur(12px);
            flex-shrink: 0;
        }

        .mw-brand {
            font-size: 13px;
            font-weight: 800;
            letter-spacing: 0.12em;
            text-transform: uppercase;
            background: linear-gradient(135deg, #F2CA50 0%, #FFDB70 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .mw-status {
            display: flex;
            align-items: center;
            gap: 6px;
            font-size: 10px;
            color: #888;
            font-weight: 600;
            letter-spacing: 0.06em;
            text-transform: uppercase;
        }

        .status-dot {
            width: 7px;
            height: 7px;
            border-radius: 50%;
            background: #22c55e;
            box-shadow: 0 0 6px #22c55e88;
            flex-shrink: 0;
        }

        .mw-content {
            flex: 1;
            display: flex;
            flex-direction: column;
            overflow-y: auto;
            scrollbar-width: thin;
            scrollbar-color: #1a1a1a transparent;
        }

        .mw-content::-webkit-scrollbar {
            width: 4px;
        }

        .mw-content::-webkit-scrollbar-thumb {
            background: #1a1a1a;
            border-radius: 4px;
        }

        .mw-empty {
            padding: 40px 20px;
            color: #555;
            font-size: 13px;
            text-align: center;
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-direction: column;
            gap: 12px;
        }
        
        .mw-empty svg {
            color: #333;
        }

        .chat-list {
            list-style: none;
            display: flex;
            flex-direction: column;
        }

        .chat-list-item {
            display: flex;
            align-items: center;
            padding: 16px 20px;
            border-bottom: 1px solid rgba(255,255,255,0.04);
            cursor: pointer;
            transition: background 0.2s ease;
        }

        .chat-list-item:hover {
            background: rgba(255,255,255,0.02);
        }
        
        .chat-list-item:last-child {
            border-bottom: none;
        }

        .chat-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: #2a2a2a;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 14px;
            color: #f2ca50;
            margin-right: 14px;
            flex-shrink: 0;
        }

        .chat-details {
            flex: 1;
            min-width: 0;
        }

        .chat-name-time {
            display: flex;
            justify-content: space-between;
            align-items: baseline;
            margin-bottom: 4px;
        }

        .chat-name {
            font-size: 14px;
            font-weight: 600;
            color: #eee;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .chat-time {
            font-size: 10px;
            color: #666;
            flex-shrink: 0;
            margin-left: 8px;
        }

        .chat-preview {
            font-size: 12px;
            color: #888;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        
        .chat-unread .chat-name {
            color: #fff;
        }
        
        .chat-unread .chat-preview {
            color: #ccc;
            font-weight: 500;
        }

        .unread-badge {
            background: #F2CA50;
            color: #000;
            font-size: 9px;
            font-weight: 800;
            padding: 2px 6px;
            border-radius: 10px;
            margin-left: 8px;
        }

        /* Coming soon overlay */
        .coming-soon-overlay {
            position: absolute;
            inset: 0;
            background: rgba(14,14,14,0.85);
            backdrop-filter: blur(4px);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 20;
            opacity: 0;
            pointer-events: none;
            transition: opacity 0.3s ease;
        }
        
        .coming-soon-overlay.active {
            opacity: 1;
            pointer-events: all;
        }
        
        .coming-soon-card {
            background: #111;
            border: 1px solid #222;
            padding: 24px;
            border-radius: 12px;
            text-align: center;
            max-width: 80%;
            box-shadow: 0 10px 30px rgba(0,0,0,0.5);
        }
        
        .coming-soon-card h3 {
            color: #f2ca50;
            font-size: 16px;
            margin-bottom: 8px;
        }
        
        .coming-soon-card p {
            font-size: 13px;
            color: #aaa;
            line-height: 1.5;
            margin-bottom: 16px;
        }
        
        .btn-close {
            background: transparent;
            border: 1px solid #333;
            color: #eee;
            padding: 8px 16px;
            border-radius: 6px;
            font-family: inherit;
            font-size: 12px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s ease;
        }
        
        .btn-close:hover {
            background: #222;
            border-color: #444;
        }
    </style>
</head>
<body>

    <div class="mw-header">
        <span class="mw-brand">Messages</span>
        <div class="mw-status">
            <span class="status-dot"></span>
            <span>Online</span>
        </div>
    </div>

    <div class="mw-content">
        <!-- Currently returning an empty state as there is no backend -->
        <div class="mw-empty">
            <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path>
            </svg>
            <p>No messages yet.<br>Your private conversations will appear here.</p>
        </div>
        
        <!-- Placeholder for when messages exist
        <ul class="chat-list">
            <li class="chat-list-item chat-unread" onclick="showComingSoon()">
                <div class="chat-avatar">M</div>
                <div class="chat-details">
                    <div class="chat-name-time">
                        <div class="chat-name">Mentor Support <span class="unread-badge">1</span></div>
                        <div class="chat-time">Just now</div>
                    </div>
                    <div class="chat-preview">Welcome to 2RICH! Let us know if you need any help.</div>
                </div>
            </li>
        </ul>
        -->
    </div>
    
    <div class="coming-soon-overlay" id="comingSoonOverlay">
        <div class="coming-soon-card">
            <h3>Coming Soon</h3>
            <p>Direct messaging is currently being built. It will be available in an upcoming update.</p>
            <button class="btn-close" onclick="hideComingSoon()">Close</button>
        </div>
    </div>

    <script>
        function showComingSoon() {
            document.getElementById('comingSoonOverlay').classList.add('active');
        }
        
        function hideComingSoon() {
            document.getElementById('comingSoonOverlay').classList.remove('active');
        }
    </script>
</body>
</html>
