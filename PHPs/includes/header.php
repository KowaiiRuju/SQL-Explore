<?php
/**
 * Shared page header.
 *
 * Expected variables before include:
 *   $pageTitle  — string, the <title> text (required)
 *   $pageCss    — array of page-specific CSS paths relative to /css/ (optional)
 *   $bodyClass  — string, class(es) for <body> (optional)
 *   $extraHead  — string, additional markup for <head> like extra CDN links (optional)
 */
$pageTitle = $pageTitle ?? 'SQL Explore';
$pageCss   = $pageCss   ?? [];
$bodyClass = $bodyClass ?? '';
$extraHead = $extraHead ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="SQL Explore — A simple database management dashboard.">
    <title><?= htmlspecialchars($pageTitle) ?></title>

    <!-- Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">

    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">

    <!-- Global styles -->
    <link rel="stylesheet" href="../css/style.css">

    <!-- Page-specific styles -->
    <?php foreach ($pageCss as $css): ?>
    <link rel="stylesheet" href="../css/<?= htmlspecialchars($css) ?>">
    <?php endforeach; ?>

    <?= $extraHead ?>
</head>
<body class="<?= htmlspecialchars($bodyClass) ?>">
