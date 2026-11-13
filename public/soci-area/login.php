<?php
require_once __DIR__ . '/../../includes/auth_soci.php';
require_once __DIR__ . '/../../includes/soci_layout.php';
start_soci_session();
if (!soci_ready()) soci_message_page('Area soci in arrivo', 'L\'area soci non è ancora attiva. Riprova più tardi.', 503);

/** Dove mandare il socio dopo l'accesso: la partita del link ricevuto su WhatsApp, se c'era. */
function soci_dopo_login_url(): string
{
    $id = (int)($_SESSION['socio_dopo_login'] ?? 0);
    unset($_SESSION['socio_dopo_login']);
    return base_path('/soci-area/index.php') . ($id > 0 ? '?partita=' . $id . '#partita-' . $id : '');
}

if (current_socio()) { header('Location: ' . soci_dopo_login_url()); exit; }

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
        if ($esito === true) { header('Location: ' . soci_dopo_login_url()); exit; }
        $errore = is_string($esito) ? $esito : 'Email o password non corretti.';
    }
}

soci_header('Accedi');
?>
<div class="sa-login">
    <section class="sa-login-intro" aria-labelledby="sa-login-title">
        <span class="sa-kicker">AREA SOCI</span>
        <h1 id="sa-login-title">Il tuo posto in sede, prenotato dal telefono.</h1>
        <p>Entra con l’email che hai lasciato al club e tieni tutto sotto controllo in un’unica pagina.</p>
        <ul class="sa-perks">
            <li><strong>Prenoti la partita</strong> con un tocco, e se cambi idea la annulli.</li>
            <li><strong>Scopri la tua sedia</strong> appena il direttivo la assegna.</li>
            <li><strong>Prenoti per la famiglia</strong>, se il direttivo ha collegato i vostri profili.</li>
        </ul>
    </section>
    <section class="sa-card sa-login-card" aria-labelledby="sa-form-title">
        <h2 id="sa-form-title">Accedi</h2>
        <?php if ($errore): ?><div class="sa-flash sa-flash-error" role="alert"><?= h($errore) ?></div><?php endif; ?>
        <form method="post" class="sa-form" action="<?= u('/soci-area/login.php') ?>">
            <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
            <label for="email">Email</label>
            <input type="email" id="email" name="email" autocomplete="username" inputmode="email" value="<?= h($email) ?>" required autofocus>
            <label for="password">Password</label>
            <input type="password" id="password" name="password" autocomplete="current-password" required>
            <button type="submit" class="sa-btn">Entra</button>
        </form>
        <p class="sa-small"><a href="<?= u('/soci-area/recupero.php') ?>">Hai dimenticato la password?</a></p>
        <div class="sa-login-help">
            <strong>Prima volta qui?</strong>
            <p>L’accesso non si crea da soli: lo attiva il direttivo e ti arriva un’email con il link per scegliere la password. Se sei socio e non l’hai ricevuta, <a href="<?= u('/index.php') ?>#contatti">scrivici</a>.</p>
        </div>
    </section>
</div>
<?php soci_footer(); ?>
