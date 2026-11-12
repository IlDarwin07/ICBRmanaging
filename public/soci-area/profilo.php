<?php
require_once __DIR__ . '/../../includes/auth_soci.php';
require_once __DIR__ . '/../../includes/soci_layout.php';
$socio = require_socio_login();

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    soci_verify_csrf();
    $attuale = is_string($_POST['password_attuale'] ?? null) ? $_POST['password_attuale'] : '';
    $nuova = is_string($_POST['password'] ?? null) ? $_POST['password'] : '';
    $conferma = is_string($_POST['password_conferma'] ?? null) ? $_POST['password_conferma'] : '';
    $stmt = get_db_connection()->prepare('SELECT password_hash FROM soci_account WHERE id_account = ?');
    $stmt->execute([$socio['id_account']]);
    $hash = $stmt->fetchColumn();
    if (!$hash || !password_verify($attuale, $hash)) $errors[] = 'La password attuale non è corretta.';
    $errors = array_merge($errors, socio_password_errors($nuova, $conferma));
    if (!$errors) {
        soci_account_set_password($socio['id_account'], $nuova);
        session_regenerate_id(true);
        redirect_with_message('/soci-area/profilo.php', 'Password aggiornata.');
    }
}

$tesserato = socio_tesserato($socio['id_socio']);
$stagione = stagione_corrente();
soci_header('Profilo', $socio);
?>
<section class="sa-card">
    <span class="sa-kicker">PROFILO</span>
    <h1><?= h($socio['nome'] . ' ' . $socio['cognome']) ?></h1>
    <p>Accedi con <strong><?= h($socio['email']) ?></strong>.</p>
    <p>Tesseramento <?= h($stagione['codice_stagione'] ?? 'stagione in corso') ?>: <strong><?= $tesserato ? 'registrato' : 'non ancora registrato' ?></strong>.</p>
    <p class="sa-small">Per correggere i tuoi dati o cambiare email rivolgiti al direttivo.</p>
</section>
<section class="sa-card">
    <h2>Cambia password</h2>
    <?php if ($errors): ?><div class="sa-flash sa-flash-error" role="alert"><?= implode('<br>', array_map('h', $errors)) ?></div><?php endif; ?>
    <form method="post" class="sa-form">
        <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
        <input type="email" name="username" value="<?= h($socio['email']) ?>" autocomplete="username" hidden>
        <label for="password_attuale">Password attuale</label>
        <input type="password" id="password_attuale" name="password_attuale" autocomplete="current-password" required>
        <label for="password">Nuova password (almeno <?= SOCI_PASSWORD_MIN ?> caratteri)</label>
        <input type="password" id="password" name="password" autocomplete="new-password" minlength="<?= SOCI_PASSWORD_MIN ?>" required>
        <label for="password_conferma">Ripeti la nuova password</label>
        <input type="password" id="password_conferma" name="password_conferma" autocomplete="new-password" minlength="<?= SOCI_PASSWORD_MIN ?>" required>
        <button type="submit" class="sa-btn">Aggiorna password</button>
    </form>
</section>
<?php soci_footer(); ?>
