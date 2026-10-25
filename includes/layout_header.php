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
$nav_sito     = str_contains($current_path, '/sito/');
$nav_utenti   = str_contains($current_path, '/utenti/');
$nav_attivita = str_contains($current_path, '/attivita/');
$nav_config   = str_contains($current_path, '/stagioni')
               || str_contains($current_path, '/tipologie');
$nav_dash     = !$nav_soci && !$nav_tess && !$nav_nota && !$nav_msg
                && !$nav_config && !$nav_import && !$nav_contatti && !$nav_sito && !$nav_utenti && !$nav_attivita;

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
    <link rel="stylesheet" href="<?= $base ?>/assets/css/editor.css">
    <link rel="stylesheet" href="<?= $base ?>/assets/css/access-control.css">
    <script>window.icbrAccess=<?= json_encode(['admin'=>($utente['ruolo'] ?? '')==='admin','permissions'=>$utente['permessi'] ?? [],'routes'=>permission_routes(),'current'=>request_route()],JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT) ?>;</script>
    <script src="<?= $base ?>/assets/js/access-control.js" defer></script>
    <link rel="icon" type="image/jpeg" href="<?= u('/assets/images/logo-inter-club-brindisi.jpg') ?>">
    <script src="<?= $base ?>/assets/js/site.js" defer></script>
    <script src="<?= $base ?>/assets/js/admin.js" defer></script>
</head>
<body class="admin-body">
<a href="#contenuto-admin" class="skip-link">Salta al contenuto</a>
<?php if (getenv('ICBR_PREVIEW') === '1'): ?><div class="admin-demo-note">Anteprima con soli dati sintetici. Nessuna connessione al database del club.</div><?php endif; ?>
<?php if (!security_ready()): ?><div class="flash flash-error">Migrazione 010 non applicata: gestione direttivo e registro attività non ancora attivi. Gli accessi non admin restano limitati.</div><?php endif; ?>
<header class="topbar">
    <a href="<?= $base ?>/dashboard.php" class="topbar-brand"><img class="club-logo" src="<?= u('/assets/images/logo-inter-club-brindisi.jpg') ?>" alt="" width="68" height="68"><span class="admin-brand-copy"><strong>INTER CLUB <span>BRINDISI</span></strong><small>JAVIER ZANETTI<span class="brand-area"> / AREA RISERVATA</span></small></span></a>
    <button class="menu-toggle" type="button" aria-expanded="false" aria-controls="admin-navigation">Menu <span aria-hidden="true">☰</span></button>
    <nav class="topbar-nav" id="admin-navigation" aria-label="Gestionale">
        <a href="<?= $base ?>/dashboard.php"
           <?= $nav_dash   ? 'class="active"' : '' ?>>Dashboard</a>
        <?php if (can('soci.view')): ?><a href="<?= $base ?>/soci/list.php"
           <?= $nav_soci   ? 'class="active"' : '' ?>>Soci</a><?php endif; ?>
        <?php if (can('tesseramenti.view')): ?><a href="<?= $base ?>/tesseramenti/list.php"
           <?= $nav_tess  ? 'class="active"' : '' ?>>Tesseramenti</a><?php endif; ?>
        <?php if (can('prima_nota.view')): ?><a href="<?= $base ?>/prima_nota/list.php"
           <?= $nav_nota  ? 'class="active"' : '' ?>>Prima nota</a><?php endif; ?>
        <?php if (can('whatsapp.view')): ?><a href="<?= $base ?>/messaggi/index.php"
           <?= $nav_msg   ? 'class="active"' : '' ?>>WhatsApp</a><?php endif; ?>
        <?php if (can('richieste.view')): ?><a href="<?= $base ?>/richieste/list.php"
           <?= $nav_contatti ? 'class="active"' : '' ?>>Richieste sito</a><?php endif; ?>
        <?php if (can('importazioni.write')): ?><a href="<?= $base ?>/importazioni/upload.php"
           <?= $nav_import ? 'class="active"' : '' ?>>Importa</a><?php endif; ?>
        <?php if (can('sito.write')): ?><a href="<?= $base ?>/sito/edit.php" <?= $nav_sito ? 'class="active"' : '' ?>>Sito vetrina</a><?php endif; ?>
        <?php if (can('utenti.manage')): ?><a href="<?= $base ?>/utenti/list.php" <?= $nav_utenti ? 'class="active"' : '' ?>>Direttivo</a><?php endif; ?>
        <?php if (can('attivita.view')): ?><a href="<?= $base ?>/attivita/list.php" <?= $nav_attivita ? 'class="active"' : '' ?>>Attività</a><?php endif; ?>
        <?php if (can('configurazione.view')): ?><div class="nav-dropdown <?= $nav_config ? 'active' : '' ?>">
            <button class="nav-dropdown-toggle" type="button"
                    aria-haspopup="true" aria-expanded="false">
                Configurazione &#9662;
            </button>
            <ul class="nav-dropdown-menu" role="menu">
                <li><a href="<?= $base ?>/stagioni/list.php" role="menuitem">Stagioni</a></li>
                <li><a href="<?= $base ?>/tipologie/list.php" role="menuitem">Tipologie tessera</a></li>
            </ul>
        </div><?php endif; ?>
    </nav>
    <div class="topbar-user">
        <a href="<?= $base ?>/index.php">Vedi sito ↗</a>
        <?php if ($utente): ?>
            <span><?= h($utente['nome'] . ' ' . $utente['cognome']) ?>
                (<?= h($utente['etichetta_ruolo'] ?? $utente['ruolo']) ?>)</span>
            <a href="<?= $base ?>/logout.php">Esci</a>
        <?php endif; ?>
    </div>
</header>
<main class="content" id="contenuto-admin">
    <?php if ($flash): ?>
        <div class="flash flash-<?= h($flash['type']) ?>"><?= h($flash['message']) ?></div>
    <?php endif; ?>
