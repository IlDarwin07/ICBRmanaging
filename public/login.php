<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

start_secure_session();

if (is_logged_in()) {
    header('Location: /dashboard.php');
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
            header('Location: /dashboard.php');
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
    <link rel="stylesheet" href="/assets/css/style.css">
</head>
<body class="login-body">
<div class="login-box">
    <h1>Inter Club Javier Zanetti Brindisi</h1>
    <h2>Accesso area riservata</h2>

    <?php if ($errore): ?>
        <div class="flash flash-error"><?= h($errore) ?></div>
    <?php endif; ?>

    <form method="post" action="/login.php" autocomplete="off">
        <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">

        <label for="username">Username</label>
        <input type="text" id="username" name="username" required autofocus>

        <label for="password">Password</label>
        <input type="password" id="password" name="password" required>

        <button type="submit">Accedi</button>
    </form>
</div>
</body>
</html>
