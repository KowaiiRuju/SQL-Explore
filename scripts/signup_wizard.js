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
}

async function nextStep(step) {
    const stepEl = document.getElementById(`step${step}`);
    const inputs = stepEl.querySelectorAll('input, select');
    let valid = true;

    // Basic validation
    inputs.forEach(input => {
        if (!input.checkValidity()) {
            input.reportValidity();
            valid = false;
        }
    });

    if (!valid) return;

    // Specific Step Logic
    if (step === 1) {
        const username = document.getElementById('usernameInput').value;
        const btn = stepEl.querySelector('.btn-next');

        // Disable button while checking
        const originalText = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Checking...';

        try {
            const isAvailable = await checkUsername(username);

            if (isAvailable) {
                showStep(2);
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
        // Password validation (min length is already handled by HTML5, but we can add more)
        const pw = document.getElementById('passwordInput').value;
        if (pw.length < 8) {
            alert("Password must be at least 8 characters.");
            return;
        }
        showStep(3);
    }
    else if (step === 3) {
        // Name validation (basic HTML5 is usually enough)
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
