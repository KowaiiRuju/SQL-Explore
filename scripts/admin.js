// Initialize modal reference
let addUserModalBS;

document.addEventListener('DOMContentLoaded', function () {
    const modalEl = document.getElementById('addUserModal');
    if (modalEl) {
        addUserModalBS = new bootstrap.Modal(modalEl);
    }
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
