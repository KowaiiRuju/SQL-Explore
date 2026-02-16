/**
 * newsfeed.js — Handles timeline AJAX interactions: likes, comments, image preview, and post deletion.
 */

const API_URL = 'api/posts.php';

/* ── Image Preview ─────────────────────────────── */
function previewPostImage(input) {
    const container = document.getElementById('imagePreviewContainer');
    const preview = document.getElementById('imagePreview');
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = function (e) {
            preview.src = e.target.result;
            container.classList.remove('d-none');
        };
        reader.readAsDataURL(input.files[0]);
    }
}

function clearImagePreview() {
    const container = document.getElementById('imagePreviewContainer');
    const preview = document.getElementById('imagePreview');
    const input = document.getElementById('postImageInput');
    if (container) container.classList.add('d-none');
    if (preview) preview.src = '';
    if (input) input.value = '';
}

/* ── Like Toggle ───────────────────────────────── */
async function toggleLike(postId, btn) {
    btn.disabled = true;
    try {
        const formData = new FormData();
        formData.append('action', 'toggle_like');
        formData.append('post_id', postId);

        const res = await fetch(API_URL, { method: 'POST', body: formData });
        const data = await res.json();

        if (data.success) {
            const icon = btn.querySelector('i');
            const countEl = btn.querySelector('.like-count');

            if (data.liked) {
                btn.classList.add('liked');
                icon.classList.remove('bi-hand-thumbs-up');
                icon.classList.add('bi-hand-thumbs-up-fill');
            } else {
                btn.classList.remove('liked');
                icon.classList.remove('bi-hand-thumbs-up-fill');
                icon.classList.add('bi-hand-thumbs-up');
            }
            countEl.textContent = data.count;
        }
    } catch (err) {
        console.error('Like error:', err);
    }
    btn.disabled = false;
}

/* ── Comments Toggle ───────────────────────────── */
function toggleComments(postId) {
    const section = document.getElementById('comments-' + postId);
    if (!section) return;
    if (section.style.display === 'none') {
        section.style.display = 'block';
        // Focus the comment input
        const input = section.querySelector('.comment-input');
        if (input) input.focus();
    } else {
        section.style.display = 'none';
    }
}

/* ── Submit Comment ────────────────────────────── */
async function submitComment(postId, inputEl) {
    const content = inputEl.value.trim();
    if (!content) return;

    inputEl.disabled = true;
    try {
        const formData = new FormData();
        formData.append('action', 'add_comment');
        formData.append('post_id', postId);
        formData.append('content', content);

        const res = await fetch(API_URL, { method: 'POST', body: formData });
        const data = await res.json();

        if (data.success) {
            // Add comment to the list
            const list = document.getElementById('comments-list-' + postId);
            const comment = data.comment;

            const div = document.createElement('div');
            div.className = 'comment-item';

            const avatarHtml = comment.profile_pic
                ? `<img src="../uploads/${escapeHtml(comment.profile_pic)}" class="comment-avatar" alt="">`
                : `<div class="comment-avatar bg-primary d-flex align-items-center justify-content-center text-white"><i class="bi bi-person-fill" style="font-size:0.7rem;"></i></div>`;

            div.innerHTML = `
                ${avatarHtml}
                <div class="comment-body">
                    <div class="comment-bubble">
                        <strong class="small">${escapeHtml(comment.name)}</strong>
                        <p class="mb-0 small">${escapeHtml(comment.content)}</p>
                    </div>
                    <small class="text-muted ms-2">Just now</small>
                </div>
            `;

            // Insert before the comment input area
            const inputArea = document.querySelector('#comments-' + postId + ' .comment-input-area');
            list.insertBefore(div, null);

            // Update comment count
            const countEls = document.querySelectorAll('.comment-count-' + postId);
            countEls.forEach(el => {
                el.textContent = data.count;
            });

            inputEl.value = '';
        }
    } catch (err) {
        console.error('Comment error:', err);
    }
    inputEl.disabled = false;
    inputEl.focus();
}

/* ── Load All Comments ─────────────────────────── */
async function loadAllComments(postId) {
    try {
        const res = await fetch(API_URL + '?action=get_comments&post_id=' + postId);
        const data = await res.json();

        if (data.success) {
            const list = document.getElementById('comments-list-' + postId);
            list.innerHTML = '';

            data.comments.forEach(comment => {
                const div = document.createElement('div');
                div.className = 'comment-item';

                const avatarHtml = comment.profile_pic
                    ? `<img src="../uploads/${escapeHtml(comment.profile_pic)}" class="comment-avatar" alt="">`
                    : `<div class="comment-avatar bg-light d-flex align-items-center justify-content-center text-muted"><i class="bi bi-person-fill" style="font-size:0.7rem;"></i></div>`;

                div.innerHTML = `
                    ${avatarHtml}
                    <div class="comment-body">
                        <div class="comment-bubble">
                            <strong class="small">${escapeHtml(comment.name)}</strong>
                            <p class="mb-0 small">${escapeHtml(comment.content)}</p>
                        </div>
                        <small class="text-muted ms-2">${timeAgo(comment.created_at)}</small>
                    </div>
                `;
                list.appendChild(div);
            });
        }
    } catch (err) {
        console.error('Load comments error:', err);
    }
}

/* ── Delete Post ───────────────────────────────── */
async function deletePost(postId) {
    if (!confirm('Delete this post? This cannot be undone.')) return;

    try {
        const formData = new FormData();
        formData.append('action', 'delete_post');
        formData.append('post_id', postId);

        const res = await fetch(API_URL, { method: 'POST', body: formData });
        const data = await res.json();

        if (data.success) {
            const card = document.getElementById('post-' + postId);
            if (card) {
                card.style.transition = 'opacity 0.3s ease, transform 0.3s ease';
                card.style.opacity = '0';
                card.style.transform = 'scale(0.95)';
                setTimeout(() => card.remove(), 300);
            }
        }
    } catch (err) {
        console.error('Delete error:', err);
    }
}

/* ── Helpers ───────────────────────────────────── */
function escapeHtml(str) {
    const div = document.createElement('div');
    div.textContent = str;
    return div.innerHTML;
}

function timeAgo(dateStr) {
    const now = new Date();
    const then = new Date(dateStr);
    const diff = Math.floor((now - then) / 1000);

    if (diff < 60) return 'Just now';
    if (diff < 3600) return Math.floor(diff / 60) + 'm ago';
    if (diff < 86400) return Math.floor(diff / 3600) + 'h ago';
    if (diff < 2592000) return Math.floor(diff / 86400) + 'd ago';

    return then.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
}
