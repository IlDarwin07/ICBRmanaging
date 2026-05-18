<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($pageTitle) ? $pageTitle . ' - ' : ''; ?>ICBR Gestionale</title>
    <link rel="stylesheet" href="/assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
</head>
<body>
    <?php if (isLoggedIn()): ?>
    <nav class="navbar">
        <div class="nav-brand">
            <i class="fas fa-futbol"></i>
            <span>Interclub Brindisi</span>
        </div>
        <div class="nav-links">
            <a href="/pages/dashboard.php"><i class="fas fa-home"></i> Dashboard</a>
            <a href="/pages/partite.php"><i class="fas fa-calendar"></i> Partite</a>
            <?php if (isAdminOrCollaboratore()): ?>
            <a href="/pages/gestione_partite.php"><i class="fas fa-plus-circle"></i> Gestisci Partite</a>
            <a href="/pages/assegna_posti.php"><i class="fas fa-chair"></i> Assegna Posti</a>
            <?php endif; ?>
            <?php if (isAdmin()): ?>
            <a href="/pages/gestione_utenti.php"><i class="fas fa-users-cog"></i> Utenti</a>
            <?php endif; ?>
        </div>
        <div class="nav-user">
            <span class="user-info">
                <i class="fas fa-user"></i>
                <?php echo htmlspecialchars($_SESSION['nome'] . ' ' . $_SESSION['cognome']); ?>
                <span class="badge badge-<?php echo $_SESSION['ruolo']; ?>"><?php echo ucfirst($_SESSION['ruolo']); ?></span>
            </span>
            <a href="/api/logout.php" class="btn btn-logout"><i class="fas fa-sign-out-alt"></i> Esci</a>
        </div>
    </nav>
    <?php endif; ?>
    <main class="container">
