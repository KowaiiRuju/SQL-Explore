<?php
/**
 * Shared authentication header markup.
 * Includes the opening tags for the layout and branding.
 */
?>
<main class="login-wrapper">
    <div class="login-card">
        <!-- Left Side: Form Content -->
        <div class="login-left">
            <div class="login-header">
                <div class="logo-placeholder">Sanchez E.</div>
                <h1 class="welcome-text"><?= $welcomeText ?? 'Hello,<br>welcome!' ?></h1>
                <?= $extraHeaderContent ?? '' ?>
            </div>
