/**
 * teams.js - Logic for Team Management
 * Expects 'teamsData' to be defined globally by the PHP page.
 */

document.addEventListener('DOMContentLoaded', function () {
    // Color preview listeners
    const newColorInput = document.getElementById('newTeamColor');
    if (newColorInput) {
        newColorInput.addEventListener('input', function () {
            const preview = document.getElementById('colorPreview');
            if (preview) preview.textContent = this.value;
        });
    }

    const editColorInput = document.getElementById('editTeamColor');
    if (editColorInput) {
        editColorInput.addEventListener('input', function () {
            const preview = document.getElementById('editColorPreview');
            if (preview) preview.textContent = this.value;
        });
    }

    // Logo Previews
    setupLogoPreview('editTeamLogo', 'editLogoPreview', 'editLogoPlaceholder');
    // For new team logo, we might want a preview if there was an img element, but the UI might not have one.
    // The original code had onchange="previewImage" which implies it might have been intended.
    // We'll stick to what the inline script was doing: basic setup.

    // Delete/Reset Confirmations
    document.addEventListener('submit', function (e) {
        if (e.target.classList.contains('delete-team-form')) {
            if (!confirm('Delete this team? Members will be unassigned.')) {
                e.preventDefault();
            }
        }
        if (e.target.classList.contains('auto-assign-form')) {
            if (!confirm('Are you sure you want to randomly assign ALL users to teams? Existing assignments will be overwritten.')) {
                e.preventDefault();
            }
        }
        if (e.target.classList.contains('reset-teams-form')) {
            if (!confirm('Are you sure you want to RESET all team assignments? This cannot be undone.')) {
                e.preventDefault();
            }
        }
    });

    // Member Search
    const memberSearch = document.getElementById('memberSearch');
    if (memberSearch) {
        memberSearch.addEventListener('input', filterMembersList);
    }
});

function setupLogoPreview(inputId, imgId, placeholderId) {
    const input = document.getElementById(inputId);
    if (!input) return;

    input.addEventListener('change', function () {
        if (this.files && this.files[0]) {
            const reader = new FileReader();
            reader.onload = function (e) {
                if (imgId) {
                    const img = document.getElementById(imgId);
                    if (img) {
                        img.src = e.target.result;
                        img.classList.remove('d-none');
                    }
                }
                if (placeholderId) {
                    const ph = document.getElementById(placeholderId);
                    if (ph) ph.classList.add('d-none');
                }
            }
            reader.readAsDataURL(this.files[0]);
        }
    });
}

function openEditTeamModal(teamId) {
    if (typeof teamsData === 'undefined') return;
    const team = teamsData.find(t => t.id === teamId);
    if (!team) return;

    document.getElementById('editTeamId').value = team.id;
    document.getElementById('editTeamName').value = team.name;
    document.getElementById('editTeamColor').value = team.color;

    const colorPrev = document.getElementById('editColorPreview');
    if (colorPrev) colorPrev.textContent = team.color;

    document.getElementById('editTeamScore').value = team.score;
    document.getElementById('editTeamModalLabel').textContent = 'Edit: ' + team.name;

    // Handle logo visual
    const prevImg = document.getElementById('editLogoPreview');
    const prevIcon = document.getElementById('editLogoPlaceholder');
    const fileInput = document.getElementById('editTeamLogo');
    if (fileInput) fileInput.value = ''; // Reset input selection

    if (team.logo) {
        if (prevImg) {
            prevImg.src = '../uploads/' + team.logo;
            prevImg.classList.remove('d-none');
        }
        if (prevIcon) prevIcon.classList.add('d-none');
    } else {
        if (prevImg) prevImg.classList.add('d-none');
        if (prevIcon) prevIcon.classList.remove('d-none');
    }

    const modal = document.getElementById('editTeamModal');
    if (modal) new bootstrap.Modal(modal).show();
}

function openMembersModal(teamId) {
    if (typeof teamsData === 'undefined') return;
    const team = teamsData.find(t => t.id === teamId);
    if (!team) return;

    document.getElementById('membersTeamId').value = team.id;
    document.getElementById('membersModalLabel').textContent = 'Manage Members: ' + team.name;

    // Check appropriate users
    document.querySelectorAll('.member-check').forEach(cb => {
        cb.checked = parseInt(cb.dataset.team) === teamId;
    });

    const searchInput = document.getElementById('memberSearch');
    if (searchInput) {
        searchInput.value = '';
    }
    filterMembersList();

    const modal = document.getElementById('membersModal');
    if (modal) new bootstrap.Modal(modal).show();
}

function filterMembersList() {
    const searchInput = document.getElementById('memberSearch');
    if (!searchInput) return;

    const q = searchInput.value.trim().toLowerCase();
    document.querySelectorAll('.member-row').forEach(row => {
        row.style.display = !q || row.dataset.username.includes(q) ? '' : 'none';
    });
}
