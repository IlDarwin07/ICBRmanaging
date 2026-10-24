<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';
start_secure_session();
require_login();
$pdo = get_db_connection();
$requestId = (int)($_GET['richiesta'] ?? $_POST['id_richiesta'] ?? 0);
$request = null;
if ($requestId) {
    $stmt = $pdo->prepare('SELECT * FROM richieste_contatto WHERE id_richiesta = ?');
    $stmt->execute([$requestId]);
    $request = $stmt->fetch();
    if (!$request) render_error_page('Richiesta non trovata', 'La richiesta potrebbe essere stata rimossa. Puoi ritrovarla nell’inbox, se ancora disponibile.');
}
$templates = $pdo->query('SELECT * FROM template_whatsapp WHERE attivo = 1 ORDER BY nome_template')->fetchAll();
$name = $request['nome'] ?? '';
$phone = $request['telefono'] ?? (is_string($_POST['telefono'] ?? null) ? $_POST['telefono'] : '');
$text = is_string($_POST['testo'] ?? null) ? $_POST['testo'] : ($request ? 'Ciao ' . $name . ', siamo dell’Inter Club Javier Zanetti Brindisi. Ti rispondiamo alla richiesta su ' . mb_strtolower($request['oggetto']) . '.' : '');
$errors = [];
$_SESSION['wa_direct_nonce'] ??= bin2hex(random_bytes(24));
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf_token($_POST['csrf_token'] ?? '');
    if (!hash_equals($_SESSION['wa_direct_nonce'], (string)($_POST['nonce'] ?? ''))) $errors[] = 'Modulo già usato o scaduto. Ricarica la pagina.';
    if (!is_string($phone) || !wa_phone($phone) || strlen($phone) > 30) $errors[] = 'Inserisci un numero valido con prefisso internazionale.';
    if (!is_string($text) || mb_strlen(trim($text)) < 2 || mb_strlen($text) > 4000) $errors[] = 'Scrivi un testo da 2 a 4000 caratteri.';
    if (preg_match('/\{\{[^}]*\}\}/', $text)) $errors[] = 'Completa tutti i segnaposto del template prima di preparare il messaggio.';
    $templateId = (int)($_POST['id_template'] ?? 0);
    if ($templateId && !in_array($templateId, array_map('intval', array_column($templates, 'id_template')), true)) $errors[] = 'Template non disponibile.';
    if (!$errors) {
        try {
            $stmt = $pdo->prepare("INSERT INTO messaggi_whatsapp (id_template,id_richiesta,telefono_destinatario,testo_finale,stato_messaggio) VALUES (?,?,?,?,'preparato')");
            $stmt->execute([$templateId ?: null, $requestId ?: null, '+' . wa_phone($phone), trim($text)]);
            $_SESSION['wa_direct_nonce'] = bin2hex(random_bytes(24));
            redirect_with_message('/messaggi/list.php?stato=preparato', 'Messaggio preparato. Apri WhatsApp, invialo dalla chat e poi conferma l’invio.');
        } catch (PDOException $e) {
            if ($e->getCode() !== '42S22') throw $e;
            $errors[] = 'Applica la migrazione 009 prima di usare i messaggi singoli collegati alla vetrina.';
        }
    }
}
$page_title = 'Prepara messaggio WhatsApp';
require __DIR__ . '/../../includes/layout_header.php';
?>
<div class="page-header"><h1>Prepara messaggio WhatsApp</h1><a class="btn btn-secondary" href="<?= u('/messaggi/index.php') ?>">← Centro WhatsApp</a></div>
<?php foreach ($errors as $error): ?><div class="flash flash-error"><?= h($error) ?></div><?php endforeach; ?>
<div class="workflow-note">Questo passaggio salva un messaggio in coda: non invia nulla. Usa questo canale solo per comunicazioni richieste o consentite dal destinatario.</div>
<?php if ($request): ?><p>Risposta a <strong><?= h($name) ?></strong> · <a href="<?= u('/richieste/view.php') ?>?id=<?= $requestId ?>">Richiesta #<?= $requestId ?></a>. La richiesta non verrà chiusa automaticamente.</p><?php endif; ?>
<form method="post" class="record-form">
    <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>"><input type="hidden" name="nonce" value="<?= h($_SESSION['wa_direct_nonce']) ?>"><input type="hidden" name="id_richiesta" value="<?= $requestId ?>">
    <div class="form-row"><label for="telefono">Telefono destinatario<input id="telefono" name="telefono" type="tel" required maxlength="30" value="<?= h($phone) ?>" <?= $request ? 'readonly' : '' ?>></label><label for="id_template">Template facoltativo<select id="id_template" name="id_template"><option value="">Testo libero</option><?php foreach ($templates as $t): ?><option value="<?= (int)$t['id_template'] ?>"><?= h($t['nome_template']) ?></option><?php endforeach; ?></select></label></div>
    <p class="note">I numeri nazionali sono interpretati come italiani. Per numeri esteri usa sempre +prefisso. Per risposte singole vengono sostituiti solo nome, nome completo, telefono e data: gli altri segnaposto vanno compilati manualmente.</p>
    <label for="testo">Messaggio da controllare prima dell’invio<textarea id="testo" name="testo" rows="8" minlength="2" maxlength="4000" required><?= h($text) ?></textarea></label>
    <div class="form-actions"><button class="btn">Salva nella coda WhatsApp</button><a class="btn btn-secondary" href="<?= u('/messaggi/list.php') ?>">Annulla</a></div>
</form>
<script>
const templates = <?= json_encode(array_column($templates, 'testo_template', 'id_template'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
const variables = <?= json_encode(['nome'=>$name, 'nome_completo'=>$name, 'telefono'=>$phone, 'data'=>date('d/m/Y')], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
document.getElementById('id_template').addEventListener('change', async function () {
    if (!this.value || !templates[this.value]) return;
    if (document.getElementById('testo').value.trim() && !await window.icbrConfirm('Sostituire il testo attuale con il template selezionato?')) { this.value = ''; return; }
    document.getElementById('testo').value = templates[this.value].replace(/\{\{(\w+)\}\}/g, (match, key) => variables[key] || match);
});
</script>
<?php require __DIR__ . '/../../includes/layout_footer.php'; ?>
