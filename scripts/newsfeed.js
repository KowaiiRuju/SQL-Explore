/**
 * newsfeed.js — Handles timeline AJAX interactions: likes, comments, image preview, and post deletion.
 */

const API_URL = 'api/posts.php';
const CSRF_TOKEN = document.querySelector('meta[name="csrf-token"]')?.content || document.querySelector('input[name="_token"]')?.value || '';

function appendCsrf(formData) {
    if (CSRF_TOKEN) formData.append('_token', CSRF_TOKEN);
}

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
        appendCsrf(formData);

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

    // Check if it's hidden (either via inline style OR d-none class)
    const isHidden = (section.style.display === 'none') || section.classList.contains('d-none');

    if (isHidden) {
        section.classList.remove('d-none');
        section.style.display = 'block'; // Ensure it's visible if inline style existed

        // Focus the comment input
        const input = section.querySelector('.comment-input');
        if (input) input.focus();
    } else {
        section.classList.add('d-none');
        section.style.display = 'none'; // Sync inline style for consistency
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
        appendCsrf(formData);

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
        appendCsrf(formData);

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
        } else {
            alert('Failed to delete post: ' + (data.error || 'Unknown error'));
        }
    } catch (err) {
        console.error('Delete error:', err);
        alert('Failed to delete post. Check console for details.');
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

/* ── Lightbox ──────────────────────────────────── */
let currentPostIdInLightbox = null;
const DEFAULT_PFP = "data:image/svg+xml;charset=UTF-8,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='%23cccccc'%3e%3cpath d='M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm0 3c1.66 0 3 1.34 3 3s-1.34 3-3 3-3-1.34-3-3 1.34-3 3-3zm0 14.2c-2.5 0-4.71-1.28-6-3.22.03-1.99 4-3.08 6-3.08 1.99 0 5.97 1.09 6 3.08-1.29 1.94-3.5 3.22-6 3.22z'/%3e%3c/svg%3e";

document.addEventListener('DOMContentLoaded', function () {
    // Lightbox triggers
    document.body.addEventListener('click', function (e) {
        if (e.target.classList.contains('clickable-image')) {
            openLightbox(e.target.src);
        }
    });

    // Lightbox enter key comment
    const lbInput = document.getElementById('lightboxCommentInput');
    if (lbInput) {
        lbInput.addEventListener('keydown', function (e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                submitCommentFromLightbox();
            }
        });
    }
});

function openLightbox(src) {
    // Find the post from the image source
    const allImages = document.querySelectorAll('.clickable-image');
    let postCard = null;

    for (let img of allImages) {
        if (img.src === src || img.src.includes(src)) {
            postCard = img.closest('.post-card');
            break;
        }
    }

    if (postCard) {
        const postIdMatch = postCard.id.match(/\d+/);
        const postId = postIdMatch ? parseInt(postIdMatch[0]) : null;

        if (postId) {
            currentPostIdInLightbox = postId;
            loadPostDetailsInLightbox(postId);
        }
    }

    document.getElementById('lightboxImage').src = src;
    new bootstrap.Modal(document.getElementById('imageLightboxModal')).show();
}

function loadPostDetailsInLightbox(postId) {
    fetch(API_URL + '?action=get_post_details&post_id=' + postId)
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                const post = data.post;

                // Update post content
                document.getElementById('lightboxPostContent').textContent = post.content;
                document.getElementById('lightboxPostTime').textContent = post.time_ago;
                document.getElementById('lightboxAuthorName').textContent = post.author_name;

                const authorImg = document.getElementById('lightboxAuthorPic');
                authorImg.src = post.author_pic && post.author_pic !== 'default.jpg'
                    ? '../uploads/' + post.author_pic
                    : DEFAULT_PFP;
                authorImg.onerror = function () { this.src = DEFAULT_PFP; };
                authorImg.style.display = '';

                // Update stats
                document.getElementById('lightboxLikeCount').textContent = post.like_count;
                document.getElementById('lightboxCommentCount').textContent = post.comment_count;

                // Update likes list
                // Update likes list
                const likesList = document.getElementById('lightboxLikesList');
                likesList.className = 'd-flex align-items-center'; // Ensure horizontal layout

                if (post.likes && post.likes.length > 0) {
                    const firstLike = post.likes[0];
                    const othersCount = post.like_count - 1;
                    const pic = (firstLike.profile_pic && firstLike.profile_pic !== 'default.jpg')
                        ? '../uploads/' + firstLike.profile_pic
                        : DEFAULT_PFP;

                    likesList.innerHTML = `
                        <img src="${pic}" class="rounded-circle me-2" style="width:24px; height:24px; object-fit:cover;" onerror="this.src='${DEFAULT_PFP}'">
                        <span class="small fw-bold">${firstLike.name}</span>
                        ${othersCount > 0 ? '<span class="text-secondary small ms-1">, others...</span>' : ''}
                    `;
                } else {
                    likesList.innerHTML = '<small class="text-secondary">No likes yet</small>';
                }

                // Update comments list
                const commentsList = document.getElementById('lightboxCommentsList');
                if (post.comments && post.comments.length > 0) {
                    commentsList.innerHTML = post.comments.map(comment => {
                        const cPic = (comment.profile_pic && comment.profile_pic !== 'default.jpg')
                            ? '../uploads/' + comment.profile_pic
                            : DEFAULT_PFP;

                        return `
                        <div class="mb-3 pb-3 border-bottom border-secondary">
                            <div class="d-flex gap-2 mb-2">
                                <img src="${cPic}" class="rounded-circle" style="width:28px; height:28px; object-fit:cover;" onerror="this.src='${DEFAULT_PFP}'">
                                <div class="flex-grow-1">
                                    <small class="d-block"><strong>${comment.name}</strong></small>
                                    <small class="text-secondary">${comment.content}</small>
                                </div>
                            </div>
                            <small class="text-secondary ms-5">${comment.time_ago}</small>
                        </div>
                    `}).join('');
                } else {
                    commentsList.innerHTML = '<small class="text-secondary">No comments yet</small>';
                }
            }
        })
        .catch(err => console.error('Error loading post details:', err));
}

function submitCommentFromLightbox() {
    const input = document.getElementById('lightboxCommentInput');
    const content = input.value.trim();

    if (!content || !currentPostIdInLightbox) return;

    const formData = new FormData();
    formData.append('action', 'add_comment');
    formData.append('post_id', currentPostIdInLightbox);
    formData.append('content', content);
    appendCsrf(formData);

    fetch(API_URL, {
        method: 'POST',
        body: formData
    })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                input.value = '';
                loadPostDetailsInLightbox(currentPostIdInLightbox);
            }
        })
        .catch(err => console.error('Error submitting comment:', err));
}
