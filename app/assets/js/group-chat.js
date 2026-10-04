/**
 * 2Rich Group Chat — single shared implementation.
 *
 * Used by:
 *   - /dashboard/index.php          (dashboard chat card)
 *   - /dashboard/messages-window.php (pop-out window)
 *
 * Markup/CSS is NOT touched here: the same element IDs and CSS classes are rendered as before.
 * Per-page differences are passed in through GroupChat.init({...}).
 *
 * Also exposes window.TwoRichChatAudio (unlock + play) which the Trading Floor reuses so
 * the new-message sound behaves identically everywhere (incl. Safari).
 */
(function () {
    'use strict';

    /* ------------------------------------------------------------------
     * Shared audio (Safari requires an AudioContext resumed in a user gesture)
     * ------------------------------------------------------------------ */
    let audioCtx = null;

    function unlockAudio() {
        try {
            const AC = window.AudioContext || window.webkitAudioContext;
            if (!AC) return;
            if (!audioCtx) audioCtx = new AC();
            if (audioCtx.state === 'suspended') audioCtx.resume();
            const buf = audioCtx.createBuffer(1, 1, 22050);
            const src = audioCtx.createBufferSource();
            src.buffer = buf;
            src.connect(audioCtx.destination);
            src.start(0);
        } catch (e) {}
    }

    function playPop() {
        try {
            const AC = window.AudioContext || window.webkitAudioContext;
            if (!AC) return;
            if (!audioCtx) audioCtx = new AC();
            if (audioCtx.state === 'suspended') audioCtx.resume();
            const osc = audioCtx.createOscillator();
            const gain = audioCtx.createGain();
            osc.connect(gain);
            gain.connect(audioCtx.destination);
            osc.type = 'sine';
            osc.frequency.setValueAtTime(800, audioCtx.currentTime);
            gain.gain.setValueAtTime(0.4, audioCtx.currentTime);
            gain.gain.exponentialRampToValueAtTime(0.01, audioCtx.currentTime + 0.1);
            osc.start(audioCtx.currentTime);
            osc.stop(audioCtx.currentTime + 0.1);
        } catch (e) {}
    }

    ['pointerdown', 'touchstart', 'click', 'keydown'].forEach(evt => {
        document.addEventListener(evt, unlockAudio, true);
    });

    window.TwoRichChatAudio = { unlock: unlockAudio, play: playPop };

    /* ------------------------------------------------------------------
     * Chat engine
     * ------------------------------------------------------------------ */
    function init(opts) {
        opts = opts || {};
        const membershipsUrl = '/api/signals/my-memberships.php';
        const messagesUrl = '/api/signals/messages.php';
        const CURRENT_USER_ID = opts.currentUserId;
        const CURRENT_USER_NAME = opts.currentUserName;

        const state = document.getElementById('dashboardGroupChatState');
        const messages = document.getElementById('dashboardGroupChatMessages');
        const composer = document.getElementById('dashboardGroupChatComposer');
        const input = document.getElementById('dashboardGroupChatInput');
        const send = document.getElementById('dashboardGroupChatSend');
        const cta = document.getElementById('dashboardGroupChatCta');
        const footer = document.getElementById('dashboardGroupChatFooter');
        if (!state || !messages) return null;

        // Per-page options
        const navigate = opts.navigate || (href => { window.location.href = href; });
        const imageOpener = opts.imageOpener || 'openGlobalImageModal';
        const showReplyIcon = !!opts.showReplyIcon;
        const composerHiddenAttr = !!opts.composerUsesHiddenAttr; // pop-out uses [hidden], dashboard uses style.display
        const remember = opts.rememberSelection !== false;        // dashboard remembers last group in localStorage
        const preselectFromUrl = !!opts.preselectFromUrl;         // pop-out reads ?group_id=

        let memberships = [];
        let selectedGroupId = 0;
        let lastSeenId = 0;
        let currentMessagesCache = '';
        let currentReplyToId = null;

        const escapeHtml = value => String(value ?? '').replace(/[&<>'"]/g, ch => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#039;', '"': '&quot;' }[ch]));

        const userDate = (value, mode) => {
            if (typeof opts.formatUserDate === 'function') {
                const out = opts.formatUserDate(value, mode);
                if (out !== undefined && out !== null) return out;
            }
            return null;
        };

        const time = value => {
            const viaUser = userDate(value, 'time');
            if (viaUser !== null) return viaUser;
            const d = new Date(String(value).replace(' ', 'T') + (String(value).includes('Z') ? '' : 'Z'));
            return Number.isNaN(d.getTime()) ? '' : d.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
        };

        const formatDateSeparator = value => {
            const d = new Date(String(value).replace(' ', 'T') + (String(value).includes('Z') ? '' : 'Z'));
            if (Number.isNaN(d.getTime())) return '';
            const today = new Date();
            const yesterday = new Date();
            yesterday.setDate(today.getDate() - 1);
            const isSameDate = (d1, d2) => d1.getDate() === d2.getDate() && d1.getMonth() === d2.getMonth() && d1.getFullYear() === d2.getFullYear();
            if (isSameDate(d, today)) return 'Today';
            if (isSameDate(d, yesterday)) return 'Yesterday';
            const viaUser = userDate(value, 'date');
            if (viaUser !== null) return viaUser;
            return d.toLocaleDateString([], { month: 'long', day: 'numeric', year: 'numeric' });
        };

        function setComposerVisible(visible) {
            if (composerHiddenAttr) composer.hidden = !visible;
            else composer.style.display = visible ? '' : 'none';
        }

        function showState(html) { state.innerHTML = html; state.hidden = false; }

        function setCta(label, href, visible) {
            cta.innerHTML = `${label} <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg>`;
            cta.onclick = () => navigate(href);
            cta.hidden = !visible;
            if (footer) footer.hidden = false;
        }

        /* ---------------- reply ---------------- */
        window.replyToMessage = function (btn) {
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
            if (input) input.focus();
        };

        window.cancelReply = function () {
            currentReplyToId = null;
            document.getElementById('dashboardGroupChatReplyPreview').style.display = 'none';
        };

        /* ---------------- render ---------------- */
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
                const hasMention = CURRENT_USER_NAME ? (item.message || '').toLowerCase().includes('@' + String(CURRENT_USER_NAME).toLowerCase()) : false;
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
                const timestamp = item.created_at ? time(item.created_at) : 'Just now';

                let replyHtml = '';
                if (item.reply_to_id) {
                    const rAuthor = escapeHtml(item.reply_to_author_name || 'Member');
                    const rText = escapeHtml(item.reply_to_message_text || '...');
                    replyHtml = `<div class="dashboard-group-chat-replied-to"><div class="dashboard-group-chat-replied-author">${rAuthor}</div><div class="dashboard-group-chat-replied-text">${rText}</div></div>`;
                }

                let msgText = escapeHtml(item.message || '');
                msgText = msgText.replace(/(https?:\/\/[^\s]+(?:png|jpg|jpeg|gif|webp)|https?:\/\/pub-[a-zA-Z0-9-]+\.r2\.dev\/[^\s]+)/gi, function (match) {
                    const lower = match.toLowerCase();
                    if (lower.endsWith('.mp4') || lower.endsWith('.mov') || lower.endsWith('.webm')) {
                        return '<video src="' + match + '" controls style="max-width:100%;max-height:300px;border-radius:8px;margin-top:8px;display:block;"></video>';
                    } else if (lower.match(/\.(pdf|doc|docx|xls|xlsx|csv|txt|md|zip)$/)) {
                        const filenameParts = match.split('/');
                        const filenameFull = filenameParts[filenameParts.length - 1].split('?')[0];
                        let filenameParsed = filenameFull;
                        try { filenameParsed = decodeURIComponent(filenameFull); } catch (e) {}
                        const label = filenameParsed.length > 25 ? filenameParsed.substring(0, 25) + '...' : filenameParsed;
                        return '<a href="' + match + '" target="_blank" style="display:inline-flex;align-items:center;gap:6px;background:rgba(255,255,255,0.05);border:1px solid rgba(255,255,255,0.1);padding:8px 12px;border-radius:8px;color:#f2ca50;text-decoration:none;margin-top:8px;font-weight:600;font-size:12px;"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M13 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V9z"></path><polyline points="13 2 13 9 20 9"></polyline></svg> ' + escapeHtml(label) + '</a>';
                    } else {
                        return '<img src="' + match + '" style="max-width:100%;max-height:200px;border-radius:8px;margin-top:8px;display:block;cursor:pointer;" onclick="' + imageOpener + '(\'' + match + '\')">';
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

                const replyIcon = showReplyIcon
                    ? `<button type="button" class="dashboard-group-chat-message-reply" onclick="window.replyToMessage(this)" data-id="${item.id}" data-author="${replyAuthor}" data-text="${replyText}" aria-label="Reply" title="Reply to ${replyAuthor}" style="background:none;border:none;color:#666;cursor:pointer;padding:2px;display:flex;align-items:center;justify-content:center;transition:color 0.2s;"><svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 17 4 12 9 7"></polyline><path d="M20 18v-2a4 4 0 0 0-4-4H4"></path></svg></button>`
                    : '';

                html += `<div class="dashboard-group-chat-message${highlightClass}" oncontextmenu="window.openMessageContextMenu(event, ${item.id}, '${authorJs}', '${textJs}'); return false;">${replyHtml}<div class="dashboard-group-chat-message-meta"><span class="dashboard-group-chat-message-author" data-user-id="${item.user_id}" onclick="window.openProfilePreview(this, ${item.user_id})" style="cursor:pointer;">${safeAuthor}</span><div style="display:flex;align-items:center;gap:6px;"><span>${escapeHtml(timestamp)}</span>${replyIcon}</div></div><div class="dashboard-group-chat-message-text">${msgText}</div>${reactionsHtml}</div>`;
            });

            messages.innerHTML = html;
            lastSeenId = Math.max(...items.map(i => Number(i.id)));

            if (isScrolledToBottom || currentCount === 0 || currentCount === 1) {
                messages.scrollTop = messages.scrollHeight;
            }
            if (footer) footer.hidden = false;
            if (hasNewExternalMessage) playPop();
        }

        /* ---------------- context menu ---------------- */
        window.openMessageContextMenu = function (e, msgId, author, text) {
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

                document.addEventListener('click', function (ev) {
                    if (ev.target.closest('#messageContextMenu')) return;
                    menu.style.display = 'none';
                });
                document.addEventListener('contextmenu', function (ev) {
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
            actionsHtml += `<button type="button" onclick="navigator.clipboard.writeText('${text.replace(/'/g, "\\'").replace(/\n/g, "\\n")}'); document.getElementById('messageContextMenu').style.display='none'" style="background:none;border:none;color:#cfd4dd;font-size:13px;text-align:left;cursor:pointer;padding:8px 12px;border-radius:6px;transition:background 0.2s;" onmouseover="this.style.background='rgba(255,255,255,0.05)'" onmouseout="this.style.background='transparent'">Copy</button>`;

            menu.innerHTML = emojiHtml + '<div style="display:flex;flex-direction:column;gap:2px;">' + actionsHtml + '</div>';
            menu.style.display = 'flex';
            menu.style.left = e.clientX + 'px';
            menu.style.top = e.clientY + 'px';

            setTimeout(() => {
                const rect = menu.getBoundingClientRect();
                if (rect.right > window.innerWidth) menu.style.left = (window.innerWidth - rect.width - 10) + 'px';
                if (rect.bottom > window.innerHeight) menu.style.top = (window.innerHeight - rect.height - 10) + 'px';
            }, 0);
        };

        // Long-press on messages (touch)
        (function () {
            let touchTimer = null;
            let touchStartX = 0, touchStartY = 0;

            document.addEventListener('touchstart', (e) => {
                const msgEl = e.target.closest('.dashboard-group-chat-message');
                if (msgEl) {
                    const touch = e.touches[0];
                    touchStartX = touch.clientX;
                    touchStartY = touch.clientY;
                    touchTimer = setTimeout(() => {
                        touchTimer = null;
                        const onclickStr = msgEl.getAttribute('oncontextmenu');
                        if (onclickStr) {
                            const match = onclickStr.match(/openMessageContextMenu\(event,\s*(\d+),\s*'([^']*)',\s*'([^']*)'\)/);
                            if (match && typeof window.openMessageContextMenu === 'function') {
                                const synthEvent = { clientX: touchStartX, clientY: touchStartY, preventDefault: () => {} };
                                const author = match[2].replace(/\\'/g, "'");
                                const text = match[3].replace(/\\'/g, "'").replace(/\\n/g, "\n");
                                window.openMessageContextMenu(synthEvent, parseInt(match[1], 10), author, text);
                            }
                        }
                    }, 500);
                }
            }, { passive: true });

            document.addEventListener('touchend', () => {
                if (touchTimer) { clearTimeout(touchTimer); touchTimer = null; }
            });
            document.addEventListener('touchmove', (e) => {
                if (touchTimer) {
                    const touch = e.touches[0];
                    if (Math.abs(touch.clientX - touchStartX) > 10 || Math.abs(touch.clientY - touchStartY) > 10) {
                        clearTimeout(touchTimer);
                        touchTimer = null;
                    }
                }
            });
        })();

        /* ---------------- reactions ---------------- */
        window.toggleMessageReaction = async function (messageId, reaction) {
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

        /* ---------------- attachments ---------------- */
        window.handleDashboardAttachment = function (event) {
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

        window.clearDashboardAttachment = function () {
            const fileInput = document.getElementById('dashboardGroupChatAttachment');
            const preview = document.getElementById('dashboardGroupChatAttachmentPreview');
            if (fileInput) fileInput.value = '';
            if (preview) preview.style.display = 'none';
        };

        /* ---------------- load / select / init ---------------- */
        async function loadMessages() {
            if (!selectedGroupId) return;
            try {
                const r = await fetch(`${messagesUrl}?group_id=${encodeURIComponent(selectedGroupId)}`, { credentials: 'same-origin' });
                const data = await r.json();
                if (!r.ok || !data.success) throw new Error(data.message || 'Unable to load messages');
                renderMessages(data.messages || []);
            } catch (e) { /* silent fail on poll */ }
        }

        async function selectGroup(id) {
            selectedGroupId = Number(id);
            window.selectedGroupId = selectedGroupId; // lets the dashboard open the pop-out on the same group
            if (remember) localStorage.setItem('2rich_chat_id', selectedGroupId);
            lastSeenId = 0;
            currentMessagesCache = '';
            const group = memberships.find(item => Number(item.id) === selectedGroupId);
            if (!group) return;
            state.innerHTML = `<select class="dashboard-group-chat-switcher" aria-label="Select joined group">${memberships.map(item => `<option value="${item.id}" ${Number(item.id) === selectedGroupId ? 'selected' : ''}>${escapeHtml(item.name)}</option>`).join('')}</select>`;
            state.hidden = false;
            state.querySelector('select').addEventListener('change', e => selectGroup(e.target.value));
            setComposerVisible(true);
            setCta('Visit Group', `/trading-floor#groups&group=${encodeURIComponent(String(group.id))}`, true);
            await loadMessages();

            try {
                const res = await fetch(`/api/signals/group-staff.php?group_id=${selectedGroupId}`);
                const d = await res.json();
                if (d.success) window.currentGroupMembers = d.staff || [];
            } catch (e) {}
        }

        async function initChat() {
            try {
                const r = await fetch(membershipsUrl, { credentials: 'same-origin' });
                const data = await r.json();
                if (!r.ok || !data.success) throw new Error(data.message || 'Unable to load memberships');
                memberships = data.memberships || [];
                if (!memberships.length) {
                    showState('<div class="widget-content-block"><p class="widget-content-text dashboard-group-chat-empty">You have not joined a trading group yet. Choose a group on the Trading Floor to start chatting.</p></div>');
                    messages.innerHTML = '';
                    setComposerVisible(false);
                    setCta('Choose a Group', '/trading-floor#groups', true);
                    return;
                }
                let targetId = memberships[0].id;
                if (preselectFromUrl) {
                    const preselectId = new URLSearchParams(window.location.search).get('group_id');
                    if (preselectId && memberships.some(m => String(m.id) === String(preselectId))) targetId = preselectId;
                } else if (remember) {
                    const savedChat = localStorage.getItem('2rich_chat_id');
                    if (savedChat && memberships.find(m => Number(m.id) === Number(savedChat))) targetId = savedChat;
                }
                await selectGroup(targetId);
            } catch (e) {
                if (footer) footer.hidden = true;
                showState(`<div class="widget-content-block"><p class="widget-content-text">${escapeHtml(e.message)}</p></div>`);
            }
        }

        /* ---------------- send ---------------- */
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
                    if (!upRes.ok || !upData.success) throw new Error(upData.message || 'Failed to upload media.');
                    uploadedUrl = upData.url;
                }

                let finalMessage = value;
                if (uploadedUrl) finalMessage = finalMessage ? (finalMessage + '\n\n' + uploadedUrl) : uploadedUrl;

                const r = await fetch(messagesUrl, {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ group_id: selectedGroupId, message: finalMessage, reply_to_id: currentReplyToId })
                });
                const data = await r.json();
                if (!r.ok || !data.success) throw new Error(data.message || 'Unable to send message');

                input.value = '';
                window.cancelReply();
                window.clearDashboardAttachment();
                await loadMessages();
            } catch (e) {
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

        /* ---------------- @mentions ---------------- */
        window.currentGroupMembers = [];
        window.mentionState = { active: false, query: '', members: [], selectedIndex: 0 };

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
        window.renderMentionDropdown = renderMentionDropdown;

        window.handleMentionAutocomplete = function (inputEl) {
            const val = inputEl.value;
            const cursor = inputEl.selectionStart;
            const textBeforeCursor = val.slice(0, cursor);
            const match = textBeforeCursor.match(/(?:^|\s)@([a-zA-Z0-9_]*)$/);
            const dropdown = document.getElementById('mentionAutocomplete');

            if (match) {
                const query = match[1].toLowerCase();
                window.mentionState.active = true;
                window.mentionState.query = query;

                let members = window.currentGroupMembers || [];
                if (query) {
                    members = members.filter(m => (m.display_name || m.user_login || '').toLowerCase().includes(query));
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

        window.selectMention = function (idx) {
            if (!window.mentionState.active) return;
            const member = window.mentionState.members[idx];
            if (!member) return;
            if (!input) return;

            const val = input.value;
            const cursor = input.selectionStart;
            const match = val.slice(0, cursor).match(/(?:^|\s)@([a-zA-Z0-9_]*)$/);

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

        window.handleMentionKeydown = function (e) {
            if (!window.mentionState || !window.mentionState.active) return;
            const dropdown = document.getElementById('mentionAutocomplete');
            const n = window.mentionState.members.length;
            if (e.key === 'ArrowDown' || e.key === 'ArrowUp') {
                e.preventDefault();
                const delta = e.key === 'ArrowDown' ? 1 : -1;
                window.mentionState.selectedIndex = (window.mentionState.selectedIndex + delta + n) % n;
                renderMentionDropdown();
                if (dropdown && dropdown.children[window.mentionState.selectedIndex]) {
                    dropdown.children[window.mentionState.selectedIndex].scrollIntoView({ block: 'nearest' });
                }
            } else if (e.key === 'Enter') {
                e.preventDefault();
                window.selectMention(window.mentionState.selectedIndex);
            } else if (e.key === 'Escape') {
                window.mentionState.active = false;
                if (dropdown) dropdown.style.display = 'none';
            }
        };

        /* ---------------- boot + polling ---------------- */
        initChat();

        // Web Worker for background-friendly polling (timers aren't throttled like setInterval in hidden tabs)
        try {
            const workerBlob = new Blob([`setInterval(() => postMessage('tick'), 4000);`], { type: 'application/javascript' });
            const pollWorker = new Worker(URL.createObjectURL(workerBlob));
            pollWorker.onmessage = () => loadMessages();
        } catch (e) {
            setInterval(loadMessages, 4000);
        }

        return { refresh: initChat, reload: loadMessages };
    }

    window.GroupChat = { init: init };
})();
