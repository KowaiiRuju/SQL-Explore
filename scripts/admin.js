// Initialize modal references
let addUserModalBS;
let editUserModalBS;

document.addEventListener('DOMContentLoaded', function () {
    const addModalEl = document.getElementById('addUserModal');
    if (addModalEl) {
        addUserModalBS = new bootstrap.Modal(addModalEl);
    }

    const editModalEl = document.getElementById('editUserModal');
    if (editModalEl) {
        editUserModalBS = new bootstrap.Modal(editModalEl);
    }

    // Delete Confirmation
    document.addEventListener('submit', function (e) {
        if (e.target.classList.contains('delete-user-form')) {
            if (!confirm('Delete this user?')) {
                e.preventDefault();
            }
        }
    });
});

function openAddUserModal() {
    if (addUserModalBS) {
        addUserModalBS.show();
    }
}

function closeAddUserModal() {
    if (addUserModalBS) {
        addUserModalBS.hide();
    }
}

function openEditUserModal(userId) {
    // Find the user from the data passed by PHP
    const user = usersData.find(u => u.id === userId);
    if (!user || !editUserModalBS) return;

    // Populate the form fields
    document.getElementById('editUserId').value = user.id;
    document.getElementById('editUsername').value = user.username;
    document.getElementById('editPassword').value = '';
    document.getElementById('editFname').value = user.f_name;
    document.getElementById('editMname').value = user.m_name;
    document.getElementById('editLname').value = user.l_name;
    document.getElementById('editEmail').value = user.email;
    document.getElementById('editGender').value = user.gender;
    document.getElementById('editBirthdate').value = user.birthdate;
    document.getElementById('editIsAdmin').checked = user.is_admin === 1;
    document.getElementById('editTeamId').value = user.team_id || '';

    // Update modal title
    document.getElementById('editUserModalLabel').textContent = 'Edit User: ' + user.username;

    editUserModalBS.show();
}

// ── Search & Filter ──────────────────────────────────

function filterUsers() {
    const query = document.getElementById('searchInput').value.trim().toLowerCase();
    const matchMode = document.getElementById('matchMode').value;
    const roleFilter = document.getElementById('roleFilter').value;

    const tbody = document.querySelector('.table-responsive table tbody');
    if (!tbody) return;

    const rows = tbody.querySelectorAll('tr');
    let visible = 0;
    let total = 0;

    rows.forEach(row => {
        const cells = row.querySelectorAll('td');
        if (cells.length < 3) return; // skip the "No users found" row

        total++;
        const username = cells[1].textContent.trim().toLowerCase();
        const isAdmin = cells[2].textContent.trim().toLowerCase().includes('admin');

        // Search match
        let matchesSearch = true;
        if (query) {
            if (matchMode === 'exact') {
                matchesSearch = username === query;
            } else {
                matchesSearch = username.includes(query);
            }
        }

        // Role match
        let matchesRole = true;
        if (roleFilter === 'admin') {
            matchesRole = isAdmin;
        } else if (roleFilter === 'user') {
            matchesRole = !isAdmin;
        }

        const show = matchesSearch && matchesRole;
        row.style.display = show ? '' : 'none';
        if (show) visible++;
    });

    // Update count indicator
    const countEl = document.getElementById('searchResultCount');
    if (query || roleFilter !== 'all') {
        countEl.textContent = `Showing ${visible} of ${total} user${total !== 1 ? 's' : ''}`;
        countEl.classList.remove('d-none');
    } else {
        countEl.classList.add('d-none');
    }
}

function clearFilters() {
    document.getElementById('searchInput').value = '';
    document.getElementById('matchMode').value = 'general';
    document.getElementById('roleFilter').value = 'all';
    filterUsers();
}

function toggleFilterPanel() {
    const panel = document.getElementById('filterPanel');
    const btn = document.querySelector('.filter-toggle-btn');
    const isOpen = panel.classList.toggle('open');

    btn.classList.toggle('active', isOpen);
    btn.setAttribute('aria-expanded', isOpen);
}
