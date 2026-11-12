<?php
/** Layout dell'area soci: leggero, pensato per il telefono. */

function soci_header(string $title, ?array $socio = null): void
{
    $flash = get_flash_message();
    $v = function (string $file): string {
        $path = __DIR__ . '/../public/assets/css/' . $file;
        return is_file($path) ? '?v=' . filemtime($path) : '';
    };
    ?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= h($title) ?> - Area soci Inter Club Brindisi</title>
    <meta name="robots" content="noindex,nofollow">
    <meta name="theme-color" content="#011ea0">
    <link rel="stylesheet" href="<?= u('/assets/css/base.css') . h($v('base.css')) ?>">
    <link rel="stylesheet" href="<?= u('/assets/css/soci-area.css') . h($v('soci-area.css')) ?>">
    <link rel="icon" type="image/jpeg" href="<?= u('/assets/images/logo-inter-club-brindisi.jpg') ?>">
</head>
<body class="sa-body">
<a href="#contenuto" class="skip-link">Salta al contenuto</a>
<header class="sa-top">
    <a class="sa-brand" href="<?= u($socio ? '/soci-area/index.php' : '/index.php') ?>">
        <img src="<?= u('/assets/images/logo-inter-club-brindisi.jpg') ?>" alt="" width="40" height="40">
        <span>INTER CLUB <b>BRINDISI</b><small>AREA SOCI</small></span>
    </a>
    <?php if ($socio): ?>
    <nav class="sa-nav" aria-label="Area soci">
        <a href="<?= u('/soci-area/index.php') ?>">Partite</a>
        <a href="<?= u('/soci-area/profilo.php') ?>">Profilo</a>
        <form method="post" action="<?= u('/soci-area/logout.php') ?>">
            <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
            <button type="submit">Esci</button>
        </form>
    </nav>
    <?php endif; ?>
</header>
<main id="contenuto" class="sa-main">
    <?php if ($flash): ?><div class="sa-flash sa-flash-<?= h($flash['type']) ?>" role="status"><?= h($flash['message']) ?></div><?php endif; ?>
<?php
}

function soci_footer(): void
{
    ?>
</main>
<footer class="sa-footer">
    <span>Inter Club Javier Zanetti Brindisi</span>
    <a href="<?= u('/privacy.php') ?>">Privacy</a>
</footer>
</body>
</html>
<?php
}

/** Pagina di errore/avviso dell'area soci (sostituisce render_error_page). */
function soci_message_page(string $title, string $message, int $status = 200, ?array $socio = null): void
{
    http_response_code($status);
    soci_header($title, $socio);
    echo '<section class="sa-card"><h1>' . h($title) . '</h1><p>' . h($message) . '</p>'
        . '<a class="sa-btn" href="' . u($socio ? '/soci-area/index.php' : '/soci-area/login.php') . '">' . ($socio ? 'Torna alle partite' : 'Vai all\'accesso') . '</a></section>';
    soci_footer();
    exit;
}

/** Verifica CSRF per i form dell'area soci, con pagina d'errore adatta. */
function soci_verify_csrf(): void
{
    if (!csrf_verify($_POST['csrf_token'] ?? null)) {
        soci_message_page('Pagina scaduta', 'Il modulo è scaduto. Torna indietro, ricarica la pagina e riprova.', 403, current_socio());
    }
}
