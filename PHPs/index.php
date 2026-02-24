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
            <div class="logo-square d-inline-block bg-primary me-2"></div>
            Sanchez E.
        </a>.
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav ms-auto mb-2 mb-lg-0 fw-semibold text-uppercase small spacing-wide">
                <li class="nav-item"><a class="nav-link active" href="index.php">Home</a></li>
                <li class="nav-item"><a class="nav-link" href="about_us.php">About Us</a></li>
                <li class="nav-item"><a class="nav-link" href="login.php">Login</a></li>
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
                <img src="../images/landing.png" alt="Business Meeting Illustration" class="img-fluid pointer-events-none">
                
                <!-- Chat bubbles overlay -->
                <div class="chat-bubble bubble-1"><i class="bi bi-graph-up-arrow"></i></div>
                <div class="chat-bubble bubble-2"><i class="bi bi-gear-fill"></i></div>
                <div class="chat-bubble bubble-3"><i class="bi bi-chat-dots-fill"></i></div>
            </div>
        </div>
    </div>
</main>

<!-- About the Creator Section -->
<section class="creator-section" id="creator">
    <div class="container">
        <h2 class="section-heading text-center mb-5">About the Creator</h2>
        <div class="row align-items-center g-5">
            <!-- Profile Picture -->
            <div class="col-lg-4 text-center">
                <div class="creator-avatar-wrapper mx-auto">
                    <img src="../images/pfp_emman.jpg" alt="Emmanuel V. Sanchez" class="creator-avatar" draggable="false"/>
                </div>
                <p class="creator-quote mt-4">"Everything Goes On"</p>
            </div>

            <!-- Creator Info -->
            <div class="col-lg-8">
                <h3 class="creator-name">Emmanuel V. Sanchez</h3>
                <span class="creator-badge"><i class="bi bi-mortarboard-fill me-1"></i>BS Computer Science</span>

                <p class="creator-bio mt-3">
                    Born on December 24, 2003, he had always been easy-going. He didn't strive for anything. 
                    But now that he's actively pursuing something, only God can tell what lies ahead of him.
                </p>

                <div class="creator-details mt-4">
                    <div class="row g-3">
                        <div class="col-sm-6">
                            <div class="detail-card">
                                <i class="bi bi-heart-fill detail-icon"></i>
                                <div>
                                    <span class="detail-label">Likes</span>
                                    <span class="detail-value">Chocolate, RPG Games</span>
                                </div>
                            </div>
                        </div>
                        <div class="col-sm-6">
                            <div class="detail-card">
                                <i class="bi bi-controller detail-icon"></i>
                                <div>
                                    <span class="detail-label">Hobbies</span>
                                    <span class="detail-value">Chess, 2D Art Illustration</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Social Links -->
                <div class="creator-socials mt-4">
                    <a href="https://github.com/kowaii/" target="_blank" rel="noopener" class="social-btn" title="GitHub">
                        <i class="bi bi-github"></i>
                    </a>
                    <a href="https://www.instagram.com/ruju.pi" target="_blank" rel="noopener" class="social-btn" title="Instagram">
                        <i class="bi bi-instagram"></i>
                    </a>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Background Graphic -->
<div class="landing-bg-wave"></div>

<?php 
$pageScripts = [];
require __DIR__ . '/includes/footer.php'; 
?>
