/**
 * messages.js — Handles real-time messaging: conversations list, chat view, sending, and polling.
 */

const MSG_API = 'api/messages.php';
const CSRF_TOKEN = document.querySelector('meta[name="csrf-token"]')?.content || '';

function appendCsrf(formData) {
    if (CSRF_TOKEN) formData.append('_token', CSRF_TOKEN);
}

let activeConversationUserId = null;
let pollInterval = null;

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

    // Check URL to highlight current chat if needed (e.g. ?user=123)
    const urlParams = new URLSearchParams(window.location.search);
    const initialUser = urlParams.get('user');

    loadConversations().then(() => {
        if (initialUser) {
            selectUser(initialUser);
        }
    });

    // Mobile: Move actions to navbar
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

        // Only update if message count changed or not silent
        if (silent && data.messages.length === previousCount) return;

        container.innerHTML = data.messages.map(m => `
            <div class="message-row ${m.is_mine ? 'mine' : 'theirs'}">
                ${!m.is_mine ? avatarHtml(m.profile_pic, m.name, 32) : ''}
                <div class="message-bubble ${m.is_mine ? 'mine' : 'theirs'}">
                    <p class="mb-0">${escapeHtml(m.content)}</p>
                    <small class="message-time">${formatTime(m.created_at)}</small>
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

    input.value = '';
    input.style.height = 'auto';

    try {
        const formData = new FormData();
        formData.append('action', 'send_message');
        formData.append('receiver_id', activeConversationUserId);
        formData.append('content', content);
        appendCsrf(formData);

        const res = await fetch(MSG_API, { method: 'POST', body: formData });
        const data = await res.json();

        if (data.success) {
            // Append message immediately
            const container = document.getElementById('chatMessages');
            const div = document.createElement('div');
            div.className = 'message-row mine';
            div.innerHTML = `
                <div class="message-bubble mine">
                    <p class="mb-0">${escapeHtml(content)}</p>
                    <small class="message-time">Just now</small>
                </div>
            `;
            container.appendChild(div);
            container.scrollTop = container.scrollHeight;

            // Refresh conversation list
            loadConversations(true);
        }
    } catch (err) {
        console.error('Send message error:', err);
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
