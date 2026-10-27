<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/site_content.php';
$site = array_replace(require __DIR__ . '/../config/site.php', site_content());

/** URL di un asset con versione basata sulla data di modifica, per una cache lunga e sicura. */
function asset_url(string $path): string
{
    $file = __DIR__ . '/../public' . $path;
    return u($path . (is_file($file) ? '?v=' . filemtime($file) : ''));
}

/** URL assoluto del sito: ICBR_SITE_URL (consigliato in produzione) oppure host della richiesta validato. */
function site_absolute_url(string $path = '/'): string
{
    $root = rtrim((string) (getenv('ICBR_SITE_URL') ?: ''), '/');
    if ($root === '' || !preg_match('#\Ahttps?://[a-z0-9.-]+(?::\d+)?(?:/[\w./-]*)?\z#i', $root)) {
        $host = strtolower((string) ($_SERVER['HTTP_HOST'] ?? ''));
        if (!preg_match('/\A[a-z0-9.-]+(?::\d+)?\z/', $host)) return '';
        $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (int) ($_SERVER['SERVER_PORT'] ?? 0) === 443;
        $root = ($https ? 'https://' : 'http://') . $host . base_url();
    }
    return $root . $path;
}

/**
 * Intestazione pubblica con metadati SEO e social.
 * $title è il titolo completo della pagina; $jsonLd sono i dati strutturati aggiuntivi.
 */
function public_header(string $title, string $description, string $path = '/', array $jsonLd = []): void
{
    $home = base_path('/index.php');
    $content = $GLOBALS['site'] ?? site_content();
    $canonical = site_absolute_url($path);
    $image = site_absolute_url('/assets/images/' . (site_image_valid($content['hero_foto'] ?? '') ? $content['hero_foto'] : 'club-sede.jpg'));
    $logo = site_absolute_url('/assets/images/logo-inter-club-brindisi.jpg');
    $sameAs = array_values(array_filter(array_intersect_key($content, array_flip(['instagram','facebook','youtube','tiktok'])), fn($url, $key) => $url !== '' && site_social_valid($key, $url), ARRAY_FILTER_USE_BOTH));
    $club = [
        '@context' => 'https://schema.org',
        '@type' => 'SportsClub',
        'name' => $content['nome'] ?? 'Inter Club Javier Zanetti Brindisi',
        'alternateName' => 'Inter Club Brindisi',
        'description' => 'Inter Club di Brindisi dal 2003: tifosi dell’Inter, partite in sede e tesseramento Inter Club.',
        'sport' => 'Calcio',
        'foundingDate' => $content['fondazione'] ?? '2003',
        'address' => [
            '@type' => 'PostalAddress',
            'streetAddress' => $content['indirizzo'] ?? '',
            'postalCode' => preg_replace('/\D/', '', $content['citta'] ?? '') ?: null,
            'addressLocality' => 'Brindisi',
            'addressRegion' => 'BR',
            'addressCountry' => 'IT',
        ],
        'hasMap' => $content['mappe'] ?? null,
    ];
    if ($canonical !== '') { $club['url'] = site_absolute_url('/'); $club['logo'] = $logo; $club['image'] = $image; }
    if ($sameAs) $club['sameAs'] = $sameAs;
    $schemas = array_merge([$club], $jsonLd);
    ?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= h($title) ?></title>
    <meta name="description" content="<?= h($description) ?>">
    <meta name="robots" content="index,follow,max-image-preview:large">
    <meta name="theme-color" content="#011ea0">
<?php if ($canonical !== ''): ?>
    <link rel="canonical" href="<?= h($canonical) ?>">
    <meta property="og:url" content="<?= h($canonical) ?>">
    <meta property="og:image" content="<?= h($image) ?>">
    <meta name="twitter:image" content="<?= h($image) ?>">
<?php endif; ?>
    <meta property="og:type" content="website">
    <meta property="og:locale" content="it_IT">
    <meta property="og:site_name" content="Inter Club Javier Zanetti Brindisi">
    <meta property="og:title" content="<?= h($title) ?>">
    <meta property="og:description" content="<?= h($description) ?>">
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="<?= h($title) ?>">
    <meta name="twitter:description" content="<?= h($description) ?>">
    <link rel="preload" href="<?= u('/assets/fonts/barlow-condensed-bold.woff2') ?>" as="font" type="font/woff2" crossorigin>
    <link rel="preload" href="<?= u('/assets/fonts/barlow-regular.woff2') ?>" as="font" type="font/woff2" crossorigin>
    <link rel="stylesheet" href="<?= asset_url('/assets/css/base.css') ?>">
    <link rel="stylesheet" href="<?= asset_url('/assets/css/public.css') ?>">
    <link rel="stylesheet" href="<?= asset_url('/assets/css/accessibility.css') ?>">
    <link rel="stylesheet" href="<?= asset_url('/assets/css/brand.css') ?>">
    <link rel="stylesheet" href="<?= asset_url('/assets/css/modern.css') ?>">
    <link rel="stylesheet" href="<?= asset_url('/assets/css/membership.css') ?>">
    <link rel="stylesheet" href="<?= asset_url('/assets/css/faq.css') ?>">
    <link rel="icon" type="image/jpeg" href="<?= u('/assets/images/logo-inter-club-brindisi.jpg') ?>">
    <link rel="apple-touch-icon" href="<?= u('/assets/images/logo-inter-club-brindisi.jpg') ?>">
<?php foreach ($schemas as $schema): ?>
    <script type="application/ld+json"><?= json_encode($schema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP) ?></script>
<?php endforeach; ?>
    <script src="<?= asset_url('/assets/js/site.js') ?>" defer></script>
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
    $content = $GLOBALS['site'] ?? site_content();
    ?>
<footer class="public-footer">
    <?php $socials = array_filter(array_intersect_key($content,array_flip(['instagram','facebook','youtube','tiktok']))); if ($socials): ?>
    <div class="footer-social-heading"><span class="eyebrow">SEGUICI</span><h2>Il club non finisce<br>al novantesimo.</h2></div>
    <nav class="footer-socials" aria-label="I social del club"><?php foreach ($socials as $key=>$url): if (!site_social_valid($key,$url)) continue; ?><a href="<?= h($url) ?>" target="_blank" rel="noopener noreferrer"><span class="social-monogram" aria-hidden="true"><?= h(['instagram'=>'IG','facebook'=>'FB','youtube'=>'YT','tiktok'=>'TT'][$key]) ?></span><span><?= h(['instagram'=>'Instagram','facebook'=>'Facebook','youtube'=>'YouTube','tiktok'=>'TikTok'][$key]) ?></span><span aria-hidden="true">↗</span></a><?php endforeach; ?></nav>
    <?php endif; ?>
    <div class="footer-grid">
        <div><img class="footer-club-logo" src="<?= u('/assets/images/logo-inter-club-brindisi.jpg') ?>" alt="Logo Inter Club Javier Zanetti Brindisi" width="100" height="100" loading="lazy"><strong>Inter Club Javier Zanetti Brindisi</strong><address><?= h($content['indirizzo']) ?>, <?= h($content['citta']) ?></address></div>
        <div><a href="<?= u('/index.php') ?>#tesseramento">Tesseramento <?= h($content['stagione']) ?></a><a href="<?= u('/index.php') ?>#contatti">Contatti</a></div>
        <div><a href="<?= u('/privacy.php') ?>">Privacy e cookie</a></div>
    </div>
    <div class="footer-bottom"><span>© <?= date('Y') ?> Inter Club Javier Zanetti Brindisi · Club locale, distinto da FC Internazionale Milano.</span><a class="footer-staff-link" href="<?= u('/login.php') ?>" rel="nofollow">Direttivo</a></div>
</footer>
</body>
</html>
<?php
}
