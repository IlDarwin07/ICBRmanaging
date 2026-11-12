<?php
require_once __DIR__ . '/../../includes/auth_soci.php';
require_once __DIR__ . '/../../includes/soci_layout.php';
start_soci_session();
if (!soci_ready()) soci_message_page('Area soci in arrivo', 'L\'area soci non è ancora attiva. Riprova più tardi.', 503);
if (current_socio()) { header('Location: ' . base_path('/soci-area/index.php')); exit; }

$errore = isset($_GET['revocato']) ? 'Il tuo accesso non è più attivo. Contatta il direttivo.' : null;
$email = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    soci_verify_csrf();
    $email = clean_email(is_string($_POST['email'] ?? null) ? $_POST['email'] : '') ?? '';
    $password = is_string($_POST['password'] ?? null) ? $_POST['password'] : '';
    if ($email === '' || $password === '') {
        $errore = 'Inserisci email e password.';
    } else {
        $esito = socio_attempt_login($email, $password);
        if ($esito === true) { header('Location: ' . base_path('/soci-area/index.php')); exit; }
        $errore = is_string($esito) ? $esito : 'Email o password non corretti.';
    }
}

soci_header('Accesso');
?>
<section class="sa-card sa-narrow">
    <span class="sa-kicker">AREA SOCI</span>
    <h1>Le partite in sede, dal tuo telefono.</h1>
    <p>Conferma la tua presenza e scopri la tua sedia. L'accesso te lo attiva il direttivo via email.</p>
    <?php if ($errore): ?><div class="sa-flash sa-flash-error" role="alert"><?= h($errore) ?></div><?php endif; ?>
    <form method="post" class="sa-form">
        <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
        <label for="email">Email</label>
        <input type="email" id="email" name="email" autocomplete="username" value="<?= h($email) ?>" required>
        <label for="password">Password</label>
        <input type="password" id="password" name="password" autocomplete="current-password" required>
        <button type="submit" class="sa-btn">Entra</button>
    </form>
    <p class="sa-small"><a href="<?= u('/soci-area/recupero.php') ?>">Password dimenticata?</a></p>
</section>
<?php soci_footer(); ?>
