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
            display: flex;
            flex-direction: column;
            gap: 10px;
            position: relative;
            margin-top: auto;
            z-index: 10;
        }
        
        .dashboard-group-chat-reply-preview {
            position: absolute;
            bottom: 100%;
            left: 0;
            right: 0;
            background: rgba(20,20,20,0.95);
            border-top: 1px solid #1a1a1a;
            border-bottom: 1px solid #1a1a1a;
            padding: 10px 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            backdrop-filter: blur(8px);
            z-index: 11;
        }
        .dashboard-group-chat-reply-preview-content {
            display: flex;
            flex-direction: column;
            overflow: hidden;
            flex: 1;
            border-left: 2px solid #F2CA50;
            padding-left: 8px;
        }
        .dashboard-group-chat-reply-preview-author { color: #F2CA50; font-size: 11px; font-weight: 700; margin-bottom: 2px; }
        .dashboard-group-chat-reply-preview-text { color: #aaa; font-size: 11px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; line-height: 1.3; }
        .dashboard-group-chat-reply-cancel { background: none; border: none; color: #888; cursor: pointer; padding: 4px; display: flex; align-items: center; justify-content: center; }
        .dashboard-group-chat-reply-cancel:hover { color: #f87171; }
        

        
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
            <div id="dashboardGroupChatAttachmentPreview" style="display:none;margin-bottom:8px;align-items:center;gap:8px;padding:8px;background:rgba(255,255,255,0.03);border:1px solid rgba(255,255,255,0.08);border-radius:12px;">
                <img id="dashboardGroupChatAttachmentImg" src="" style="width:40px;height:40px;object-fit:cover;border-radius:6px;">
                <div style="flex:1;overflow:hidden;">
                    <div id="dashboardGroupChatAttachmentName" style="font-size:12px;color:#f5f5f5;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;"></div>
                    <div id="dashboardGroupChatAttachmentSize" style="font-size:11px;color:#a9afb8;"></div>
                </div>
                <button type="button" onclick="clearDashboardAttachment()" style="background:none;border:none;color:#f87171;cursor:pointer;padding:4px;"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg></button>
            </div>
            <div id="dashboardGroupChatComposer" class="dashboard-group-chat-composer" style="position:relative;" hidden>
                <div id="mentionAutocomplete" style="display:none;position:absolute;bottom:calc(100% + 8px);left:48px;background:#1a1d24;border:1px solid rgba(255,255,255,0.1);border-radius:12px;padding:8px;max-height:200px;overflow-y:auto;z-index:100;min-width:200px;flex-direction:column;gap:4px;box-shadow:0 10px 30px rgba(0,0,0,0.5);"></div>
                <button type="button" class="dashboard-group-chat-attach" onclick="document.getElementById('dashboardGroupChatAttachment').click()" aria-label="Attach image"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21.44 11.05l-9.19 9.19a6 6 0 0 1-8.49-8.49l9.19-9.19a4 4 0 0 1 5.66 5.66l-9.2 9.19a2 2 0 0 1-2.83-2.83l8.49-8.48"></path></svg></button>
                <input type="file" id="dashboardGroupChatAttachment" accept="image/*,.pdf,.doc,.docx,.xls,.xlsx,.csv,.txt,.md,.zip,.mp4,.mov" style="display:none" onchange="window.handleDashboardAttachment(event)">
                <input id="dashboardGroupChatInput" type="text" maxlength="1000" placeholder="Write a message..." aria-label="Write a group chat message" oninput="window.handleMentionAutocomplete(this)" onkeydown="window.handleMentionKeydown(event)">
                <button id="dashboardGroupChatSend" class="dashboard-group-chat-send" type="button" aria-label="Send message" title="Send message"><svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg></button>
            </div>
            <button id="dashboardGroupChatCta" class="widget-action" type="button" onclick="window.opener ? window.opener.location.href='/trading-floor#groups' : window.location.href='/trading-floor#groups'" hidden>Choose a Group <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg></button>
        </div>
    </div>

    <script>
        window.CSRF_TOKEN = '<?php echo $_SESSION["csrf_token"] ?? ""; ?>';
        const CURRENT_USER_ID = <?php echo $user_id; ?>;
        const CURRENT_USER_NAME = <?php echo json_encode($_SESSION['user_name'] ?? 'Member'); ?>;
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

        window.replyToMessage = function(btn) {
            let id, author, text;
            if (btn.getAttribute) {
                id = btn.getAttribute('data-id');
                author = btn.getAttribute('data-author');
                text = btn.getAttribute('data-text');
            } else if (btn.dataset) {
                id = btn.dataset.id;
                author = btn.dataset.author;
                text = btn.dataset.text;
            }
            currentReplyToId = id;
            document.getElementById('dashboardGroupChatReplyPreviewAuthor').textContent = author;
            document.getElementById('dashboardGroupChatReplyPreviewText').textContent = text;
            document.getElementById('dashboardGroupChatReplyPreview').style.display = 'flex';
            const input = document.getElementById('dashboardGroupChatInput');
            if (input) input.focus();
        };

        window.cancelReply = function() {
            currentReplyToId = null;
            document.getElementById('dashboardGroupChatReplyPreview').style.display = 'none';
        };

        window.openImageLightbox = function(url) {
            document.getElementById('imageLightboxImg').src = url;
            document.getElementById('imageLightboxModal').style.display = 'flex';
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
            const newCache = items.length + '_' + lastItem.id + '_' + JSON.stringify(items.map(i => i.reactions || {}));
            if (newCache === currentMessagesCache) return;
            currentMessagesCache = newCache;
            window.currentMessages = items;

            const isScrolledToBottom = messages.scrollHeight - messages.clientHeight <= messages.scrollTop + 10;
            const currentCount = messages.childElementCount;
            let hasNewExternalMessage = false;
            
            let html = '';
            let lastDateStr = null;
            
            items.forEach(item => {
                const isNew = lastSeenId > 0 && Number(item.id) > lastSeenId;
                if (isNew && String(item.user_id || '') !== String(CURRENT_USER_ID)) hasNewExternalMessage = true;
                const hasMention = typeof CURRENT_USER_NAME !== 'undefined' && CURRENT_USER_NAME ? (item.message || '').toLowerCase().includes('@' + String(CURRENT_USER_NAME).toLowerCase()) : false;
                let highlightClass = isNew ? ' unread-highlight' : '';
                if (hasMention) highlightClass += ' mention-highlight';
                
                const currentDateStr = formatDateSeparator(item.created_at);
                if (currentDateStr && currentDateStr !== lastDateStr) {
                    html += `<div class="dashboard-group-chat-date-separator"><span>${escapeHtml(currentDateStr)}</span></div>`;
                    lastDateStr = currentDateStr;
                }
                
                const safeAuthor = escapeHtml(item.author_name || 'Member');
                const replyAuthor = escapeHtml(item.author_name || 'Member');
                const replyText = escapeHtml(item.message || '');
                const timestamp = item.created_at ? new Date(String(item.created_at).replace(' ', 'T')).toLocaleString() : 'Just now';
                
                let replyHtml = '';
                if (item.reply_to_id) {
                    const rAuthor = escapeHtml(item.reply_to_author_name || 'Member');
                    const rText = escapeHtml(item.reply_to_message_text || '...');
                    replyHtml = `<div class="dashboard-group-chat-replied-to"><div class="dashboard-group-chat-replied-author">${rAuthor}</div><div class="dashboard-group-chat-replied-text">${rText}</div></div>`;
                }
                
                let msgText = escapeHtml(item.message || '');
                msgText = msgText.replace(/(https?:\/\/[^\s]+(?:png|jpg|jpeg|gif|webp)|https?:\/\/pub-[a-zA-Z0-9-]+\.r2\.dev\/[^\s]+)/gi, function(match) {
                    const lower = match.toLowerCase();
                    if (lower.endsWith('.mp4') || lower.endsWith('.mov') || lower.endsWith('.webm')) {
                        return '<video src="'+match+'" controls style="max-width:100%;max-height:300px;border-radius:8px;margin-top:8px;display:block;"></video>';
                    } else if (lower.match(/\.(pdf|doc|docx|xls|xlsx|csv|txt|md|zip)$/)) {
                        const filenameParts = match.split('/');
                        const filenameFull = filenameParts[filenameParts.length - 1].split('?')[0];
                        let filenameParsed = filenameFull;
                        try { filenameParsed = decodeURIComponent(filenameFull); } catch(e) {}
                        const label = filenameParsed.length > 25 ? filenameParsed.substring(0, 25) + '...' : filenameParsed;
                        return '<a href="'+match+'" target="_blank" style="display:inline-flex;align-items:center;gap:6px;background:rgba(255,255,255,0.05);border:1px solid rgba(255,255,255,0.1);padding:8px 12px;border-radius:8px;color:#f2ca50;text-decoration:none;margin-top:8px;font-weight:600;font-size:12px;"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M13 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V9z"></path><polyline points="13 2 13 9 20 9"></polyline></svg> '+escapeHtml(label)+'</a>';
                    } else {
                        return '<img src="'+match+'" style="max-width:100%;max-height:200px;border-radius:8px;margin-top:8px;display:block;cursor:pointer;" onclick="openImageLightbox(\''+match+'\')">';
                    }
                });
                msgText = msgText.replace(/(^|\s)(@[a-zA-Z0-9_]+)/g, '$1<span class="dashboard-group-chat-mention">$2</span>');
                
                let reactionsHtml = '';
                if (item.reactions && Object.keys(item.reactions).length > 0) {
                    reactionsHtml = '<div style="display:flex;gap:4px;margin-top:6px;flex-wrap:wrap;">';
                    for (const react in item.reactions) {
                        const users = item.reactions[react];
                        const isMe = typeof CURRENT_USER_ID !== 'undefined' && users.includes(Number(CURRENT_USER_ID));
                        const bg = isMe ? 'rgba(242,202,80,0.15)' : 'rgba(255,255,255,0.05)';
                        const border = isMe ? '1px solid rgba(242,202,80,0.3)' : '1px solid transparent';
                        reactionsHtml += `<button type="button" onclick="window.toggleMessageReaction(${item.id}, '${react.replace(/'/g, "\\'")}')" style="background:${bg};border:${border};border-radius:12px;padding:2px 6px;font-size:11px;color:#cfd4dd;display:flex;align-items:center;gap:4px;cursor:pointer;line-height:1;">${escapeHtml(react)} <span style="opacity:0.7;">${users.length}</span></button>`;
                    }
                    reactionsHtml += '</div>';
                }
                
                const authorJs = escapeHtml(item.author_name || 'Member').replace(/'/g, "\\'");
                const textJs = escapeHtml(item.message || '').replace(/'/g, "\\'");
                
                const replyIcon = `<button type="button" class="dashboard-group-chat-message-reply" onclick="window.replyToMessage(this)" data-id="${item.id}" data-author="${replyAuthor}" data-text="${replyText}" aria-label="Reply" title="Reply to ${replyAuthor}" style="background:none;border:none;color:#666;cursor:pointer;padding:2px;display:flex;align-items:center;justify-content:center;transition:color 0.2s;"><svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 17 4 12 9 7"></polyline><path d="M20 18v-2a4 4 0 0 0-4-4H4"></path></svg></button>`;
                
                html += `<div class="dashboard-group-chat-message${highlightClass}" oncontextmenu="window.openMessageContextMenu(event, ${item.id}, '${authorJs}', '${textJs}'); return false;">${replyHtml}<div class="dashboard-group-chat-message-meta"><span class="dashboard-group-chat-message-author" data-user-id="${item.user_id}">${safeAuthor}</span><div style="display:flex;align-items:center;gap:6px;"><span>${escapeHtml(timestamp)}</span>${replyIcon}</div></div><div class="dashboard-group-chat-message-text">${msgText}</div>${reactionsHtml}</div>`;
            });
            
            messages.innerHTML = html;
            if (typeof updateOnlineDots === 'function') updateOnlineDots();
            
            lastSeenId = Math.max(...items.map(i => Number(i.id)));

            if (isScrolledToBottom || currentCount === 0 || currentCount === 1) {
                messages.scrollTop = messages.scrollHeight;
            }
            if (footer) footer.hidden = false;
        }

        window.openMessageContextMenu = function(e, msgId, author, text) {
            e.preventDefault();
            
            let menu = document.getElementById('messageContextMenu');
            if (!menu) {
                menu = document.createElement('div');
                menu.id = 'messageContextMenu';
                menu.style.position = 'fixed';
                menu.style.background = '#1a1d24';
                menu.style.border = '1px solid rgba(255,255,255,0.1)';
                menu.style.borderRadius = '12px';
                menu.style.padding = '8px';
                menu.style.zIndex = '9999';
                menu.style.boxShadow = '0 10px 30px rgba(0,0,0,0.5)';
                menu.style.display = 'flex';
                menu.style.flexDirection = 'column';
                menu.style.gap = '8px';
                document.body.appendChild(menu);
                
                document.addEventListener('click', function(ev) {
                    if (ev.target.closest('#messageContextMenu')) return;
                    menu.style.display = 'none';
                });
                document.addEventListener('contextmenu', function(ev) {
                    if (!ev.target.closest('.dashboard-group-chat-message')) {
                        menu.style.display = 'none';
                    }
                });
            }
            
            const emojis = ['👍', '❤️', '😂', '🔥', '🚀', '👀'];
            let emojiHtml = '<div style="display:flex;gap:8px;justify-content:space-between;padding-bottom:8px;border-bottom:1px solid rgba(255,255,255,0.05);">';
            for (const emoji of emojis) {
                emojiHtml += `<button type="button" onclick="window.toggleMessageReaction(${msgId}, '${emoji}'); document.getElementById('messageContextMenu').style.display='none'" style="background:none;border:none;cursor:pointer;font-size:18px;padding:4px;border-radius:50%;transition:background 0.2s;" onmouseover="this.style.background='rgba(255,255,255,0.1)'" onmouseout="this.style.background='transparent'">${emoji}</button>`;
            }
            emojiHtml += '</div>';
            
            let actionsHtml = `<button type="button" onclick="window.replyToMessage({dataset:{id:${msgId}, author:'${author.replace(/'/g, "\\'")}', text:'${text.replace(/'/g, "\\'")}'}}); document.getElementById('messageContextMenu').style.display='none'" style="background:none;border:none;color:#cfd4dd;font-size:13px;text-align:left;cursor:pointer;padding:8px 12px;border-radius:6px;transition:background 0.2s;" onmouseover="this.style.background='rgba(255,255,255,0.05)'" onmouseout="this.style.background='transparent'">Reply</button>`;
            
            menu.innerHTML = emojiHtml + actionsHtml;
            menu.style.display = 'flex';
            
            menu.style.left = e.clientX + 'px';
            menu.style.top = e.clientY + 'px';
            
            setTimeout(() => {
                const rect = menu.getBoundingClientRect();
                if (rect.right > window.innerWidth) {
                    menu.style.left = (window.innerWidth - rect.width - 10) + 'px';
                }
                if (rect.bottom > window.innerHeight) {
                    menu.style.top = (window.innerHeight - rect.height - 10) + 'px';
                }
            }, 0);
        };

        window.toggleMessageReaction = async function(messageId, reaction) {
            const msg = (window.currentMessages || []).find(m => Number(m.id) === Number(messageId));
            if (msg) {
                if (!msg.reactions) msg.reactions = {};
                if (!msg.reactions[reaction]) msg.reactions[reaction] = [];
                const userIndex = msg.reactions[reaction].indexOf(Number(CURRENT_USER_ID));
                if (userIndex > -1) {
                    msg.reactions[reaction].splice(userIndex, 1);
                    if (msg.reactions[reaction].length === 0) delete msg.reactions[reaction];
                } else {
                    msg.reactions[reaction].push(Number(CURRENT_USER_ID));
                }
                currentMessagesCache = '';
                renderMessages(window.currentMessages);
            }
            try {
                await fetch('/api/signals/react-message.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': window.CSRF_TOKEN || '' },
                    body: JSON.stringify({ message_id: messageId, reaction: reaction }),
                    credentials: 'include'
                });
            } catch (err) {
                console.error(err);
            }
        };
        
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
            
            try {
                const res = await fetch(`/api/signals/group-staff.php?group_id=${selectedGroupId}`);
                const d = await res.json();
                if(d.success) window.currentGroupMembers = d.staff || [];
            } catch(e) {}
        }
        
        async function init() {
            try { const r = await fetch(membershipsUrl, {credentials:'same-origin'}); const data = await r.json(); if (!r.ok || !data.success) throw new Error(data.message || 'Unable to load memberships'); memberships = data.memberships || []; if (!memberships.length) { showState('<div class="widget-content-block"><p class="widget-content-text dashboard-group-chat-empty">You have not joined a trading group yet. Choose a group on the Trading Floor to start chatting.</p></div>'); messages.innerHTML = ''; composer.hidden = true; setCta('Choose a Group', '/trading-floor#groups', true); return; } await selectGroup(memberships[0].id); } catch (e) { if (footer) footer.hidden = true; showState(`<div class="widget-content-block"><p class="widget-content-text">${escapeHtml(e.message)}</p></div>`); }
        }

        window.userActivityData = {};
        async function pollOnlineMembers() {
            if (!selectedGroupId) return;
            try {
                const res = await fetch(`/api/signals/online-members.php?group_id=${selectedGroupId}`);
                const data = await res.json();
                if (data.success && data.user_activity) {
                    window.userActivityData = data.user_activity;
                    updateOnlineDots();
                }
            } catch (e) { }
        }
        setInterval(pollOnlineMembers, 30000);

        function updateOnlineDots() {
            const now = Math.floor(Date.now() / 1000);
            document.querySelectorAll('[data-user-id]').forEach(el => {
                const uid = Number(el.getAttribute('data-user-id'));
                if (!uid || !window.userActivityData[uid]) return;
                
                const lastActive = window.userActivityData[uid];
                const diff = now - lastActive;
                let isOnline = diff < 120;
                let text = isOnline ? 'Online' : `Last seen ${Math.floor(diff/60)}m ago`;
                if (diff >= 3600) text = `Last seen ${Math.floor(diff/3600)}h ago`;
                if (diff >= 86400) text = `Last seen ${Math.floor(diff/86400)}d ago`;

                let dot = el.querySelector('.online-status-dot');
                if (!dot) {
                    dot = document.createElement('span');
                    dot.className = 'online-status-dot';
                    dot.style.cssText = 'display:inline-block; width:6px; height:6px; border-radius:50%; margin-left:6px; vertical-align:middle;';
                    el.appendChild(dot);
                }
                
                if (isOnline) {
                    dot.style.background = '#28a745';
                    dot.style.boxShadow = '0 0 4px rgba(40,167,69,0.6)';
                } else {
                    dot.style.background = '#6c757d';
                    dot.style.boxShadow = 'none';
                }
                dot.title = text;
            });
        }
        
        window.handleDashboardAttachment = function(event) {
            const file = event.target.files[0];
            if (!file) return;
            const preview = document.getElementById('dashboardGroupChatAttachmentPreview');
            const img = document.getElementById('dashboardGroupChatAttachmentImg');
            const name = document.getElementById('dashboardGroupChatAttachmentName');
            const size = document.getElementById('dashboardGroupChatAttachmentSize');
            if (preview && img && name && size) {
                if (file.type.startsWith('image/')) {
                    img.src = URL.createObjectURL(file);
                    img.style.display = 'block';
                } else {
                    img.style.display = 'none';
                }
                name.textContent = file.name;
                size.textContent = (file.size / 1024 / 1024).toFixed(2) + ' MB';
                preview.style.display = 'flex';
            }
        };

        window.clearDashboardAttachment = function() {
            const fileInput = document.getElementById('dashboardGroupChatAttachment');
            const preview = document.getElementById('dashboardGroupChatAttachmentPreview');
            if (fileInput) fileInput.value = '';
            if (preview) preview.style.display = 'none';
        };
        
        async function sendMessage() { 
            const value = input.value.trim(); 
            const attachmentInput = document.getElementById('dashboardGroupChatAttachment');
            const file = attachmentInput && attachmentInput.files.length ? attachmentInput.files[0] : null;
            
            if ((!value && !file) || !selectedGroupId) return; 
            send.disabled = true; 
            
            try { 
                let uploadedUrl = null;
                if (file) {
                    const fd = new FormData();
                    fd.append('file', file);
                    fd.append('group_id', selectedGroupId);
                    
                    const upRes = await fetch('/api/signals/upload-media.php', {
                        method: 'POST',
                        headers: { 'X-CSRF-Token': window.CSRF_TOKEN || '' },
                        body: fd
                    });
                    const upData = await upRes.json().catch(() => ({}));
                    if (!upRes.ok || !upData.success) {
                        throw new Error(upData.message || 'Failed to upload media.');
                    }
                    uploadedUrl = upData.url;
                }
                
                let finalMessage = value;
                if (uploadedUrl) {
                    finalMessage = finalMessage ? (finalMessage + '\n\n' + uploadedUrl) : uploadedUrl;
                }

                const r = await fetch(messagesUrl, {
                    method:'POST', 
                    credentials:'same-origin', 
                    headers:{'Content-Type':'application/json'}, 
                    body:JSON.stringify({group_id:selectedGroupId, message:finalMessage, reply_to_id:currentReplyToId})
                }); 
                const data = await r.json(); 
                if (!r.ok || !data.success) throw new Error(data.message || 'Unable to send message'); 
                
                input.value = ''; 
                cancelReply(); 
                clearDashboardAttachment();
                await loadMessages(); 
            } catch(e) { 
                showState(`<div class="widget-content-block"><p class="widget-content-text" style="color:#f87171;">${escapeHtml(e.message)}</p></div>`); 
            } finally { 
                send.disabled = false; 
            } 
        }
        
        send.addEventListener('click', sendMessage); 
        input.addEventListener('keydown', e => { 
            if (!window.mentionState || !window.mentionState.active) {
                if (e.key === 'Enter') { e.preventDefault(); sendMessage(); }
            }
        });
        
        window.currentGroupMembers = [];
        window.mentionState = { active: false, query: '', members: [], selectedIndex: 0 };
        
        window.handleMentionAutocomplete = function(input) {
            const val = input.value;
            const cursor = input.selectionStart;
            const textBeforeCursor = val.slice(0, cursor);
            const match = textBeforeCursor.match(/(?:^|\s)@([a-zA-Z0-9_]*)$/);
            const dropdown = document.getElementById('mentionAutocomplete');
            
            if (match) {
                const query = match[1].toLowerCase();
                window.mentionState.active = true;
                window.mentionState.query = query;
                
                let members = window.currentGroupMembers || [];
                if (query) {
                    members = members.filter(m => {
                        const name = m.display_name || m.user_login || '';
                        return name.toLowerCase().includes(query);
                    });
                }
                members = members.slice(0, 10);
                window.mentionState.members = members;
                window.mentionState.selectedIndex = 0;
                
                if (members.length > 0 && dropdown) {
                    renderMentionDropdown();
                    dropdown.style.display = 'flex';
                } else if (dropdown) {
                    dropdown.style.display = 'none';
                }
            } else {
                window.mentionState.active = false;
                if (dropdown) dropdown.style.display = 'none';
            }
        };
        
        function renderMentionDropdown() {
            const dropdown = document.getElementById('mentionAutocomplete');
            if (!dropdown) return;
            dropdown.innerHTML = window.mentionState.members.map((m, idx) => {
                const name = m.display_name || m.user_login || 'User #' + m.user_id;
                const bg = idx === window.mentionState.selectedIndex ? 'rgba(255,255,255,0.1)' : 'transparent';
                return `<div onmousedown="window.selectMention(${idx}); return false;" style="padding:6px 12px;border-radius:6px;cursor:pointer;background:${bg};color:#fff;font-size:13px;display:flex;align-items:center;gap:8px;" onmouseover="window.mentionState.selectedIndex=${idx};renderMentionDropdown()">
                    <div style="width:20px;height:20px;border-radius:50%;background:rgba(242,202,80,0.2);color:#f2ca50;display:flex;align-items:center;justify-content:center;font-size:10px;font-weight:700;">${name.charAt(0).toUpperCase()}</div>
                    ${escapeHtml(name)}
                </div>`;
            }).join('');
        }
        
        window.selectMention = function(idx) {
            if (!window.mentionState.active) return;
            const member = window.mentionState.members[idx];
            if (!member) return;
            const input = document.getElementById('dashboardGroupChatInput');
            if (!input) return;
            
            const val = input.value;
            const cursor = input.selectionStart;
            const textBeforeCursor = val.slice(0, cursor);
            const match = textBeforeCursor.match(/(?:^|\s)@([a-zA-Z0-9_]*)$/);
            
            if (match) {
                const name = member.display_name || member.user_login;
                const mentionText = '@' + name.replace(/\s+/g, '') + ' ';
                const replaceStart = cursor - match[1].length - 1;
                input.value = val.slice(0, replaceStart) + mentionText + val.slice(cursor);
                input.focus();
                const newCursor = replaceStart + mentionText.length;
                input.setSelectionRange(newCursor, newCursor);
            }
            
            window.mentionState.active = false;
            const dropdown = document.getElementById('mentionAutocomplete');
            if (dropdown) dropdown.style.display = 'none';
        };
        
        window.handleMentionKeydown = function(e) {
            if (!window.mentionState || !window.mentionState.active) return;
            
            const dropdown = document.getElementById('mentionAutocomplete');
            if (e.key === 'ArrowDown') {
                e.preventDefault();
                window.mentionState.selectedIndex = (window.mentionState.selectedIndex + 1) % window.mentionState.members.length;
                renderMentionDropdown();
                if (dropdown && dropdown.children[window.mentionState.selectedIndex]) {
                    dropdown.children[window.mentionState.selectedIndex].scrollIntoView({block: 'nearest'});
                }
            } else if (e.key === 'ArrowUp') {
                e.preventDefault();
                window.mentionState.selectedIndex = (window.mentionState.selectedIndex - 1 + window.mentionState.members.length) % window.mentionState.members.length;
                renderMentionDropdown();
                if (dropdown && dropdown.children[window.mentionState.selectedIndex]) {
                    dropdown.children[window.mentionState.selectedIndex].scrollIntoView({block: 'nearest'});
                }
            } else if (e.key === 'Enter') {
                e.preventDefault();
                window.selectMention(window.mentionState.selectedIndex);
            } else if (e.key === 'Escape') {
                window.mentionState.active = false;
                if (dropdown) dropdown.style.display = 'none';
            }
        };
        
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
    <div id="imageLightboxModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.9);z-index:9999;align-items:center;justify-content:center;cursor:zoom-out;flex-direction:column;gap:16px;" onclick="this.style.display='none'">
        <img id="imageLightboxImg" src="" style="max-width:90%;max-height:90%;object-fit:contain;border-radius:8px;box-shadow:0 20px 40px rgba(0,0,0,0.5);">
    </div>
</body>
</html>
