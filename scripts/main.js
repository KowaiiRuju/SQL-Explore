/**
 * main.js — Global scripts shared across pages.
 */

document.addEventListener('DOMContentLoaded', () => {
    // Global Password Toggle
    document.addEventListener('click', function (e) {
        const toggle = e.target.closest('.toggle-password');
        if (toggle) {
            const icon = toggle.querySelector('i');
            const inputGroup = toggle.closest('.input-group');
            if (inputGroup) {
                const input = inputGroup.querySelector('input');
                if (input) {
                    if (input.type === 'password') {
                        input.type = 'text';
                        icon.classList.remove('bi-eye-slash');
                        icon.classList.add('bi-eye');
                    } else {
                        input.type = 'password';
                        icon.classList.remove('bi-eye');
                        icon.classList.add('bi-eye-slash');
                    }
                }
            }
        }
    });

    // Initialize Bootstrap tooltips globally if needed
    // const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    // tooltipTriggerList.map(function (tooltipTriggerEl) {
    //     return new bootstrap.Tooltip(tooltipTriggerEl);
    // });
});
