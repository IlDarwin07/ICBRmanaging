<?php
/**
 * Header comune a tutte le pagine dell'area riservata.
 * Richiede che $page_title sia definito prima dell'include.
 *
 * BASE_URL viene definito in config.php come:
 *   define('BASE_URL', '/ICBRmanaging/public');  // XAMPP locale
 *   define('BASE_URL', '');                       // Produzione con DocumentRoot su /public
 */
$utente = current_user();
$flash  = get_flash_message();

// Determina la sezione attiva per evidenziare la voce di menu
$current_path = $_SERVER['REQUEST_URI'] ?? '';
$nav_soci     = str_contains($current_path, '/soci');
$nav_tess     = str_contains($current_path, '/tesseramenti');
$nav_nota     = str_contains($current_path, '/prima_nota');
$nav_msg      = str_contains($current_path, '/messaggi');
$nav_contatti = str_contains($current_path, '/richieste');
$nav_import   = str_contains($current_path, '/importazioni');
$nav_config   = str_contains($current_path, '/stagioni')
               || str_contains($current_path, '/tipologie');
$nav_dash     = !$nav_soci && !$nav_tess && !$nav_nota && !$nav_msg
                && !$nav_config && !$nav_import && !$nav_contatti;

// Percorso base (definito in config.php, default sicuro per XAMPP)
if (!defined('BASE_URL')) {
    // Auto-rileva: se lo script è sotto /ICBRmanaging/ lo includiamo nel base
    $script = $_SERVER['SCRIPT_NAME'] ?? '';
    if (str_contains($script, '/ICBRmanaging/')) {
        define('BASE_URL', '/ICBRmanaging/public');
    } else {
        define('BASE_URL', '');
    }
}
$base = rtrim(BASE_URL, '/');
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= h($page_title ?? 'Gestionale') ?> - Inter Club Brindisi</title>
    <meta name="robots" content="noindex,nofollow">
    <meta name="theme-color" content="#011ea0">
    <link rel="stylesheet" href="<?= $base ?>/assets/css/base.css">
    <link rel="stylesheet" href="<?= $base ?>/assets/css/style.css">
    <link rel="stylesheet" href="<?= $base ?>/assets/css/admin.css">
    <link rel="stylesheet" href="<?= $base ?>/assets/css/accessibility.css">
    <link rel="stylesheet" href="<?= $base ?>/assets/css/details.css">
    <link rel="stylesheet" href="<?= $base ?>/assets/css/brand.css">
    <link rel="icon" type="image/jpeg" href="<?= u('/assets/images/logo-inter-club-brindisi.jpg') ?>">
    <script src="<?= $base ?>/assets/js/site.js" defer></script>
    <script src="<?= $base ?>/assets/js/admin.js" defer></script>
</head>
<body class="admin-body">
<a href="#contenuto-admin" class="skip-link">Salta al contenuto</a>
<?php if (getenv('ICBR_PREVIEW') === '1'): ?><div class="admin-demo-note">Anteprima con soli dati sintetici. Nessuna connessione al database del club.</div><?php endif; ?>
<header class="topbar">
    <a href="<?= $base ?>/dashboard.php" class="topbar-brand"><img class="club-logo" src="<?= u('/assets/images/logo-inter-club-brindisi.jpg') ?>" alt="" width="68" height="68"><span class="admin-brand-copy"><strong>INTER CLUB <span>BRINDISI</span></strong><small>JAVIER ZANETTI<span class="brand-area"> / AREA RISERVATA</span></small></span></a>
    <button class="menu-toggle" type="button" aria-expanded="false" aria-controls="admin-navigation">Menu <span aria-hidden="true">☰</span></button>
    <nav class="topbar-nav" id="admin-navigation" aria-label="Gestionale">
        <a href="<?= $base ?>/dashboard.php"
           <?= $nav_dash   ? 'class="active"' : '' ?>>Dashboard</a>
        <a href="<?= $base ?>/soci/list.php"
           <?= $nav_soci   ? 'class="active"' : '' ?>>Soci</a>
        <a href="<?= $base ?>/tesseramenti/list.php"
           <?= $nav_tess  ? 'class="active"' : '' ?>>Tesseramenti</a>
        <a href="<?= $base ?>/prima_nota/list.php"
           <?= $nav_nota  ? 'class="active"' : '' ?>>Prima nota</a>
        <a href="<?= $base ?>/messaggi/index.php"
           <?= $nav_msg   ? 'class="active"' : '' ?>>WhatsApp</a>
        <a href="<?= $base ?>/richieste/list.php"
           <?= $nav_contatti ? 'class="active"' : '' ?>>Richieste sito</a>
        <a href="<?= $base ?>/importazioni/upload.php"
           <?= $nav_import ? 'class="active"' : '' ?>>Importa</a>
        <div class="nav-dropdown <?= $nav_config ? 'active' : '' ?>">
            <button class="nav-dropdown-toggle" type="button"
                    aria-haspopup="true" aria-expanded="false">
                Configurazione &#9662;
            </button>
            <ul class="nav-dropdown-menu" role="menu">
                <li><a href="<?= $base ?>/stagioni/list.php" role="menuitem">Stagioni</a></li>
                <li><a href="<?= $base ?>/tipologie/list.php" role="menuitem">Tipologie tessera</a></li>
            </ul>
        </div>
    </nav>
    <div class="topbar-user">
        <a href="<?= $base ?>/index.php">Vedi sito ↗</a>
        <?php if ($utente): ?>
            <span><?= h($utente['nome'] . ' ' . $utente['cognome']) ?>
                (<?= h($utente['ruolo']) ?>)</span>
            <a href="<?= $base ?>/logout.php">Esci</a>
        <?php endif; ?>
    </div>
</header>
<main class="content" id="contenuto-admin">
    <?php if ($flash): ?>
        <div class="flash flash-<?= h($flash['type']) ?>"><?= h($flash['message']) ?></div>
    <?php endif; ?>
