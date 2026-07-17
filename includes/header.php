<?php ob_start(); header('Content-Type: text/html; charset=utf-8'); ?>
<!DOCTYPE html>
<html lang="es-MX">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#059669">
    <link rel="icon" type="image/svg+xml" href="<?= APP_URL ?>/assets/favicon.svg">
    <meta name="csrf-token" content="<?= isset($_SESSION['csrf_token']) ? $_SESSION['csrf_token'] : generateCSRFToken() ?>">
    <meta name="base-url" content="<?= APP_URL ?>">
    <title><?= APP_NAME ?> <?= isset($pageTitle) ? '— ' . $pageTitle : '' ?></title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,400;14..32,500;14..32,600;14..32,700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">

    <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/main.css?v=<?= APP_VERSION ?>">
    <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/components.css?v=<?= APP_VERSION ?>">
    <?php if (isset($extraCss)): ?>
        <?php foreach ((array)$extraCss as $css): ?>
            <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/<?= $css ?>.css?v=<?= APP_VERSION ?>">
        <?php endforeach; ?>
    <?php endif; ?>
</head>
<body>
<?php if (isLoggedIn()): ?>
<div class="app-layout">
    <?php require_once __DIR__ . '/sidebar.php'; ?>
    <main class="main-content">
    <?php $flash = getFlash(); if ($flash): ?>
        <div class="alert alert-<?= h($flash['type']) ?>"><?= h($flash['message']) ?></div>
    <?php endif; ?>
<?php endif; ?>
