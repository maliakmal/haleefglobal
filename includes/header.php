<?php
$pageTitle = $pageTitle ?? 'Strategic B2B Events Partner';
$pageDescription = $pageDescription ?? 'Haleef creates and delivers purposeful corporate events, conferences, awards and learning experiences across the Middle East and Africa.';
$currentPage = $currentPage ?? 'home';
$analyticsId = \Haleef\Support\Analytics::measurementId();
$analyticsPath = $analyticsId === '' ? '' : \Haleef\Support\Analytics::publicPath();
if ($analyticsPath !== '') header('Referrer-Policy: no-referrer');
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($pageTitle) ?> | <?= SITE_NAME ?></title>
    <meta name="description" content="<?= e($pageDescription) ?>">
    <meta name="theme-color" content="#0b1916">
    <meta property="og:title" content="<?= e($pageTitle) ?> | <?= SITE_NAME ?>">
    <meta property="og:description" content="<?= e($pageDescription) ?>">
    <meta property="og:type" content="website">
    <?php if (!empty($shareUrl)): ?><link rel="canonical" href="<?= e($shareUrl) ?>"><meta property="og:url" content="<?= e($shareUrl) ?>"><?php endif; ?>
    <?php if (!empty($shareImage) && filter_var($shareImage, FILTER_VALIDATE_URL)): ?><meta property="og:image" content="<?= e($shareImage) ?>"><?php endif; ?>
    <link rel="stylesheet" href="/assets/css/fonts.css?v=1">
    <link rel="stylesheet" href="/assets/css/site.css?v=3">
    <link rel="stylesheet" href="/assets/css/cookies.css?v=3">
    <?php if ($currentPage === 'contact'): ?><link rel="stylesheet" href="/assets/css/contact-offices.css?v=2"><?php endif; ?>
    <?php if ($currentPage === 'training'): ?><link rel="stylesheet" href="/assets/css/learning-editorial.css?v=10"><script src="/assets/js/learning-reveal.js" defer></script><?php endif; ?>
</head>
<body class="page-<?= e($currentPage) ?>">
<header class="site-header<?= !empty($solidHeader) ? ' site-header-solid' : '' ?>" data-header>
    <div class="container nav-wrap">
        <a class="brand" href="<?= page_url() ?>" aria-label="Haleef home">
            <img src="/assets/images/haleef-logo.webp" alt="Haleef" width="180" height="82">
        </a>
        <button class="menu-toggle" type="button" aria-expanded="false" aria-controls="site-navigation" data-menu-toggle>
            <span></span><span></span><span></span><span class="sr-only">Menu</span>
        </button>
        <nav id="site-navigation" class="site-nav" data-menu>
            <a href="<?= page_url('services') ?>" <?= $currentPage === 'services' ? 'aria-current="page"' : '' ?>>What we do</a>
            <a href="<?= page_url('training') ?>" <?= $currentPage === 'training' ? 'aria-current="page"' : '' ?>>Training</a>
            <a href="<?= page_url('work') ?>" <?= $currentPage === 'work' ? 'aria-current="page"' : '' ?>>Our work</a>
            <a href="<?= page_url('about') ?>" <?= $currentPage === 'about' ? 'aria-current="page"' : '' ?>>About</a>
            <a href="<?= MONEYAURA_URL ?>" target="_blank" rel="noopener">MoneyAura ↗</a>
            <a class="button button-small" href="<?= page_url('contact') ?>">Plan an event</a>
        </nav>
    </div>
</header>
<main>
