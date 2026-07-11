<?php
/**
 * Header comune a tutte le pagine dell'area riservata.
 * Richiede che $page_title sia definito prima dell'include.
 */
$utente = current_user();
$flash = get_flash_message();
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
        <a href="/dashboard.php">Dashboard</a>
        <a href="/soci/list.php">Soci</a>
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
