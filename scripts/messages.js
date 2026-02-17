const MSG_API = 'api/messages.php';
const CSRF_TOKEN = document.querySelector('meta[name="csrf-token"]')?.content || '';

function appendCsrf(formData) {
    if (CSRF_TOKEN) formData.append('_token', CSRF_TOKEN);
}

let activeConversationUserId = null;
let pollInterval = null;
let replyingToId = null;

/* ── Init ──────────────────────────────────────── */
document.addEventListener('DOMContentLoaded', () => {
    loadConversations();

    // Send message button
    document.getElementById('sendMessageBtn').addEventListener('click', sendMessage);

    // Enter to send (Shift+Enter for newline)
    document.getElementById('messageInput').addEventListener('keydown', (e) => {
        if (e.key === 'Enter' && !e.shiftKey) {
            e.preventDefault();
            sendMessage();
        }
    });

    // Cancel Reply
    document.getElementById('cancelReplyBtn')?.addEventListener('click', cancelReply);

    // Message List Delegation (Actions)
    document.getElementById('chatMessages').addEventListener('click', (e) => {
        const replyBtn = e.target.closest('.btn-reply');
        if (replyBtn) {
            const row = replyBtn.closest('.message-row');
            const id = row.dataset.id;
            const name = row.dataset.name;
            const content = row.querySelector('.message-body').textContent;
            setReply(id, name, content);
            return;
        }

        const deleteBtn = e.target.closest('.btn-delete-msg');
        if (deleteBtn) {
            const id = deleteBtn.closest('.message-row').dataset.id;
            const isMine = deleteBtn.closest('.message-row').classList.contains('mine');
            showDeleteOptions(id, isMine);
            return;
        }

        const reactBtn = e.target.closest('.btn-react');
        if (reactBtn) {
            const id = reactBtn.closest('.message-row').dataset.id;
            showEmojiPicker(id, reactBtn);
            return;
        }

        const reactionBadge = e.target.closest('.reaction-badge');
        if (reactionBadge) {
            const id = reactionBadge.closest('.message-row').dataset.id;
            const emoji = reactionBadge.dataset.emoji;
            toggleReaction(id, emoji);
            return;
        }
    });

    // Check URL to highlight current chat if needed (e.g. ?user=123)
    const urlParams = new URLSearchParams(window.location.search);
    const initialUser = urlParams.get('user');

    loadConversations().then(() => {
        if (initialUser) {
            selectUser(initialUser);
        }
    });

    // Mobile: Move actions to navbar
    const mobileActions = document.getElementById('mobileActionsTemplate');
    const navbarActions = document.getElementById('mobileNavbarActions');
    if (mobileActions && navbarActions) {
        navbarActions.innerHTML = mobileActions.innerHTML;

        // Attach listeners to the moved buttons
        const searchBtn = navbarActions.querySelector('.mobile-search-btn');
        if (searchBtn) {
            searchBtn.addEventListener('click', () => {
                const searchInput = document.querySelector('.conversations-search input');
                if (searchInput) {
                    searchInput.focus();
                    window.scrollTo(0, 0);
                }
            });
        }

        const newChatMobileBtn = navbarActions.querySelector('.mobile-new-chat-btn');
        if (newChatMobileBtn) {
            newChatMobileBtn.addEventListener('click', () => {
                const mainBtn = document.getElementById('newChatBtn');
                if (mainBtn) mainBtn.click();
            });
        }
    }

    // Auto-resize textarea
    document.getElementById('messageInput').addEventListener('input', function () {
        this.style.height = 'auto';
        this.style.height = Math.min(this.scrollHeight, 120) + 'px';
    });

    // New Chat Button
    const newChatBtn = document.getElementById('newChatBtn');
    if (newChatBtn) {
        newChatBtn.addEventListener('click', () => {
            const modal = new bootstrap.Modal(document.getElementById('newMessageModal'));
            modal.show();
            loadFriendsForModal();
        });
    }

    // Modal Search
    document.getElementById('friendSearchInputModal')?.addEventListener('input', (e) => {
        const term = e.target.value.toLowerCase();
        document.querySelectorAll('#friendListContainer .list-group-item').forEach(item => {
            const name = item.innerText.toLowerCase();
            item.style.display = name.includes(term) ? 'flex' : 'none';
        });
    });

    // User search
    let searchTimeout;
    document.getElementById('userSearchInput').addEventListener('input', function () {
        clearTimeout(searchTimeout);
        const q = this.value.trim();
        if (q.length < 1) {
            document.getElementById('searchResults').classList.add('d-none');
            return;
        }
        searchTimeout = setTimeout(() => searchUsers(q), 300);
    });

    // Conversation List Delegation
    document.getElementById('conversationsList').addEventListener('click', (e) => {
        const item = e.target.closest('.conversation-item');
        if (item) {
            const { userId, name, username, profilePic } = item.dataset;
            openConversation(parseInt(userId), name, username, profilePic);
        }
    });

    // Search Results Delegation
    document.getElementById('searchResults').addEventListener('click', (e) => {
        const item = e.target.closest('.search-result-item');
        if (item) {
            const { userId, name, username, profilePic } = item.dataset;
            startConversation(parseInt(userId), name, username, profilePic);
        }
    });

    // Back button (mobile)
    document.getElementById('backToConversations')?.addEventListener('click', () => {
        document.getElementById('conversationsPanel').classList.remove('d-none');
        document.getElementById('chatPanel').classList.remove('active-chat');
    });

    // Chat Options
    document.getElementById('viewProfileOption')?.addEventListener('click', (e) => {
        e.preventDefault();
        if (activeConversationUserId) {
            window.location.href = `profile_view.php?id=${activeConversationUserId}`;
        }
    });

    document.getElementById('deleteConvOption')?.addEventListener('click', (e) => {
        e.preventDefault();
        if (activeConversationUserId && confirm('Are you sure you want to delete this conversation? This cannot be undone.')) {
            deleteConversation(activeConversationUserId);
        }
    });

    // Poll for new messages every 5 seconds
    pollInterval = setInterval(() => {
        if (activeConversationUserId) {
            loadMessages(activeConversationUserId, true);
        }
        loadConversations(true);
    }, 5000);
});

/* ── Select User (for new message modal) ──────── */
async function selectUser(userId) {
    try {
        const res = await fetch(MSG_API + '?action=search_users&q=');
        const data = await res.json();
        // Try to find user in existing conversations first
        const convItem = document.querySelector(`.conversation-item[data-user-id="${userId}"]`);
        if (convItem) {
            convItem.click();
            return;
        }
        // Otherwise fetch user info and open conversation
        const res2 = await fetch(`api/friends.php?action=get_friends`);
        const data2 = await res2.json();
        if (data2.success) {
            const friend = data2.friends.find(f => f.id == userId);
            if (friend) {
                openConversation(friend.id, friend.name, friend.username, friend.profile_pic);
                return;
            }
        }
        // Fallback: just open with minimal info
        openConversation(userId, 'User', '', '');
    } catch (err) {
        console.error('selectUser error:', err);
    }
}

/* ── Load Friends for New Message Modal ───────── */
function loadFriendsForModal() {
    const container = document.getElementById('friendListContainer');
    container.innerHTML = '<div class="text-center py-3 text-muted small">Loading friends...</div>';

    fetch('api/friends.php?action=get_friends')
        .then(r => r.json())
        .then(data => {
            if (!data.success || data.friends.length === 0) {
                container.innerHTML = '<div class="text-center py-3 text-muted small">No friends found. Add some friends first!</div>';
                return;
            }

            container.innerHTML = '';
            data.friends.forEach(f => {
                const item = document.createElement('a');
                item.href = '#';
                item.className = 'list-group-item list-group-item-action d-flex align-items-center gap-3 py-2 px-3 border-0 rounded-3 mb-1';
                item.onclick = (e) => {
                    e.preventDefault();
                    const modalEl = document.getElementById('newMessageModal');
                    const modal = bootstrap.Modal.getInstance(modalEl);
                    modal.hide();
                    selectUser(f.id);
                };

                item.innerHTML = `
                    ${avatarHtml(f.profile_pic, f.name, 40)}
                    <div class="flex-grow-1 min-width-0">
                        <div class="fw-bold text-dark text-truncate">${escapeHtml(f.name)}</div>
                        <small class="text-muted text-truncate d-block">@${escapeHtml(f.username)}</small>
                    </div>
                `;
                container.appendChild(item);
            });
        })
        .catch(err => {
            console.error(err);
            container.innerHTML = '<div class="text-center py-3 text-danger small">Failed to load friends.</div>';
        });
}

/* ── Load Conversations ────────────────────────── */
async function loadConversations(silent = false) {
    try {
        const res = await fetch(MSG_API + '?action=get_conversations');
        const data = await res.json();

        if (!data.success) return;

        const list = document.getElementById('conversationsList');

        if (data.conversations.length === 0 && !silent) {
            list.innerHTML = `
                <div class="text-center py-5 text-muted">
                    <i class="bi bi-chat-dots" style="font-size:2rem;"></i>
                    <p class="small mt-2 mb-0">No conversations yet</p>
                    <p class="small text-muted">Search for a user to start chatting!</p>
                </div>`;
            return;
        }

        list.innerHTML = data.conversations.map(c => `
            <div class="conversation-item ${activeConversationUserId === c.user_id ? 'active' : ''} ${c.unread_count > 0 ? 'unread' : ''}" 
                 data-user-id="${c.user_id}"
                 data-name="${escapeHtml(c.name)}"
                 data-username="${escapeHtml(c.username)}"
                 data-profile-pic="${escapeHtml(c.profile_pic)}">
                ${avatarHtml(c.profile_pic, c.name, 44)}
                <div class="conversation-info">
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="conversation-name">${escapeHtml(c.name)}</span>
                        <small class="conversation-time">${c.last_time ? timeAgo(c.last_time) : ''}</small>
                    </div>
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="conversation-preview">${escapeHtml(truncate(c.last_message, 35))}</span>
                        ${c.unread_count > 0 ? `<span class="unread-badge">${c.unread_count}</span>` : ''}
                    </div>
                </div>
            </div>
        `).join('');
    } catch (err) {
        console.error('Load conversations error:', err);
    }
}

/* ── Open Conversation ─────────────────────────── */
function openConversation(userId, name, username, profilePic) {
    activeConversationUserId = userId;
    cancelReply();

    // Update header
    document.getElementById('chatHeader').classList.remove('d-none');
    document.getElementById('chatMessages').classList.remove('d-none');
    document.getElementById('chatInputArea').classList.remove('d-none');
    document.getElementById('chatEmpty').style.display = 'none';

    document.getElementById('chatUserName').textContent = name;
    document.getElementById('chatUserHandle').textContent = '@' + username;
    document.getElementById('chatAvatar').innerHTML = avatarHtml(profilePic, name, 40);

    // Mobile: show chat panel
    document.getElementById('conversationsPanel').classList.add('d-none');
    document.getElementById('chatPanel').classList.add('active-chat');

    // Mark active in list
    document.querySelectorAll('.conversation-item').forEach(el => {
        el.classList.toggle('active', parseInt(el.dataset.userId) === userId);
    });

    // Load messages
    loadMessages(userId);

    // Focus input
    document.getElementById('messageInput').focus();
}

/* ── Load Messages ─────────────────────────────── */
async function loadMessages(userId, silent = false) {
    try {
        const res = await fetch(MSG_API + '?action=get_messages&user_id=' + userId);
        const data = await res.json();

        if (!data.success) return;

        const container = document.getElementById('chatMessages');
        const wasAtBottom = container.scrollTop + container.clientHeight >= container.scrollHeight - 50;
        const previousCount = container.querySelectorAll('.message-bubble').length;

        // Simple check to see if we should re-render (count or content might change due to reactions/deletions)
        const currentDataHash = JSON.stringify(data.messages);
        if (silent && container.dataset.hash === currentDataHash) return;
        container.dataset.hash = currentDataHash;

        container.innerHTML = data.messages.map(m => `
            <div class="message-row ${m.is_mine ? 'mine' : 'theirs'}" 
                 data-id="${m.id}" 
                 data-name="${escapeHtml(m.name)}">
                
                ${!m.is_mine ? avatarHtml(m.profile_pic, m.name, 32) : ''}
                
                <div class="message-bubble ${m.is_mine ? 'mine' : 'theirs'}">
                    ${m.reply_to ? `
                        <div class="message-reply-bubble">
                            <small class="fw-bold d-block">@${escapeHtml(m.reply_to.username)}</small>
                            <div class="text-truncate">${escapeHtml(m.reply_to.content)}</div>
                        </div>
                    ` : ''}
                    
                    <p class="mb-0 message-body">${escapeHtml(m.content)}</p>
                    <small class="message-time">${formatTime(m.created_at)}</small>
                    
                    ${m.reactions && m.reactions.length > 0 ? `
                        <div class="message-reactions">
                            ${m.reactions.map(r => `
                                <span class="reaction-badge ${r.is_mine ? 'mine' : ''}" data-emoji="${escapeHtml(r.emoji)}">
                                    ${escapeHtml(r.emoji)}
                                </span>
                            `).join('')}
                        </div>
                    ` : ''}
                </div>

                <!-- Hover Actions -->
                <div class="message-actions">
                    <button class="btn-action btn-react" title="React"><i class="bi bi-emoji-smile"></i></button>
                    <button class="btn-action btn-reply" title="Reply"><i class="bi bi-reply"></i></button>
                    <button class="btn-action btn-delete-msg" title="Delete"><i class="bi bi-trash"></i></button>
                </div>
            </div>
        `).join('');

        // Scroll to bottom
        if (!silent || wasAtBottom) {
            container.scrollTop = container.scrollHeight;
        }
    } catch (err) {
        console.error('Load messages error:', err);
    }
}

/* ── Send Message ──────────────────────────────── */
async function sendMessage() {
    const input = document.getElementById('messageInput');
    const content = input.value.trim();
    if (!content || !activeConversationUserId) return;

    const currentReplyId = replyingToId;
    cancelReply(); // Reset UI immediately

    input.value = '';
    input.style.height = 'auto';

    try {
        const formData = new FormData();
        formData.append('action', 'send_message');
        formData.append('receiver_id', activeConversationUserId);
        formData.append('content', content);
        if (currentReplyId) formData.append('reply_to_id', currentReplyId);
        appendCsrf(formData);

        const res = await fetch(MSG_API, { method: 'POST', body: formData });
        const data = await res.json();

        if (data.success) {
            // Load all messages for freshness (reactions, replies, etc.)
            loadMessages(activeConversationUserId);
            loadConversations(true);
        }
    } catch (err) {
        console.error('Send message error:', err);
    }
}

/* ── Replies ───────────────────────────────────── */
function setReply(messageId, name, content) {
    replyingToId = messageId;
    document.getElementById('replyName').textContent = name;
    document.getElementById('replyContent').textContent = content;
    document.getElementById('replyPreview').classList.remove('d-none');
    document.getElementById('messageInput').focus();
}

function cancelReply() {
    replyingToId = null;
    document.getElementById('replyPreview').classList.add('d-none');
}

/* ── Reactions ─────────────────────────────────── */
async function toggleReaction(messageId, emoji) {
    try {
        const formData = new FormData();
        // Determine if adding or removing (optimistic check)
        const row = document.querySelector(`.message-row[data-id="${messageId}"]`);
        const existing = row?.querySelector(`.reaction-badge.mine[data-emoji="${emoji}"]`);

        formData.append('action', existing ? 'remove_reaction' : 'add_reaction');
        formData.append('message_id', messageId);
        formData.append('emoji', emoji);
        appendCsrf(formData);

        const res = await fetch(MSG_API, { method: 'POST', body: formData });
        const data = await res.json();

        if (data.success) {
            loadMessages(activeConversationUserId, true);
        }
    } catch (err) {
        console.error('Reaction error:', err);
    }
}

function showEmojiPicker(messageId, btn) {
    // Remove any existing pickers
    document.querySelectorAll('.emoji-picker-mini').forEach(p => p.remove());

    const picker = document.createElement('div');
    picker.className = 'emoji-picker-mini';
    const emojis = ['❤️', '😂', '😮', '😢', '🔥', '👍'];

    picker.innerHTML = emojis.map(e => `<span class="emoji-option" data-emoji="${e}">${e}</span>`).join('');

    document.body.appendChild(picker);

    // Position picker
    const rect = btn.getBoundingClientRect();
    picker.style.top = (rect.top + window.scrollY - picker.offsetHeight - 10) + 'px';
    picker.style.left = (rect.left + window.scrollX - (picker.offsetWidth / 2) + 14) + 'px';

    picker.onclick = (e) => {
        const option = e.target.closest('.emoji-option');
        if (option) {
            toggleReaction(messageId, option.dataset.emoji);
            picker.remove();
        }
    };

    // Close on click outside
    const closePicker = (e) => {
        if (!picker.contains(e.target) && e.target !== btn) {
            picker.remove();
            document.removeEventListener('click', closePicker);
        }
    };
    setTimeout(() => document.addEventListener('click', closePicker), 10);
}

/* ── Deletions ─────────────────────────────────── */
function showDeleteOptions(messageId, isMine) {
    if (!isMine) {
        if (confirm('Delete this message for you?')) {
            deleteMessage(messageId, 'me');
        }
        return;
    }

    // Custom prompt for mine
    const choice = prompt('Type "me" to delete for you, or "everyone" to delete for both:', 'me');
    if (choice === 'me' || choice === 'everyone') {
        deleteMessage(messageId, choice);
    }
}

async function deleteMessage(messageId, type) {
    try {
        const formData = new FormData();
        formData.append('action', 'delete_message');
        formData.append('message_id', messageId);
        formData.append('type', type);
        appendCsrf(formData);

        const res = await fetch(MSG_API, { method: 'POST', body: formData });
        const data = await res.json();

        if (data.success) {
            loadMessages(activeConversationUserId, true);
        } else {
            alert(data.error);
        }
    } catch (err) {
        console.error('Delete error:', err);
    }
}

/* ── Delete Conversation ────────────────────────── */
async function deleteConversation(userId) {
    try {
        const formData = new FormData();
        formData.append('action', 'delete_conversation');
        formData.append('user_id', userId);
        appendCsrf(formData);

        const res = await fetch(MSG_API, { method: 'POST', body: formData });
        const data = await res.json();

        if (data.success) {
            activeConversationUserId = null;

            // Reset UI
            document.getElementById('chatHeader').classList.add('d-none');
            document.getElementById('chatMessages').classList.add('d-none');
            document.getElementById('chatInputArea').classList.add('d-none');
            document.getElementById('chatEmpty').style.display = 'flex';

            // Mobile: show conversations list
            document.getElementById('conversationsPanel').classList.remove('d-none');
            document.getElementById('chatPanel').classList.remove('active-chat');

            loadConversations();
        } else {
            alert('Failed to delete conversation: ' + (data.error || 'Unknown error'));
        }
    } catch (err) {
        console.error('Delete conversation error:', err);
    }
}

/* ── Search Users ──────────────────────────────── */
async function searchUsers(query) {
    try {
        const res = await fetch(MSG_API + '?action=search_users&q=' + encodeURIComponent(query));
        const data = await res.json();

        const container = document.getElementById('searchResults');

        if (!data.success || data.users.length === 0) {
            container.innerHTML = '<div class="p-3 text-muted small text-center">No users found</div>';
            container.classList.remove('d-none');
            return;
        }

        container.innerHTML = data.users.map(u => `
            <div class="search-result-item" 
                 data-user-id="${u.id}"
                 data-name="${escapeHtml(u.name)}"
                 data-username="${escapeHtml(u.username)}"
                 data-profile-pic="${escapeHtml(u.profile_pic)}">
                ${avatarHtml(u.profile_pic, u.name, 36)}
                <div>
                    <div class="fw-medium small">${escapeHtml(u.name)}</div>
                    <small class="text-muted">@${escapeHtml(u.username)}</small>
                </div>
            </div>
        `).join('');
        container.classList.remove('d-none');
    } catch (err) {
        console.error('Search error:', err);
    }
}

function startConversation(userId, name, username, profilePic) {
    document.getElementById('searchResults').classList.add('d-none');
    document.getElementById('userSearchInput').value = '';
    openConversation(userId, name, username, profilePic);
}

/* ── Helpers ───────────────────────────────────── */
function escapeHtml(str) {
    if (!str) return '';
    const div = document.createElement('div');
    div.textContent = str;
    return div.innerHTML;
}

function avatarHtml(pic, name, size) {
    if (pic) {
        return `<img src="../uploads/${escapeHtml(pic)}" class="rounded-circle" width="${size}" height="${size}" style="object-fit:cover; flex-shrink:0;" alt="${escapeHtml(name)}">`;
    }
    return `<div class="rounded-circle bg-primary d-flex align-items-center justify-content-center text-white" style="width:${size}px; height:${size}px; font-size:${size * 0.4}px; flex-shrink:0;"><i class="bi bi-person-fill"></i></div>`;
}

function truncate(str, len) {
    if (!str) return '';
    return str.length > len ? str.substring(0, len) + '...' : str;
}

function timeAgo(dateStr) {
    if (!dateStr) return '';
    const now = new Date();
    const then = new Date(dateStr);
    const diff = Math.floor((now - then) / 1000);
    if (diff < 60) return 'now';
    if (diff < 3600) return Math.floor(diff / 60) + 'm';
    if (diff < 86400) return Math.floor(diff / 3600) + 'h';
    if (diff < 604800) return Math.floor(diff / 86400) + 'd';
    return then.toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
}

function formatTime(dateStr) {
    const d = new Date(dateStr);
    const now = new Date();
    const isToday = d.toDateString() === now.toDateString();
    if (isToday) {
        return d.toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit', hour12: true });
    }
    return d.toLocaleDateString('en-US', { month: 'short', day: 'numeric' }) + ' ' +
        d.toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit', hour12: true });
}

// Close search results when clicking outside
document.addEventListener('click', (e) => {
    const search = document.getElementById('userSearchInput');
    const results = document.getElementById('searchResults');
    if (search && results && !search.contains(e.target) && !results.contains(e.target)) {
        results.classList.add('d-none');
    }
});
