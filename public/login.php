<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

start_secure_session();

if (is_logged_in()) {
    header('Location: ' . base_path('/dashboard.php'));
    exit;
}

$errore = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify($_POST['csrf_token'] ?? null)) {
        $errore = 'Sessione scaduta, ricarica la pagina e riprova.';
    } else {
        $username = trim($_POST['username'] ?? '');
        $password = (string)($_POST['password'] ?? '');

        if ($username === '' || $password === '') {
            $errore = 'Inserisci username e password.';
        } elseif (attempt_login($username, $password)) {
            header('Location: ' . base_path('/dashboard.php'));
            exit;
        } else {
            $errore = 'Credenziali non valide.';
        }
    }
}

$csrf = csrf_token();
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Accesso - Gestionale Inter Club Brindisi</title>
    <meta name="robots" content="noindex,nofollow">
    <link rel="stylesheet" href="<?= u('/assets/css/base.css') ?>">
    <link rel="stylesheet" href="<?= u('/assets/css/style.css') ?>">
    <link rel="stylesheet" href="<?= u('/assets/css/admin.css') ?>">
    <link rel="stylesheet" href="<?= u('/assets/css/accessibility.css') ?>">
    <link rel="stylesheet" href="<?= u('/assets/css/login.css') ?>">
    <link rel="stylesheet" href="<?= u('/assets/css/brand.css') ?>">
    <link rel="icon" type="image/jpeg" href="<?= u('/assets/images/logo-inter-club-brindisi.jpg') ?>">
</head>
<body class="login-body">
<main class="login-shell">
    <section class="login-story" aria-labelledby="login-story-title">
        <a href="<?= u('/index.php') ?>" class="login-brand"><img class="club-logo" src="<?= u('/assets/images/logo-inter-club-brindisi.jpg') ?>" alt="" width="84" height="84"><span class="login-brand-copy">INTER CLUB <span>BRINDISI</span><small>JAVIER ZANETTI</small></span></a>
        <div class="login-story-content"><div class="login-story-kicker">LA NOSTRA SQUADRA, FUORI DAL CAMPO.</div><h1 id="login-story-title">LA PASSIONE.<br>DIETRO<br><span>LE QUINTE.</span></h1><p>Persone, tessere, iniziative. <br>Prendersi cura del club <br>è un altro modo di esserci.</p></div>
        <div class="login-story-footer"><span>BRINDISI / DAL 2003</span><span>INSIEME, SEMPRE.</span></div>
    </section>
    <section class="login-workspace" aria-labelledby="login-form-title">
        <a class="login-back" href="<?= u('/index.php') ?>">← Torna al sito del club</a>
        <div class="login-box">
    <div class="login-kicker"><span aria-hidden="true"></span> ACCESSO AL GESTIONALE</div>
    <h2 id="login-form-title">BENTORNATO,<br>NERAZZURRO.</h2>
    <p class="login-intro">Il tuo spazio per far vivere il club.<br>Accedi con le credenziali del direttivo.</p>

    <?php if ($errore): ?>
        <div class="flash flash-error"><?= h($errore) ?></div>
    <?php endif; ?>

    <form method="post" action="<?= u('/login.php') ?>">
        <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">

        <label for="username">USERNAME</label>
        <input type="text" id="username" name="username" autocomplete="username" placeholder="Il tuo username" required>

        <label for="password">PASSWORD</label>
        <div class="password-field">
        <input type="password" id="password" name="password" autocomplete="current-password" required>
        <button class="password-toggle" type="button" aria-controls="password" aria-pressed="false">Mostra</button>
        </div>

        <button type="submit" class="login-submit">Entra nel gestionale <span aria-hidden="true">↗</span></button>
    </form>
    <p class="login-help">Accesso riservato agli operatori autorizzati.<br>Se non ricordi le credenziali, contatta l’amministratore del club.</p>
</div>
    <div class="login-workspace-footer"><span>ICBR / AREA RISERVATA</span><a href="<?= u('/privacy.php') ?>">Privacy e cookie</a></div>
    </section>
</main>
<script>
document.querySelector('.password-toggle')?.addEventListener('click', function() {
    const input = document.getElementById('password');
    const visible = input.type === 'password';
    input.type = visible ? 'text' : 'password';
    this.setAttribute('aria-pressed', String(visible));
    this.textContent = visible ? 'Nascondi' : 'Mostra';
});
</script>
</body>
</html>
