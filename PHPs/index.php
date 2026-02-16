<?php
session_start();
$pageTitle = 'Welcome to SQL Explore';
$pageCss   = ['landing.css'];
$bodyClass = 'landing-page';
require __DIR__ . '/includes/header.php';
?>

<nav class="navbar navbar-expand-lg navbar-light bg-transparent py-4">
    <div class="container-fluid px-5">
        <a class="navbar-brand fw-bold text-primary fs-4" href="#">
            <div class="logo-square d-inline-block bg-primary me-2" style="width: 24px; height: 24px;"></div>
            LOGO
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav ms-auto mb-2 mb-lg-0 fw-semibold text-uppercase small spacing-wide">
                <li class="nav-item"><a class="nav-link active" href="index.php">Home</a></li>
                <li class="nav-item"><a class="nav-link" href="about_us.php">About Us</a></li>
                <li class="nav-item"><a class="nav-link" href="login.php">Login</a></li>
                <li class="nav-item"><a class="nav-link" href="signup.php">Sign Up</a></li>
            </ul>
            <div class="d-flex ms-lg-4">
                <a href="<?php echo isset($_SESSION['user']) ? 'newsfeed.php' : 'signup.php'; ?>" class="btn btn-primary rounded-pill px-4 fw-bold">Get Started</a>
            </div>
        </div>
    </div>
</nav>

<main class="container-fluid hero-section d-flex align-items-center px-5">
    <div class="row w-100 align-items-center">
        <!-- Left Text -->
        <div class="col-lg-5 mb-5 mb-lg-0">
            <h1 class="display-4 fw-bold text-dark text-uppercase mb-3 hero-title">
                Event Scoring<br>System
            </h1>
            <p class="text-muted lead mb-4 pe-lg-5 hero-text">
                The comprehensive centralized platform where users are assigned to teams, participate in managed events, and score points to compete for the top spot.
            </p>
            <a href="about_us.php" class="btn btn-primary rounded-pill px-5 py-2 fw-bold shadow-sm">Learn More</a>
        </div>
        
        <!-- Right Image -->
        <div class="col-lg-7">
            <div class="hero-image-container position-relative">
                <!-- Illustration placeholder -->
                <img src="../images/landing.png" alt="Business Meeting Illustration" class="img-fluid" style="pointer-events: none;">
                
                <!-- Chat bubbles overlay -->
                <div class="chat-bubble bubble-1"><i class="bi bi-graph-up-arrow"></i></div>
                <div class="chat-bubble bubble-2"><i class="bi bi-gear-fill"></i></div>
                <div class="chat-bubble bubble-3"><i class="bi bi-chat-dots-fill"></i></div>
            </div>
        </div>
    </div>
</main>

<!-- Background Graphic -->
<div class="landing-bg-wave"></div>

<?php 
$pageScripts = [];
require __DIR__ . '/includes/footer.php'; 
?>
