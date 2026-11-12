<?php
/** Invio, rinnovo e revoca dell'accesso di un socio all'area soci (solo POST). */
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth_soci.php';
start_secure_session();
require_login();
partite_require_ready();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') redirect_with_message('/soci/list.php', 'Operazione non valida.', 'error');
verify_csrf_token($_POST['csrf_token'] ?? '');

$pdo = get_db_connection();
$id_socio = (int)($_POST['id_socio'] ?? 0);
$stmt = $pdo->prepare('SELECT id_socio, nome, cognome, telefono, attivo_record FROM soci WHERE id_socio = ?');
$stmt->execute([$id_socio]);
$socio = $stmt->fetch();
if (!$socio) redirect_with_message('/soci/list.php', 'Socio non trovato.', 'error');
$back = '/soci/view.php?id=' . $id_socio;
$azione = $_POST['azione'] ?? '';

if ($azione === 'revoca') {
    $pdo->prepare('UPDATE soci_account SET attivo = 0, password_hash = NULL, token_hash = NULL, token_scadenza = NULL WHERE id_socio = ?')->execute([$id_socio]);
    redirect_with_message($back, 'Accesso all\'area soci revocato.');
}
if ($azione !== 'invia') redirect_with_message($back, 'Operazione non valida.', 'error');
if (!(int)$socio['attivo_record']) redirect_with_message($back, 'Il socio è disattivato: riattivalo prima di inviare l\'accesso.', 'error');

try {
    $stmt = $pdo->prepare('SELECT password_hash IS NOT NULL FROM soci_account WHERE id_socio = ?');
    $stmt->execute([$id_socio]);
    $gia_attivo = (bool)$stmt->fetchColumn();
    $email = clean_email($_POST['email'] ?? '') ?? '';
    $token = soci_account_invite($id_socio, $email);
} catch (InvalidArgumentException $e) {
    redirect_with_message($back, $e->getMessage(), 'error');
}

$tipo = $gia_attivo ? 'recupero' : 'attivazione';
if (soci_send_access_email($email, $socio['nome'], $token, $tipo)) {
    redirect_with_message($back, 'Email inviata a ' . $email . '. Il link vale ' . SOCI_TOKEN_ORE . ' ore.');
}

// Email non configurata o non partita: il direttivo inoltra il link a mano.
$link = soci_access_link($token);
$wa = wa_phone($socio['telefono']);
$testo = 'Ciao ' . $socio['nome'] . ', ecco il link per ' . ($gia_attivo ? 'scegliere una nuova password dell\'' : 'attivare la tua ')
    . 'area soci dell\'Inter Club Brindisi (vale ' . SOCI_TOKEN_ORE . ' ore, usalo solo tu): ' . $link;
$page_title = 'Link di accesso per ' . $socio['nome'];
require __DIR__ . '/../../includes/layout_header.php';
?>
<div class="page-header">
    <div><span class="admin-eyebrow">SOCI / AREA SOCI</span><h1>Link di accesso pronto</h1></div>
    <a class="btn btn-secondary" href="<?= u($back) ?>">← Torna alla scheda</a>
</div>
<div class="flash flash-error" role="status"><?= mail_enabled() ? 'L\'email non è partita (controlla i dati SMTP nel registro errori).' : 'L\'invio email non è ancora configurato.' ?> Inoltra tu il link al socio.</div>
<section class="admin-panel">
    <p>Il link è personale e monouso: mandalo solo a <strong><?= h($socio['cognome'] . ' ' . $socio['nome']) ?></strong>. Non verrà mostrato di nuovo.</p>
    <label for="link-accesso">Link</label>
    <input type="text" id="link-accesso" readonly value="<?= h($link) ?>" style="width:100%" onclick="this.select()">
    <div class="quick-links" style="margin-top:1rem">
        <?php if ($wa): ?><a class="btn" target="_blank" rel="noopener" href="https://wa.me/<?= h($wa) ?>?text=<?= h(rawurlencode($testo)) ?>">Invia su WhatsApp ↗</a><?php endif; ?>
        <button class="btn btn-secondary" type="button" onclick="navigator.clipboard && navigator.clipboard.writeText(document.getElementById('link-accesso').value).then(()=>{this.textContent='Copiato ✓'})">Copia link</button>
    </div>
</section>
<?php require __DIR__ . '/../../includes/layout_footer.php'; ?>
