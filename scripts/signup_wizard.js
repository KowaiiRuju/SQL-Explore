let currentStep = 1;
const totalSteps = 4;

document.addEventListener('DOMContentLoaded', () => {
    // Prevent Enter key from submitting form (unless on last step, but even then we want validation)
    document.getElementById('signupForm').addEventListener('keypress', function (e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            if (currentStep < totalSteps) {
                nextStep(currentStep);
            } else {
                // On last step, maybe submit?
                // But better to let user click the button to be explicit
            }
        }
    });

    // Handle Form Submit
    document.getElementById('signupForm').addEventListener('submit', function (e) {
        const stepEl = document.getElementById(`step${currentStep}`);
        const inputs = stepEl.querySelectorAll('input, select');
        let valid = true;

        inputs.forEach(input => {
            if (!input.checkValidity()) {
                input.reportValidity();
                valid = false;
            }
        });

        if (!valid) {
            e.preventDefault();
            e.stopPropagation();
        }
    });

    // Password Real-time Validation
    const passwordInput = document.getElementById('passwordInput');
    if (passwordInput) {
        passwordInput.addEventListener('input', validatePassword);
    }

    // Username realtime check (optional enhancement: denounce)
    const usernameInput = document.getElementById('usernameInput');
    let timeout = null;
    usernameInput.addEventListener('input', () => {
        clearTimeout(timeout);
        const feedback = document.getElementById('usernameFeedback');
        feedback.textContent = '';
        usernameInput.classList.remove('is-valid', 'is-invalid');

        timeout = setTimeout(() => {
            if (usernameInput.value.length >= 3) {
                checkUsername(usernameInput.value, true); // true = silent/passive check
            }
        }, 500);
    });
});

function validatePassword() {
    const pw = document.getElementById('passwordInput').value;
    const reqs = {
        length: pw.length >= 8,
        upper: /[A-Z]/.test(pw),
        number: /[0-9]/.test(pw),
        special: /[!@#$%^&*\-_]/.test(pw)
    };

    updateRequirement('req-length', reqs.length);
    updateRequirement('req-upper', reqs.upper);
    updateRequirement('req-number', reqs.number);
    updateRequirement('req-special', reqs.special);

    return Object.values(reqs).every(Boolean);
}

function updateRequirement(id, isValid) {
    const el = document.getElementById(id);
    const icon = el.querySelector('i');

    if (isValid) {
        el.classList.remove('text-danger', 'text-muted');
        el.classList.add('text-success');
        icon.classList.remove('bi-circle', 'bi-x-circle');
        icon.classList.add('bi-check-circle-fill');
    } else {
        el.classList.remove('text-success', 'text-muted');
        // Only show red if user has typed something, otherwise keep muted (neutral)
        if (document.getElementById('passwordInput').value.length > 0) {
            el.classList.add('text-danger');
            icon.classList.remove('bi-circle', 'bi-check-circle-fill');
            icon.classList.add('bi-x-circle');
        } else {
            el.classList.add('text-muted');
            el.classList.remove('text-danger');
            icon.classList.remove('bi-check-circle-fill', 'bi-x-circle');
            icon.classList.add('bi-circle');
        }
    }
}

function showStep(step) {
    // Hide all steps
    document.querySelectorAll('.step').forEach(el => el.classList.add('d-none'));

    // Show target step
    const target = document.getElementById(`step${step}`);
    if (target) {
        target.classList.remove('d-none');
        // Re-trigger animation
        target.style.animation = 'none';
        target.offsetHeight; /* trigger reflow */
        target.style.animation = null;
    }

    currentStep = step;
    updateProgressBar(step);
}

function updateProgressBar(step) {
    const percent = (step / totalSteps) * 100;
    const bar = document.getElementById('progressBar');
    if (bar) bar.style.width = percent + '%';
}

async function nextStep(step) {
    const stepEl = document.getElementById(`step${step}`);
    const inputs = stepEl.querySelectorAll('input, select');
    let valid = true;

    // 1. Basic HTML5 Validation
    inputs.forEach(input => {
        if (!input.checkValidity()) {
            input.reportValidity();
            valid = false;
        }
    });

    if (!valid) return; // Stop if basic validation fails

    // 2. Step-Specific Logic
    if (step === 1) {
        // Username Check
        const username = document.getElementById('usernameInput').value;
        const btn = stepEl.querySelector('.btn-next');
        const originalText = btn.innerHTML;

        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Checking...';

        try {
            // Check availability via API
            const isAvailable = await checkUsername(username);

            if (isAvailable) {
                showStep(2);
            } else {
                // If not available, checkUsername already showed the error feedback
                document.getElementById('usernameInput').reportValidity();
            }
        } catch (e) {
            console.error(e);
            alert('Error checking username. Please try again.');
        } finally {
            btn.disabled = false;
            btn.innerHTML = originalText;
        }
    }
    else if (step === 2) {
        // Password Validation - Check all requirements
        if (!validatePassword()) {
            const pwInput = document.getElementById('passwordInput');
            pwInput.classList.add('is-invalid');
            return;
        }
        document.getElementById('passwordInput').classList.remove('is-invalid');
        showStep(3);
    }
    else if (step === 3) {
        // Names are already validated by HTML5 pattern in step 1 loop above
        showStep(4);
    }
}

function prevStep(step) {
    if (step > 1) {
        showStep(step - 1);
    }
}

async function checkUsername(username, passive = false) {
    const feedback = document.getElementById('usernameFeedback');
    const input = document.getElementById('usernameInput');

    try {
        const response = await fetch(`signup.php?action=check_username&username=${encodeURIComponent(username)}`);
        const data = await response.json();

        if (data.available) {
            input.classList.add('is-valid');
            input.classList.remove('is-invalid');
            feedback.className = 'validation-feedback text-success';
            feedback.innerHTML = '<i class="bi bi-check-circle"></i> Username available';
            return true;
        } else {
            input.classList.add('is-invalid');
            input.classList.remove('is-valid');
            feedback.className = 'validation-feedback text-danger';
            feedback.innerHTML = `<i class="bi bi-x-circle"></i> ${data.message || 'Username taken'}`;
            return false;
        }
    } catch (e) {
        if (!passive) alert('Connection error');
        return false;
    }
}
