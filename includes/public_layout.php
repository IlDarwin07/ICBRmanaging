<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/site_content.php';
$site = array_replace(require __DIR__ . '/../config/site.php', site_content());

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
    <link rel="stylesheet" href="<?= u('/assets/css/modern.css') ?>">
    <link rel="stylesheet" href="<?= u('/assets/css/membership.css') ?>">
    <link rel="stylesheet" href="<?= u('/assets/css/faq.css') ?>">
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
        <a href="<?= h($home) ?>#faq">FAQ</a>
    </nav>
</header>
<?php
}

function public_footer(): void
{
    $content = site_content();
    ?>
<footer class="public-footer">
    <?php $socials = array_filter(array_intersect_key($content,array_flip(['instagram','facebook','youtube','tiktok']))); if ($socials): ?>
    <div class="footer-social-heading"><span class="eyebrow">CI TROVI ANCHE QUI</span><h2>Il club non finisce<br>al novantesimo.</h2><p>Le foto, le trasferte, le prossime cose da fare insieme.<br>Seguici e resta nel gruppo.</p></div>
    <nav class="footer-socials" aria-label="I social del club"><?php foreach ($socials as $key=>$url): if (!site_social_valid($key,$url)) continue; ?><a href="<?= h($url) ?>" target="_blank" rel="noopener noreferrer"><span class="social-monogram" aria-hidden="true"><?= h(['instagram'=>'IG','facebook'=>'FB','youtube'=>'YT','tiktok'=>'TT'][$key]) ?></span><span><?= h(['instagram'=>'Instagram','facebook'=>'Facebook','youtube'=>'YouTube','tiktok'=>'TikTok'][$key]) ?><small>Segui il club</small></span><span aria-hidden="true">↗</span></a><?php endforeach; ?></nav>
    <?php endif; ?>
    <img class="footer-club-logo" src="<?= u('/assets/images/logo-inter-club-brindisi.jpg') ?>" alt="Inter Club Brindisi" width="100" height="100" loading="lazy">
    <div class="footer-heading"><span>BRINDISI.</span><span>SEMPRE NERAZZURRA.</span></div>
    <div class="footer-grid">
        <div><strong>Inter Club Javier Zanetti Brindisi</strong><p>Una città. Una passione. Una famiglia.</p></div>
        <div><a href="<?= u('/index.php') ?>#sede">Vieni a trovarci</a><a href="<?= u('/index.php') ?>#contatti">Scrivi al club</a></div>
        <div><a href="<?= u('/privacy.php') ?>">Privacy e cookie</a></div>
    </div>
    <div class="footer-bottom"><span>© <?= date('Y') ?> Inter Club Javier Zanetti Brindisi</span><span>Sito del club locale, distinto dal sito di FC Internazionale Milano.</span><a class="footer-staff-link" href="<?= u('/login.php') ?>">Direttivo</a></div>
</footer>
</body>
</html>
<?php
}
