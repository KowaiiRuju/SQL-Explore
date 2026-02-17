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

    <!-- Global Script -->
    <script src="../scripts/main.js"></script>
</body>
</html>
