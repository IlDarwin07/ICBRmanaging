<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';
start_secure_session(); require_login();
$pdo = get_db_connection();
$id = (int)($_GET['id'] ?? 0);
$stmt = $pdo->prepare('SELECT * FROM messaggi_whatsapp WHERE id_messaggio = ?');
$stmt->execute([$id]); $message = $stmt->fetch();
if (!$message) render_error_page('Messaggio non trovato', 'Il messaggio potrebbe essere stato rimosso dalla coda.');
if ($message['stato_messaggio'] === 'inviato') redirect_with_message('/messaggi/list.php', 'Un invio confermato non è modificabile. Prepara un nuovo messaggio.', 'error');
$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf_token($_POST['csrf_token'] ?? '');
    $text = trim(is_string($_POST['testo'] ?? null) ? $_POST['testo'] : '');
    if (mb_strlen($text) < 2 || mb_strlen($text) > 4000) $errors[] = 'Il testo deve contenere da 2 a 4000 caratteri.';
    if (preg_match('/\\{\\{[^}]*\\}\\}/', $text)) $errors[] = 'Completa tutti i segnaposto prima di salvare il messaggio.';
    if (!$errors) {
        $pdo->prepare("UPDATE messaggi_whatsapp SET testo_finale = ?, stato_messaggio = 'preparato', data_invio = NULL WHERE id_messaggio = ? AND stato_messaggio <> 'inviato'")->execute([$text, $id]);
        redirect_with_message('/messaggi/list.php', 'Testo aggiornato e rimesso in coda. Nessun messaggio inviato.');
    }
    $message['testo_finale'] = $text;
}
$page_title = 'Modifica messaggio';
require __DIR__ . '/../../includes/layout_header.php';
?>
<div class="page-header"><h1>Modifica messaggio #<?= $id ?></h1><a class="btn btn-secondary" href="<?= u('/messaggi/list.php') ?>">← Coda WhatsApp</a></div>
<?php foreach ($errors as $error): ?><div class="flash flash-error"><?= h($error) ?></div><?php endforeach; ?>
<form class="record-form" method="post"><input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>"><p class="note">Destinatario: <?= h($message['telefono_destinatario']) ?>. Il testo viene aggiornato solo nella coda, non in una chat già aperta.</p><label for="testo">Testo<textarea id="testo" name="testo" rows="8" minlength="2" maxlength="4000" required><?= h($message['testo_finale']) ?></textarea></label><div class="form-actions"><button class="btn">Salva testo</button></div></form>
<?php require __DIR__ . '/../../includes/layout_footer.php'; ?>
