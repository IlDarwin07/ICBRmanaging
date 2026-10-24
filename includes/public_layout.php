<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/functions.php';
$site = require __DIR__ . '/../config/site.php';

function public_header(string $title, string $description): void
{
    $home = base_path('/index.php');
    ?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#011ea0">
    <meta name="description" content="<?= h($description) ?>">
    <title><?= h($title) ?> | Inter Club Javier Zanetti Brindisi</title>
    <link rel="stylesheet" href="<?= u('/assets/css/base.css') ?>">
    <link rel="stylesheet" href="<?= u('/assets/css/public.css') ?>">
    <link rel="stylesheet" href="<?= u('/assets/css/accessibility.css') ?>">
    <link rel="stylesheet" href="<?= u('/assets/css/brand.css') ?>">
    <link rel="icon" type="image/jpeg" href="<?= u('/assets/images/logo-inter-club-brindisi.jpg') ?>">
    <script src="<?= u('/assets/js/site.js') ?>" defer></script>
</head>
<body class="public-site">
<a href="#contenuto" class="skip-link">Salta al contenuto</a>
<?php if (getenv('ICBR_PREVIEW') === '1'): ?>
<div class="preview-notice">Anteprima dimostrativa · nessun dato del club · non inviare dati personali reali</div>
<?php endif; ?>
<header class="public-header">
    <a href="<?= h($home) ?>" class="wordmark" aria-label="Inter Club Javier Zanetti Brindisi, home">
        <img class="club-logo" src="<?= u('/assets/images/logo-inter-club-brindisi.jpg') ?>" alt="" width="76" height="76">
        <span class="wordmark-copy">
        <span class="wordmark-top">INTER CLUB</span>
        <span class="wordmark-bottom">JAVIER ZANETTI <b>BRINDISI</b></span>
        </span>
    </a>
    <button class="menu-toggle" type="button" aria-expanded="false" aria-controls="public-navigation">
        <span>Menu</span><span class="menu-lines" aria-hidden="true"></span>
    </button>
    <nav id="public-navigation" class="public-nav" aria-label="Navigazione principale">
        <a href="<?= h($home) ?>#club">Il club</a>
        <a href="<?= h($home) ?>#tesseramento">Tesseramento</a>
        <a href="<?= h($home) ?>#sede">La sede</a>
        <a href="<?= h($home) ?>#contatti">Contatti</a>
        <a href="<?= u('/login.php') ?>" class="admin-link">Area riservata <span aria-hidden="true">↗</span></a>
    </nav>
</header>
<?php
}

function public_footer(): void
{
    ?>
<footer class="public-footer">
    <img class="footer-club-logo" src="<?= u('/assets/images/logo-inter-club-brindisi.jpg') ?>" alt="Inter Club Brindisi" width="100" height="100" loading="lazy">
    <div class="footer-heading"><span>BRINDISI.</span><span>SEMPRE NERAZZURRA.</span></div>
    <div class="footer-grid">
        <div><strong>Inter Club Javier Zanetti Brindisi</strong><p>Una città. Una passione. Una famiglia.</p></div>
        <div><a href="<?= u('/index.php') ?>#sede">Vieni a trovarci</a><a href="<?= u('/index.php') ?>#contatti">Scrivi al club</a></div>
        <div><a href="<?= u('/privacy.php') ?>">Privacy e cookie</a><a href="<?= u('/login.php') ?>">Accesso amministratori ↗</a></div>
    </div>
    <div class="footer-bottom"><span>© <?= date('Y') ?> Inter Club Javier Zanetti Brindisi</span><span>Sito del club locale, distinto dal sito di FC Internazionale Milano.</span></div>
</footer>
</body>
</html>
<?php
}
