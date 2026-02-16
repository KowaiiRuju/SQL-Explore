<?php
/**
 * Shared page footer.
 *
 * Expected variables before include:
 *   $pageScripts — array of page-specific JS paths relative to /scripts/ (optional)
 */
$pageScripts = $pageScripts ?? [];
?>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"></script>

    <!-- Page-specific scripts -->
    <?php foreach ($pageScripts as $script): ?>
    <script src="../scripts/<?= htmlspecialchars($script) ?>"></script>
    <?php endforeach; ?>

    <script>
        // Global Password Toggle
        document.addEventListener('click', function (e) {
            const toggle = e.target.closest('.toggle-password');
            if (toggle) {
                const icon = toggle.querySelector('i');
                const  inputGroup = toggle.closest('.input-group');
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
    </script>
</body>
</html>
