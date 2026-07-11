<?php
/**
 * Header comune a tutte le pagine dell'area riservata.
 * Richiede che $page_title sia definito prima dell'include.
 */
$utente = current_user();
$flash = get_flash_message();

// Determina la sezione attiva per evidenziare la voce di menu
$current_path = $_SERVER['REQUEST_URI'] ?? '';
$nav_soci    = str_contains($current_path, '/soci');
$nav_import  = str_contains($current_path, '/import');
$nav_config  = str_contains($current_path, '/stagioni') || str_contains($current_path, '/tipologie') || str_contains($current_path, '/tesseramenti');
$nav_dash    = !$nav_soci && !$nav_config && !$nav_import;
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= h($page_title ?? 'Gestionale') ?> - Inter Club Brindisi</title>
    <link rel="stylesheet" href="/assets/css/style.css">
</head>
<body>
<header class="topbar">
    <div class="topbar-brand">Inter Club Javier Zanetti Brindisi</div>
    <nav class="topbar-nav">
        <a href="/dashboard.php" <?= $nav_dash   ? 'class="active"' : '' ?>>Dashboard</a>
        <a href="/soci/list.php" <?= $nav_soci   ? 'class="active"' : '' ?>>Soci</a>
        <a href="/import/upload.php" <?= $nav_import ? 'class="active"' : '' ?>>Import</a>
        <div class="nav-dropdown <?= $nav_config ? 'active' : '' ?>">
            <button class="nav-dropdown-toggle" type="button" aria-haspopup="true" aria-expanded="false">
                Configurazione &#9662;
            </button>
            <ul class="nav-dropdown-menu" role="menu">
                <li><a href="/stagioni/list.php" role="menuitem">Stagioni</a></li>
                <li><a href="/tipologie/list.php" role="menuitem">Tipologie tessera</a></li>
            </ul>
        </div>
    </nav>
    <div class="topbar-user">
        <?php if ($utente): ?>
            <span><?= h($utente['nome'] . ' ' . $utente['cognome']) ?> (<?= h($utente['ruolo']) ?>)</span>
            <a href="/logout.php">Esci</a>
        <?php endif; ?>
    </div>
</header>
<main class="content">
    <?php if ($flash): ?>
        <div class="flash flash-<?= h($flash['type']) ?>"><?= h($flash['message']) ?></div>
    <?php endif; ?>
