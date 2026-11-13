<?php
/** Attivazione account e scelta di una nuova password tramite link monouso. */
require_once __DIR__ . '/../../includes/auth_soci.php';
require_once __DIR__ . '/../../includes/soci_layout.php';
start_soci_session();
if (!soci_ready()) soci_message_page('Area soci in arrivo', 'L\'area soci non è ancora attiva. Riprova più tardi.', 503);

$token = is_string($_POST['token'] ?? null) ? $_POST['token'] : (is_string($_GET['token'] ?? null) ? $_GET['token'] : '');
$account = soci_account_by_token($token);
if (!$account) {
    soci_message_page('Link non valido o scaduto', 'Il link è già stato usato oppure è scaduto. Chiedi un nuovo link dalla pagina "Password dimenticata" o al direttivo.', 410);
}

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    soci_verify_csrf();
    $password = is_string($_POST['password'] ?? null) ? $_POST['password'] : '';
    $conferma = is_string($_POST['password_conferma'] ?? null) ? $_POST['password_conferma'] : '';
    $errors = socio_password_errors($password, $conferma);
    if (!$errors) {
        $nuovo = $account['password_hash'] === null;
        soci_account_set_password((int)$account['id_account'], $password);
        get_db_connection()->prepare('UPDATE soci_account SET ultimo_accesso = NOW() WHERE id_account = ?')->execute([$account['id_account']]);
        socio_open_session($account);
        redirect_with_message('/soci-area/index.php', $nuovo ? 'Benvenuto nell\'area soci, ' . $account['nome'] . '!' : 'Password aggiornata.');
    }
}

soci_header('Scegli la password');
?>
<section class="sa-card sa-narrow">
    <span class="sa-kicker">AREA SOCI</span>
    <h1>Ciao <?= h($account['nome']) ?>, scegli la tua password.</h1>
    <p>Da ora entrerai con <strong><?= h($account['email']) ?></strong> e la password che scegli qui. Un consiglio: una frase breve che ricordi è più sicura di una parola complicata.</p>
    <?php if ($errors): ?><div class="sa-flash sa-flash-error" role="alert"><?= implode('<br>', array_map('h', $errors)) ?></div><?php endif; ?>
    <form method="post" class="sa-form" action="<?= u('/soci-area/attiva.php') ?>">
        <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
        <input type="hidden" name="token" value="<?= h($token) ?>">
        <input type="email" name="username" value="<?= h($account['email']) ?>" autocomplete="username" hidden>
        <label for="password">Nuova password (almeno <?= SOCI_PASSWORD_MIN ?> caratteri)</label>
        <input type="password" id="password" name="password" autocomplete="new-password" minlength="<?= SOCI_PASSWORD_MIN ?>" required>
        <label for="password_conferma">Ripeti la password</label>
        <input type="password" id="password_conferma" name="password_conferma" autocomplete="new-password" minlength="<?= SOCI_PASSWORD_MIN ?>" required>
        <button type="submit" class="sa-btn">Salva ed entra</button>
    </form>
</section>
<?php soci_footer(); ?>
