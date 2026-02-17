/**
 * friends.js — Handles friend search, list loading, and action buttons.
 */

const FRIENDS_API = 'api/friends.php';
const CSRF_TOKEN = document.querySelector('meta[name="csrf-token"]')?.content || '';

function appendCsrf(formData) {
    if (CSRF_TOKEN) formData.append('_token', CSRF_TOKEN);
}

document.addEventListener('DOMContentLoaded', () => {
    // Check if we are on friends.php
    if (document.getElementById('friendsMainContent')) {
        loadFriends();
        loadRequests();
        setupSearch();

        // Mobile: Move actions to navbar
        const mobileActions = document.getElementById('mobileActionsTemplate');
        const navbarActions = document.getElementById('mobileNavbarActions');
        if (mobileActions && navbarActions) {
            navbarActions.innerHTML = mobileActions.innerHTML;

            const searchBtn = navbarActions.querySelector('.mobile-search-btn');
            if (searchBtn) {
                searchBtn.addEventListener('click', () => {
                    const input = document.getElementById('friendSearchInput');
                    if (input) {
                        input.focus();
                        window.scrollTo(0, 0);
                    }
                });
            }
        }

        // Close search button
        document.querySelector('.close-search-btn')?.addEventListener('click', clearSearch);
    }

    // Global action button handler (works on profile_view.php too)
    document.body.addEventListener('click', async (e) => {
        const btn = e.target.closest('.action-btn');
        if (!btn) return;

        const action = btn.dataset.action;
        const userId = btn.dataset.id;

        if (!action || !userId) return;

        btn.disabled = true;
        const originalHtml = btn.innerHTML;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';

        try {
            const formData = new FormData();
            formData.append('action', action);
            formData.append('user_id', userId);
            appendCsrf(formData);

            const res = await fetch(FRIENDS_API, { method: 'POST', body: formData });
            const data = await res.json();

            if (data.success) {
                // If on friends.php, reload lists
                if (document.getElementById('friendsMainContent')) {
                    loadFriends();
                    loadRequests();
                    // If in search results, update that card's button
                    if (btn.closest('#searchResultsGrid')) {
                        // Re-run search to update status? Or just hide?
                        // Simple: reload search if active
                        const q = document.getElementById('friendSearchInput').value.trim();
                        if (q) performSearch(q);
                    }
                } else {
                    // Start refresh to show new state
                    window.location.reload();
                }
            } else {
                alert(data.error || 'Action failed');
                btn.disabled = false;
                btn.innerHTML = originalHtml;
            }
        } catch (err) {
            console.error('Action error:', err);
            btn.disabled = false;
            btn.innerHTML = originalHtml;
        }
    });
});

/* ── Search ────────────────────────────────────── */
function setupSearch() {
    const input = document.getElementById('friendSearchInput');
    const clearBtn = document.getElementById('clearSearchBtn');
    let timeout;

    input.addEventListener('input', () => {
        clearTimeout(timeout);
        const q = input.value.trim();

        if (q.length > 0) {
            clearBtn.classList.remove('d-none');
            timeout = setTimeout(() => performSearch(q), 400);
        } else {
            clearSearch();
        }
    });

    // Clear button functionality needs to be attached or called
    clearBtn.addEventListener('click', clearSearch);
}

function clearSearch() {
    const input = document.getElementById('friendSearchInput');
    const clearBtn = document.getElementById('clearSearchBtn');
    input.value = '';
    clearBtn.classList.add('d-none');

    document.getElementById('searchResultsGrid').innerHTML = '';
    document.getElementById('searchResultsSection').classList.add('d-none');
    document.getElementById('friendsMainContent').classList.remove('d-none');
}

async function performSearch(q) {
    try {
        const res = await fetch(FRIENDS_API + '?action=search_users&q=' + encodeURIComponent(q));
        const data = await res.json();

        if (data.success) {
            const grid = document.getElementById('searchResultsGrid');
            document.getElementById('searchResultsSection').classList.remove('d-none');
            document.getElementById('friendsMainContent').classList.add('d-none');

            if (data.users.length === 0) {
                grid.innerHTML = '<div class="col-12 text-center text-muted py-4">No users found.</div>';
                return;
            }

            grid.innerHTML = data.users.map(u => createUserCard(u)).join('');
        }
    } catch (err) {
        console.error('Search error:', err);
    }
}

/* ── Load Lists ────────────────────────────────── */
async function loadFriends() {
    try {
        const res = await fetch(FRIENDS_API + '?action=get_friends');
        const data = await res.json();

        if (data.success) {
            const grid = document.getElementById('friendsGrid');
            document.getElementById('friendsCountBadge').textContent = data.friends.length;

            if (data.friends.length === 0) {
                grid.innerHTML = `
                    <div class="col-12 text-center py-5 text-muted">
                        <i class="bi bi-people" style="font-size:2rem;"></i>
                        <p class="mt-2">No friends yet. Search to add some!</p>
                    </div>`;
            } else {
                grid.innerHTML = data.friends.map(f => createFriendCard(f)).join('');
            }
        }
    } catch (err) { }
}

async function loadRequests() {
    try {
        const res = await fetch(FRIENDS_API + '?action=get_requests');
        const data = await res.json();

        if (data.success) {
            const grid = document.getElementById('requestsGrid');
            const badge = document.getElementById('requestsCountBadge');

            if (data.requests.length > 0) {
                badge.textContent = data.requests.length;
                badge.classList.remove('d-none');
                grid.innerHTML = data.requests.map(r => createRequestCard(r)).join('');
            } else {
                badge.classList.add('d-none');
                grid.innerHTML = `
                    <div class="col-12 text-center py-5 text-muted">
                        <i class="bi bi-inbox" style="font-size:2rem;"></i>
                        <p class="mt-2">No pending requests</p>
                    </div>`;
            }
        }
    } catch (err) { }
}

/* ── HTML Generators ───────────────────────────── */
function createUserCard(u) {
    // For search results
    let actionBtn = '';

    if (u.status === 'accepted') {
        actionBtn = `<button class="btn btn-sm btn-outline-success rounded-pill" disabled><i class="bi bi-check-lg"></i> Friends</button>`;
    } else if (u.status === 'pending_sent') {
        actionBtn = `<button class="btn btn-sm btn-secondary rounded-pill action-btn" data-action="remove_friend" data-id="${u.id}">Current Request</button>`;
    } else if (u.status === 'pending_received') {
        actionBtn = `
            <button class="btn btn-sm btn-primary rounded-pill action-btn me-1" data-action="accept_request" data-id="${u.id}">Accept</button>
            <button class="btn btn-sm btn-outline-danger rounded-pill action-btn" data-action="remove_friend" data-id="${u.id}"><i class="bi bi-x"></i></button>
        `;
    } else {
        actionBtn = `<button class="btn btn-sm btn-primary rounded-pill action-btn" data-action="send_request" data-id="${u.id}"><i class="bi bi-person-plus"></i> Add</button>`;
    }

    return `
        <div class="col-12">
            <div class="card border-0 shadow-sm">
                <div class="card-body d-flex align-items-center py-2">
                    <a href="profile_view.php?id=${u.id}">
                        ${avatarHtml(u.profile_pic, u.name, 50)}
                    </a>
                    <div class="ms-3 flex-grow-1 min-width-0">
                        <h6 class="mb-0 fw-bold text-truncate">
                            <a href="profile_view.php?id=${u.id}" class="text-dark text-decoration-none">${escapeHtml(u.name)}</a>
                        </h6>
                        <small class="text-muted text-truncate d-block">@${escapeHtml(u.username)}</small>
                    </div>
                    <div class="ms-2">
                        ${actionBtn}
                    </div>
                </div>
            </div>
        </div>
    `;
}

function createFriendCard(u) {
    return `
        <div class="col-12 mb-2">
            <div class="card border-0 shadow-sm">
                <div class="card-body d-flex align-items-center py-2">
                    <a href="profile_view.php?id=${u.id}">
                        ${avatarHtml(u.profile_pic, u.name, 50)}
                    </a>
                    <div class="ms-3 flex-grow-1 min-width-0">
                        <h6 class="mb-0 fw-bold text-truncate">
                            <a href="profile_view.php?id=${u.id}" class="text-dark text-decoration-none">${escapeHtml(u.name)}</a>
                        </h6>
                        <small class="text-muted text-truncate d-block">@${escapeHtml(u.username)}</small>
                    </div>
                    
                    <div class="ms-2 d-flex gap-1">
                        <a href="messages.php?user=${u.id}" class="btn btn-sm btn-primary rounded-pill px-3 py-1" style="font-size: 0.75rem;">
                            <i class="bi bi-chat-dots"></i>
                        </a>
                        <button class="btn btn-sm btn-outline-danger rounded-pill action-btn py-1" data-action="remove_friend" data-id="${u.id}" title="Unfriend">
                            <i class="bi bi-person-x"></i>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    `;
}

function createRequestCard(u) {
    return `
        <div class="col-12 mb-2">
            <div class="card border-0 shadow-sm">
                <div class="card-body d-flex align-items-center py-2">
                    <a href="profile_view.php?id=${u.id}">
                        ${avatarHtml(u.profile_pic, u.name, 50)}
                    </a>
                    <div class="ms-3 flex-grow-1 min-width-0">
                        <h6 class="mb-0 fw-bold text-truncate">
                            <a href="profile_view.php?id=${u.id}" class="text-dark text-decoration-none">${escapeHtml(u.name)}</a>
                        </h6>
                        <small class="text-muted text-truncate d-block">Sent you a request</small>
                    </div>
                    <div class="ms-2 d-flex gap-1">
                        <button class="btn btn-sm btn-primary rounded-circle action-btn" data-action="accept_request" data-id="${u.id}" title="Accept">
                            <i class="bi bi-check-lg"></i>
                        </button>
                        <button class="btn btn-sm btn-outline-danger rounded-circle action-btn" data-action="remove_friend" data-id="${u.id}" title="Reject">
                            <i class="bi bi-x-lg"></i>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    `;
}

/* ── Helpers ───────────────────────────────────── */
function escapeHtml(str) {
    if (!str) return '';
    const div = document.createElement('div');
    div.textContent = str;
    return div.innerHTML;
}

function avatarHtml(pic, name, size, classes = '') {
    if (pic) {
        return `<img src="../uploads/${escapeHtml(pic)}" class="rounded-circle ${classes}" width="${size}" height="${size}" style="object-fit:cover;" alt="${escapeHtml(name)}">`;
    }
    return `<div class="rounded-circle bg-light d-flex align-items-center justify-content-center text-primary ${classes}" style="width:${size}px; height:${size}px; font-size:${size * 0.4}px;"><i class="bi bi-person-fill"></i></div>`;
}
