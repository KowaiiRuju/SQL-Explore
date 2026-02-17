<?php
session_start();
$pageTitle = 'About Us - Event Scoring System';
$pageCss   = ['landing.css']; // Reuse landing styles for consistency
$bodyClass = 'landing-page';
require __DIR__ . '/includes/header.php';
?>

<nav class="navbar navbar-expand-lg navbar-light bg-transparent py-4">
    <div class="container-fluid px-5">
        <a class="navbar-brand fw-bold text-primary fs-4" href="index.php">
            <div class="logo-square d-inline-block bg-primary me-2"></div>
            LOGO
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav ms-auto mb-2 mb-lg-0 fw-semibold text-uppercase small spacing-wide">
                <li class="nav-item"><a class="nav-link" href="index.php">Home</a></li>
                <li class="nav-item"><a class="nav-link active" href="about_us.php">About Us</a></li>
                <li class="nav-item"><a class="nav-link" href="login.php">Login</a></li>
                <li class="nav-item"><a class="nav-link" href="signup.php">Sign Up</a></li>
            </ul>
            <div class="d-flex ms-lg-4">
                <a href="<?php echo isset($_SESSION['user']) ? 'newsfeed.php' : 'signup.php'; ?>" class="btn btn-primary rounded-pill px-4 fw-bold">Get Started</a>
            </div>
        </div>
    </div>
</nav>

<main class="container py-5">
    <div class="text-center mb-5">
        <h1 class="display-4 fw-bold text-dark mb-3">About Our Platform</h1>
        <p class="lead text-muted col-lg-8 mx-auto">
            Empowering communities through organized events and spirited competition.
        </p>
    </div>

    <div class="row align-items-center mb-5">
        <div class="col-lg-6">
            <h2 class="fw-bold mb-3 text-primary">What We Do</h2>
            <p class="text-muted lead">
                The <strong class="text-dark">Event Scoring System</strong> is designed to streamline the way organizations manage competitive events. 
                Whether it's a sports day, a hackathon, or a corporate team-building exercise, our platform handles the logistics so you can focus on the fun.
            </p>
            <p class="text-muted">
                We provide a centralized hub where users can register, join teams, and track their progress in real-time as events unfold.
            </p>
        </div>
        <div class="col-lg-6 text-center">
            <div class="p-5 bg-light rounded-4 shadow-sm">
                <i class="bi bi-trophy-fill text-warning display-1"></i>
            </div>
        </div>
    </div>

    <div class="row g-4 mb-5">
        <div class="col-md-4">
            <div class="card border-0 shadow-sm h-100 p-4 text-center rounded-4">
                <div class="mb-3 text-primary display-5"><i class="bi bi-people-fill"></i></div>
                <h4 class="fw-bold">Team Assignment</h4>
                <p class="text-muted">
                    Users are dynamically assigned to teams, fostering collaboration and friendly rivalry. Each team has its own identity and aggregate score.
                </p>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card border-0 shadow-sm h-100 p-4 text-center rounded-4">
                <div class="mb-3 text-success display-5"><i class="bi bi-calendar-event-fill"></i></div>
                <h4 class="fw-bold">Event Management</h4>
                <p class="text-muted">
                    Administrators can easily create and schedule events. From single matches to tournament brackets, our system supports various event types.
                </p>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card border-0 shadow-sm h-100 p-4 text-center rounded-4">
                <div class="mb-3 text-danger display-5"><i class="bi bi-graph-up-arrow"></i></div>
                <h4 class="fw-bold">Live Scoring</h4>
                <p class="text-muted">
                    Scores are updated instantly. Watch the leaderboard change in real-time as points are awarded for participation and victory.
                </p>
            </div>
        </div>
    </div>

    <div class="bg-primary bg-opacity-10 rounded-4 p-5 text-center my-5">
        <h2 class="fw-bold mb-3">Ready to join the action?</h2>
        <p class="text-muted mb-4">Create an account today and get assigned to your team!</p>
        <a href="signup.php" class="btn btn-primary btn-lg rounded-pill px-5 fw-bold shadow-sm">Sign Up Now</a>
    </div>

</main>

<!-- Background Graphic -->
<div class="landing-bg-wave"></div>

<?php 
require __DIR__ . '/includes/footer.php'; 
?>
