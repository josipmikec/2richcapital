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

    <script src="/assets/js/group-chat.js?v=<?php echo filemtime(__DIR__ . '/../assets/js/group-chat.js'); ?>"></script>
    <script>
        window.CSRF_TOKEN = '<?php echo $_SESSION["csrf_token"] ?? ""; ?>';

        const escapeHtml = value => String(value ?? '').replace(/[&<>'"]/g, ch => ({'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#039;','"':'&quot;'}[ch]));

        window.openImageLightbox = function(url) {
            document.getElementById('imageLightboxImg').src = url;
            document.getElementById('imageLightboxModal').style.display = 'flex';
        };

        window.openProfilePreview = async function(el, userId) {
            if (!userId) return;
            const existingModal = document.getElementById('profilePreviewModal');
            if (existingModal) existingModal.remove();

            const rect = el.getBoundingClientRect();
            
            const modal = document.createElement('div');
            modal.id = 'profilePreviewModal';
            modal.style.cssText = `position:fixed; left:${rect.left}px; top:${rect.bottom + 8}px; width:280px; background:#1e2025; border:1px solid #333; border-radius:12px; box-shadow:0 10px 25px rgba(0,0,0,0.5); z-index:99999; padding:16px; font-family:-apple-system,BlinkMacSystemFont,sans-serif; color:#fff; display:flex; flex-direction:column; gap:12px;`;
            
            modal.innerHTML = '<div style="text-align:center;color:#888;font-size:13px;padding:20px 0;">Loading profile...</div>';
            document.body.appendChild(modal);

            const closeHandler = (e) => {
                if (!modal.contains(e.target) && e.target !== el) {
                    modal.remove();
                    document.removeEventListener('click', closeHandler);
                }
            };
            setTimeout(() => document.addEventListener('click', closeHandler), 10);

            try {
                const res = await fetch(`/api/signals/profile-preview.php?user_id=${userId}`);
                const data = await res.json();
                if (!data.success) throw new Error();
                
                const p = data.profile;
                const now = Math.floor(Date.now() / 1000);
                const diff = now - p.last_active;
                let isOnline = p.last_active > 0 && diff < 120;
                let lastSeenText = isOnline ? '<span style="color:#28a745;font-weight:600;">Online</span>' : `Last seen ${Math.floor(diff/60)}m ago`;
                if (!isOnline && diff >= 3600) lastSeenText = `Last seen ${Math.floor(diff/3600)}h ago`;
                if (!isOnline && diff >= 86400) lastSeenText = `Last seen ${Math.floor(diff/86400)}d ago`;
                if (p.last_active === 0) lastSeenText = 'Offline';

                modal.innerHTML = `
                    <div style="display:flex; align-items:center; gap:12px;">
                        <div style="width:48px;height:48px;border-radius:50%;background:linear-gradient(135deg, #F2CA50, #FFDB70);color:#0e0e0e;display:flex;align-items:center;justify-content:center;font-size:20px;font-weight:800;position:relative;">
                            ${escapeHtml(p.avatar_char)}
                            <div style="position:absolute;bottom:0;right:0;width:12px;height:12px;border-radius:50%;background:${isOnline ? '#28a745' : '#6c757d'};border:2px solid #1e2025;"></div>
                        </div>
                        <div style="flex:1;overflow:hidden;">
                            <div style="font-weight:700;font-size:15px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">${escapeHtml(p.display_name)}</div>
                            <div style="font-size:12px;color:#8f95a3;margin-top:2px;">${escapeHtml(p.handle)}</div>
                        </div>
                    </div>
                    ${p.bio ? `<div style="font-size:13px;color:#cfd4dd;line-height:1.4;margin:4px 0;">${escapeHtml(p.bio)}</div>` : ''}
                    <div style="display:flex;align-items:center;justify-content:space-between;font-size:12px;margin-top:4px;">
                        <span style="color:#8f95a3;">${p.followers} followers</span>
                        <span style="color:#8f95a3;">${lastSeenText}</span>
                    </div>
                    <a href="/trading-floor/?profile=${p.user_id}" style="display:block;text-align:center;background:#2a2c33;color:#fff;text-decoration:none;padding:8px;border-radius:6px;font-size:13px;font-weight:600;margin-top:4px;transition:background 0.2s;">View Full Profile</a>
                `;
            } catch (e) {
                modal.innerHTML = '<div style="text-align:center;color:#ff5b5b;font-size:13px;padding:20px 0;">Failed to load profile.</div>';
            }
        };

        // Chat engine is shared with the dashboard card (assets/js/group-chat.js)
        window.GroupChat.init({
            currentUserId: <?php echo $user_id; ?>,
            currentUserName: <?php echo json_encode($_SESSION['user_name'] ?? 'Member'); ?>,
            formatUserDate: (v, mode) => (window.opener && window.opener.formatUserDate) ? window.opener.formatUserDate(v, mode) : null,
            imageOpener: 'openImageLightbox',
            showReplyIcon: true,
            composerUsesHiddenAttr: true,
            rememberSelection: false,
            preselectFromUrl: true,
            navigate: href => { if (window.opener) window.opener.location.href = href; else window.location.href = href; }
        });
    </script>
    <div id="imageLightboxModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.9);z-index:9999;align-items:center;justify-content:center;cursor:zoom-out;flex-direction:column;gap:16px;" onclick="this.style.display='none'">
        <img id="imageLightboxImg" src="" style="max-width:90%;max-height:90%;object-fit:contain;border-radius:8px;box-shadow:0 20px 40px rgba(0,0,0,0.5);">
    </div>
</body>
</html>
