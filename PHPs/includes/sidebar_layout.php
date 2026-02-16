<?php
/**
 * Shared sidebar layout — include this once in any page that needs the sidebar.
 *
 * Outputs:
 *  • Desktop: persistent sidebar column (col-lg-3 col-xl-2)
 *  • Mobile:  fixed top-bar + offcanvas sidebar
 *
 * Requires: $userProfile to be set before including this file.
 */
?>

<!-- Sidebar (Desktop Persistent) -->
<div class="col-lg-3 col-xl-2 d-none d-lg-block">
    <?php include __DIR__ . '/../sidebar.php'; ?>
</div>

<!-- Mobile Top Bar -->
<div class="d-lg-none px-3 py-2 bg-white w-100 border-bottom d-flex justify-content-between align-items-center fixed-top shadow-sm" style="z-index: 1040; height: 60px;">
    <div class="d-flex align-items-center gap-3 overflow-hidden">
        <button class="btn btn-light" type="button" data-bs-toggle="offcanvas" data-bs-target="#mobileSidebar">
            <i class="bi bi-list fs-4"></i>
        </button>
        <span class="fw-bold text-primary fs-5 text-truncate"><?= htmlspecialchars($mobileKey ?? 'SQL Explore') ?></span>
    </div>
    <div id="mobileNavbarActions" class="d-flex align-items-center gap-2">
        <!-- Page specific actions will be teleported here via JS -->
    </div>
</div>

<!-- Mobile Sidebar Offcanvas -->
<div class="offcanvas offcanvas-start" tabindex="-1" id="mobileSidebar">
    <div class="offcanvas-body p-0">
         <?php include __DIR__ . '/../sidebar.php'; ?>
    </div>
</div>
