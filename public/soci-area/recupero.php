<?php
require_once __DIR__ . '/../../includes/auth_soci.php';
require_once __DIR__ . '/../../includes/soci_layout.php';
start_soci_session();
if (!soci_ready()) soci_message_page('Area soci in arrivo', 'L\'area soci non è ancora attiva. Riprova più tardi.', 503);

$inviato = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    soci_verify_csrf();
    $email = clean_email(is_string($_POST['email'] ?? null) ? $_POST['email'] : '') ?? '';
    if (filter_var($email, FILTER_VALIDATE_EMAIL)) soci_request_reset($email);
    $inviato = true; // stessa risposta in ogni caso
}

soci_header('Password dimenticata');
?>
<section class="sa-card sa-narrow">
    <span class="sa-kicker">AREA SOCI</span>
    <h1>Password dimenticata</h1>
    <?php if ($inviato): ?>
        <p>Se l'indirizzo è tra quelli dei soci, entro qualche minuto ti arriva un'email con il link per scegliere una nuova password. Dai un'occhiata anche nella cartella spam.</p>
        <p>Ancora niente? Scrivi al direttivo: ti mandiamo un nuovo accesso.</p>
        <a class="sa-btn" href="<?= u('/soci-area/login.php') ?>">Torna all'accesso</a>
    <?php else: ?>
        <p>Capita a tutti. Scrivi l'email con cui entri nell'area soci e ti mandiamo un link per sceglierne una nuova.</p>
        <form method="post" class="sa-form">
            <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
            <label for="email">Email</label>
            <input type="email" id="email" name="email" autocomplete="email" required>
            <button type="submit" class="sa-btn">Invia il link</button>
        </form>
        <p class="sa-small"><a href="<?= u('/soci-area/login.php') ?>">← Torna all'accesso</a></p>
    <?php endif; ?>
</section>
<?php soci_footer(); ?>
