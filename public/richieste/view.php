<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';
start_secure_session();
require_login();
$pdo = get_db_connection();
$id = (int)($_GET['id'] ?? 0);
$stmt = $pdo->prepare('SELECT * FROM richieste_contatto WHERE id_richiesta = ?');
$stmt->execute([$id]);
$request = $stmt->fetch();
if (!$request) render_error_page('Richiesta non trovata', 'La richiesta potrebbe essere stata rimossa dall’inbox.');
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf_token($_POST['csrf_token'] ?? '');
    if (($_POST['azione'] ?? '') === 'elimina') {
        $pdo->beginTransaction();
        try {
            $linked = $pdo->prepare('SELECT id_messaggio FROM messaggi_whatsapp WHERE id_richiesta=? FOR UPDATE');
            $linked->execute([$id]);
            if ($linked->fetch() && !can('whatsapp.delete')) {
                $pdo->rollBack();
                require_permission('whatsapp.delete');
            }
            $pdo->prepare('DELETE FROM messaggi_whatsapp WHERE id_richiesta = ?')->execute([$id]);
            $pdo->prepare('DELETE FROM richieste_contatto WHERE id_richiesta = ?')->execute([$id]);
            $pdo->commit();
        } catch (Throwable $e) { $pdo->rollBack(); throw $e; }
        redirect_with_message('/richieste/list.php', 'Richiesta e risposte WhatsApp collegate eliminate.');
    }
    $stato = $_POST['stato'] ?? '';
    if (in_array($stato, ['nuova','in_gestione','chiusa'], true)) {
        $pdo->prepare('UPDATE richieste_contatto SET stato = ? WHERE id_richiesta = ?')->execute([$stato, $id]);
        redirect_with_message('/richieste/view.php?id=' . $id, 'Stato aggiornato.');
    }
}
$messages = $pdo->prepare('SELECT * FROM messaggi_whatsapp WHERE id_richiesta = ? ORDER BY data_creazione DESC');
$messages->execute([$id]);
$messages = $messages->fetchAll();
$page_title = 'Richiesta #' . $id;
require __DIR__ . '/../../includes/layout_header.php';
?>
<div class="page-header"><h1>Richiesta #<?= $id ?></h1><a class="btn btn-secondary" href="<?= u('/richieste/list.php') ?>">← Tutte le richieste</a></div>
<div class="request-layout">
<article class="admin-panel"><div class="admin-eyebrow"><?= h($request['oggetto']) ?> / <?= h(date('d/m/Y H:i', strtotime($request['data_creazione']))) ?></div><h2><?= h($request['nome']) ?></h2><dl class="request-meta"><dt>Email</dt><dd><?= h($request['email']) ?></dd><dt>Telefono</dt><dd><?= h($request['telefono'] ?: 'Non indicato') ?></dd></dl><div class="contact-message"><?= h($request['messaggio']) ?></div></article>
<aside class="admin-panel"><h2>Gestisci il contatto</h2>
    <p>La richiesta non è un tesseramento. Dopo aver risposto, aggiorna lo stato manualmente.</p>
    <a class="btn btn-secondary" href="mailto:<?= h(rawurlencode($request['email'])) ?>?subject=<?= rawurlencode('Inter Club Brindisi: ' . $request['oggetto']) ?>">Rispondi via email</a>
    <?php if (wa_phone($request['telefono'])): ?><a class="btn" href="<?= u('/messaggi/direct.php') ?>?richiesta=<?= $id ?>">Prepara risposta WhatsApp</a><?php else: ?><p class="note">Nessun telefono valido: WhatsApp non è disponibile per questa richiesta.</p><?php endif; ?>
    <form method="post" class="form-card"><input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>"><label for="stato">Stato della richiesta</label><select id="stato" name="stato"><?php foreach (['nuova'=>'Nuova','in_gestione'=>'In gestione','chiusa'=>'Chiusa'] as $key=>$label): ?><option value="<?= $key ?>" <?= $request['stato'] === $key ? 'selected' : '' ?>><?= $label ?></option><?php endforeach; ?></select><div class="form-actions"><button class="btn">Aggiorna stato</button></div></form>
    <form method="post" data-confirm="Eliminare definitivamente questa richiesta e le risposte WhatsApp collegate?"><input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>"><input type="hidden" name="azione" value="elimina"><div class="form-actions"><button class="btn btn-danger">Elimina richiesta e risposte</button></div></form>
</aside>
</div>
<?php if ($messages): ?><h2>Risposte WhatsApp collegate</h2><table class="data-table"><thead><tr><th>Data</th><th>Testo</th><th>Stato</th></tr></thead><tbody><?php foreach ($messages as $m): ?><tr><td><?= h($m['data_creazione']) ?></td><td><?= h($m['testo_finale']) ?></td><td><?= h($m['stato_messaggio']) ?></td></tr><?php endforeach; ?></tbody></table><a class="btn btn-secondary" href="<?= u('/messaggi/list.php') ?>">Apri coda WhatsApp</a><?php endif; ?>
<?php require __DIR__ . '/../../includes/layout_footer.php'; ?>
