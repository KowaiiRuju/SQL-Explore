<!-- Offcanvas Sidebar -->
<div class="offcanvas offcanvas-start" tabindex="-1" id="sidebarOffcanvas" aria-labelledby="sidebarOffcanvasLabel">
    <div class="offcanvas-header">
        <h5 class="offcanvas-title" id="sidebarOffcanvasLabel">SQL Explore</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="offcanvas" aria-label="Close"></button>
    </div>
    <div class="offcanvas-body">
        <ul class="nav flex-column">
            <li class="nav-item mb-3">
                <a class="nav-link fs-5" href="#" data-bs-toggle="modal" data-bs-target="#profileModal">
                    <i class="bi bi-person-circle me-2"></i> Profile
                </a>
            </li>
            <li class="nav-item mb-3">
                <a class="nav-link fs-5" href="index.php">
                    <i class="bi bi-house-fill me-2"></i> Home
                </a>
            </li>
            <?php if ($_SESSION['is_admin'] ?? false): ?>
                <li class="nav-item mb-3">
                    <a class="nav-link fs-5" href="admin.php">
                        <i class="bi bi-speedometer2 me-2"></i> Admin Dashboard
                    </a>
                </li>
            <?php endif; ?>
            
            <li class="nav-item mb-3">
                <a class="nav-link fs-5" href="logout.php">
                    <i class="bi bi-box-arrow-right me-2"></i> Log Out
                </a>
            </li>
        </ul>
    </div>
</div>
