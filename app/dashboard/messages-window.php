<?php
require_once '../auth/session-config.php';

if (
    (!isset($_SESSION['userid']) && !isset($_SESSION['user_id'])) ||
    !isset($_SESSION['authenticated'])
) {
    header('Location: https://app.2rich.capital/login');
    exit;
}

$user_id = $_SESSION['user_id'] ?? $_SESSION['userid'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Messages — 2RICH CAPITAL</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/assets/css/dashboard.css?v=<?php echo time(); ?>">
    <style>
        * { 
            margin: 0; 
            padding: 0; 
            box-sizing: border-box; 
            min-width: 0 !important;
        }

        html, body {
            overflow-x: hidden !important;
            min-width: 150px !important;
        }

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
            word-break: break-word;
            white-space: normal;
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
            overflow: hidden;
            position: relative;
        }
        
        /* Dashboard Group Chat Extracted Styles */
        .dashboard-group-chat-state {
            padding: 14px 20px;
            background: rgba(255,255,255,0.02);
            border-bottom: 1px solid rgba(255,255,255,0.05);
        }
        
        .dashboard-group-chat-switcher {
            width: 100%;
            background: rgba(255,255,255,0.05);
            border: 1px solid rgba(255,255,255,0.1);
            color: #fff;
            padding: 8px 12px;
            border-radius: 6px;
            font-family: inherit;
            font-size: 13px;
            cursor: pointer;
            outline: none;
            text-overflow: ellipsis;
            white-space: nowrap;
            overflow: hidden;
        }
        
        .dashboard-group-chat-messages {
            flex: 1 !important;
            max-height: none !important;
            height: auto !important;
            overflow-y: auto;
            padding: 14px 20px;
            display: flex;
            flex-direction: column;
            gap: 12px;
            scrollbar-width: thin;
            scrollbar-color: #1a1a1a transparent;
        }
        
        .dashboard-group-chat-messages::-webkit-scrollbar {
            width: 4px;
        }
        
        .dashboard-group-chat-messages::-webkit-scrollbar-thumb {
            background: #1a1a1a;
            border-radius: 4px;
        }
        
        .dashboard-group-chat-message {
            background: rgba(255,255,255,0.03);
            padding: 12px;
            border-radius: 8px;
            border: 1px solid rgba(255,255,255,0.02);
        }
        
        .dashboard-group-chat-message.unread-highlight {
            animation: highlightFade 4s forwards;
        }
        
        @keyframes highlightFade {
            0% { background: rgba(242, 202, 80, 0.15); border-color: rgba(242, 202, 80, 0.3); }
            100% { background: rgba(255,255,255,0.03); border-color: rgba(255,255,255,0.02); }
        }
        
        .dashboard-group-chat-message-meta {
            display: flex;
            justify-content: space-between;
            font-size: 10px;
            color: #666;
            margin-bottom: 6px;
        }
        
        .dashboard-group-chat-date-separator {
            display: flex;
            align-items: center;
            text-align: center;
            margin: 12px 0 4px 0;
            color: #666;
            font-size: 10px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.08em;
        }
        
        .dashboard-group-chat-date-separator:first-child {
            margin-top: 0;
        }
        
        .dashboard-group-chat-date-separator::before,
        .dashboard-group-chat-date-separator::after {
            content: '';
            flex: 1;
            border-bottom: 1px solid rgba(255, 255, 255, 0.05);
        }
        
        .dashboard-group-chat-date-separator span {
            padding: 0 10px;
        }
        
        .dashboard-group-chat-message-reply {
            background: none;
            border: none;
            color: #666;
            cursor: pointer;
            padding: 2px;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: color 0.2s, opacity 0.2s;
            opacity: 0;
        }
        
        .dashboard-group-chat-message:hover .dashboard-group-chat-message-reply {
            opacity: 1;
        }
        
        .dashboard-group-chat-message-reply:hover {
            color: #F2CA50;
        }
        
        @media (hover: none) {
            .dashboard-group-chat-message-reply { opacity: 0.6; }
        }
        
        .dashboard-group-chat-message-author {
            color: #F2CA50;
            font-weight: 600;
        }
        
        .dashboard-group-chat-message-text {
            font-size: 13px;
            color: #e0e0e0;
            line-height: 1.4;
            word-wrap: break-word;
        }
        
        .dashboard-group-chat-empty {
            color: #666;
            text-align: center;
            font-size: 12px;
            padding: 40px 20px;
            font-style: italic;
        }
        
        .dashboard-group-chat-footer {
            padding: 14px 20px;
            background: rgba(14,14,14,0.95);
            border-top: 1px solid #1a1a1a;
            display: flex;
            flex-direction: column;
            gap: 10px;
            position: relative;
            margin-top: auto;
            z-index: 10;
        }
        
        .dashboard-group-chat-composer {
            display: flex;
            gap: 8px;
        }
        
        .dashboard-group-chat-composer input {
            flex: 1;
            background: rgba(255,255,255,0.05);
            border: 1px solid rgba(255,255,255,0.1);
            padding: 10px 14px;
            border-radius: 20px;
            color: #fff;
            font-family: inherit;
            font-size: 13px;
            outline: none;
            transition: border-color 0.2s;
        }
        
        .dashboard-group-chat-composer input:focus {
            border-color: rgba(242, 202, 80, 0.5);
        }
        
        .dashboard-group-chat-send {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: #F2CA50;
            color: #0E0E0E;
            border: none;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: transform 0.1s, opacity 0.2s;
            flex-shrink: 0;
        }
        
        .dashboard-group-chat-send:active {
            transform: scale(0.95);
        }
        
        .dashboard-group-chat-send:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }
        
        .widget-action {
            width: 100%;
            background: rgba(255,255,255,0.05);
            border: 1px solid rgba(255,255,255,0.1);
            color: #eee;
            padding: 10px;
            border-radius: 6px;
            font-family: inherit;
            font-size: 12px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 8px;
            white-space: normal !important;
            flex-wrap: wrap;
        }
        
        .widget-action:hover {
            background: rgba(255,255,255,0.1);
            color: #F2CA50;
        }
        
        .widget-content-block {
            text-align: center;
            padding: 20px;
        }
        .widget-content-text {
            font-size: 12px;
            color: #888;
        }
    </style>
</head>
<body>

    <div class="mw-header">
        <span class="mw-brand">2RICH — Messages</span>
        <div class="mw-status">
            <span class="status-dot"></span>
            <span>Online</span>
        </div>
    </div>

    <div class="mw-content">
        <div id="dashboardGroupChatState" class="dashboard-group-chat-state">
            <div class="widget-content-block"><p class="widget-content-text">Loading your joined trading group...</p></div>
        </div>
        <div id="dashboardGroupChatMessages" class="dashboard-group-chat-messages" aria-live="polite"></div>
        <div id="dashboardGroupChatFooter" class="dashboard-group-chat-footer" hidden>
            <div id="dashboardGroupChatReplyPreview" class="dashboard-group-chat-reply-preview" style="display:none;">
                <div class="dashboard-group-chat-reply-preview-content">
                    <span id="dashboardGroupChatReplyPreviewAuthor" class="dashboard-group-chat-reply-preview-author"></span>
                    <span id="dashboardGroupChatReplyPreviewText" class="dashboard-group-chat-reply-preview-text"></span>
                </div>
                <button type="button" class="dashboard-group-chat-reply-cancel" onclick="cancelReply()">
                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>
                </button>
            </div>
            <div id="dashboardGroupChatComposer" class="dashboard-group-chat-composer" hidden>
                <input id="dashboardGroupChatInput" type="text" maxlength="1000" placeholder="Write a message..." aria-label="Write a group chat message">
                <button id="dashboardGroupChatSend" class="dashboard-group-chat-send" type="button" aria-label="Send message" title="Send message"><svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg></button>
            </div>
            <button id="dashboardGroupChatCta" class="widget-action" type="button" onclick="window.opener ? window.opener.location.href='/trading-floor#groups' : window.location.href='/trading-floor#groups'" hidden>Choose a Group <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg></button>
        </div>
    </div>

    <script>
        const CURRENT_USER_ID = <?php echo $user_id; ?>;
        const membershipsUrl = '/api/signals/my-memberships.php';
        const messagesUrl = '/api/signals/messages.php';
        
        let memberships = [];
        let selectedGroupId = null;

        const state = document.getElementById('dashboardGroupChatState');
        const messages = document.getElementById('dashboardGroupChatMessages');
        const composer = document.getElementById('dashboardGroupChatComposer');
        const input = document.getElementById('dashboardGroupChatInput');
        const send = document.getElementById('dashboardGroupChatSend');
        const cta = document.getElementById('dashboardGroupChatCta');
        const footer = document.getElementById('dashboardGroupChatFooter');

        const escapeHtml = value => String(value ?? '').replace(/[&<>'"]/g, ch => ({'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#039;','"':'&quot;'}[ch]));
        const time = value => { const d = new Date(String(value).replace(' ', 'T') + (String(value).includes('Z') ? '' : 'Z')); return Number.isNaN(d.getTime()) ? '' : d.toLocaleTimeString([], {hour:'2-digit', minute:'2-digit'}); };

        const formatDateSeparator = value => {
            const d = new Date(String(value).replace(' ', 'T') + (String(value).includes('Z') ? '' : 'Z'));
            if (Number.isNaN(d.getTime())) return '';
            const today = new Date();
            const yesterday = new Date();
            yesterday.setDate(today.getDate() - 1);
            
            const isSameDate = (d1, d2) => d1.getDate() === d2.getDate() && d1.getMonth() === d2.getMonth() && d1.getFullYear() === d2.getFullYear();
            
            if (isSameDate(d, today)) return 'Today';
            if (isSameDate(d, yesterday)) return 'Yesterday';
            
            return d.toLocaleDateString([], { month: 'long', day: 'numeric', year: 'numeric' });
        };

        let currentReplyToId = null;

        window.replyToMessage = function(id, authorName, text) {
            currentReplyToId = id;
            document.getElementById('dashboardGroupChatReplyPreviewAuthor').textContent = authorName;
            document.getElementById('dashboardGroupChatReplyPreviewText').textContent = text;
            document.getElementById('dashboardGroupChatReplyPreview').style.display = 'flex';
            if (input) input.focus();
        };

        window.cancelReply = function() {
            currentReplyToId = null;
            document.getElementById('dashboardGroupChatReplyPreview').style.display = 'none';
        };

        function showState(html) { state.innerHTML = html; state.hidden = false; }
        function setCta(label, href, visible) { cta.innerHTML = `${label} <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg>`; cta.onclick = () => { if (window.opener) window.opener.location.href = href; else window.location.href = href; }; cta.hidden = !visible; if (footer) footer.hidden = false; }
        
        let lastSeenId = 0;
        let currentMessagesCache = '';

        function renderMessages(items) {
            if (!items || !items.length) {
                messages.innerHTML = '<div class="dashboard-group-chat-empty">No messages yet. Start the conversation.</div>';
                return;
            }
            
            const lastItem = items[items.length - 1];
            const newCache = items.length + '_' + lastItem.id;
            if (newCache === currentMessagesCache) return;
            currentMessagesCache = newCache;

            const isScrolledToBottom = messages.scrollHeight - messages.clientHeight <= messages.scrollTop + 10;
            const currentCount = messages.childElementCount;
            let hasNewExternalMessage = false;
            
            let html = '';
            let lastDateStr = null;
            
            items.forEach(item => {
                const isNew = lastSeenId > 0 && Number(item.id) > lastSeenId;
                if (isNew && String(item.user_id || '') !== String(CURRENT_USER_ID)) hasNewExternalMessage = true;
                const highlightClass = isNew ? ' unread-highlight' : '';
                
                const currentDateStr = formatDateSeparator(item.created_at);
                if (currentDateStr && currentDateStr !== lastDateStr) {
                    html += `<div class="dashboard-group-chat-date-separator"><span>${escapeHtml(currentDateStr)}</span></div>`;
                    lastDateStr = currentDateStr;
                }
                
                const safeAuthor = escapeHtml(item.author_name || 'Member');
                const safeAuthorForJs = safeAuthor.replace(/'/g, "\\'");
                const safeTextForJs = escapeHtml(item.message).replace(/'/g, "\\'").replace(/\n/g, " ");
                const replyIcon = `<button class="dashboard-group-chat-message-reply" onclick="replyToMessage(${item.id}, '${safeAuthorForJs}', '${safeTextForJs}')" aria-label="Reply" title="Reply to ${safeAuthor}"><svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 17 4 12 9 7"></polyline><path d="M20 18v-2a4 4 0 0 0-4-4H4"></path></svg></button>`;
                
                let replyHtml = '';
                if (item.reply_to_id) {
                    const rAuthor = escapeHtml(item.reply_to_author_name || 'Member');
                    const rText = escapeHtml(item.reply_to_message_text || '...');
                    replyHtml = `<div class="dashboard-group-chat-replied-to"><div class="dashboard-group-chat-replied-author">${rAuthor}</div><div class="dashboard-group-chat-replied-text">${rText}</div></div>`;
                }
                
                html += `<div class="dashboard-group-chat-message${highlightClass}">${replyHtml}<div class="dashboard-group-chat-message-meta"><span class="dashboard-group-chat-message-author">${safeAuthor}</span><div style="display:flex;align-items:center;gap:6px;"><span>${escapeHtml(time(item.created_at))}</span>${replyIcon}</div></div><div class="dashboard-group-chat-message-text">${escapeHtml(item.message)}</div></div>`;
            });
            
            messages.innerHTML = html;
            
            lastSeenId = Math.max(...items.map(i => Number(i.id)));

            if (isScrolledToBottom || currentCount === 0 || currentCount === 1) {
                messages.scrollTop = messages.scrollHeight;
            }
            if (footer) footer.hidden = false;
        }
        
        async function loadMessages() {
            if (!selectedGroupId) return;
            try { const r = await fetch(`${messagesUrl}?group_id=${encodeURIComponent(selectedGroupId)}`, {credentials:'same-origin'}); const data = await r.json(); if (!r.ok || !data.success) throw new Error(data.message || 'Unable to load messages'); renderMessages(data.messages || []); } catch (e) { /* silent fail on poll */ }
        }
        
        async function selectGroup(id) {
            selectedGroupId = Number(id);
            lastSeenId = 0;
            currentMessagesCache = '';
            const group = memberships.find(item => Number(item.id) === selectedGroupId);
            if (!group) return;
            state.innerHTML = `<select class="dashboard-group-chat-switcher" aria-label="Select joined group">${memberships.map(item => `<option value="${item.id}" ${Number(item.id) === selectedGroupId ? 'selected' : ''}>${escapeHtml(item.name)}</option>`).join('')}</select>`;
            state.hidden = false;
            state.querySelector('select').addEventListener('change', e => selectGroup(e.target.value));
            composer.hidden = false; setCta('Visit Group', `/trading-floor#groups&group=${encodeURIComponent(String(group.id))}`, true);
            await loadMessages();
        }
        
        async function init() {
            try { const r = await fetch(membershipsUrl, {credentials:'same-origin'}); const data = await r.json(); if (!r.ok || !data.success) throw new Error(data.message || 'Unable to load memberships'); memberships = data.memberships || []; if (!memberships.length) { showState('<div class="widget-content-block"><p class="widget-content-text dashboard-group-chat-empty">You have not joined a trading group yet. Choose a group on the Trading Floor to start chatting.</p></div>'); messages.innerHTML = ''; composer.hidden = true; setCta('Choose a Group', '/trading-floor#groups', true); return; } await selectGroup(memberships[0].id); } catch (e) { if (footer) footer.hidden = true; showState(`<div class="widget-content-block"><p class="widget-content-text">${escapeHtml(e.message)}</p></div>`); }
        }
        
        async function sendMessage() { const value = input.value.trim(); if (!value || !selectedGroupId) return; send.disabled = true; try { const r = await fetch(messagesUrl, {method:'POST', credentials:'same-origin', headers:{'Content-Type':'application/json'}, body:JSON.stringify({group_id:selectedGroupId, message:value, reply_to_id:currentReplyToId})}); const data = await r.json(); if (!r.ok || !data.success) throw new Error(data.message || 'Unable to send message'); input.value = ''; cancelReply(); await loadMessages(); } catch(e) { showState(`<div class="widget-content-block"><p class="widget-content-text">${escapeHtml(e.message)}</p></div>`); } finally { send.disabled = false; } }
        
        send.addEventListener('click', sendMessage); input.addEventListener('keydown', e => { if (e.key === 'Enter') sendMessage(); });
        
        init();
        
        // Use Web Worker for background-friendly polling
        const workerBlob = new Blob([`
            setInterval(() => postMessage('tick'), 4000);
        `], { type: 'application/javascript' });
        const pollWorker = new Worker(URL.createObjectURL(workerBlob));
        
        pollWorker.onmessage = () => {
            loadMessages();
        };
    </script>
</body>
</html>
