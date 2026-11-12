<?php
/** Email di sistema: indirizzo mittente, modificabile solo dall'amministratore. */
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/mailer.php';
start_secure_session();
require_login();
if ((current_user()['ruolo'] ?? '') !== 'admin') {
    render_error_page('Questa operazione non è abilitata', 'Solo l\'amministratore può modificare le email di sistema.', 403);
}

$pdo = get_db_connection();
$pronto = (int)$pdo->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'impostazioni'")->fetchColumn() === 1;
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf_token($_POST['csrf_token'] ?? '');
    $azione = is_string($_POST['azione'] ?? null) ? $_POST['azione'] : '';

    if ($azione === 'salva') {
        $nuova = mb_strtolower(clean_email($_POST['mittente'] ?? '') ?? '');
        if (!$pronto) {
            $errors[] = 'Applica prima la migrazione database/migrations/015_impostazioni.sql.';
        } elseif (!filter_var($nuova, FILTER_VALIDATE_EMAIL) || strlen($nuova) > 190) {
            $errors[] = 'Indirizzo email non valido.';
        } else {
            $vecchia = mail_config()['from'];
            $utente = current_user();
            $pdo->prepare(
                'INSERT INTO impostazioni (chiave, valore, aggiornata_da) VALUES (?, ?, ?)
                 ON DUPLICATE KEY UPDATE valore = VALUES(valore), aggiornata_da = VALUES(aggiornata_da)'
            )->execute(['mail_mittente', $nuova, $utente ? (int)$utente['id_utente'] : null]);
            audit_event('impostazioni', 'mail_mittente_modificata', 'impostazioni', 'mail_mittente', ['da' => $vecchia, 'a' => $nuova]);
            redirect_with_message('/impostazioni/mail.php', 'Indirizzo mittente aggiornato.');
        }
    } elseif ($azione === 'prova') {
        $dest = mb_strtolower(clean_email($_POST['destinatario'] ?? '') ?? '');
        if (!filter_var($dest, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Indirizzo del destinatario non valido.';
        } elseif (!mail_enabled()) {
            $errors[] = 'Invio email disattivato: manca ICBR_SMTP_HOST nella configurazione di Apache.';
        } else {
            $ok = send_mail($dest, 'Prova email Inter Club Brindisi', "Questa è un'email di prova inviata dal gestionale dell'Inter Club Brindisi.\nSe la leggi, l'invio funziona.\n");
            audit_event('impostazioni', 'mail_prova', 'impostazioni', 'mail_mittente', ['esito' => $ok ? 'ok' : 'errore']);
            redirect_with_message('/impostazioni/mail.php', $ok ? 'Email di prova inviata a ' . $dest . '.' : 'Invio non riuscito: controlla utente, password per le app e log di Apache.', $ok ? 'success' : 'error');
        }
    }
}

$config = mail_config();
$csrf = generate_csrf_token();
$page_title = 'Email di sistema';
require __DIR__ . '/../../includes/layout_header.php';
?>
<div class="page-header">
    <div>
        <span class="admin-eyebrow">GESTIONE / IMPOSTAZIONI</span>
        <h1>Email di sistema</h1>
    </div>
</div>

<?php if ($errors): ?>
<div class="flash flash-error"><?= implode('<br>', array_map('h', $errors)) ?></div>
<?php endif; ?>

<?php if (!$pronto): ?>
<div class="flash flash-error">Impostazioni non ancora installate: applica la migrazione <code>database/migrations/015_impostazioni.sql</code> e ricarica la pagina.</div>
<?php endif; ?>

<section class="admin-panel">
    <h2>Indirizzo mittente</h2>
    <p>Le email dell'area soci (attivazione account, recupero password, posto liberato) partono da questo indirizzo. Solo l'amministratore può cambiarlo.</p>
    <form method="post" class="form-card">
        <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">
        <input type="hidden" name="azione" value="salva">
        <div class="form-group">
            <label>Email mittente</label>
            <input type="email" name="mittente" value="<?= h($config['from']) ?>" maxlength="190" required>
        </div>
        <div class="form-actions"><button type="submit" class="btn" <?= $pronto ? '' : 'disabled' ?>>Salva</button></div>
    </form>
    <p class="note">Stato invio: <strong><?= mail_enabled() ? 'attivo' : 'disattivato (manca ICBR_SMTP_HOST in Apache)' ?></strong>. Server: <?= h($config['host'] ?: 'non configurato') ?>, account di accesso: <?= h($config['user'] ?: 'non configurato') ?>.</p>
    <p class="note">La password per le app di Gmail non si imposta qui: resta nella configurazione di Apache. Con Gmail il mittente deve coincidere con l'account di accesso (ICBR_SMTP_USER), altrimenti Gmail lo sostituisce con quello.</p>
</section>

<section class="admin-panel">
    <h2>Email di prova</h2>
    <form method="post" class="form-card">
        <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">
        <input type="hidden" name="azione" value="prova">
        <div class="form-group">
            <label>Invia una prova a</label>
            <input type="email" name="destinatario" maxlength="190" required>
        </div>
        <div class="form-actions"><button type="submit" class="btn btn-secondary">Invia prova</button></div>
    </form>
</section>
<?php require __DIR__ . '/../../includes/layout_footer.php'; ?>
